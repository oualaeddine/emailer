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
 *
 * Runs on the low-priority `maintenance` queue. Probing is parallel and
 * time-boxed inside {@see LinkChecker::check()} (`probeMany`: batches of 10,
 * ~60 s total budget), so the job stays under the queue's `retry_after`.
 */
class CheckCampaignLinksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Probe budget (~60 s) + per-request timeouts, below `retry_after` (90 s). */
    public int $timeout = 85;

    /** A re-check is user-triggered; never retried behind the user's back. */
    public int $tries = 1;

    public function __construct(public readonly int $campaignId)
    {
        $this->onQueue('maintenance');
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
