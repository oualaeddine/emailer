<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * docs/45-brevo-api-contracts.md §45.8 Slot L (WP-70) — one-off backfill of
 * `message_events` from the first-reached timestamps of existing `messages`.
 *
 * Once `message_events` exists, the contact timeline (WP-52) and engagement
 * segments read opens / clicks / bounces from it instead of `messages`, so
 * messages tracked before this slot get one event per reached stage
 * (repeat opens / clicks stay represented by `messages.open_count` /
 * `click_count`, which the rollup reads for the difference).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('messages') || DB::table('message_events')->exists()) {
            return;
        }

        DB::table('messages')
            ->where(function ($q): void {
                $q->whereNotNull('opened_at')->orWhereNotNull('clicked_at')->orWhereNotNull('bounced_at')
                    ->orWhereIn('status', ['spam_complaint', 'unsubscribed']);
            })
            ->orderBy('id')
            ->select(['id', 'campaign_id', 'recipient_id', 'status', 'opened_at', 'clicked_at', 'bounced_at', 'updated_at'])
            ->chunkById(1000, function ($messages): void {
                $rows = [];
                $now = now();
                foreach ($messages as $m) {
                    $events = [];
                    if ($m->opened_at !== null) {
                        $events['opened'] = $m->opened_at;
                    }
                    if ($m->clicked_at !== null) {
                        $events['clicked'] = $m->clicked_at;
                    }
                    if ($m->bounced_at !== null && in_array($m->status, ['hard_bounced', 'soft_bounced'], true)) {
                        $events[$m->status] = $m->bounced_at;
                    }
                    if (in_array($m->status, ['spam_complaint', 'unsubscribed'], true)) {
                        $events[$m->status] = $m->updated_at ?? $now;
                    }
                    foreach ($events as $type => $at) {
                        $rows[] = [
                            'uuid' => (string) Str::uuid(),
                            'message_id' => $m->id,
                            'campaign_id' => $m->campaign_id,
                            'recipient_id' => $m->recipient_id,
                            'event_type' => $type,
                            'occurred_at' => $at,
                            'metadata' => json_encode(['backfilled' => true]),
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];
                    }
                }
                foreach (array_chunk($rows, 500) as $chunk) {
                    DB::table('message_events')->insert($chunk);
                }
            });
    }

    public function down(): void
    {
        // Backfilled rows are indistinguishable from history once new events
        // accrue; the table itself is dropped by 2026_10_22_110001.
    }
};
