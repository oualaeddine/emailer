<?php

namespace App\Modules\Recipients\Attributes\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Finds segments / forms / automations referencing an attribute (blocks deletion, docs/45 §45.5.1).
 * Segments are scanned here; WP-90 / WP-91 register their own finder with {@see self::extend()}.
 */
class AttributeUsageFinder
{
    /** @var list<callable(string): list<array{type: string, id: string, name: string}>> */
    private static array $extra = [];

    /** @param callable(string): list<array{type: string, id: string, name: string}> $finder */
    public static function extend(callable $finder): void
    {
        self::$extra[] = $finder;
    }

    /** @return list<array{type: string, id: string, name: string}> */
    public function find(string $name): array
    {
        $used = [];

        if (Schema::hasTable('segments') && Schema::hasColumn('segments', 'rules')) {
            $nameColumn = Schema::hasColumn('segments', 'name') ? 'segments.name' : 'recipient_lists.name';
            $query = DB::table('segments')
                ->where(fn ($q) => $q->where('segments.rules', 'like', '%"'.$name.'"%')
                    ->orWhere('segments.rules', 'like', '%.'.$name.'"%'));
            if ($nameColumn === 'recipient_lists.name') {
                $query->leftJoin('recipient_lists', 'recipient_lists.id', '=', 'segments.recipient_list_id');
            }
            foreach ($query->get(['segments.uuid as uuid', $nameColumn.' as name']) as $row) {
                $used[] = ['type' => 'segment', 'id' => (string) $row->uuid, 'name' => (string) $row->name];
            }
        }

        foreach (self::$extra as $finder) {
            array_push($used, ...$finder($name));
        }

        return $used;
    }
}
