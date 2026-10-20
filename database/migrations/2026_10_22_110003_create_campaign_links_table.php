<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot L (WP-70) — per-link click
 * counters upserted by the click tracker (docs/43 §43.6.3). `link_id` is the
 * `data-link-id` injected by the rendering pipeline (WP-63).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_links', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->string('link_id', 16);
            $table->text('url');
            $table->char('url_hash', 40)->index();
            $table->string('label', 255)->nullable();
            $table->unsignedInteger('unique_clicks')->default(0);
            $table->unsignedInteger('total_clicks')->default(0);
            $table->timestamp('last_clicked_at')->nullable();
            $table->timestamps();

            $table->unique(['campaign_id', 'link_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_links');
    }
};
