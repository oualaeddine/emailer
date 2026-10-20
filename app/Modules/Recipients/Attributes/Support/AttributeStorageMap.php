<?php

namespace App\Modules\Recipients\Attributes\Support;

use Illuminate\Container\Attributes\Scoped;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45 §45.5.1 — where each contact attribute's value lives, shared by
 * the segment compiler ({@see \App\Modules\Recipients\Segments\Services\FieldCatalog})
 * and the contacts table ({@see \App\Modules\Recipients\Views\Support\ContactColumns}).
 *
 * `storage = 'column'` attributes (EMAIL, PRENOM, NOM, SOCIETE, EXT_ID → `ext_id`)
 * read a `recipients` column; every other attribute reads
 * `recipients.custom_fields-><name>`. One registry read per request (scoped).
 */
#[Scoped]
class AttributeStorageMap
{
    /** Fallback when the `contact_attributes` registry (WP-50) is absent. */
    public const BUILTIN_COLUMNS = [
        'EMAIL' => 'email',
        'PRENOM' => 'first_name',
        'NOM' => 'last_name',
        'SOCIETE' => 'company_name',
    ];

    /** @var array<string, array{name: string, type: string, column: ?string}>|null */
    private ?array $rows = null;

    /** @var list<string>|null */
    private ?array $recipientColumns = null;

    /**
     * Attribute definition keyed by UPPERCASE name, or null when unknown.
     *
     * @return array{name: string, type: string, column: ?string}|null
     */
    public function find(string $name): ?array
    {
        return $this->rows()[strtoupper($name)] ?? null;
    }

    /**
     * The existing `recipients` column holding the attribute, or null when it
     * is stored in `custom_fields` (or unknown).
     */
    public function column(string $name): ?string
    {
        return $this->find($name)['column'] ?? null;
    }

    /** The attribute type (`text` when unknown). */
    public function type(string $name): string
    {
        return $this->find($name)['type'] ?? 'text';
    }

    /**
     * @return array<string, array{name: string, type: string, column: ?string}>
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $rows = [];
        foreach (self::BUILTIN_COLUMNS as $name => $column) {
            $rows[$name] = ['name' => $name, 'type' => 'text', 'column' => $column];
        }

        if (Schema::hasTable('contact_attributes') && Schema::hasColumns('contact_attributes', ['name', 'type'])) {
            $hasStorage = Schema::hasColumns('contact_attributes', ['storage', 'column']);
            foreach (DB::table('contact_attributes')->get() as $row) {
                $upper = strtoupper((string) $row->name);
                $column = $hasStorage && $row->storage === 'column' && is_string($row->column) && $this->hasRecipientColumn($row->column)
                    ? $row->column
                    : null;
                $rows[$upper] = [
                    'name' => (string) $row->name,
                    'type' => (string) $row->type,
                    'column' => $column ?? (self::BUILTIN_COLUMNS[$upper] ?? null),
                ];
            }
        }

        return $this->rows = $rows;
    }

    private function hasRecipientColumn(string $column): bool
    {
        $this->recipientColumns ??= Schema::getColumnListing('recipients');

        return in_array($column, $this->recipientColumns, true);
    }
}
