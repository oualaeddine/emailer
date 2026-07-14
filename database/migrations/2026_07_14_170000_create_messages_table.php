<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/04-database-design.md §4.10 — `messages`. `campaign_id` is a plain
 * unconstrained column (no FK yet) since `campaigns` does not exist until
 * the Campaigns module (docs/34-roadmap.md build order) — the constraint
 * is added by that module's migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('messages', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedBigInteger('campaign_id')->nullable();
            $table->foreignId('draft_id')->nullable()->constrained('drafts')->nullOnDelete();
            $table->foreignId('recipient_id')->constrained('recipients')->restrictOnDelete();
            $table->foreignId('smtp_account_id')->nullable()->constrained('smtp_accounts')->nullOnDelete();
            $table->string('subject', 998);
            $table->longText('html_body');
            $table->string('status', 20)->default('queued');
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('delivered_at')->nullable();
            $table->timestampTz('opened_at')->nullable();
            $table->timestampTz('clicked_at')->nullable();
            $table->timestampTz('bounced_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->string('provider_message_id')->nullable();
            $table->text('last_provider_response')->nullable();
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);
            $table->timestampsTz();

            $table->index('status');
            $table->index('campaign_id');
            $table->index('recipient_id');
            $table->index('provider_message_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('messages');
    }
};
