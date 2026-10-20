<?php

namespace Tests\Feature\Modules\Reporting\Support;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Identity\Models\User;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Models\RecipientList;
use App\Modules\Tracking\Models\Message;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seeds one sent campaign with rollup rows (the WP-70 tables come from the real migrations).
 */
trait SeedsReportData
{
    private function sentCampaign(array $attributes = []): Campaign
    {
        $campaign = Campaign::factory()->create(array_merge([
            'name' => 'Newsletter octobre',
            'status' => 'completed',
            'subject' => 'Offres',
            'tags' => ['promo'],
            'sent_at' => Carbon::parse('2026-10-10 09:30:00', 'UTC'),
            'completed_at' => Carbon::parse('2026-10-10 11:00:00', 'UTC'),
            'recipients_count' => 100, 'sent_count' => 100, 'delivered_count' => 90,
            'unique_opens' => 45, 'total_opens' => 80, 'proxy_opens' => 5,
            'unique_clicks' => 18, 'total_clicks' => 30, 'unsubscribes' => 3,
            'hard_bounces' => 4, 'soft_bounces' => 6, 'complaints' => 1, 'mirror_clicks' => 2,
        ], $attributes));

        return $campaign;
    }

    /**
     * @param  array<string, mixed>  $counters
     */
    private function hourly(Campaign $campaign, string $hour, array $counters): void
    {
        DB::table('campaign_stats_hourly')->insert(array_merge([
            'campaign_id' => $campaign->id, 'hour' => $hour, 'variant_id' => null,
            'created_at' => now(), 'updated_at' => now(),
        ], $counters));
    }

    /**
     * @param  array<string, mixed>  $counters
     */
    private function byList(Campaign $campaign, string $type, int $id, array $counters): void
    {
        DB::table('campaign_stats_by_list')->insert(array_merge([
            'campaign_id' => $campaign->id, 'target_type' => $type, 'target_id' => $id,
            'created_at' => now(), 'updated_at' => now(),
        ], $counters));
    }

    private function link(Campaign $campaign, string $linkId, string $url, int $unique, int $total): void
    {
        DB::table('campaign_links')->insert([
            'uuid' => (string) Str::uuid(), 'campaign_id' => $campaign->id, 'link_id' => $linkId,
            'url' => $url, 'url_hash' => sha1($url), 'label' => null,
            'unique_clicks' => $unique, 'total_clicks' => $total, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function message(Campaign $campaign, string $email, array $attributes = []): Message
    {
        $message = new Message;
        $message->forceFill(array_merge([
            'recipient_id' => Recipient::factory()->create(['email' => $email])->id,
            'campaign_id' => $campaign->id, 'subject' => 'Objet', 'html_body' => '<p>x</p>',
            'status' => 'delivered', 'queued_at' => now(), 'sent_at' => now(), 'delivered_at' => now(),
            'is_test' => false,
        ], $attributes));
        $message->save();

        return $message;
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function event(Message $message, string $type, array $attributes = []): void
    {
        DB::table('message_events')->insert(array_merge([
            'uuid' => (string) Str::uuid(), 'message_id' => $message->id,
            'campaign_id' => $message->campaign_id, 'recipient_id' => $message->recipient_id,
            'event_type' => $type, 'occurred_at' => now(),
            'created_at' => now(), 'updated_at' => now(),
        ], $attributes));
    }

    private function audit(Campaign $campaign, string $action, ?User $actor, string $at): void
    {
        $log = AuditLog::query()->create([
            'user_id' => $actor?->id, 'action' => $action, 'auditable_type' => Campaign::class,
            'auditable_id' => $campaign->id, 'new_values' => ['x' => 1], 'ip_address' => '127.0.0.1',
        ]);
        DB::table('audit_logs')->where('id', $log->id)->update(['created_at' => $at]);
    }

    private function list(string $name, int $count = 50): RecipientList
    {
        $list = RecipientList::query()->create(['name' => $name, 'type' => 'static']);
        DB::table('recipient_lists')->where('id', $list->id)->update(['contact_count' => $count]);

        return $list;
    }
}
