<?php

namespace App\Modules\Campaigns\Jobs;

use App\Modules\Campaigns\AbTesting\Services\AbTestDispatcher;
use App\Modules\Campaigns\Enums\CampaignStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * docs/30-background-jobs.md §30.1/§30.4 — per-campaign fan-out, self
 * re-enqueuing per chunk so pause/cancel take effect within one chunk.
 * Carries only the campaign id (§30.2), reloading fresh state on every run.
 *
 * v2 (docs/43 §43.6.1, docs/45 §45.4.2): iterates the materialised
 * `campaign_recipients` rows; batches mode re-enqueues itself with a delay
 * until the next batch is due.
 *
 * A/B test campaigns (`ab_test_enabled`, docs/43 §43.6.2, WP-80) run the
 * split cycle of {@see AbTestDispatcher}: the sample gets versions A/B
 * evenly, the remainder waits for `PickAbWinnerJob` and is then sent with
 * the winner's subject / html.
 *
 * Campaigns with a send window (`business_hours_*`) are only released
 * inside it: outside, the job re-enqueues itself for the next opening
 * ({@see CampaignService::sendWindowDeferral()}).
 *
 * `$scheduledFor` is set on the delayed job created by a schedule: if the
 * campaign was unscheduled / rescheduled / sent now since, the stale job
 * does nothing.
 */
class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Not promoted / not readonly: payloads serialized before this property
     * existed unserialize without it and must keep its default (null).
     */
    public ?string $scheduledFor = null;

    public function __construct(
        public readonly int $campaignId,
        ?string $scheduledFor = null,
    ) {
        $this->scheduledFor = $scheduledFor;
    }

    public function handle(CampaignService $campaigns, ?AbTestDispatcher $abTests = null): void
    {
        $campaign = Campaign::find($this->campaignId);

        if ($campaign === null || $this->isStale($campaign)) {
            return;
        }

        // Send window: outside it nothing is released — come back when it opens.
        $opening = $campaigns->sendWindowDeferral($campaign);
        if ($opening !== null) {
            static::dispatch($campaign->id, $this->scheduledFor)->delay($opening);

            return;
        }

        $lock = Cache::lock("campaigns.{$campaign->id}.dispatch", 600);

        if (! $lock->get()) {
            // Another cycle is running for this campaign — retry shortly.
            $this->release(10);

            return;
        }

        try {
            if ($campaign->ab_test_enabled) {
                ($abTests ?? app(AbTestDispatcher::class))->runDispatchCycle($campaign);
            } else {
                $campaigns->runDispatchCycle($campaign);
            }
        } finally {
            $lock->release();
        }
    }

    private function isStale(Campaign $campaign): bool
    {
        if (! isset($this->scheduledFor)) {
            return false;
        }

        return $campaign->status !== CampaignStatus::Scheduled->value
            || $campaign->scheduled_at === null
            || $campaign->scheduled_at->timestamp !== Carbon::parse($this->scheduledFor)->timestamp;
    }
}
