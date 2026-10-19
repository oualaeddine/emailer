<?php

namespace App\Modules\Recipients\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45 §45.8 Slot E (WP-52) — nightly reconciliation of the cached
 * per-recipient counters and `last_opened_at` / `last_clicked_at` from
 * `messages` (test sends excluded). Optionally restricted to one recipient.
 */
class RecomputeRecipientCountersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly ?int $recipientId = null)
    {
    }

    public function handle(): void
    {
        $real = Schema::hasColumn('messages', 'is_test') ? ' AND m.is_test = false' : '';
        $count = fn (string $c): string => "(SELECT COUNT(*) FROM messages m WHERE m.recipient_id = recipients.id AND {$c}{$real})";
        $max = fn (string $col): string => "(SELECT MAX(m.{$col}) FROM messages m WHERE m.recipient_id = recipients.id{$real})";

        $values = [
            'messages_sent' => DB::raw($count('m.sent_at IS NOT NULL')),
            'delivered' => DB::raw($count('m.delivered_at IS NOT NULL')),
            'opened' => DB::raw($count('m.opened_at IS NOT NULL')),
            'clicked' => DB::raw($count('m.clicked_at IS NOT NULL')),
            'hard_bounces' => DB::raw($count("m.status = 'hard_bounced'")),
            'soft_bounces' => DB::raw($count("m.status = 'soft_bounced'")),
            'complaints' => DB::raw($count("m.status = 'spam_complaint'")),
            'unsubscriptions' => DB::raw($count("m.status = 'unsubscribed'")),
            'last_opened_at' => DB::raw($max('opened_at')),
            'last_clicked_at' => DB::raw($max('clicked_at')),
            'counters_updated_at' => now(),
        ];

        $base = DB::table('recipients');
        if ($this->recipientId !== null) {
            $base->where('id', $this->recipientId);
        }

        $base->orderBy('id')->chunkById(1000, function ($chunk) use ($values): void {
            DB::table('recipients')->whereIn('id', $chunk->pluck('id')->all())->update($values);
        });
    }
}
