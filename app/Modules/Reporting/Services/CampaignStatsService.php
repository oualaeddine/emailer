<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Reporting\Contracts\CampaignStats;
use App\Modules\Reporting\Jobs\RollupCampaignStatsJob;
use App\Modules\Tracking\Services\CampaignStatsAggregator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * docs/43-brevo-parity-plan.md §43.4.15 (backend), §43.1 "Statistics" and
 * docs/45-brevo-api-contracts.md §45.3 / §45.6.4 / §45.9 (WP-70) — Brevo's
 * campaign statistics model served from WP-70's rollups.
 *
 * Sources, all per campaign:
 * - `campaign_stats_hourly` → globalStats, timeseries (summed in SQL);
 * - `campaign_stats_by_list` → campaignStats;
 * - `campaign_links` → linksStats / links;
 * - breakdown cache written by the rollup → statsByDomain / Device /
 *   Browser / Country and returnBounce;
 * - `campaigns.mirror_clicks` → mirrorClick; queued messages + pending
 *   audience members → remaining.
 *
 * Freshness: a read first checks `campaigns.stats_updated_at`. When the
 * rollup is missing or older than `tracking.stats.max_age_seconds`, it is
 * recomputed inline for campaigns of at most `tracking.stats.live_threshold`
 * messages and queued (`RollupCampaignStatsJob`, unique) above that — so a
 * request never walks more than 100k raw messages / events.
 *
 * Rates (§45.1): ratios 0–1 with `delivered` as denominator except
 * `delivery_rate` (delivered / sent) and `click_to_open_rate`
 * (uniqueClicks / uniqueViews); Brevo's own `opensRate` is a 0–100
 * percentage with 2 decimals.
 */
class CampaignStatsService implements CampaignStats
{
    private const HOURLY_SUMS = [
        'sent', 'delivered', 'opens', 'unique_opens', 'proxy_opens', 'clicks', 'unique_clicks',
        'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions', 'deferred', 'blocked',
    ];

    /** @var array<int, true> campaigns refreshed during this instance's lifetime */
    private array $fresh = [];

    public function __construct(private readonly CampaignStatsAggregator $aggregator) {}

    public function globalStats(Campaign $campaign): array
    {
        $this->ensureFresh($campaign);

        $row = DB::table('campaign_stats_hourly')
            ->where('campaign_id', $campaign->id)
            ->selectRaw(implode(', ', array_map(fn (string $c): string => "COALESCE(SUM({$c}), 0) as {$c}", self::HOURLY_SUMS)))
            ->first();

        $stats = $this->brevoStats((array) $row);
        $stats['returnBounce'] = (int) ($this->breakdowns($campaign)['returnBounce'] ?? 0);

        return $stats;
    }

    public function byList(Campaign $campaign): array
    {
        $this->ensureFresh($campaign);

        $audiences = DB::table('campaign_audiences')
            ->where('campaign_id', $campaign->id)
            ->where('mode', 'include')
            ->whereIn('target_type', ['list', 'segment'])
            ->orderBy('id')
            ->get(['target_type', 'target_id']);

        $rows = DB::table('campaign_stats_by_list')
            ->where('campaign_id', $campaign->id)
            ->get()
            ->keyBy(fn (object $r): string => $r->target_type.':'.$r->target_id);

        $lists = DB::table('recipient_lists')
            ->whereIn('id', $audiences->where('target_type', 'list')->pluck('target_id'))
            ->get(['id', 'uuid', 'name'])->keyBy('id');
        $segments = DB::table('segments')
            ->whereIn('id', $audiences->where('target_type', 'segment')->pluck('target_id'))
            ->get(['id', 'uuid', 'name'])->keyBy('id');

        $entries = [];
        foreach ($audiences as $audience) {
            $isList = $audience->target_type === 'list';
            $target = ($isList ? $lists : $segments)->get($audience->target_id);
            $counters = (array) ($rows->get($audience->target_type.':'.$audience->target_id) ?? []);

            $entries[] = [
                'listId' => $isList ? $target?->uuid : null,
                'segmentId' => $isList ? null : $target?->uuid,
                'name' => $target?->name,
            ] + $this->brevoStats($counters);
        }

        return $entries;
    }

