<?php

namespace App\Modules\Automations\Services;

use App\Modules\Automations\Enums\AutomationStepType;
use App\Modules\Automations\Enums\AutomationTriggerType;
use App\Modules\Automations\Enums\AutomationWaitUnit;
use App\Modules\Recipients\Attributes\Contracts\ContactAttributeService;
use App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * docs/45 §45.7.5 — trigger and step-tree validation. "Lenient" mode (save
 * of a draft) accepts missing references (no template yet…) but rejects
 * malformed or unknown ones; "strict" mode (activation) also requires every
 * reference and at least one step. Errors are keyed by dot path
 * (`trigger.config.list_ids.0`, `steps.1.yes.0.config.template_id`).
 */
class AutomationValidator
{
    public const MAX_STEPS = 50;

    public const MAX_CONDITION_DEPTH = 3;

    private const MAX_WAIT_MINUTES = 365 * 1440;

    public function __construct(
        private readonly RuleTreeCompiler $compiler,
        private readonly ContactAttributeService $attributes,
    ) {}

    // ------------------------------------------------------------------
    // Trigger
    // ------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $trigger  { type, config }
     * @return array<string, string>
     */
    public function triggerErrors(array $trigger, bool $strict, string $path = 'trigger'): array
    {
        $type = AutomationTriggerType::tryFrom((string) ($trigger['type'] ?? ''));
        if ($type === null) {
            return ["{$path}.type" => 'Déclencheur inconnu.'];
        }

        $config = $trigger['config'] ?? [];
        if (! is_array($config)) {
            return ["{$path}.config" => 'Configuration du déclencheur invalide.'];
        }

        $p = "{$path}.config";
        $errors = [];

        switch ($type) {
            case AutomationTriggerType::ListAdded:
                $errors += $this->uuidListErrors($config['list_ids'] ?? [], 'recipient_lists', "{$p}.list_ids", 'Liste introuvable.', $strict, 'Choisissez au moins une liste.');
                break;

            case AutomationTriggerType::FormSubmitted:
                $errors += $this->uuidListErrors($config['form_ids'] ?? [], 'forms', "{$p}.form_ids", 'Formulaire introuvable.', $strict, 'Choisissez au moins un formulaire.');
                break;

            case AutomationTriggerType::CampaignOpened:
            case AutomationTriggerType::CampaignClicked:
                if (($config['campaign_ids'] ?? null) !== null) {
                    $errors += $this->uuidListErrors($config['campaign_ids'], 'campaigns', "{$p}.campaign_ids", 'Campagne introuvable.', false, '');
                }
                $link = $config['link_url'] ?? null;
                if ($link !== null && (! is_string($link) || mb_strlen($link) > 2048)) {
                    $errors["{$p}.link_url"] = 'Lien invalide.';
                }
                break;

            case AutomationTriggerType::AttributeChanged:
                $errors += $this->attributeErrors($config['attribute'] ?? null, "{$p}.attribute", $strict);
                break;

            case AutomationTriggerType::DateAnniversary:
                $errors += $this->attributeErrors($config['attribute'] ?? null, "{$p}.attribute", $strict, 'date');
                $offset = $config['offset_days'] ?? 0;
                if (filter_var($offset, FILTER_VALIDATE_INT) === false || abs((int) $offset) > 365) {
                    $errors["{$p}.offset_days"] = 'Le décalage doit être compris entre -365 et 365 jours.';
                }
                $time = $config['time'] ?? '09:00';
                if (! is_string($time) || preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) !== 1) {
                    $errors["{$p}.time"] = 'Heure invalide (HH:MM).';
                }
                break;
        }

