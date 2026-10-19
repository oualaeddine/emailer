<?php

namespace App\Modules\Recipients\Segments\Services;

use App\Domain\Enums\RecipientStatus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.5.2 (field keys, operator sets) and
 * §45.5.3 (`FieldCatalog` / `FieldDefinition` served to the ConditionBuilder).
 *
 * Besides the public catalog, every definition carries an internal `target`
 * consumed by {@see RuleTreeCompiler} (never serialised).
 *
 * Attribute fields: rows of `contact_attributes` (WP-50) when that table
 * exists; built-in attributes (EMAIL, PRENOM, NOM, SOCIETE) and rows with
 * `storage = 'column'` (EXT_ID → `ext_id`) map to `recipients` columns; any
 * other `attribute.NAME` reads `recipients.custom_fields->NAME`, typed by the
 * attribute row, else by the condition's optional `field_type`, else `text`.
 * Conditions upgraded from v1 `custom_fields.<key>` carry `legacy_key` and
 * always read `custom_fields-><key>` (exact v1 key, as text).
 */
class FieldCatalog
{
    /** @var array<string, list<string>> §45.5.2 "Operators per type" */
    public const OPERATORS = [
        'text' => ['equals', 'not_equals', 'contains', 'not_contains', 'starts_with', 'ends_with', 'in', 'not_in', 'is_empty', 'is_not_empty'],
        'number' => ['equals', 'not_equals', 'greater_than', 'greater_or_equal', 'less_than', 'less_or_equal', 'between', 'is_empty', 'is_not_empty'],
        'date' => ['on', 'before', 'after', 'between', 'in_last_days', 'more_than_days_ago', 'in_next_days', 'anniversary_today', 'is_empty', 'is_not_empty'],
        'boolean' => ['is_true', 'is_false', 'is_empty'],
        'category' => ['in', 'not_in', 'is_empty', 'is_not_empty'],
        'multiple_choice' => ['contains_any', 'contains_all', 'not_contains_any', 'is_empty', 'is_not_empty'],
        'user' => ['in', 'not_in', 'is_empty', 'is_not_empty'],
        'tags' => ['contains_any', 'contains_all', 'not_contains_any', 'is_empty', 'is_not_empty'],
        'list' => ['in', 'not_in'],
        'engagement' => ['has', 'has_not'],
    ];

    /** Attribute types usable with `attribute.*` / `field_type`. */
    public const ATTRIBUTE_TYPES = ['text', 'number', 'date', 'boolean', 'category', 'multiple_choice', 'user'];

    /** @var array<string, array{string, string}> NAME => [recipients column, French label] */
    public const BUILTIN_ATTRIBUTES = [
        'EMAIL' => ['email', 'E-mail'],
        'PRENOM' => ['first_name', 'Prénom'],
        'NOM' => ['last_name', 'Nom'],
        'SOCIETE' => ['company_name', 'Société'],
    ];

    public const ENGAGEMENT_EVENTS = [
        'sent' => 'A reçu un e-mail',
        'delivered' => 'E-mail délivré',
        'opened' => 'A ouvert',
        'clicked' => 'A cliqué',
        'soft_bounced' => 'Soft bounce',
        'hard_bounced' => 'Hard bounce',
        'unsubscribed' => "S'est désinscrit",
        'complained' => 'A signalé comme spam',
    ];

    /** @var array<string, string> */
    public const SOURCES = [
        'manual' => 'Manuel',
        'csv_import' => 'Import CSV',
        'excel_import' => 'Import Excel',
        'pagejaunes' => 'PagesJaunes',
        'internal_contact' => 'Contact interne',
        'form' => 'Formulaire',
        'api' => 'API',
    ];

    /** @var array<string, array<string, mixed>>|null */
    private ?array $attributeRows = null;

    private bool $attributesLoaded = false;

    /**
     * §45.5.3 `FieldCatalog` — `{ fields: FieldDefinition[] }`.
     *
     * @return array{fields: list<array<string, mixed>>}
     */
    public function catalog(): array
    {
        $fields = [];
        foreach ($this->attributeDefinitions() as $definition) {
            $fields[] = $this->public($definition);
        }
        foreach ($this->staticDefinitions() as $definition) {
            $fields[] = $this->public($definition);
        }

        return ['fields' => $fields];
    }

    /**
     * Resolves a field key to its definition (with internal `target`), or
     * null when unknown. `$fieldType` = the condition's optional `field_type`.
     *
     * @return array<string, mixed>|null
     */
    public function definition(string $key, ?string $fieldType = null, ?string $legacyKey = null): ?array
    {
        if (str_starts_with($key, 'attribute.')) {
            $name = substr($key, strlen('attribute.'));

            return $legacyKey !== null
                ? $this->legacyDefinition($name, $legacyKey)
                : $this->attributeDefinition($name, $fieldType);
        }

        return $this->staticDefinitions()[$key] ?? null;
    }

