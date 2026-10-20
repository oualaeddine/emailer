<?php

namespace App\Modules\Campaigns\AbTesting\Jobs;

use App\Modules\Campaigns\AbTesting\Services\AbTestService;
use App\Modules\Campaigns\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * docs/43 §43.6.2 / docs/45 §45.9 — scheduled by the A/B dispatcher at
 * `sent_at + test_duration_minutes` once the test sample is released:
 * computes unique opens / clicks per version from `messages` /
 * `message_events` (tie → A), sets `winner_variant_id` and releases the
 * remainder ({@see AbTestService::pickWinner()}).
 *
 * No-op when the test was disabled, the winner is already set (manual pick),
 * the campaign left the test phase (cancelled / completed), or
 * `auto_send_winner` is off (the user picks manually). Fired early (e.g. the
 * duration was extended), it re-schedules itself at the due time.
 */
class PickAbWinnerJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $campaignId) {}

    public function handle(AbTestService $abTests): void
    {
        $campaign = Campaign::find($this->campaignId);

        if ($campaign === null || ! $abTests->isTesting($campaign) || ! $abTests->config($campaign)['auto_send_winner']) {
            return;
        }

        $dueAt = $abTests->winnerDueAt($campaign);
        if ($dueAt->isFuture()) {
            self::dispatch($campaign->id)->delay($dueAt);

            return;
        }

        $winner = $abTests->determineWinner($campaign);

        if ($winner !== null) {
            $abTests->pickWinner($campaign, $winner, AbTestService::PICKED_AUTO);
        }
    }
}
