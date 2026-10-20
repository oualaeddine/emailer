<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot B — `campaigns` v2 columns (WP-42).
 *
 * Columns of the frozen list that already exist under another name are NOT
 * re-created (the API maps them, see CampaignResource):
 * - `tracking_enabled`      → existing `tracking_enabled`
 * - `smtp_strategy`         → existing `smtp_strategy` (`auto_rotate`|`pinned` ↔ API `rotation`|`single`)
 * - `smtp_account_id`       → existing `single_smtp_account_id`
 * - `send_window`           → existing `business_hours_only` / `business_hours_start` / `business_hours_end` / `business_days`
 * - `requires_approval`     → existing `approval_required`
 * - `send_mode`             → existing `send_mode` (string(20), default `immediate`; API `now` ↔ `immediate`)
 *
 * `sender_id` gets its FK only when the Slot A `senders` table exists
 * (Slot A runs first once WP-41 is merged).
 */
return new class extends Migration
{
    /** @var list<string> */
    private array $counters = [
        'recipients_count', 'sent_count', 'delivered_count', 'unique_opens', 'total_opens',
        'unique_clicks', 'total_clicks', 'unsubscribes', 'hard_bounces', 'soft_bounces',
        'complaints', 'proxy_opens', 'mirror_clicks',
    ];

    public function up(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            $table->string('type', 32)->default('classic')->index();
            $table->string('preview_text', 255)->nullable();

            if (Schema::hasTable('senders')) {
                $table->foreignId('sender_id')->nullable()->constrained('senders')->nullOnDelete();
            } else {
                $table->unsignedBigInteger('sender_id')->nullable()->index();
            }

            $table->string('reply_to', 254)->nullable();
            $table->string('to_field', 255)->nullable();
            $table->foreignId('folder_id')->nullable()->constrained('campaign_folders')->nullOnDelete();
            $table->json('tags')->nullable();
            $table->string('timezone', 64)->nullable();

            $table->boolean('utm_enabled')->default(false);
            foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id'] as $utm) {
                $table->string($utm, 100)->nullable();
            }

            $table->boolean('mirror_link_enabled')->default(true);
            $table->boolean('inline_images')->default(false);
            $table->foreignId('attachment_id')->nullable()->constrained('attachments')->nullOnDelete();
            $table->unsignedSmallInteger('email_expiration_duration')->nullable();
            $table->string('email_expiration_unit', 16)->nullable();
            $table->unsignedBigInteger('unsubscribe_page_id')->nullable();
            $table->unsignedBigInteger('update_profile_form_id')->nullable();

            $table->string('editor_type', 32)->nullable();
            $table->longText('design_json')->nullable();
            $table->string('thumbnail_path', 255)->nullable();

            $table->timestampTz('archived_at')->nullable()->index();
            $table->string('suspended_reason', 64)->nullable();
            $table->boolean('send_time_optimized')->default(false);
            $table->unsignedInteger('batch_size')->nullable();
            $table->unsignedSmallInteger('batch_interval_minutes')->nullable();

            $table->boolean('ab_test_enabled')->default(false);
            $table->json('ab_test_config')->nullable();
            $table->unsignedBigInteger('winner_variant_id')->nullable();

            $table->timestampTz('test_sent_at')->nullable();
            $table->foreignId('parent_campaign_id')->nullable()->constrained('campaigns')->nullOnDelete();
            $table->timestampTz('completed_at')->nullable();

            foreach ($this->counters as $counter) {
                $table->unsignedInteger($counter)->default(0);
            }
            $table->timestampTz('stats_updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('campaigns', function (Blueprint $table): void {
            if (Schema::hasTable('senders')) {
                $table->dropForeign(['sender_id']);
            }
            $table->dropForeign(['folder_id']);
            $table->dropForeign(['attachment_id']);
            $table->dropForeign(['parent_campaign_id']);

            $table->dropColumn([
                'type', 'preview_text', 'sender_id', 'reply_to', 'to_field', 'folder_id', 'tags', 'timezone',
                'utm_enabled', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term', 'utm_id',
                'mirror_link_enabled', 'inline_images', 'attachment_id', 'email_expiration_duration',
                'email_expiration_unit', 'unsubscribe_page_id', 'update_profile_form_id', 'editor_type',
                'design_json', 'thumbnail_path', 'archived_at', 'suspended_reason', 'send_time_optimized',
                'batch_size', 'batch_interval_minutes', 'ab_test_enabled', 'ab_test_config', 'winner_variant_id',
                'test_sent_at', 'parent_campaign_id', 'completed_at', ...$this->counters, 'stats_updated_at',
            ]);
        });
    }
};
