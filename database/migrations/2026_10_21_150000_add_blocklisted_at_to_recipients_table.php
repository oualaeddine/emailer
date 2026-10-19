<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45 §45.8 Slot I — WP-56 `recipients.blocklisted_at` mirror, backfilled
 * from `suppression_entries` by email.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('recipients', 'blocklisted_at')) {
            Schema::table('recipients', function (Blueprint $table): void {
                $table->timestamp('blocklisted_at')->nullable()->index();
            });
        }

        DB::statement(
            'UPDATE recipients SET blocklisted_at = COALESCE('
            .'(SELECT MIN(se.created_at) FROM suppression_entries se WHERE se.email = recipients.email), CURRENT_TIMESTAMP) '
            .'WHERE blocklisted_at IS NULL AND EXISTS (SELECT 1 FROM suppression_entries se2 WHERE se2.email = recipients.email)'
        );
    }

    public function down(): void
    {
        if (Schema::hasColumn('recipients', 'blocklisted_at')) {
            Schema::table('recipients', function (Blueprint $table): void {
                $table->dropIndex(['blocklisted_at']);
                $table->dropColumn('blocklisted_at');
            });
        }
    }
};
