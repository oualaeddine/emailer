<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * docs/42-parallel-execution-plan.md §42.6 (WP-11) — `suppression_entries`
 * (2026_07_14_170001_create_suppression_entries_table.php) predates the
 * documented API contract and was never given a `uuid` column, but
 * docs/29-api-specification.md §29.1 requires "All IDs in URLs/payloads
 * are the `uuid` column, never the internal `bigint` PK" — the same
 * convention `recipients.uuid` already follows (2026_07_14_120001). This
 * is a genuine gap (not a rebuild of the original migration, which is
 * left untouched per WP-11 scope) needed for
 * `GET/POST/DELETE /api/v1/suppression-entries...` (docs/29 §29.8) to be
 * addressable the same way every other resource in this API is.
 *
 * Added nullable (unlike `recipients.uuid`, which is `NOT NULL`): making it
 * `NOT NULL` in the same statement would require a column default under
 * MySQL strict mode once any row exists, and promoting it after backfill
 * would require `doctrine/dbal` (`Blueprint::change()`), which this
 * project does not depend on. The column is always populated at the
 * application layer instead (`SuppressionEntry` uses
 * `App\Support\Concerns\HasUuid`, the same trait `Recipient` uses, which
 * generates the UUID on `creating`), and any pre-existing rows are
 * backfilled below.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('suppression_entries', function (Blueprint $table): void {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        DB::table('suppression_entries')->whereNull('uuid')->orderBy('id')->cursor()->each(function (object $row): void {
            DB::table('suppression_entries')->where('id', $row->id)->update(['uuid' => (string) Str::uuid()]);
        });
    }

    public function down(): void
    {
        Schema::table('suppression_entries', function (Blueprint $table): void {
            $table->dropUnique(['uuid']);
            $table->dropColumn('uuid');
        });
    }
};