        $filter = $config['filter'] ?? null;
        if ($filter !== null) {
            $errors += is_array($filter) ? $this->ruleErrors($filter, "{$p}.filter") : ["{$p}.filter" => 'Filtre invalide.'];
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeTriggerConfig(string $type, array $config): array
    {
        $normalized = match (AutomationTriggerType::from($type)) {
            AutomationTriggerType::ListAdded => ['list_ids' => array_values((array) ($config['list_ids'] ?? []))],
            AutomationTriggerType::FormSubmitted => ['form_ids' => array_values((array) ($config['form_ids'] ?? []))],
            AutomationTriggerType::CampaignOpened => ['campaign_ids' => self::nullableList($config['campaign_ids'] ?? null)],
            AutomationTriggerType::CampaignClicked => [
                'campaign_ids' => self::nullableList($config['campaign_ids'] ?? null),
                'link_url' => ($config['link_url'] ?? null) ?: null,
            ],
            AutomationTriggerType::AttributeChanged => [
                'attribute' => self::upperOrNull($config['attribute'] ?? null),
                'to_value' => $config['to_value'] ?? null,
            ],
            AutomationTriggerType::DateAnniversary => [
                'attribute' => self::upperOrNull($config['attribute'] ?? null),
                'offset_days' => (int) ($config['offset_days'] ?? 0),
                'time' => (string) ($config['time'] ?? '09:00'),
            ],
        };

        $normalized['filter'] = is_array($config['filter'] ?? null) ? $config['filter'] : null;

        return $normalized;
    }

    // ------------------------------------------------------------------
    // Steps
    // ------------------------------------------------------------------

    /**
     * @param  mixed  $steps  AutomationStepInput[]
     * @return array<string, string>
     */
    public function stepsErrors(mixed $steps, bool $strict, string $path = 'steps'): array
    {
        if (! is_array($steps) || ! array_is_list($steps)) {
            return [$path => 'Les étapes doivent être une liste.'];
        }

        if ($strict && $steps === []) {
            return [$path => 'Ajoutez au moins une étape.'];
        }

        $errors = [];
        $count = 0;
        $ids = [];
        $this->walk($steps, $path, 0, $strict, $errors, $count, $ids);

        if ($count > self::MAX_STEPS) {
            $errors[$path] = 'Un scénario compte au plus '.self::MAX_STEPS.' étapes.';
        }

        return $errors;
    }

    /**
     * @param  array<int, mixed>  $nodes
     * @param  array<string, string>  $errors
     * @param  array<string, true>  $ids
     */
    private function walk(array $nodes, string $path, int $depth, bool $strict, array &$errors, int &$count, array &$ids): void
    {
        $lastIndex = count($nodes) - 1;

        foreach (array_values($nodes) as $i => $node) {
            $count++;
            $p = "{$path}.{$i}";

            if (! is_array($node)) {
                $errors[$p] = 'Étape invalide.';

                continue;
            }

            $type = AutomationStepType::tryFrom((string) ($node['type'] ?? ''));
            if ($type === null) {
                $errors["{$p}.type"] = 'Type d’étape inconnu.';

                continue;
            }

            if (isset($node['id'])) {
                if (! is_string($node['id']) || isset($ids[$node['id']])) {
                    $errors["{$p}.id"] = 'Identifiant d’étape invalide ou dupliqué.';
                }
                $ids[(string) $node['id']] = true;
            }

            if (isset($node['position']) && filter_var($node['position'], FILTER_VALIDATE_INT) === false) {
                $errors["{$p}.position"] = 'Position invalide.';
            }

            $config = $node['config'] ?? [];
            if (! is_array($config)) {
                $errors["{$p}.config"] = 'Configuration invalide.';

                continue;
            }

            $errors += $this->configErrors($type, $config, "{$p}.config", $strict);

            $yes = $node['yes'] ?? [];
            $no = $node['no'] ?? [];
            if (! is_array($yes) || ! is_array($no)) {
                $errors[$p] = 'Branches invalides.';

                continue;
            }

            if ($type === AutomationStepType::Condition) {
                if ($depth + 1 > self::MAX_CONDITION_DEPTH) {
                    $errors["{$p}.type"] = 'Au plus '.self::MAX_CONDITION_DEPTH.' conditions imbriquées.';
                }
                $this->walk(array_values($yes), "{$p}.yes", $depth + 1, $strict, $errors, $count, $ids);
                $this->walk(array_values($no), "{$p}.no", $depth + 1, $strict, $errors, $count, $ids);
            } elseif ($yes !== [] || $no !== []) {
                $errors[$p] = 'Seules les conditions ont des branches oui / non.';
            }

            if ($type === AutomationStepType::End && $i !== $lastIndex) {
                $errors["{$p}.type"] = 'L’étape « Fin » doit être la dernière de sa branche.';
            }
        }
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, string>
     */
    private function configErrors(AutomationStepType $type, array $config, string $p, bool $strict): array
    {
        $errors = [];

        switch ($type) {
            case AutomationStepType::SendEmail:
                $errors += $this->referenceErrors($config['template_id'] ?? null, 'templates', "{$p}.template_id", 'Modèle introuvable.', $strict, 'Choisissez un modèle d’e-mail.');
                if (($config['sender_id'] ?? null) !== null && Schema::hasTable('senders')) {
                    $errors += $this->referenceErrors($config['sender_id'], 'senders', "{$p}.sender_id", 'Expéditeur introuvable.', false, '');
                    // next-sender — optional, but it must be verified before activation (lenient for drafts).
                    if ($strict && ! isset($errors["{$p}.sender_id"])
                        && DB::table('senders')->where('uuid', $config['sender_id'])->whereNull('verified_at')->exists()) {
                        $errors["{$p}.sender_id"] = 'L’expéditeur doit être vérifié avant l’activation.';
                    }
                }
                foreach (['subject' => 998, 'preview_text' => 255] as $key => $max) {
                    $value = $config[$key] ?? null;
                    if ($value !== null && (! is_string($value) || mb_strlen($value) > $max)) {
                        $errors["{$p}.{$key}"] = "Texte trop long (max {$max} caractères).";
                    }
                }
                break;

            case AutomationStepType::Wait:
                $amount = $config['amount'] ?? null;
                $unit = AutomationWaitUnit::tryFrom((string) ($config['unit'] ?? ''));
                if ($amount === null && $unit === null && ! $strict) {
                    break;
                }
                if ($unit === null) {
                    $errors["{$p}.unit"] = 'Unité invalide (minutes, heures ou jours).';
                } elseif (filter_var($amount, FILTER_VALIDATE_INT) === false || (int) $amount < 1 || $unit->toMinutes((int) $amount) > self::MAX_WAIT_MINUTES) {
                    $errors["{$p}.amount"] = 'Le délai doit être compris entre 1 minute et 365 jours.';
                }
                break;

            case AutomationStepType::Condition:
                $rules = $config['rules'] ?? null;
                if ($rules === null) {
                    if ($strict) {
                        $errors["{$p}.rules"] = 'Définissez la condition.';
                    }
                    break;
                }
                $errors += is_array($rules) ? $this->ruleErrors($rules, "{$p}.rules") : ["{$p}.rules" => 'Condition invalide.'];
                break;

            case AutomationStepType::AddToList:
            case AutomationStepType::RemoveFromList:
                $errors += $this->referenceErrors($config['list_id'] ?? null, 'recipient_lists', "{$p}.list_id", 'Liste introuvable.', $strict, 'Choisissez une liste.');
                break;

            case AutomationStepType::UpdateAttribute:
                $errors += $this->attributeErrors($config['attribute'] ?? null, "{$p}.attribute", $strict);
                $name = self::upperOrNull($config['attribute'] ?? null);
                if ($name === 'EMAIL') {
                    $errors["{$p}.attribute"] = 'L’adresse e-mail ne peut pas être modifiée par une automatisation.';
                } elseif ($name !== null && ! isset($errors["{$p}.attribute"]) && ($config['value'] ?? null) !== null) {
                    try {
                        $this->attributes->castForStorage($this->attributes->definitions()->get($name), $config['value']);
                    } catch (ValidationException $e) {
                        $errors["{$p}.value"] = (string) collect($e->errors())->flatten()->first();
                    }
                }
                break;

            case AutomationStepType::End:
                break;
        }

        return $errors;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    public function normalizeStepConfig(string $type, array $config): array
    {
        return match (AutomationStepType::from($type)) {
            AutomationStepType::SendEmail => [
                'template_id' => $config['template_id'] ?? null,
                'subject' => ($config['subject'] ?? null) ?: null,
                'sender_id' => $config['sender_id'] ?? null,
                'preview_text' => ($config['preview_text'] ?? null) ?: null,
            ],
            AutomationStepType::Wait => [
                'amount' => isset($config['amount']) ? (int) $config['amount'] : null,
                'unit' => $config['unit'] ?? null,
            ],
            AutomationStepType::Condition => ['rules' => $config['rules'] ?? null],
            AutomationStepType::AddToList, AutomationStepType::RemoveFromList => ['list_id' => $config['list_id'] ?? null],
            AutomationStepType::UpdateAttribute => [
                'attribute' => self::upperOrNull($config['attribute'] ?? null),
                'value' => $config['value'] ?? null,
            ],
            AutomationStepType::End => [],
        };
    }

    /** @param  array<string, string>  $errors */
    public static function throwIfAny(array $errors): void
    {
        if ($errors !== []) {
            throw ValidationException::withMessages(array_map(fn (string $m) => [$m], $errors));
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @return array<string, string> */
    private function ruleErrors(array $tree, string $path): array
    {
        try {
            $this->compiler->validate($tree, $path);

            return [];
        } catch (ValidationException $e) {
            return array_map(fn ($messages) => (string) (is_array($messages) ? reset($messages) : $messages), $e->errors());
        }
    }

    /** @return array<string, string> */
    private function attributeErrors(mixed $name, string $path, bool $strict, ?string $type = null): array
    {
        $name = self::upperOrNull($name);
        if ($name === null) {
            return $strict ? [$path => 'Choisissez un attribut.'] : [];
        }

        $definition = $this->attributes->definitions()->get($name);
        if ($definition === null) {
            return [$path => "Attribut inconnu : {$name}"];
        }

        if ($type !== null && $definition->type !== $type) {
            return [$path => 'L’attribut doit être de type date.'];
        }

        return [];
    }

    /** @return array<string, string> */
    private function uuidListErrors(mixed $ids, string $table, string $path, string $missing, bool $strict, string $emptyMessage): array
    {
        if (! is_array($ids) || ! array_is_list($ids)) {
            return [$path => 'Liste d’identifiants invalide.'];
        }

        if ($ids === []) {
            return $strict ? [$path => $emptyMessage] : [];
        }

        if (! Schema::hasTable($table)) {
            return [$path => $missing];
        }

        $found = $this->existingUuids($table, $ids);
        foreach ($ids as $i => $id) {
            if (! is_string($id) || ! in_array($id, $found, true)) {
                return ["{$path}.{$i}" => $missing];
            }
        }

        return [];
    }

    /** @return array<string, string> */
    private function referenceErrors(mixed $id, string $table, string $path, string $missing, bool $strict, string $emptyMessage): array
    {
        if ($id === null || $id === '') {
            return $strict ? [$path => $emptyMessage] : [];
        }

        return is_string($id) && $this->existingUuids($table, [$id]) !== [] ? [] : [$path => $missing];
    }

    /**
     * @param  array<int, mixed>  $ids
     * @return list<string>
     */
    private function existingUuids(string $table, array $ids): array
    {
        $ids = array_values(array_filter($ids, fn ($id): bool => is_string($id) && Str::isUuid($id)));
        if ($ids === []) {
            return [];
        }

        $query = DB::table($table)->whereIn('uuid', $ids);
        if (Schema::hasColumn($table, 'deleted_at')) {
            $query->whereNull('deleted_at');
        }

        return $query->pluck('uuid')->map(fn ($u) => (string) $u)->all();
    }

    /** @return list<string>|null */
    private static function nullableList(mixed $value): ?array
    {
        return is_array($value) && $value !== [] ? array_values($value) : null;
    }

    private static function upperOrNull(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? strtoupper(trim($value)) : null;
    }
}
