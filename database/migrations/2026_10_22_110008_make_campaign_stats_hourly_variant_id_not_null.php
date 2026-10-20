<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot L (Wave 3 review S8) —
 * `campaign_stats_hourly.variant_id` becomes NOT NULL DEFAULT 0 (0 = no A/B
 * variant): with NULLs the `(campaign_id, hour, variant_id)` unique key never
 * matched, so concurrent rollups could double the hourly counters.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('campaign_stats_hourly')->whereNull('variant_id')->update(['variant_id' => 0]);

        Schema::table('campaign_stats_hourly', function (Blueprint $table): void {
            $table->unsignedBigInteger('variant_id')->default(0)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_stats_hourly', function (Blueprint $table): void {
            $table->unsignedBigInteger('variant_id')->nullable()->default(null)->change();
        });

        DB::table('campaign_stats_hourly')->where('variant_id', 0)->update(['variant_id' => null]);
    }
};
