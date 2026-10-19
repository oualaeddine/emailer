<?php

namespace App\Modules\Campaigns\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * uuid ⇄ id lookups for tables owned by WPs built in parallel (WP-41
 * `senders`, Slot M unsubscribe pages / profile forms). They go through the
 * query builder — never through the other module's models — and degrade to
 * "not found" while the table does not exist yet, so this module works
 * before and after those WPs are merged.
 */
class ExternalReferences
{
    public const SENDERS_TABLE = 'senders';

    public const UNSUBSCRIBE_PAGES_TABLE = 'unsubscribe_pages';

    public const UPDATE_PROFILE_FORMS_TABLE = 'forms';

    /** @var array<string, bool> */
    private static array $tables = [];

    /** @var array<int, array<string, mixed>|null> */
    private array $senders = [];

    public static function hasTable(string $table): bool
    {
        return self::$tables[$table] ??= Schema::hasTable($table);
    }

    public static function flush(): void
    {
        self::$tables = [];
    }

    public function idForUuid(string $table, ?string $uuid): ?int
    {
        if ($uuid === null || ! self::hasTable($table)) {
            return null;
        }

        $id = DB::table($table)->where('uuid', $uuid)->value('id');

        return $id === null ? null : (int) $id;
    }

    public function uuidForId(string $table, ?int $id): ?string
    {
        if ($id === null || ! self::hasTable($table)) {
            return null;
        }

        $uuid = DB::table($table)->where('id', $id)->value('uuid');

        return $uuid === null ? null : (string) $uuid;
    }

    /**
     * `CampaignV2.sender` = `{ id, name, email, is_verified }` or null.
     *
     * @return array{id: string, name: string|null, email: string|null, is_verified: bool}|null
     */
    public function sender(?int $senderId): ?array
    {
        if ($senderId === null || ! self::hasTable(self::SENDERS_TABLE)) {
            return null;
        }

        if (! array_key_exists($senderId, $this->senders)) {
            $row = DB::table(self::SENDERS_TABLE)->where('id', $senderId)->first();
            $this->senders[$senderId] = $row === null ? null : (array) $row;
        }

        $row = $this->senders[$senderId];

        if ($row === null) {
            return null;
        }

        return [
            'id' => (string) $row['uuid'],
            'name' => $row['name'] ?? null,
            'email' => $row['email'] ?? null,
            'is_verified' => self::rowIsVerified($row),
        ];
    }

    public function senderIsVerified(int $senderId): bool
    {
        return (bool) ($this->sender($senderId)['is_verified'] ?? false);
    }

    /**
     * Accepts the plausible Slot A spellings (`is_verified`, `verified_at`,
     * `status = verified`).
     *
     * @param  array<string, mixed>  $row
     */
    private static function rowIsVerified(array $row): bool
    {
        if (array_key_exists('is_verified', $row)) {
            return (bool) $row['is_verified'];
        }

        if (array_key_exists('verified_at', $row)) {
            return $row['verified_at'] !== null;
        }

        return ($row['status'] ?? null) === 'verified';
    }
}
