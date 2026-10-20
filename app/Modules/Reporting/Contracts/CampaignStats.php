<?php

namespace App\Modules\Reporting\Contracts;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Reporting\Services\CampaignStatsService;

/**
 * docs/45-brevo-api-contracts.md §45.9 — campaign statistics read API over
 * WP-70's rollups (`campaign_stats_hourly`, `campaign_stats_by_list`,
 * `campaign_links`, cached `campaigns` counters and the breakdown cache).
 * Implemented by {@see CampaignStatsService}
 * (bound in AppServiceProvider); consumed by WP-72 (report / statistics
 * controllers) and WP-80 (A/B winner metrics).
 *
 * Keys of every Brevo-shaped array are Brevo's camelCase names verbatim
 * (doc 45 §45.1 "Casing", §45.3 "Brevo statistics objects"). No method reads
 * raw `message_events` on the request path for campaigns above
 * `tracking.stats.live_threshold` messages (100k) — those are served from the
 * rollups and refreshed by `RollupCampaignStatsJob`.
 */
interface CampaignStats
{
    /**
     * BrevoStats: `sent`, `delivered`, `hardBounces`, `softBounces`,
     * `complaints`, `unsubscriptions`, `viewed`, `uniqueViews`,
     * `trackableViews`, `estimatedViews`, `appleMppOpens`, `uniqueClicks`,
     * `clickers`, `deferred`, `returnBounce`, `opensRate` (0–100, 2 decimals).
     *
     * @return array<string, int|float>
     */
    public function globalStats(Campaign $campaign): array;

    /**
     * Brevo `campaignStats`: one BrevoCampaignStatsEntry per included list /
     * segment (`listId`, `segmentId` — uuids or null —, `name`, every
     * BrevoStats key).
     *
     * @return list<array<string, int|float|string|null>>
     */
    public function byList(Campaign $campaign): array;

    /**
     * ReportTimeseries `{granularity, points[]}`; `$granularity` = `hour` |
     * `day` | `auto` (hour within 72 h of `sent_at`, day afterwards). Point
     * keys: `t` (ISO-8601 UTC), `sent`, `delivered`, `viewed`, `uniqueViews`,
     * `uniqueClicks`, `unsubscriptions`, `hardBounces`, `softBounces`,
     * `complaints`.
     *
     * @return array{granularity: string, points: list<array<string, int|string>>}
     */
    public function timeseries(Campaign $campaign, string $granularity = 'auto'): array;

    /**
     * Brevo `linksStats`: `{ "<url>": <total clicks> }`, most clicked first.
     *
     * @return array<string, int>
     */
    public function linksStats(Campaign $campaign): array;

    /**
     * ReportLinkRow list (`id`, `link_id`, `url`, `label`, `unique_clicks`,
     * `total_clicks`, `share` of all clicks 0–1), sorted by unique clicks.
     *
     * @return list<array<string, int|float|string|null>>
     */
    public function links(Campaign $campaign, ?int $limit = null): array;

    /**
     * Brevo `statsByDomain`: `{ "gmail.com": BrevoDomainStats }`.
     *
     * @return array<string, array<string, int>>
     */
    public function statsByDomain(Campaign $campaign): array;

    /**
     * Brevo `statsByDevice`: `{ desktop|mobile|tablet|unknown: { "<os or client>": Breakdown } }`.
     *
     * @return array<string, array<string, array<string, int>>>
     */
    public function statsByDevice(Campaign $campaign): array;

    /**
     * Brevo `statsByBrowser`: `{ "<mail client or browser>": Breakdown }`.
     *
     * @return array<string, array<string, int>>
     */
    public function statsByBrowser(Campaign $campaign): array;

    /**
     * `statsByCountry` (our addition): `{ "<ISO alpha-2|unknown>": Breakdown }`.
     *
     * @return array<string, array<string, int>>
     */
    public function statsByCountry(Campaign $campaign): array;

    /**
     * Brevo campaign `statistics` object: `globalStats`, `campaignStats`,
     * `mirrorClick`, `remaining`, `linksStats`, `statsByDomain`,
     * `statsByDevice`, `statsByBrowser`, `statsByCountry`.
     *
     * @return array<string, mixed>
     */
    public function statistics(Campaign $campaign): array;

    /** Brevo `mirrorClick` (web-version opens, `campaigns.mirror_clicks`). */
    public function mirrorClick(Campaign $campaign): int;

    /** Brevo `remaining`: audience members / messages not sent yet. */
    public function remaining(Campaign $campaign): int;

    /**
     * RateSet (ratios 0–1) computed from a BrevoStats array: `delivery_rate`,
     * `open_rate`, `click_rate`, `click_to_open_rate`, `unsubscribe_rate`,
     * `bounce_rate`, `complaint_rate`.
     *
     * @param  array<string, int|float>  $stats
     * @return array<string, float>
     */
    public function rates(array $stats): array;

    /**
     * Account averages ("vs moyenne du compte"): RateSet over campaigns sent
     * in the last `$days` days, from the cached campaign counters (cached 1 h).
     *
     * @return array<string, float>
     */
    public function accountAverages(int $days = 90): array;

    /**
     * Recomputes the campaign's rollups now (jobs / commands only for large
     * campaigns).
     */
    public function rollup(Campaign $campaign): void;
}
