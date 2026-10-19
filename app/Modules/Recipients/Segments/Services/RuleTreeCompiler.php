<?php

namespace App\Modules\Recipients\Segments\Services;

use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\Tag;
use App\Modules\Recipients\Segments\Contracts\SegmentCompilerContract;
use Closure;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

/**
 * docs/45-brevo-api-contracts.md §45.5.2 — rule-tree grammar v2 compiler
 * (contract §45.9 `RuleTreeCompiler`).
 *
 * Compilation strategy: every condition is reduced to a *positive*
 * predicate that is NULL-safe (`expr IS NOT NULL AND …`), and negative
 * operators (`not_equals`, `not_contains`, `not_in`, `is_empty`,
 * `not_contains_any`, `has_not`) compile to `NOT (positive)`. Hence a
 * condition and its negation always partition the contact base, and
 * contacts without a value (no PagesJaunes snapshot, missing attribute)
 * match the negative side. Relations (tags, lists, PagesJaunes, messages,
 * suppression) are correlated `EXISTS` sub-queries on `recipients.id` —
 * no joins, so `apply()` is safe on nested builders.
 *
 * Text comparisons are case-insensitive. Dates are evaluated in the
 * account timezone (`config('app.account_timezone')`, default
 * Africa/Algiers); timestamp columns are compared with UTC bounds,
 * date-only attributes (`YYYY-MM-DD` strings in `custom_fields`) by string.
 */
class RuleTreeCompiler implements SegmentCompilerContract
{
    public const MAX_CONDITIONS = 100;

    public const MAX_LIST_VALUES = 500;

    public const MAX_CAMPAIGNS = 50;

    public const DEFAULT_TIMEZONE = 'Africa/Algiers';

    /** negative operator => positive operator it negates */
    private const NEGATIONS = [
        'not_equals' => 'equals',
        'not_contains' => 'contains',
        'not_in' => 'in',
        'is_empty' => 'is_not_empty',
        'not_contains_any' => 'contains_any',
        'has_not' => 'has',
    ];

    private const VALUELESS = ['is_empty', 'is_not_empty', 'is_true', 'is_false', 'anniversary_today'];

    /** @var array<string, list<string>> */
    private static array $columns = [];

    /**
     * False while validating at apply time: list / campaign UUIDs are only
     * checked when a tree is saved (FormRequests → {@see validate()}); at
     * evaluation an unknown reference simply matches nobody.
     */
    private bool $checkReferences = true;

    public function __construct(private readonly FieldCatalog $fields) {}

    // ---------------------------------------------------------------------
    // Public API
    // ---------------------------------------------------------------------

    public function compile(array $tree): EloquentBuilder
    {
        return $this->apply(Recipient::query(), $tree);
    }

    public function constraint(array $tree): Closure
    {
        $tree = $this->normalize($tree);
        $this->validateShape($tree);

        return fn (EloquentBuilder $query): EloquentBuilder => $this->apply($query, $tree);
    }

    public function apply(EloquentBuilder $recipients, array $tree): EloquentBuilder
    {
        $tree = $this->normalize($tree);
        $this->validateShape($tree);

        $recipients->whereNull('recipients.deleted_at');
        $recipients->where(function (EloquentBuilder $query) use ($tree): void {
            $this->compileGroup($query, $tree);
        });

        return $recipients;
    }

    public function count(array $tree): int
    {
        return $this->compile($tree)->count();
    }

    public function matches(Recipient $recipient, array $tree): bool
    {
        return $this->compile($tree)->whereKey($recipient->getKey())->exists();
    }

    public function evaluate(Recipient $recipient, array $tree): bool
    {
        return $this->matches($recipient, $tree);
    }

    public function normalize(?array $tree): array
    {
        if ($tree === null || $tree === []) {
            return ['version' => 2, 'type' => 'group', 'operator' => 'and', 'children' => []];
        }

        return array_key_exists('version', $tree) ? $tree : $this->upgrade($tree);
    }