    /**
     * True when attribute names are checked against `contact_attributes` (WP-50).
     */
    public function hasAttributeRegistry(): bool
    {
        return $this->attributeRows() !== null;
    }

    /**
     * @return list<string>
     */
    public function operatorsFor(string $type): array
    {
        return self::OPERATORS[$type] ?? [];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function attributeDefinition(string $name, ?string $fieldType): ?array
    {
        if (! preg_match('/^[A-Za-z][A-Za-z0-9_]{0,99}$/', $name)) {
            return null;
        }

        $upper = strtoupper($name);
        if (isset(self::BUILTIN_ATTRIBUTES[$upper])) {
            [$column, $label] = self::BUILTIN_ATTRIBUTES[$upper];

            return [
                'key' => 'attribute.'.$upper, 'label' => $label, 'category' => 'attribute', 'type' => 'text',
                'target' => ['kind' => 'column', 'column' => $column],
            ];
        }

        $rows = $this->attributeRows();
        if ($rows !== null) {
            $row = $rows[$upper] ?? null;
            if ($row === null) {
                return null; // "Attribut inconnu"
            }

            $target = isset($row['column'])
                ? ['kind' => 'optional_column', 'column' => $row['column']]
                : ['kind' => 'json', 'name' => $row['name']];
            unset($row['column']);

            return $row + ['target' => $target];
        }

        $type = in_array($fieldType, self::ATTRIBUTE_TYPES, true) ? $fieldType : 'text';

        return [
            'key' => 'attribute.'.$name, 'label' => $name, 'category' => 'attribute', 'type' => $type,
            'target' => ['kind' => 'json', 'name' => $name],
        ];
    }

    /**
     * A condition upgraded from v1 `custom_fields.<key>` (doc 13 §13.7): v1
     * compared the raw JSON key as text, so the definition keeps that exact
     * key whether or not a registry row exists for it.
     *
     * @return array<string, mixed>|null
     */
    private function legacyDefinition(string $name, string $legacyKey): ?array
    {
        if (! preg_match('/^[A-Za-z0-9_-]{1,100}$/', $legacyKey) || strcasecmp($name, $legacyKey) !== 0) {
            return null;
        }

        $label = $this->attributeRows()[strtoupper($legacyKey)]['label'] ?? $legacyKey;

        return [
            'key' => 'attribute.'.$name, 'label' => $label, 'category' => 'attribute', 'type' => 'text',
            'target' => ['kind' => 'json', 'name' => $legacyKey],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function attributeDefinitions(): array
    {
        $definitions = [];
        foreach (self::BUILTIN_ATTRIBUTES as $name => [$column, $label]) {
            $definitions[$name] = [
                'key' => 'attribute.'.$name, 'label' => $label, 'category' => 'attribute', 'type' => 'text',
            ];
        }
        foreach ($this->attributeRows() ?? [] as $upper => $row) {
            unset($row['column']);
            $definitions[$upper] ??= $row;
        }

        return array_values($definitions);
    }

    /**
     * `contact_attributes` rows keyed by upper-cased name, or null when the
     * table (WP-50) is not there.
     *
     * @return array<string, array<string, mixed>>|null
     */
    private function attributeRows(): ?array
    {
        if ($this->attributesLoaded) {
            return $this->attributeRows;
        }
        $this->attributesLoaded = true;

        if (! $this->registryExists()) {
            return $this->attributeRows = null;
        }

        $columns = Schema::getColumnListing('contact_attributes');
        $rows = [];
        foreach (DB::table('contact_attributes')->orderBy('name')->get() as $row) {
            $row = (array) $row;
            $name = (string) $row['name'];
            $type = in_array($row['type'] ?? null, self::ATTRIBUTE_TYPES, true) ? $row['type'] : 'text';
            $definition = [
                'key' => 'attribute.'.$name,
                'name' => $name,
                'label' => in_array('label', $columns, true) && filled($row['label']) ? (string) $row['label'] : $name,
                'category' => 'attribute',
                'type' => $type,
            ];
            if (in_array('storage', $columns, true) && ($row['storage'] ?? null) === 'column' && filled($row['column'] ?? null)) {
                $definition['column'] = (string) $row['column'];
            }
            if (in_array('options', $columns, true) && filled($row['options'])) {
                $options = is_string($row['options']) ? json_decode($row['options'], true) : $row['options'];
                if (is_array($options)) {
                    $definition['options'] = array_values(array_map(
                        fn ($o) => is_array($o) ? ['value' => $o['value'] ?? $o['label'] ?? '', 'label' => (string) ($o['label'] ?? $o['value'] ?? '')] : ['value' => $o, 'label' => (string) $o],
                        $options,
                    ));
                }
            }
            $rows[strtoupper($name)] = $definition;
        }

        return $this->attributeRows = $rows;
    }

    private function registryExists(): bool
    {
        static $exists = [];
        $connection = DB::connection()->getName();

        return $exists[$connection] ??= Schema::hasTable('contact_attributes')
            && Schema::hasColumns('contact_attributes', ['name', 'type']);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function staticDefinitions(): array
    {
        $statusOptions = array_map(fn (RecipientStatus $s) => ['value' => $s->value, 'label' => $s->label()], RecipientStatus::cases());
        $sourceOptions = [];
        foreach (self::SOURCES as $value => $label) {
            $sourceOptions[] = ['value' => $value, 'label' => $label];
        }

        $defs = [
            'contact.tags' => ['Tags', 'contact', 'tags', ['kind' => 'tags']],
            'contact.status' => ['Statut', 'contact', 'category', ['kind' => 'column', 'column' => 'status'], $statusOptions],
            'contact.source' => ['Source', 'contact', 'category', ['kind' => 'column', 'column' => 'source'], $sourceOptions],
            'contact.blocklisted' => ['Dans la liste de blocage', 'contact', 'boolean', ['kind' => 'blocklisted']],
            'contact.engagement_score' => ["Score d'engagement", 'contact', 'number', ['kind' => 'optional_column', 'column' => 'engagement_score']],
            'list' => ['Listes', 'list', 'list', ['kind' => 'list']],
        ];
        foreach (self::ENGAGEMENT_EVENTS as $event => $label) {
            $defs['engagement.'.$event] = [$label, 'engagement', 'engagement', ['kind' => 'engagement', 'event' => $event]];
        }
        $defs += [
            'contact.created_at' => ["Date d'ajout", 'date', 'date', ['kind' => 'column', 'column' => 'created_at']],
            'contact.updated_at' => ['Date de modification', 'date', 'date', ['kind' => 'column', 'column' => 'updated_at']],
            'contact.last_contacted_at' => ['Dernier contact', 'date', 'date', ['kind' => 'column', 'column' => 'last_contacted_at']],
            'contact.last_opened_at' => ['Dernière ouverture', 'date', 'date', ['kind' => 'message_max', 'column' => 'last_opened_at', 'message_column' => 'opened_at']],
            'contact.last_clicked_at' => ['Dernier clic', 'date', 'date', ['kind' => 'message_max', 'column' => 'last_clicked_at', 'message_column' => 'clicked_at']],
            'contact.opt_in_at' => ["Date d'opt-in", 'date', 'date', ['kind' => 'optional_column', 'column' => 'opt_in_at']],
            'contact.blocklisted_at' => ['Date de blocage', 'date', 'date', ['kind' => 'blocklisted_at']],
            'pagejaunes.sector' => ['Secteur', 'pagejaunes', 'text', ['kind' => 'pagejaunes', 'column' => 'raw_source_data->sector']],
            'pagejaunes.wilaya' => ['Wilaya', 'pagejaunes', 'text', ['kind' => 'pagejaunes', 'column' => 'wilaya_label']],
            'pagejaunes.locality' => ['Localité', 'pagejaunes', 'text', ['kind' => 'pagejaunes', 'column' => 'locality_label']],
            'pagejaunes.legal_form' => ['Forme juridique', 'pagejaunes', 'text', ['kind' => 'pagejaunes', 'column' => 'legal_form_label']],
            'pagejaunes.is_exporter' => ['Exportateur', 'pagejaunes', 'boolean', ['kind' => 'pagejaunes', 'column' => 'is_exporter']],
            'pagejaunes.is_importer' => ['Importateur', 'pagejaunes', 'boolean', ['kind' => 'pagejaunes', 'column' => 'is_importer']],
            'pagejaunes.is_producer' => ['Producteur', 'pagejaunes', 'boolean', ['kind' => 'pagejaunes', 'column' => 'is_producer']],
            'pagejaunes.is_distributor' => ['Distributeur', 'pagejaunes', 'boolean', ['kind' => 'pagejaunes', 'column' => 'is_distributor']],
        ];

        $out = [];
        foreach ($defs as $key => $def) {
            $out[$key] = array_filter([
                'key' => $key, 'label' => $def[0], 'category' => $def[1], 'type' => $def[2],
                'options' => $def[4] ?? null, 'target' => $def[3],
            ], fn ($v) => $v !== null);
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $definition
     * @return array<string, mixed>
     */
    private function public(array $definition): array
    {
        return array_intersect_key($definition, array_flip(['key', 'label', 'category', 'type', 'options', 'operators']));
    }
}
