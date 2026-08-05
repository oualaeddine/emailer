<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/15-campaign-management.md §15.1-15.9 — Campaigns Module.
 *
 * `messages.campaign_id` already exists as an unconstrained column
 * (`database/migrations/2026_07_14_170000_create_messages_table.php`) —
 * the FK back-fill lives in its own sibling migration
 * (`2026_08_06_100002_add_foreign_key_messages_campaign_id.php`), mirroring
 * the `send_attempts.message_id` deferred-FK pattern used earlier in the
 * build (`2026_07_14_170002_add_foreign_key_send_attempts_message_id.php`).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('name', 150);

            // §15.2 step 1/2 — Basics + Content. `subject`/`html_body` are a
            // snapshot copied from the template at creation time and remain
            // independently editable while the campaign is `draft`
            // (§15.7, §15.8 "Content" tab).
            $table->foreignId('template_id')->nullable()->constrained('templates')->nullOnDelete();
            $table->string('subject', 998)->nullable();
            $table->longText('html_body')->nullable();

            // §15.2 step 3 — Recipients: a campaign targets exactly one of a
            // Recipient List or a Segment (enforced in the FormRequest layer,
            // never both / neither).
            $table->foreignId('recipient_list_id')->nullable()->constrained('recipient_lists')->nullOnDelete();
            $table->foreignId('segment_id')->nullable()->constrained('segments')->nullOnDelete();

            // §15.1 — lifecycle state machine, see CampaignStatus enum.
            $table->string('status', 20)->default('draft');

            // §15.2 step 4 — Sending Configuration: SMTP strategy.
            $table->string('smtp_strategy', 20)->default('auto_rotate');
            $table->foreignId('single_smtp_account_id')->nullable()->constrained('smtp_accounts')->nullOnDelete();

            // §15.3 — Scheduling & Recurrence.
            $table->string('send_mode', 20)->default('immediate');
            $table->timestampTz('scheduled_at')->nullable();
            $table->string('recurrence_rule', 255)->nullable();
            $table->boolean('business_hours_only')->default(false);
            $table->time('business_hours_start')->nullable();
            $table->time('business_hours_end')->nullable();
            $table->json('business_days')->nullable();

            // §15.4 — Review Before Sending (optional per-campaign gate).
            $table->boolean('approval_required')->default(false);
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('approved_at')->nullable();

            // docs/34-roadmap.md §34.4 noted extension point — per-campaign
            // open/click tracking toggle.
            $table->boolean('tracking_enabled')->default(true);

            // §15.7 — Campaign Cloning.
            $table->foreignId('cloned_from_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('sent_at')->nullable();
            $table->timestamps();

            $table->index('status');
            $table->index('scheduled_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};
