<?php

namespace App\Modules\Recipients\Views\Support;

use App\Modules\Recipients\Attributes\Support\AttributeStorageMap;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Segments\Services\RuleTreeCompiler;
use Illuminate\Database\Query\Expression;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * docs/45 §45.5.4 — column / sort whitelist for the contacts table.
 *
 * Keys: `attribute.NAME` (attributes stored in a column — system ones,
 * EXT_ID → `ext_id` — map to that column, any other name is read from the
 * `custom_fields` JSON column), `contact.<column>` (whitelisted base
 * columns), `contact.blocklisted` (computed, not sortable), `lists`, `tags`.
 */
final class ContactColumns
{
    public const DEFAULT_SORT = '-contact.created_at';

    /** System attributes (doc 43 §43.4.10) → recipients column. */
    public const SYSTEM_ATTRIBUTES = [
        'EMAIL' => 'email',
        'PRENOM' => 'first_name',
        'NOM' => 'last_name',
        'SOCIETE' => 'company_name',
    ];

    /** `contact.<key>` → recipients column (kept only when the column exists). */
    public const CONTACT_COLUMNS = [
        'created_at', 'updated_at', 'last_contacted_at', 'last_opened_at', 'last_clicked_at',
        'opt_in_at', 'blocklisted_at', 'engagement_score', 'status', 'source', 'ext_id', 'opt_in_source',
    ];

    /** `contact.<key>` computed from other columns (displayable, not sortable). */
    public const COMPUTED_COLUMNS = ['blocklisted'];

    private const DATE_COLUMNS = [
        'created_at', 'updated_at', 'last_contacted_at', 'last_opened_at', 'last_clicked_at', 'opt_in_at', 'blocklisted_at',
    ];

    private const LABELS = [
        'attribute.EMAIL' => 'E-mail', 'attribute.PRENOM' => 'Prénom', 'attribute.NOM' => 'Nom',
        'attribute.SOCIETE' => 'Société', 'contact.created_at' => 'Créé le', 'contact.updated_at' => 'Modifié le',
        'contact.status' => 'Statut', 'contact.source' => 'Source', 'lists' => 'Listes', 'tags' => 'Étiquettes',
        'contact.last_contacted_at' => 'Dernier contact', 'contact.blocklisted_at' => 'Mis en liste noire le',
        'contact.blocklisted' => 'En liste noire',
    ];

    /** @var list<string>|null */
    private static ?array $tableColumns = null;

    /**
     * @return list<array{key: string, width: int}>
     */
    public static function defaults(): array
    {
        return array_map(
            fn (string $key): array => ['key' => $key, 'width' => 160],
            ['attribute.EMAIL', 'attribute.PRENOM', 'attribute.NOM', 'attribute.SOCIETE', 'contact.created_at'],
        );
    }

    public static function flushCache(): void
    {
        self::$tableColumns = null;
    }

    public static function hasRecipientColumn(string $column): bool
    {
        self::$tableColumns ??= Schema::getColumnListing('recipients');

        return in_array($column, self::$tableColumns, true);
    }

    /**
     * @return array{kind: string, name: string, column: string|null}|null
     */
    public static function parse(string $key): ?array
    {
        if ($key === 'lists' || $key === 'tags') {
            return ['kind' => $key, 'name' => $key, 'column' => null];
        }

        if (preg_match('/^attribute\.([A-Za-z0-9_]{1,50})$/', $key, $m) === 1) {
            $name = strtoupper($m[1]);

            return ['kind' => 'attribute', 'name' => $name, 'column' => app(AttributeStorageMap::class)->column($name)];
        }

        if (str_starts_with($key, 'contact.')) {
            $name = substr($key, 8);

            if (in_array($name, self::COMPUTED_COLUMNS, true)) {
                return ['kind' => 'computed', 'name' => $name, 'column' => null];
            }

            if (in_array($name, self::CONTACT_COLUMNS, true) && self::hasRecipientColumn($name)) {
                return ['kind' => 'contact', 'name' => $name, 'column' => $name];
            }
        }

        return null;
    }