    public function validate(array $tree, string $path = 'rules'): void
    {
        try {
            $tree = $this->normalize($tree);
        } catch (InvalidArgumentException $e) {
            throw ValidationException::withMessages([$path => $e->getMessage()]);
        }

        $errors = [];
        $this->validateTree($tree, $path, $errors);

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * Structural validation only (grammar, fields, operators, value types):
     * list / campaign UUIDs are not looked up, so a saved segment whose list
     * or campaign was deleted still evaluates (that condition matches none).
     *
     * @param  array<string, mixed>  $tree
     *
     * @throws ValidationException
     */
    public function validateShape(array $tree, string $path = 'rules'): void
    {
        $previous = $this->checkReferences;
        $this->checkReferences = false;

        try {
            $this->validate($tree, $path);
        } finally {
            $this->checkReferences = $previous;
        }
    }

    public function upgrade(array $legacyTree): array
    {
        // Already v2-shaped but missing `version`: accept as v2.
        if (($legacyTree['type'] ?? null) === 'group' && array_key_exists('children', $legacyTree)) {
            return ['version' => 2] + $legacyTree;
        }

        $root = [
            'version' => 2,
            'type' => 'group',
            'operator' => $this->legacyOperator($legacyTree['operator'] ?? 'and'),
            'children' => [],
        ];

        foreach ((array) ($legacyTree['conditions'] ?? []) as $node) {
            if (! is_array($node)) {
                throw new InvalidArgumentException('Règle de segment héritée invalide.');
            }
            if (isset($node['conditions'])) {
                $group = ['type' => 'group', 'operator' => $this->legacyOperator($node['operator'] ?? 'and'), 'children' => []];
                foreach ((array) $node['conditions'] as $inner) {
                    if (! is_array($inner) || isset($inner['conditions'])) {
                        throw new InvalidArgumentException('Les règles héritées imbriquées sur plus de deux niveaux ne sont pas prises en charge.');
                    }
                    $group['children'][] = $this->upgradeCondition($inner);
                }
                $root['children'][] = $group;
            } else {
                $root['children'][] = $this->upgradeCondition($node);
            }
        }

        return $root;
    }

    // ---------------------------------------------------------------------
    // Validation
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $tree
     * @param  array<string, string>  $errors
     */
    private function validateTree(array $tree, string $path, array &$errors): void
    {
        if (($tree['version'] ?? null) !== 2) {
            $errors["{$path}.version"] = 'Version de règles non prise en charge (attendu : 2).';
        }
        if (($tree['type'] ?? null) !== 'group') {
            $errors["{$path}.type"] = 'La racine doit être un groupe.';
        }
        if (! $this->validateGroupShape($tree, $path, $errors)) {
            return;
        }

        $conditions = 0;
        foreach ($tree['children'] as $i => $child) {
            $childPath = "{$path}.children.{$i}";
            $type = is_array($child) ? ($child['type'] ?? null) : null;

            if ($type === 'group') {
                if (! $this->validateGroupShape($child, $childPath, $errors)) {
                    continue;
                }
                if ($child['children'] === []) {
                    $errors["{$childPath}.children"] = 'Le groupe doit contenir au moins une condition.';
                }
                foreach ($child['children'] as $j => $condition) {
                    $conditionPath = "{$childPath}.children.{$j}";
                    $innerType = is_array($condition) ? ($condition['type'] ?? null) : null;
                    if ($innerType !== 'condition') {
                        $errors["{$conditionPath}.type"] = $innerType === 'group'
                            ? "Un groupe ne peut pas contenir d'autre groupe."
                            : 'Type de nœud invalide (attendu : condition).';

                        continue;
                    }
                    $conditions++;
                    $this->validateCondition($condition, $conditionPath, $errors);
                }
            } elseif ($type === 'condition') {
                $conditions++;
                $this->validateCondition($child, $childPath, $errors);
            } else {
                $errors["{$childPath}.type"] = 'Type de nœud invalide (attendu : group ou condition).';
            }
        }

        if ($conditions > self::MAX_CONDITIONS) {
            $errors["{$path}.children"] = 'Un segment ne peut pas contenir plus de '.self::MAX_CONDITIONS.' conditions.';
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function validateGroupShape(mixed $group, string $path, array &$errors): bool
    {
        if (! is_array($group)) {
            $errors[$path] = 'Groupe invalide.';

            return false;
        }
        if (! in_array($group['operator'] ?? null, ['and', 'or'], true)) {
            $errors["{$path}.operator"] = "L'opérateur du groupe doit être « and » ou « or ».";
        }
        if (! isset($group['children']) || ! is_array($group['children']) || ! array_is_list($group['children'])) {
            $errors["{$path}.children"] = 'La liste des conditions est invalide.';

            return false;
        }

        return true;
    }

    /**
     * @param  array<string, mixed>  $condition
     * @param  array<string, string>  $errors
     */
    private function validateCondition(array $condition, string $path, array &$errors): void
    {
        $field = $condition['field'] ?? null;
        if (! is_string($field) || $field === '') {
            $errors["{$path}.field"] = 'Le champ est obligatoire.';

            return;
        }

        $fieldType = $condition['field_type'] ?? null;
        if ($fieldType !== null && ! in_array($fieldType, FieldCatalog::ATTRIBUTE_TYPES, true)) {
            $errors["{$path}.field_type"] = 'Type de champ invalide.';

            return;
        }

        $legacyKey = $condition['legacy_key'] ?? null;
        if ($legacyKey !== null && ! is_string($legacyKey)) {
            $errors["{$path}.legacy_key"] = 'Clé héritée invalide.';

            return;
        }

        $definition = $this->fields->definition($field, $fieldType, $legacyKey);
        if ($definition === null) {
            $errors["{$path}.field"] = str_starts_with($field, 'attribute.')
                ? 'Attribut inconnu : '.substr($field, strlen('attribute.'))
                : "Champ inconnu : {$field}";

            return;
        }

        $operator = $condition['operator'] ?? null;
        if (! is_string($operator) || ! in_array($operator, $this->fields->operatorsFor($definition['type']), true)) {
            $errors["{$path}.operator"] = 'Opérateur non valide pour ce champ : '.(is_scalar($operator) ? (string) $operator : '—');

            return;
        }

        if (in_array($operator, self::VALUELESS, true)) {
            return;
        }

        $this->validateValue($definition, $operator, $condition['value'] ?? null, "{$path}.value", $errors);
    }

    /**
     * @param  array<string, mixed>  $definition
     * @param  array<string, string>  $errors
     */
    private function validateValue(array $definition, string $operator, mixed $value, string $path, array &$errors): void
    {
        $type = $definition['type'];

        switch ($type) {
            case 'text':
                if (in_array($operator, ['in', 'not_in'], true)) {
                    $this->validateList($value, $path, $errors, fn ($v) => $this->isText($v));
                } elseif (! $this->isText($value)) {
                    $errors[$path] = 'Saisissez une valeur (texte, 255 caractères maximum).';
                }

                return;

            case 'number':
                if ($operator === 'between') {
                    if (! is_array($value) || ! array_is_list($value) || count($value) !== 2 || ! $this->isNumber($value[0]) || ! $this->isNumber($value[1])) {
                        $errors[$path] = 'Saisissez deux nombres [min, max].';
                    } elseif ($value[0] > $value[1]) {
                        $errors[$path] = 'La valeur minimale doit être inférieure ou égale à la valeur maximale.';
                    }
                } elseif (! $this->isNumber($value)) {
                    $errors[$path] = 'Saisissez un nombre.';
                }

                return;

            case 'date':
                if (in_array($operator, ['in_last_days', 'more_than_days_ago', 'in_next_days'], true)) {
                    if (! is_int($value) || $value < 1 || $value > 3650) {
                        $errors[$path] = 'Saisissez un nombre de jours entre 1 et 3650.';
                    }
                } elseif ($operator === 'between') {
                    if (! is_array($value) || ! array_is_list($value) || count($value) !== 2 || ! $this->isDate($value[0]) || ! $this->isDate($value[1])) {
                        $errors[$path] = 'Saisissez deux dates au format AAAA-MM-JJ.';
                    } elseif ($value[0] > $value[1]) {
                        $errors[$path] = 'La date de début doit précéder la date de fin.';
                    }
                } elseif (! $this->isDate($value)) {
                    $errors[$path] = 'Saisissez une date au format AAAA-MM-JJ.';
                }

                return;

            case 'category':
            case 'user':
            case 'multiple_choice':
                $options = isset($definition['options']) && $type === 'category'
                    ? array_map(fn ($o) => (string) $o['value'], $definition['options'])
                    : null;
                $this->validateList($value, $path, $errors, fn ($v) => $this->isText($v));
                if ($options !== null && ! isset($errors[$path])) {
                    foreach ($value as $i => $v) {
                        if (is_scalar($v) && ! in_array((string) $v, $options, true)) {
                            $errors["{$path}.{$i}"] = 'Valeur non autorisée : '.$v;
                        }
                    }
                }

                return;

            case 'tags':
                $this->validateList($value, $path, $errors, fn ($v) => is_int($v));

                return;

            case 'list':
                $this->validateList($value, $path, $errors, fn ($v) => is_string($v) && $v !== '');
                if ($this->checkReferences && ! isset($errors[$path]) && is_array($value)) {
                    $known = DB::table('recipient_lists')->whereNull('deleted_at')->whereIn('uuid', $value)->pluck('uuid')->all();
                    foreach ($value as $i => $uuid) {
                        if (! in_array($uuid, $known, true)) {
                            $errors["{$path}.{$i}"] = 'Liste inconnue : '.$uuid;
                        }
                    }
                }

                return;

            case 'engagement':
                $this->validateEngagement($definition['target']['event'], $value, $path, $errors);

                return;
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function validateEngagement(string $event, mixed $value, string $path, array &$errors): void
    {
        if (! is_array($value) || ($value !== [] && array_is_list($value))) {
            $errors[$path] = 'Paramètres d’engagement invalides.';

            return;
        }

        $campaignIds = $value['campaign_ids'] ?? null;
        if ($campaignIds !== null) {
            if (! is_array($campaignIds) || ! array_is_list($campaignIds) || $campaignIds === [] || count($campaignIds) > self::MAX_CAMPAIGNS) {
                $errors["{$path}.campaign_ids"] = 'Sélectionnez entre 1 et '.self::MAX_CAMPAIGNS.' campagnes, ou aucune pour « n’importe quelle campagne ».';
            } elseif (array_filter($campaignIds, fn ($v) => ! is_string($v)) !== []) {
                $errors["{$path}.campaign_ids"] = 'Identifiants de campagne invalides.';
            } elseif ($this->checkReferences) {
                $known = DB::table('campaigns')->whereIn('uuid', $campaignIds)->pluck('uuid')->all();
                foreach ($campaignIds as $i => $uuid) {
                    if (! in_array($uuid, $known, true)) {
                        $errors["{$path}.campaign_ids.{$i}"] = 'Campagne inconnue : '.$uuid;
                    }
                }
            }
        }

        $withinDays = $value['within_days'] ?? null;
        if ($withinDays !== null && (! is_int($withinDays) || $withinDays < 1 || $withinDays > 3650)) {
            $errors["{$path}.within_days"] = 'Saisissez un nombre de jours entre 1 et 3650.';
        }

        foreach (['from', 'to'] as $key) {
            if (($value[$key] ?? null) !== null && ! $this->isDate($value[$key])) {
                $errors["{$path}.{$key}"] = 'Saisissez une date au format AAAA-MM-JJ.';
            }
        }
        if ($this->isDate($value['from'] ?? null) && $this->isDate($value['to'] ?? null) && $value['from'] > $value['to']) {
            $errors["{$path}.to"] = 'La date de fin doit suivre la date de début.';
        }

        $linkUrl = $value['link_url'] ?? null;
        if ($linkUrl !== null) {
            if ($event !== 'clicked') {
                $errors["{$path}.link_url"] = 'Le filtre par lien ne s’applique qu’aux clics.';
            } elseif (! is_string($linkUrl) || $linkUrl === '' || mb_strlen($linkUrl) > 2048) {
                $errors["{$path}.link_url"] = 'Saisissez une URL valide.';
            } elseif (! $this->linkTrackingAvailable()) {
                $errors["{$path}.link_url"] = 'Le filtre par lien cliqué n’est pas encore disponible.';
            }
        }

        $minCount = $value['min_count'] ?? null;
        if ($minCount !== null) {
            if (! in_array($event, ['opened', 'clicked'], true)) {
                $errors["{$path}.min_count"] = 'Le nombre minimum ne s’applique qu’aux ouvertures et aux clics.';
            } elseif (! is_int($minCount) || $minCount < 1) {
                $errors["{$path}.min_count"] = 'Saisissez un nombre entier supérieur ou égal à 1.';
            }
        }

        if (($value['automation_step_id'] ?? null) !== null && ! $this->automationStepsAvailable()) {
            $errors["{$path}.automation_step_id"] = 'Le filtre par étape d’automatisation n’est pas encore disponible.';
        }
    }

    /**
     * @param  array<string, string>  $errors
     */
    private function validateList(mixed $value, string $path, array &$errors, Closure $item): void
    {
        if (! is_array($value) || ! array_is_list($value) || $value === []) {
            $errors[$path] = 'Sélectionnez au moins une valeur.';

            return;
        }
        if (count($value) > self::MAX_LIST_VALUES) {
            $errors[$path] = 'Au maximum '.self::MAX_LIST_VALUES.' valeurs.';

            return;
        }
        foreach ($value as $i => $v) {
            if (! $item($v)) {
                $errors["{$path}.{$i}"] = 'Valeur invalide.';
            }
        }
    }

    private function isText(mixed $value): bool
    {
        return (is_string($value) && $value !== '' && mb_strlen($value) <= 255) || is_int($value) || is_float($value);
    }

    private function isNumber(mixed $value): bool
    {
        return is_int($value) || is_float($value);
    }

    private function isDate(mixed $value): bool
    {
        return is_string($value)
            && preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) === 1
            && checkdate((int) $m[2], (int) $m[3], (int) $m[1]);
    }

    // ---------------------------------------------------------------------
    // Compilation
    // ---------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $group
     */
    private function compileGroup(EloquentBuilder|QueryBuilder $query, array $group): void
    {
        $boolean = $group['operator'] === 'or' ? 'or' : 'and';

        foreach ($group['children'] as $child) {
            if ($child['type'] === 'group') {
                $query->where(function ($inner) use ($child): void {
                    $this->compileGroup($inner, $child);
                }, null, null, $boolean);
            } else {
                $this->compileCondition($query, $child, $boolean);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $condition
     */
    private function compileCondition(EloquentBuilder|QueryBuilder $query, array $condition, string $boolean): void
    {
        $definition = $this->fields->definition($condition['field'], $condition['field_type'] ?? null, $condition['legacy_key'] ?? null);
        $operator = $condition['operator'];
        $negate = isset(self::NEGATIONS[$operator]);
        $positive = self::NEGATIONS[$operator] ?? $operator;
        $value = $condition['value'] ?? null;

        $closure = function ($inner) use ($definition, $positive, $value): void {
            $this->positive($inner, $definition, $positive, $value);
        };

        $negate
            ? $query->whereNot($closure, null, null, $boolean)
            : $query->where($closure, null, null, $boolean);
    }

    /**
     * Adds the NULL-safe positive predicate of one condition.
     *
     * @param  array<string, mixed>  $definition
     */
    private function positive(EloquentBuilder|QueryBuilder $q, array $definition, string $op, mixed $value): void
    {
        $type = $definition['type'];
        $target = $definition['target'];

        switch ($target['kind']) {
            case 'column':
                $this->scalar($q, $this->wrap($q, 'recipients.'.$target['column']), $type, $op, $value, false);

                return;

            case 'optional_column':
                $expression = $this->hasColumn('recipients', $target['column'])
                    ? $this->wrap($q, 'recipients.'.$target['column'])
                    : 'NULL';
                $this->scalar($q, $expression, $type, $op, $value, false);

                return;

            case 'json':
                if ($type === 'multiple_choice') {
                    $this->jsonArray($q, 'recipients.custom_fields->'.$target['name'], $op, $value);
                } else {
                    $this->scalar($q, $this->wrap($q, 'recipients.custom_fields->'.$target['name']), $type, $op, $value, true);
                }

                return;

            case 'message_max':
                $expression = $this->hasColumn('recipients', $target['column'])
                    ? $this->wrap($q, 'recipients.'.$target['column'])
                    : '(select max(m.'.$target['message_column'].') from messages m where m.recipient_id = recipients.id'
                        .($this->hasColumn('messages', 'is_test') ? ' and m.is_test = false' : '').')';
                $this->scalar($q, $expression, $type, $op, $value, false);

                return;

            case 'blocklisted_at':
                $expression = $this->hasColumn('recipients', 'blocklisted_at')
                    ? $this->wrap($q, 'recipients.blocklisted_at')
                    : '(select min(s.created_at) from suppression_entries s where s.email = recipients.email)';
                $this->scalar($q, $expression, $type, $op, $value, false);

                return;

            case 'blocklisted':
                $this->blocklisted($q, $op);

                return;

            case 'tags':
                $this->tags($q, $op, $value);

                return;

            case 'list':
                $q->whereExists(function (QueryBuilder $sub) use ($value): void {
                    $sub->selectRaw('1')
                        ->from('recipient_list_recipient as rlr')
                        ->join('recipient_lists as rl', 'rl.id', '=', 'rlr.recipient_list_id')
                        ->whereColumn('rlr.recipient_id', 'recipients.id')
                        ->whereNull('rl.deleted_at')
                        ->whereIn('rl.uuid', $value);
                });

                return;

            case 'pagejaunes':
                $q->whereExists(function (QueryBuilder $sub) use ($target, $type, $op, $value): void {
                    $sub->selectRaw('1')
                        ->from('pagejaunes_company_cache as pj')
                        ->whereColumn('pj.id', 'recipients.pagejaunes_company_cache_id');
                    $column = 'pj.'.$target['column'];
                    if ($type === 'boolean') {
                        match ($op) {
                            'is_true' => $sub->where($column, true),
                            'is_false' => $sub->where($column, false),
                            default => $sub->whereNotNull($column),
                        };
                    } else {
                        $this->scalar($sub, $this->wrap($sub, $column), $type, $op, $value, str_contains($column, '->'));
                    }
                });

                return;

            case 'engagement':
                $this->engagement($q, $target['event'], $value ?? []);

                return;
        }

        throw new InvalidArgumentException("Champ non compilable : {$definition['key']}");
    }

    /**
     * Scalar predicates (text, category, user, number, date, boolean) on a
     * SQL expression. `$json`: the expression is a JSON-extracted string.
     */
    private function scalar(EloquentBuilder|QueryBuilder $q, string $e, string $type, string $op, mixed $value, bool $json): void
    {
        $stringy = $json || in_array($type, ['text', 'category', 'user'], true);
        $has = $stringy
            ? "({$e} IS NOT NULL AND {$e} <> ''".($json ? " AND LOWER({$e}) <> 'null'" : '').')'
            : "({$e} IS NOT NULL)";

        if ($op === 'is_not_empty') {
            $q->whereRaw($has);

            return;
        }

        switch ($type) {
            case 'text':
            case 'category':
            case 'user':
                $lower = "LOWER({$e})";
                match ($op) {
                    'equals' => $q->whereRaw("{$has} AND {$lower} = ?", [$this->lower($value)]),
                    'contains' => $q->whereRaw("{$has} AND {$lower} LIKE ?", ['%'.$this->escapeLike($this->lower($value)).'%']),
                    'starts_with' => $q->whereRaw("{$has} AND {$lower} LIKE ?", [$this->escapeLike($this->lower($value)).'%']),
                    'ends_with' => $q->whereRaw("{$has} AND {$lower} LIKE ?", ['%'.$this->escapeLike($this->lower($value))]),
                    'in' => $q->whereRaw(
                        "{$has} AND {$lower} IN (".implode(', ', array_fill(0, count($value), '?')).')',
                        array_map(fn ($v) => $this->lower($v), $value),
                    ),
                };

                return;

            case 'number':
                $n = $json ? $this->castNumber($q, $e) : $e;
                match ($op) {
                    'equals' => $q->whereRaw("{$has} AND {$n} = ?", [$value]),
                    'greater_than' => $q->whereRaw("{$has} AND {$n} > ?", [$value]),
                    'greater_or_equal' => $q->whereRaw("{$has} AND {$n} >= ?", [$value]),
                    'less_than' => $q->whereRaw("{$has} AND {$n} < ?", [$value]),
                    'less_or_equal' => $q->whereRaw("{$has} AND {$n} <= ?", [$value]),
                    'between' => $q->whereRaw("{$has} AND {$n} >= ? AND {$n} <= ?", [$value[0], $value[1]]),
                };

                return;

            case 'date':
                $json ? $this->dateString($q, $e, $has, $op, $value) : $this->dateTimestamp($q, $e, $op, $value);

                return;

            case 'boolean':
                match ($op) {
                    'is_true' => $q->whereRaw("{$has} AND LOWER({$e}) IN ('true', '1', 'yes', 'oui')"),
                    'is_false' => $q->whereRaw("{$has} AND LOWER({$e}) IN ('false', '0', 'no', 'non')"),
                };

                return;
        }

        throw new InvalidArgumentException("Opérateur non compilable : {$op}");
    }

    /**
     * Date-only values stored as "YYYY-MM-DD…" strings: lexicographic compare.
     */
    private function dateString(EloquentBuilder|QueryBuilder $q, string $e, string $has, string $op, mixed $value): void
    {
        $d = self::dateCast($e);
        $today = $this->today();

        match ($op) {
            'on' => $q->whereRaw("{$has} AND {$d} = ?", [$value]),
            'before' => $q->whereRaw("{$has} AND {$d} < ?", [$value]),
            'after' => $q->whereRaw("{$has} AND {$d} > ?", [$value]),
            'between' => $q->whereRaw("{$has} AND {$d} >= ? AND {$d} <= ?", [$value[0], $value[1]]),
            'in_last_days' => $q->whereRaw("{$has} AND {$d} >= ? AND {$d} <= ?", [$today->copy()->subDays($value)->toDateString(), $today->toDateString()]),
            'more_than_days_ago' => $q->whereRaw("{$has} AND {$d} < ?", [$today->copy()->subDays($value)->toDateString()]),
            'in_next_days' => $q->whereRaw("{$has} AND {$d} >= ? AND {$d} <= ?", [$today->toDateString(), $today->copy()->addDays($value)->toDateString()]),
            'anniversary_today' => $q->whereRaw("{$has} AND SUBSTRING({$e}, 6, 5) = ?", [$today->format('m-d')]),
        };
    }

    /**
     * Date part (`YYYY-MM-DD`) of a JSON-extracted date string. Shared with
     * the contacts table sort (ContactColumns).
     */
    public static function dateCast(string $e): string
    {
        return "SUBSTRING({$e}, 1, 10)";
    }

    /**
     * Timestamp columns (stored UTC): compare with UTC bounds of account-timezone days.
     */
    private function dateTimestamp(EloquentBuilder|QueryBuilder $q, string $e, string $op, mixed $value): void
    {
        $today = $this->today();
        $start = fn (Carbon|string $day): string => $this->utcStartOf($day);

        match ($op) {
            'on' => $q->whereRaw("{$e} >= ? AND {$e} < ?", [$start($value), $start(Carbon::parse($value)->addDay())]),
            'before' => $q->whereRaw("{$e} < ?", [$start($value)]),
            'after' => $q->whereRaw("{$e} >= ?", [$start(Carbon::parse($value)->addDay())]),
            'between' => $q->whereRaw("{$e} >= ? AND {$e} < ?", [$start($value[0]), $start(Carbon::parse($value[1])->addDay())]),
            'in_last_days' => $q->whereRaw("{$e} >= ? AND {$e} < ?", [$start($today->copy()->subDays($value)), $start($today->copy()->addDay())]),
            'more_than_days_ago' => $q->whereRaw("{$e} < ?", [$start($today->copy()->subDays($value))]),
            'in_next_days' => $q->whereRaw("{$e} >= ? AND {$e} < ?", [$start($today), $start($today->copy()->addDays($value + 1))]),
            'anniversary_today' => $q->whereRaw($this->monthDayExpression($q, $e).' = ?', [$today->format('m-d')]),
        };
    }

    private function monthDayExpression(EloquentBuilder|QueryBuilder $q, string $e): string
    {
        $offset = (int) Carbon::now($this->timezone())->utcOffset() * 60;

        return match ($this->driver($q)) {
            'pgsql' => "TO_CHAR({$e} + INTERVAL '{$offset} seconds', 'MM-DD')",
            'sqlite' => "strftime('%m-%d', {$e}, '{$offset} seconds')",
            'sqlsrv' => "FORMAT(DATEADD(second, {$offset}, {$e}), 'MM-dd')",
            default => "DATE_FORMAT(DATE_ADD({$e}, INTERVAL {$offset} SECOND), '%m-%d')",
        };
    }

    private function castNumber(EloquentBuilder|QueryBuilder $q, string $e): string
    {
        return self::numberCast($this->driver($q), $e);
    }

    /**
     * SQL casting a JSON-extracted string to a number (NULL when not numeric
     * on Postgres). Shared with the contacts table sort (ContactColumns).
     */
    public static function numberCast(string $driver, string $e): string
    {
        return match ($driver) {
            'pgsql' => "(CASE WHEN {$e} ~ '^\\s*-?[0-9]+(\\.[0-9]+)?\\s*$' THEN CAST({$e} AS NUMERIC) END)",
            'sqlite' => "CAST({$e} AS REAL)",
            'sqlsrv' => "TRY_CAST({$e} AS DECIMAL(30, 10))",
            default => "CAST({$e} AS DECIMAL(30, 10))",
        };
    }

    /**
     * multiple_choice attributes: JSON arrays in `custom_fields`.
     */
    private function jsonArray(EloquentBuilder|QueryBuilder $q, string $path, string $op, mixed $value): void
    {
        $q->whereNotNull($path);

        match ($op) {
            'contains_any' => $q->where(function ($any) use ($path, $value): void {
                foreach ($value as $v) {
                    $any->orWhereJsonContains($path, $v);
                }
            }),
            'contains_all' => array_map(fn ($v) => $q->whereJsonContains($path, $v), $value),
            'is_not_empty' => $q->whereJsonLength($path, '>', 0),
        };
    }

    private function tags(EloquentBuilder|QueryBuilder $q, string $op, mixed $value): void
    {
        $exists = fn (?array $ids) => function (QueryBuilder $sub) use ($ids): void {
            $sub->selectRaw('1')->from('recipient_tag as rt')->whereColumn('rt.recipient_id', 'recipients.id');
            if ($ids !== null) {
                $sub->whereIn('rt.tag_id', $ids);
            }
        };

        match ($op) {
            'contains_any' => $q->whereExists($exists($value)),
            'contains_all' => array_map(fn ($id) => $q->whereExists($exists([$id])), $value),
            'is_not_empty' => $q->whereExists($exists(null)),
        };
    }

    private function blocklisted(EloquentBuilder|QueryBuilder $q, string $op): void
    {
        if ($op === 'is_not_empty') {
            $q->whereRaw('1 = 1'); // a contact is always either blocklisted or not

            return;
        }

        // The `blocklisted_at` mirror can lag (a contact created after its
        // suppression entry), so a suppression entry for the email also counts.
        $suppressed = function (QueryBuilder $sub): void {
            $sub->selectRaw('1')->from('suppression_entries as s')->whereColumn('s.email', 'recipients.email');
        };
        $blocked = function ($inner) use ($suppressed): void {
            $inner->whereNotNull('recipients.blocklisted_at')->orWhereExists($suppressed);
        };

        if ($this->hasColumn('recipients', 'blocklisted_at')) {
            $op === 'is_true' ? $q->where($blocked) : $q->whereNot($blocked);

            return;
        }

        $op === 'is_true' ? $q->whereExists($suppressed) : $q->whereNotExists($suppressed);
    }

    /**
     * Engagement `has`: EXISTS on `messages` (test sends ignored) with event,
     * time window, campaign / link / automation-step filters, or a SUM of
     * open/click counters when `min_count` is set. `unsubscribed` /
     * `complained` without a campaign filter also match the suppression list.
     *
     * @param  array<string, mixed>  $value
     */
    private function engagement(EloquentBuilder|QueryBuilder $q, string $event, array $value): void
    {
        [$from, $to] = $this->window($value);
        $windowColumn = match ($event) {
            'sent' => 'm.sent_at',
            'delivered' => 'm.delivered_at',
            'opened' => 'm.opened_at',
            'clicked' => 'm.clicked_at',
            'soft_bounced', 'hard_bounced' => 'm.bounced_at',
            default => 'm.updated_at',
        };
        $campaignIds = $value['campaign_ids'] ?? null;
        $linkUrl = $value['link_url'] ?? null;
        $stepId = $value['automation_step_id'] ?? null;

        $messages = function (QueryBuilder $m) use ($event, $windowColumn, $from, $to, $campaignIds, $linkUrl, $stepId): void {
            $m->from('messages as m')->whereColumn('m.recipient_id', 'recipients.id');
            if ($this->hasColumn('messages', 'is_test')) {
                $m->where('m.is_test', false);
            }
            match ($event) {
                'sent' => $m->whereNotNull('m.sent_at'),
                'delivered' => $m->whereNotNull('m.delivered_at'),
                'opened' => $m->whereNotNull('m.opened_at'),
                'clicked' => $m->whereNotNull('m.clicked_at'),
                'soft_bounced' => $m->where('m.status', 'soft_bounced'),
                'hard_bounced' => $m->where('m.status', 'hard_bounced'),
                'unsubscribed' => $m->where('m.status', 'unsubscribed'),
                'complained' => $m->where('m.status', 'spam_complaint'),
            };
            if ($from !== null) {
                $m->where($windowColumn, '>=', $from);
            }
            if ($to !== null) {
                $m->where($windowColumn, '<', $to);
            }
            if ($campaignIds !== null) {
                $m->whereIn('m.campaign_id', fn (QueryBuilder $c) => $c->select('id')->from('campaigns')->whereIn('uuid', $campaignIds));
            }
            if ($linkUrl !== null) {
                $m->whereExists(function (QueryBuilder $e) use ($linkUrl): void {
                    $e->selectRaw('1')->from('message_events as me')->whereColumn('me.message_id', 'm.id');
                    str_ends_with($linkUrl, '*')
                        ? $e->where('me.url', 'like', $this->escapeLike(substr($linkUrl, 0, -1)).'%')
                        : $e->where('me.url', $linkUrl);
                });
            }
            if ($stepId !== null) {
                $m->whereIn('m.automation_step_id', fn (QueryBuilder $s) => $s->select('id')->from('automation_steps')->where('uuid', $stepId));
            }
        };

        $minCount = $value['min_count'] ?? null;
        if ($minCount !== null) {
            $counter = $event === 'opened' ? 'm.open_count' : 'm.click_count';
            $q->where(function (QueryBuilder $sub) use ($messages, $counter): void {
                $messages($sub);
                $sub->selectRaw("COALESCE(SUM({$counter}), 0)");
            }, '>=', $minCount);

            return;
        }

        $existsMessage = function (QueryBuilder $sub) use ($messages): void {
            $sub->selectRaw('1');
            $messages($sub);
        };

        $reasons = match ($event) {
            'unsubscribed' => ['manual_unsubscribe', 'global_unsubscribe'],
            'complained' => ['spam_complaint'],
            default => null,
        };

        if ($reasons === null || $campaignIds !== null || $stepId !== null) {
            $q->whereExists($existsMessage);

            return;
        }

        $q->where(function ($either) use ($existsMessage, $reasons, $from, $to): void {
            $either->whereExists($existsMessage)->orWhereExists(function (QueryBuilder $s) use ($reasons, $from, $to): void {
                $s->selectRaw('1')->from('suppression_entries as s')
                    ->whereColumn('s.email', 'recipients.email')
                    ->whereIn('s.reason', $reasons);
                if ($from !== null) {
                    $s->where('s.created_at', '>=', $from);
                }
                if ($to !== null) {
                    $s->where('s.created_at', '<', $to);
                }
            });
        });
    }

    /**
     * UTC [from, to) bounds of an EngagementValue window (`from`/`to` override `within_days`).
     *
     * @param  array<string, mixed>  $value
     * @return array{?string, ?string}
     */
    private function window(array $value): array
    {
        $from = $value['from'] ?? null;
        $to = $value['to'] ?? null;

        if ($from !== null || $to !== null) {
            return [
                $from !== null ? $this->utcStartOf($from) : null,
                $to !== null ? $this->utcStartOf(Carbon::parse($to)->addDay()) : null,
            ];
        }

        $days = $value['within_days'] ?? null;

        return $days !== null ? [$this->utcStartOf($this->today()->subDays($days)), null] : [null, null];
    }

    // ---------------------------------------------------------------------
    // Legacy v1 upgrade (doc 13 §13.7)
    // ---------------------------------------------------------------------

    private function legacyOperator(mixed $operator): string
    {
        return strtolower((string) $operator) === 'or' ? 'or' : 'and';
    }

    /**
     * @param  array<string, mixed>  $c
     * @return array<string, mixed>
     */
    private function upgradeCondition(array $c): array
    {
        $field = (string) ($c['field'] ?? '');
        $op = (string) ($c['operator'] ?? '');
        $value = $c['value'] ?? null;
        $make = fn (string $f, string $o, mixed $v = null, array $extra = []) => ['type' => 'condition', 'field' => $f, 'operator' => $o, 'value' => $v] + $extra;
        $unsupported = fn () => new InvalidArgumentException("Opérateur de segment non pris en charge : {$op}");

        if ($field === 'tags') {
            $values = (array) $value;
            $ids = array_values(array_filter($values, 'is_int'));
            $names = array_values(array_filter($values, 'is_string'));
            if ($names !== []) {
                $ids = array_merge($ids, Tag::query()->whereIn('name', $names)->pluck('id')->map(fn ($id) => (int) $id)->all());
            }
            $ids = $ids === [] ? [0] : array_values(array_unique($ids));

            return match ($op) {
                'includes_any', 'contains_any', 'equals' => $make('contact.tags', 'contains_any', $ids),
                'includes_all', 'contains_all' => $make('contact.tags', 'contains_all', $ids),
                'excludes_any', 'not_contains_any', 'not_equals' => $make('contact.tags', 'not_contains_any', $ids),
                'is_empty', 'is_not_empty' => $make('contact.tags', $op),
                default => throw $unsupported(),
            };
        }

        if (in_array($field, ['status', 'source'], true)) {
            return match ($op) {
                'equals' => $make("contact.{$field}", 'in', [(string) $value]),
                'not_equals' => $make("contact.{$field}", 'not_in', [(string) $value]),
                'in', 'not_in' => $make("contact.{$field}", $op, array_map('strval', (array) $value)),
                'is_empty', 'is_not_empty' => $make("contact.{$field}", $op),
                default => throw $unsupported(),
            };
        }

        $textColumns = ['email' => 'attribute.EMAIL', 'first_name' => 'attribute.PRENOM', 'last_name' => 'attribute.NOM', 'company_name' => 'attribute.SOCIETE'];
        if (isset($textColumns[$field]) || str_starts_with($field, 'custom_fields.')) {
            // v1 `custom_fields.<key>` keeps its raw JSON key (`legacy_key`) so the
            // condition still reads `custom_fields-><key>` (exact case) even when
            // no `contact_attributes` row exists for it (FieldCatalog).
            $key = substr($field, strlen('custom_fields.'));
            $target = $textColumns[$field] ?? 'attribute.'.$key;
            $extra = isset($textColumns[$field]) ? [] : ['field_type' => 'text', 'legacy_key' => $key];

            return match ($op) {
                'equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with' => $make($target, $op, is_scalar($value) ? (string) $value : $value, $extra),
                'in', 'not_in' => $make($target, $op, array_map('strval', (array) $value), $extra),
                'is_empty', 'is_not_empty' => $make($target, $op, null, $extra),
                default => throw $unsupported(),
            };
        }

        if (in_array($field, ['last_contacted_at', 'created_at', 'updated_at'], true)) {
            $target = "contact.{$field}";
            if (in_array($op, ['is_empty', 'is_not_empty'], true)) {
                return $make($target, $op);
            }
            if (! in_array($op, ['before', 'after'], true) || ! is_string($value)) {
                throw $unsupported();
            }
            if (preg_match('/^-(\d+)d$/', $value, $m)) {
                return $make($target, $op === 'before' ? 'more_than_days_ago' : 'in_last_days', max(1, (int) $m[1]));
            }

            $date = preg_match('/^\+(\d+)d$/', $value, $m)
                ? $this->today()->addDays((int) $m[1])
                : Carbon::parse($value)->setTimezone($this->timezone());

            return $make($target, $op, $date->toDateString());
        }

        if (str_starts_with($field, 'pagejaunes_company_cache.')) {
            $column = substr($field, strlen('pagejaunes_company_cache.'));
            $map = ['wilaya_label' => 'wilaya', 'locality_label' => 'locality', 'legal_form_label' => 'legal_form', 'sector' => 'sector'];
            if (in_array($column, ['is_exporter', 'is_importer', 'is_producer', 'is_distributor'], true)) {
                $truthy = in_array($value, [true, 1, '1', 'true'], true);

                return match ($op) {
                    'equals' => $make("pagejaunes.{$column}", $truthy ? 'is_true' : 'is_false'),
                    'not_equals' => $make("pagejaunes.{$column}", $truthy ? 'is_false' : 'is_true'),
                    default => throw $unsupported(),
                };
            }
            if (! isset($map[$column])) {
                throw new InvalidArgumentException("Champ de segment non autorisé : {$field}");
            }

            return match ($op) {
                'equals', 'not_equals', 'contains', 'not_contains' => $make("pagejaunes.{$map[$column]}", $op, (string) $value),
                'is_empty', 'is_not_empty' => $make("pagejaunes.{$map[$column]}", $op),
                default => throw $unsupported(),
            };
        }

        throw new InvalidArgumentException("Champ de segment non autorisé : {$field}");
    }

    // ---------------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------------

    private function base(EloquentBuilder|QueryBuilder $q): QueryBuilder
    {
        return $q instanceof EloquentBuilder ? $q->getQuery() : $q;
    }

    private function wrap(EloquentBuilder|QueryBuilder $q, string $column): string
    {
        return $this->base($q)->getGrammar()->wrap($column);
    }

    private function driver(EloquentBuilder|QueryBuilder $q): string
    {
        return $this->base($q)->getConnection()->getDriverName();
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = DB::connection()->getName().'.'.$table;
        self::$columns[$key] ??= Schema::getColumnListing($table);

        return in_array($column, self::$columns[$key], true);
    }

    private function linkTrackingAvailable(): bool
    {
        return Schema::hasTable('message_events') && $this->hasColumn('message_events', 'message_id') && $this->hasColumn('message_events', 'url');
    }

    private function automationStepsAvailable(): bool
    {
        return $this->hasColumn('messages', 'automation_step_id') && Schema::hasTable('automation_steps');
    }

    private function lower(mixed $value): string
    {
        return mb_strtolower((string) $value);
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function timezone(): string
    {
        return (string) config('app.account_timezone', self::DEFAULT_TIMEZONE);
    }

    private function today(): Carbon
    {
        return Carbon::now($this->timezone())->startOfDay();
    }

    private function utcStartOf(Carbon|string $day): string
    {
        $date = $day instanceof Carbon ? $day->toDateString() : $day;

        return Carbon::parse($date, $this->timezone())->startOfDay()->utc()->format('Y-m-d H:i:s');
    }

    /**
     * Forget cached column listings (tests that alter the schema).
     */
    public static function flushSchemaCache(): void
    {
        self::$columns = [];
    }
}