    public function timeseries(Campaign $campaign, string $granularity = 'auto'): array
    {
        if (! in_array($granularity, ['auto', 'hour', 'day'], true)) {
            throw new InvalidArgumentException("Unsupported granularity [{$granularity}].");
        }

        $this->ensureFresh($campaign);

        if ($granularity === 'auto') {
            $sentAt = $campaign->sent_at;
            $granularity = $sentAt === null || Carbon::instance($sentAt)->diffInHours(now(), true) <= 72 ? 'hour' : 'day';
        }

        $rows = DB::table('campaign_stats_hourly')
            ->where('campaign_id', $campaign->id)
            ->orderBy('hour')
            ->get(['hour', 'sent', 'delivered', 'opens', 'unique_opens', 'unique_clicks', 'unsubscriptions', 'hard_bounces', 'soft_bounces', 'complaints']);

        $points = [];
        foreach ($rows as $row) {
            $at = Carbon::parse($row->hour)->utc();
            $bucket = $granularity === 'day' ? $at->startOfDay() : $at->startOfHour();
            $key = $bucket->toIso8601ZuluString();

            $points[$key] ??= [
                't' => $key, 'sent' => 0, 'delivered' => 0, 'viewed' => 0, 'uniqueViews' => 0,
                'uniqueClicks' => 0, 'unsubscriptions' => 0, 'hardBounces' => 0, 'softBounces' => 0,
                'complaints' => 0,
            ];
            $points[$key]['sent'] += (int) $row->sent;
            $points[$key]['delivered'] += (int) $row->delivered;
            $points[$key]['viewed'] += (int) $row->opens;
            $points[$key]['uniqueViews'] += (int) $row->unique_opens;
            $points[$key]['uniqueClicks'] += (int) $row->unique_clicks;
            $points[$key]['unsubscriptions'] += (int) $row->unsubscriptions;
            $points[$key]['hardBounces'] += (int) $row->hard_bounces;
            $points[$key]['softBounces'] += (int) $row->soft_bounces;
            $points[$key]['complaints'] += (int) $row->complaints;
        }

        return ['granularity' => $granularity, 'points' => array_values($points)];
    }

    public function linksStats(Campaign $campaign): array
    {
        $stats = [];
        DB::table('campaign_links')
            ->where('campaign_id', $campaign->id)
            ->orderByDesc('total_clicks')
            ->orderBy('id')
            ->get(['url', 'total_clicks'])
            ->each(function (object $row) use (&$stats): void {
                $stats[$row->url] = ($stats[$row->url] ?? 0) + (int) $row->total_clicks;
            });

        arsort($stats);

        return $stats;
    }

    public function links(Campaign $campaign, ?int $limit = null): array
    {
        $totalClicks = (int) DB::table('campaign_links')->where('campaign_id', $campaign->id)->sum('total_clicks');

        return DB::table('campaign_links')
            ->where('campaign_id', $campaign->id)
            ->orderByDesc('unique_clicks')
            ->orderByDesc('total_clicks')
            ->orderBy('id')
            ->when($limit !== null, fn ($q) => $q->limit(max(1, (int) $limit)))
            ->get(['uuid', 'link_id', 'url', 'label', 'unique_clicks', 'total_clicks'])
            ->map(fn (object $row): array => [
                'id' => $row->uuid,
                'link_id' => $row->link_id,
                'url' => $row->url,
                'label' => $row->label,
                'unique_clicks' => (int) $row->unique_clicks,
                'total_clicks' => (int) $row->total_clicks,
                'share' => $totalClicks > 0 ? round((int) $row->total_clicks / $totalClicks, 4) : 0.0,
            ])
            ->values()
            ->all();
    }

    public function statsByDomain(Campaign $campaign): array
    {
        return $this->breakdowns($campaign)['statsByDomain'] ?? [];
    }

    public function statsByDevice(Campaign $campaign): array
    {
        return $this->breakdowns($campaign)['statsByDevice']
            ?? ['desktop' => [], 'mobile' => [], 'tablet' => [], 'unknown' => []];
    }

    public function statsByBrowser(Campaign $campaign): array
    {
        return $this->breakdowns($campaign)['statsByBrowser'] ?? [];
    }

    public function statsByCountry(Campaign $campaign): array
    {
        return $this->breakdowns($campaign)['statsByCountry'] ?? [];
    }

    public function statistics(Campaign $campaign): array
    {
        return [
            'globalStats' => $this->globalStats($campaign),
            'campaignStats' => $this->byList($campaign),
            'mirrorClick' => $this->mirrorClick($campaign),
            'remaining' => $this->remaining($campaign),
            'linksStats' => $this->linksStats($campaign),
            'statsByDomain' => $this->statsByDomain($campaign),
            'statsByDevice' => $this->statsByDevice($campaign),
            'statsByBrowser' => $this->statsByBrowser($campaign),
            'statsByCountry' => $this->statsByCountry($campaign),
        ];
    }

    public function mirrorClick(Campaign $campaign): int
    {
        return (int) DB::table('campaigns')->where('id', $campaign->id)->value('mirror_clicks');
    }

    public function remaining(Campaign $campaign): int
    {
        $queued = DB::table('messages')
            ->where('campaign_id', $campaign->id)
            ->where('is_test', false)
            ->whereIn('status', ['queued', 'sending'])
            ->count();

        $pending = DB::table('campaign_recipients')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'pending')
            ->whereNull('message_id')
            ->count();