    /**
     * Validates column keys (strings or `{key, width}` objects) and returns
     * the normalised `[{key, width?}]` list.
     *
     * @param  array<int, mixed>  $columns
     * @return list<array{key: string, width?: int}>
     */
    public static function normalize(array $columns, string $field = 'columns'): array
    {
        $out = [];

        foreach (array_values($columns) as $i => $column) {
            $key = is_array($column) ? ($column['key'] ?? null) : $column;
            $parsed = is_string($key) ? self::parse($key) : null;

            if ($parsed === null) {
                throw ValidationException::withMessages([
                    "{$field}.{$i}" => ['Colonne inconnue : '.(is_string($key) ? $key : '?')],
                ]);
            }

            $item = ['key' => $parsed['kind'] === 'attribute' ? 'attribute.'.$parsed['name'] : $key];
            if (is_array($column) && isset($column['width'])) {
                $item['width'] = max(40, min(1000, (int) $column['width']));
            }
            $out[] = $item;
        }

        return $out;
    }

    /**
     * @param  array<int, mixed>  $columns
     * @return list<string>
     */
    public static function keys(array $columns): array
    {
        return array_values(array_map(fn (array $c): string => $c['key'], self::normalize($columns)));
    }

    /**
     * `-contact.created_at` → [orderBy expression, 'desc'].
     *
     * @return array{0: string|Expression, 1: 'asc'|'desc'}
     */
    public static function sortExpression(string $sort): array
    {
        $direction = str_starts_with($sort, '-') ? 'desc' : 'asc';
        $key = ltrim($sort, '-+ ');
        $parsed = in_array($key, ['lists', 'tags'], true) ? null : self::parse($key);

        if ($parsed === null || $parsed['kind'] === 'computed') {
            throw ValidationException::withMessages(['sort' => ["Tri non autorisé : {$sort}"]]);
        }

        if ($parsed['column'] !== null) {
            return ['recipients.'.$parsed['column'], $direction];
        }

        $json = 'custom_fields->'.$parsed['name'];
        $type = app(AttributeStorageMap::class)->type($parsed['name']);
        if (! in_array($type, ['number', 'date'], true)) {
            return [$json, $direction];
        }

        // Typed attributes sort by value, not by their JSON text (same casts as the segment compiler).
        $connection = DB::connection();
        $extracted = $connection->getQueryGrammar()->wrap('recipients.'.$json);
        $cast = $type === 'number'
            ? RuleTreeCompiler::numberCast($connection->getDriverName(), $extracted)
            : RuleTreeCompiler::dateCast($extracted);

        return [new Expression($cast), $direction];
    }

    /**
     * Scalar value of a column for a recipient (export + `attributes` map).
     */
    public static function value(Recipient $recipient, string $key): mixed
    {
        $parsed = self::parse($key);

        if ($parsed === null) {
            return null;
        }

        if ($parsed['kind'] === 'tags') {
            return $recipient->tags->pluck('name')->all();
        }

        if ($parsed['kind'] === 'lists') {
            return $recipient->recipientLists->pluck('name')->all();
        }

        if ($parsed['kind'] === 'computed') {
            return $recipient->getAttribute('blocklisted_at') !== null || $recipient->status === 'suppressed';
        }

        if ($parsed['column'] !== null) {
            $value = $recipient->getAttribute($parsed['column']);

            return in_array($parsed['column'], self::DATE_COLUMNS, true) ? self::iso($value) : $value;
        }

        return ($recipient->custom_fields ?? [])[$parsed['name']] ?? null;
    }

    public static function label(string $key): string
    {
        return self::LABELS[$key] ?? (str_starts_with($key, 'attribute.') ? substr($key, 10) : $key);
    }

    public static function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return \Illuminate\Support\Carbon::parse($value)->utc()->format('Y-m-d\TH:i:s\Z');
    }
}
