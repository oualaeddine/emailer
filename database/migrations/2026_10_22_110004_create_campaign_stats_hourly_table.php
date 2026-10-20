<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot L (WP-70) — hourly campaign
 * rollup written by `RollupCampaignStatsJob`. Unique counters are bucketed
 * on the hour of the *first* occurrence per message, so summing every hour
 * of a campaign yields the campaign-wide unique figure.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_stats_hourly', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->timestamp('hour');
            $table->unsignedBigInteger('variant_id')->nullable();
            foreach (self::COUNTERS as $counter) {
                $table->unsignedInteger($counter)->default(0);
            }
            $table->timestamps();

            $table->unique(['campaign_id', 'hour', 'variant_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_stats_hourly');
    }

    private const COUNTERS = [
        'sent', 'delivered', 'opens', 'unique_opens', 'proxy_opens', 'clicks', 'unique_clicks',
        'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions', 'deferred', 'blocked',
    ];
};
