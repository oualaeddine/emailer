<?php

namespace App\Modules\Reporting\Jobs;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Reporting\Contracts\CampaignStats;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

/**
 * docs/45-brevo-api-contracts.md §45.9 (WP-70) — recomputes one campaign's
 * rollups (`campaign_stats_hourly`, `campaign_stats_by_list`, cached
 * `campaigns` counters, breakdown cache). Dispatched on completion
 * (`CampaignStatusChanged` → `completed`) and by the stats service for large
 * campaigns.
 *
 * Without a `campaignId` (the hourly schedule, routes/console.php) it fans
 * out one job per active campaign: running / paused campaigns, campaigns
 * sent or completed within `tracking.stats.active_window_days`, and
 * campaigns with a `message_events` row in the last two hours.
 */
class RollupCampaignStatsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $timeout = 1800;

    public int $uniqueFor = 3600;

    public function __construct(public readonly ?int $campaignId = null) {}

    public function uniqueId(): string
    {
        return $this->campaignId === null ? 'all' : (string) $this->campaignId;
    }

    public function handle(CampaignStats $stats): void
    {
        if ($this->campaignId === null) {
            foreach (self::activeCampaignIds() as $id) {
                self::dispatch($id);
            }

            return;
        }

        $campaign = Campaign::query()->find($this->campaignId);
        if ($campaign !== null) {
            $stats->rollup($campaign);
        }
    }

    /**
     * @return list<int>
     */
    public static function activeCampaignIds(): array
    {
        $since = now()->subDays(max(1, (int) config('tracking.stats.active_window_days', 30)));

        $ids = DB::table('campaigns')
            ->where(function ($q) use ($since): void {
                $q->whereIn('status', ['running', 'paused'])
                    ->orWhere('sent_at', '>=', $since)
                    ->orWhere('completed_at', '>=', $since);
            })
            ->pluck('id');

        $recent = DB::table('message_events')
            ->where('occurred_at', '>=', now()->subHours(2))
            ->whereNotNull('campaign_id')
            ->distinct()
            ->pluck('campaign_id');

        return $ids->merge($recent)->map(fn ($id): int => (int) $id)->unique()->sort()->values()->all();
    }
}