        return $queued + $pending;
    }

    public function rates(array $stats): array
    {
        $sent = (int) ($stats['sent'] ?? 0);
        $delivered = (int) ($stats['delivered'] ?? 0);
        $uniqueViews = (int) ($stats['uniqueViews'] ?? 0);
        $uniqueClicks = (int) ($stats['uniqueClicks'] ?? 0);
        $bounces = (int) ($stats['hardBounces'] ?? 0) + (int) ($stats['softBounces'] ?? 0);

        return [
            'delivery_rate' => self::ratio($delivered, $sent),
            'open_rate' => self::ratio($uniqueViews, $delivered),
            'click_rate' => self::ratio($uniqueClicks, $delivered),
            'click_to_open_rate' => self::ratio($uniqueClicks, $uniqueViews),
            'unsubscribe_rate' => self::ratio((int) ($stats['unsubscriptions'] ?? 0), $delivered),
            'bounce_rate' => self::ratio($bounces, $delivered),
            'complaint_rate' => self::ratio((int) ($stats['complaints'] ?? 0), $delivered),
        ];
    }

    public function accountAverages(int $days = 90): array
    {
        $days = max(1, $days);

        return Cache::remember(
            "campaign-stats:averages:{$days}",
            (int) config('tracking.stats.averages_ttl_seconds', 3600),
            function () use ($days): array {
                $row = DB::table('campaigns')
                    ->where('sent_count', '>', 0)
                    ->whereNotNull('sent_at')
                    ->where('sent_at', '>=', now()->subDays($days))
                    ->selectRaw(
                        'COALESCE(SUM(sent_count), 0) as sent, COALESCE(SUM(delivered_count), 0) as delivered, '.
                        'COALESCE(SUM(unique_opens), 0) as unique_opens, COALESCE(SUM(unique_clicks), 0) as unique_clicks, '.
                        'COALESCE(SUM(unsubscribes), 0) as unsubscribes, COALESCE(SUM(hard_bounces), 0) as hard_bounces, '.
                        'COALESCE(SUM(soft_bounces), 0) as soft_bounces, COALESCE(SUM(complaints), 0) as complaints',
                    )
                    ->first();

                return $this->rates([
                    'sent' => (int) $row->sent,
                    'delivered' => (int) $row->delivered,
                    'uniqueViews' => (int) $row->unique_opens,
                    'uniqueClicks' => (int) $row->unique_clicks,
                    'unsubscriptions' => (int) $row->unsubscribes,
                    'hardBounces' => (int) $row->hard_bounces,
                    'softBounces' => (int) $row->soft_bounces,
                    'complaints' => (int) $row->complaints,
                ]);
            },
        );
    }

    public function rollup(Campaign $campaign): void
    {
        $this->aggregator->rollup($campaign);
        $this->fresh[$campaign->id] = true;
    }

    /**
     * @param  array<string, mixed>  $c  hourly / by-list counter columns
     * @return array<string, int|float>
     */
    private function brevoStats(array $c): array
    {
        $int = fn (string $key): int => (int) ($c[$key] ?? 0);
        $delivered = $int('delivered');
        $uniqueViews = $int('unique_opens');
        $proxy = min($int('proxy_opens'), $uniqueViews);
        $uniqueClicks = $int('unique_clicks');

        return [
            'sent' => $int('sent'),
            'delivered' => $delivered,
            'hardBounces' => $int('hard_bounces'),
            'softBounces' => $int('soft_bounces'),
            'complaints' => $int('complaints'),
            'unsubscriptions' => $int('unsubscriptions'),
            'viewed' => $int('opens'),
            'uniqueViews' => $uniqueViews,
            'trackableViews' => $uniqueViews - $proxy,
            'estimatedViews' => $uniqueViews,
            'appleMppOpens' => $proxy,
            'uniqueClicks' => $uniqueClicks,
            'clickers' => $uniqueClicks,
            'deferred' => $int('deferred'),
            'returnBounce' => 0,
            'opensRate' => $delivered > 0 ? round($uniqueViews / $delivered * 100, 2) : 0.0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function breakdowns(Campaign $campaign): array
    {
        $this->ensureFresh($campaign);

        $cached = Cache::get(CampaignStatsAggregator::breakdownCacheKey($campaign->id));
        if (is_array($cached)) {
            return $cached;
        }

        // Breakdown cache evicted: rebuild inline when small, else queue it.
        if ($this->isSmall($campaign)) {
            $this->fresh[$campaign->id] = true;

            return $this->aggregator->rollup($campaign);
        }

        RollupCampaignStatsJob::dispatch($campaign->id);

        return [];
    }

    private function ensureFresh(Campaign $campaign): void
    {
        if (isset($this->fresh[$campaign->id])) {
            return;
        }
        $this->fresh[$campaign->id] = true;

        $updatedAt = DB::table('campaigns')->where('id', $campaign->id)->value('stats_updated_at');
        $maxAge = (int) config('tracking.stats.max_age_seconds', 300);
        if ($updatedAt !== null && Carbon::parse($updatedAt)->gt(now()->subSeconds($maxAge))) {
            return;
        }

        if ($this->isSmall($campaign)) {
            $this->aggregator->rollup($campaign);

            return;
        }

        RollupCampaignStatsJob::dispatch($campaign->id);
    }

    private function isSmall(Campaign $campaign): bool
    {
        $threshold = max(0, (int) config('tracking.stats.live_threshold', 100000));

        $capped = DB::query()->fromSub(
            DB::table('messages')->where('campaign_id', $campaign->id)->where('is_test', false)->select('id')->limit($threshold + 1),
            'capped',
        )->count();

        return $capped <= $threshold;
    }

    private static function ratio(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round($numerator / $denominator, 4) : 0.0;
    }
}
