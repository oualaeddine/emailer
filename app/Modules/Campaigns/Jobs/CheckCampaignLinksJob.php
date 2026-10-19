<?php

namespace App\Modules\Campaigns\Jobs;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Services\Checklist\LinkChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

/**
 * docs/45-brevo-api-contracts.md §45.4.5 — probes the campaign's links and
 * caches the result by content hash.
 */
class CheckCampaignLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public readonly int $campaignId)
    {
    }

    public function handle(LinkChecker $checker): void
    {
        $campaign = Campaign::query()->find($this->campaignId);
        if ($campaign === null) {
            return;
        }

        $html = $campaign->html_body;
        $result = $checker->check($html);
        $result['checked_at'] = now()->toIso8601String();

        Cache::put(
            LinkChecker::cacheKey($campaign->uuid, $html),
            $result,
            now()->addHours(LinkChecker::CACHE_TTL_HOURS),
        );
    }
}
