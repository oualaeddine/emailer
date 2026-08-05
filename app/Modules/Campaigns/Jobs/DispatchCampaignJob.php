<?php

namespace App\Modules\Campaigns\Jobs;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\CampaignService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * docs/30-background-jobs.md §30.1/§30.4 — per-campaign fan-out, self
 * re-enqueuing per chunk (rather than one giant job looping over every
 * recipient) so pause/cancel take effect within one chunk's processing
 * time and Horizon timeouts/memory limits are never at risk regardless of
 * campaign size. Carries only the campaign id (§30.2 — jobs carry IDs, not
 * full payloads), reloading fresh state from the DB on every execution.
 *
 * Dispatch entry points (§15.1, §15.3, §15.5, §15.6):
 * - Send Now: dispatched immediately, no delay.
 * - Schedule: dispatched with `->delay($campaign->scheduled_at)`.
 * - Resume: dispatched immediately to pick up the resumption set.
 */
class DispatchCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $campaignId)
    {
    }

    public function handle(CampaignService $campaigns): void
    {
        $campaign = Campaign::find($this->campaignId);

        if ($campaign === null) {
            return;
        }

        $campaigns->runDispatchCycle($campaign);
    }
}
