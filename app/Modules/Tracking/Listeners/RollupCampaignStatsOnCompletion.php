<?php

namespace App\Modules\Tracking\Listeners;

use App\Modules\Campaigns\Events\CampaignStatusChanged;
use App\Modules\Reporting\Jobs\RollupCampaignStatsJob;

/**
 * docs/45-brevo-api-contracts.md §45.9 — "RollupCampaignStatsJob (hourly +
 * on completion)": a campaign reaching `completed` gets its final rollup.
 * Registered in AppServiceProvider::boot().
 */
class RollupCampaignStatsOnCompletion
{
    public function handle(CampaignStatusChanged $event): void
    {
        if ($event->to === 'completed') {
            RollupCampaignStatsJob::dispatch($event->campaignId);
        }
    }
}
