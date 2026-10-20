<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot L (WP-70) — per audience target
 * (list or segment, `campaign_audiences`) rollup: Brevo's `campaignStats`.
 */
return new class extends Migration
{
    private const COUNTERS = [
        'sent', 'delivered', 'opens', 'unique_opens', 'proxy_opens', 'clicks', 'unique_clicks',
        'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions', 'deferred', 'blocked',
    ];

    public function up(): void
    {
        Schema::create('campaign_stats_by_list', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('target_type', 16);
            $table->unsignedBigInteger('target_id');
            foreach (self::COUNTERS as $counter) {
                $table->unsignedInteger($counter)->default(0);
            }
            $table->timestamps();

            $table->unique(['campaign_id', 'target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_stats_by_list');
    }
};
