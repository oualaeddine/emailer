<?php

namespace App\Modules\Campaigns\Jobs;

use App\Modules\Campaigns\Contracts\AudienceResolver;
use App\Modules\Campaigns\Models\Campaign;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * docs/45-brevo-api-contracts.md §45.9 — materialises a campaign's audience
 * into `campaign_recipients` (idempotent; no-op once materialised).
 * `DispatchCampaignJob` also materialises inline on its first cycle, so this
 * job is only needed to pre-compute large audiences ahead of the send.
 */
class MaterialiseAudienceJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $campaignId) {}

    public function handle(AudienceResolver $resolver): void
    {
        $campaign = Campaign::find($this->campaignId);

        if ($campaign === null || $campaign->isAudienceMaterialised()) {
            return;
        }

        $resolver->resolve($campaign);
    }
}
