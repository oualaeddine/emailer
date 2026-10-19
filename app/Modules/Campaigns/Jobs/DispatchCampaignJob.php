<?php

namespace App\Modules\Campaigns\Jobs;

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
 * `$scheduledFor` is set on the delayed job created by a schedule: if the
 * campaign was unscheduled / rescheduled / sent now since, the stale job
 * does nothing.
 */
class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public readonly int $campaignId,
        public readonly ?string $scheduledFor = null,
    ) {}

    public function handle(CampaignService $campaigns): void
    {
        $campaign = Campaign::find($this->campaignId);

        if ($campaign === null || $this->isStale($campaign)) {
            return;
        }

        $lock = Cache::lock("campaigns.{$campaign->id}.dispatch", 600);

        if (! $lock->get()) {
            // Another cycle is running for this campaign — retry shortly.
            $this->release(10);

            return;
        }

        try {
            $campaigns->runDispatchCycle($campaign);
        } finally {
            $lock->release();
        }
    }

    private function isStale(Campaign $campaign): bool
    {
        if ($this->scheduledFor === null) {
            return false;
        }

        return $campaign->status !== CampaignStatus::Scheduled->value
            || $campaign->scheduled_at === null
            || ! $campaign->scheduled_at->equalTo(Carbon::parse($this->scheduledFor));
    }
}
