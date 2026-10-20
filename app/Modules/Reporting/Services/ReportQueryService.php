<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Audit\Models\AuditLog;
use App\Modules\Campaigns\AbTesting\Services\AbTestService;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Support\ExternalReferences;
use App\Modules\DeliveryEngine\Rendering\RenderingPipeline;
use App\Modules\DeliveryEngine\Rendering\Support\SystemUrls;
use App\Modules\Reporting\Contracts\CampaignStats;
use App\Modules\Tracking\Models\Message;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * docs/45-brevo-api-contracts.md §45.6.5 (WP-72) — campaign report tabs,
 * comparison and "voir les contacts" drill-down.
 *
 * Reads the WP-70 rollups (`campaign_stats_hourly`, `campaign_stats_by_list`,
 * `campaign_links`), the cached counters of `campaigns` and the rollup's
 * cached breakdowns. Raw `messages` / `message_events` are only read for the
 * paginated "voir les contacts" drill-down.
 *
 * The {@see CampaignStats} contract (§45.9, WP-70) is used for
 * `globalStats()`, `timeseries()` and the domain / device / browser / country
 * breakdowns when it is bound; otherwise the figures come from the counters /
 * rollup tables here (and the breakdowns are empty).
 */
class ReportQueryService
{
    public const STATS_SERVICE = CampaignStats::class;

    public const HOURLY_WINDOW_HOURS = 72;

    public const DEFAULT_TIMEZONE = 'Africa/Algiers';

    public const METRICS = [
        'sent', 'delivered', 'opened', 'not_opened', 'clicked', 'not_clicked', 'soft_bounced',
        'hard_bounced', 'unsubscribed', 'complained', 'deferred', 'blocked',
    ];

    public const TIMELINE_ACTIONS = [
        'created', 'scheduled', 'unscheduled', 'sent', 'paused', 'resumed', 'suspended', 'cancelled',
        'completed', 'ab_winner_picked', 'archived', 'unarchived', 'test_sent',
    ];

    /** @var array<string, bool> */
    private array $tables = [];

    // ------------------------------------------------------------------ helpers

    public function hasTable(string $table): bool
    {
        return $this->tables[$table] ??= Schema::hasTable($table);
    }

    public static function iso(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return ($value instanceof \DateTimeInterface ? Carbon::instance($value) : Carbon::parse((string) $value, 'UTC'))
            ->utc()->format('Y-m-d\TH:i:s\Z');
    }

    public static function ratio(int|float $numerator, int|float $denominator): float
    {
        return $denominator > 0 ? round($numerator / $denominator, 4) : 0.0;
    }

    /**
     * @param  array<string, mixed>  $stats  BrevoStats
     * @return array<string, float> RateSet (ratios 0–1)
     */
    public static function rates(array $stats): array
    {
        $delivered = (int) ($stats['delivered'] ?? 0);
        $bounces = (int) ($stats['hardBounces'] ?? 0) + (int) ($stats['softBounces'] ?? 0);

        return [
            'delivery_rate' => self::ratio((int) ($stats['delivered'] ?? 0), (int) ($stats['sent'] ?? 0)),
            'open_rate' => self::ratio((int) ($stats['uniqueViews'] ?? 0), $delivered),
            'click_rate' => self::ratio((int) ($stats['uniqueClicks'] ?? 0), $delivered),
            'click_to_open_rate' => self::ratio((int) ($stats['uniqueClicks'] ?? 0), (int) ($stats['uniqueViews'] ?? 0)),
            'unsubscribe_rate' => self::ratio((int) ($stats['unsubscriptions'] ?? 0), $delivered),
            'bounce_rate' => self::ratio($bounces, $delivered),
            'complaint_rate' => self::ratio((int) ($stats['complaints'] ?? 0), $delivered),
        ];
    }

    /**
     * Builds a BrevoStats object from raw counters.
     *
     * @param  array<string, mixed>  $c  keys: sent, delivered, hard_bounces, soft_bounces, complaints,
     *                                   unsubscriptions, opens, unique_opens, proxy_opens, unique_clicks, deferred
     * @return array<string, int|float>
     */
    public static function brevoStats(array $c): array
    {
        $delivered = (int) ($c['delivered'] ?? 0);
        $unique = (int) ($c['unique_opens'] ?? 0);
        $mpp = (int) ($c['proxy_opens'] ?? 0);
        $clicks = (int) ($c['unique_clicks'] ?? 0);

        return [
            'sent' => (int) ($c['sent'] ?? 0),
            'delivered' => $delivered,
            'hardBounces' => (int) ($c['hard_bounces'] ?? 0),
            'softBounces' => (int) ($c['soft_bounces'] ?? 0),
            'complaints' => (int) ($c['complaints'] ?? 0),
            'unsubscriptions' => (int) ($c['unsubscriptions'] ?? 0),
            'viewed' => (int) ($c['opens'] ?? 0),
            'uniqueViews' => $unique,
            'trackableViews' => max(0, $unique - $mpp),
            'estimatedViews' => $unique,
            'appleMppOpens' => $mpp,
            'uniqueClicks' => $clicks,
            'clickers' => $clicks,
            'deferred' => (int) ($c['deferred'] ?? 0),
            'returnBounce' => 0,
            'opensRate' => $delivered > 0 ? round($unique / $delivered * 100, 2) : 0.0,
        ];
    }

    /**
     * @return array<string, mixed>|\stdClass
     */
    private static function obj(array $value): array|\stdClass
    {
        return $value === [] ? new \stdClass : $value;
    }

    // ------------------------------------------------------------------ campaign basics

    /**
     * 409 for a campaign that never started sending (draft/scheduled and no messages).
     */
    public function assertReportable(Campaign $campaign): void
    {
        if (! in_array($campaign->status, ['draft', 'scheduled'], true)) {
            return;
        }

        if ($this->messages($campaign)->exists()) {
            return;
        }

        throw new HttpException(409, 'Le rapport n’est disponible qu’une fois l’envoi de la campagne démarré.');
    }

    /**
     * @return Builder
     */
    private function messages(Campaign $campaign)
    {
        return DB::table('messages as m')->where('m.campaign_id', $campaign->id)->where('m.is_test', false);
    }

    /**
     * @return array<string, mixed>
     */
    public function header(Campaign $campaign): array
    {
        $sender = app(ExternalReferences::class)->sender($campaign->sender_id);

        return [
            'id' => $campaign->uuid,
            'name' => $campaign->name,
            'subject' => $campaign->subject,
            'preview_text' => $campaign->preview_text,
            'status' => $campaign->status,
            'brevo_status' => $campaign->statusEnum()->brevoStatus($campaign->isArchived())->value,
            'sender' => $sender === null ? null : [
                'name' => $sender['name'] ?? null,
                'email' => $sender['email'] ?? null,
            ],
            'sent_at' => self::iso($campaign->sent_at),
            'completed_at' => self::iso($campaign->completed_at),
            'tags' => $campaign->tags ?? [],
            'type' => $campaign->type ?? 'classic',
            'thumbnail_url' => $campaign->thumbnail_path !== null
                ? Storage::disk('public')->url($campaign->thumbnail_path) : null,
            // docs/45 §45.6.3 — anonymous public mirror (`/m/c/{campaign}`) once the campaign was sent.
            'share_link' => $campaign->sent_at !== null ? app(SystemUrls::class)->campaignMirror($campaign) : null,
        ];
    }

    private function statsService(): ?CampaignStats
    {
        return app()->bound(CampaignStats::class) ? app(CampaignStats::class) : null;
    }

    /**
     * BrevoStats of a campaign: `CampaignStatsService::globalStats()` when bound, else the cached counters.
     *
     * @return array<string, int|float>
     */
    public function globalStats(Campaign $campaign): array
    {
        $service = $this->statsService();
        if ($service !== null) {
            return $service->globalStats($campaign);
        }

        $deferred = 0;
        if ($this->hasTable('campaign_stats_hourly')) {
            $deferred = (int) DB::table('campaign_stats_hourly')->where('campaign_id', $campaign->id)->sum('deferred');
        }

        return self::brevoStats([
            'sent' => $campaign->sent_count, 'delivered' => $campaign->delivered_count,
            'hard_bounces' => $campaign->hard_bounces, 'soft_bounces' => $campaign->soft_bounces,
            'complaints' => $campaign->complaints, 'unsubscriptions' => $campaign->unsubscribes,
            'opens' => $campaign->total_opens, 'unique_opens' => $campaign->unique_opens,
            'proxy_opens' => $campaign->proxy_opens, 'unique_clicks' => $campaign->unique_clicks,
            'deferred' => $deferred,
        ]);
    }

    /**
     * Account averages of campaigns sent during the last `$days` days (ratio of sums). Cached 1 h.
     *
     * @return array<string, float>
     */
    public function averages(int $days = 90): array
    {
        return Cache::remember('reporting:averages:'.$days, 3600, function () use ($days): array {
            $row = DB::table('campaigns')
                ->where('sent_count', '>', 0)
                ->where('sent_at', '>=', now()->subDays($days))
                ->selectRaw('COALESCE(SUM(sent_count),0) as sent, COALESCE(SUM(delivered_count),0) as delivered, '
                    .'COALESCE(SUM(unique_opens),0) as unique_opens, COALESCE(SUM(unique_clicks),0) as unique_clicks, '
                    .'COALESCE(SUM(unsubscribes),0) as unsubscriptions, COALESCE(SUM(hard_bounces),0) as hard_bounces, '
                    .'COALESCE(SUM(soft_bounces),0) as soft_bounces, COALESCE(SUM(complaints),0) as complaints')
                ->first();

            return self::rates(self::brevoStats((array) $row));
        });
    }

    /**
     * @param  array<string, float>  $rates
     * @param  array<string, float>  $averages
     * @return array<string, float>
     */
    private function versus(array $rates, array $averages): array
    {
        $out = [];
        foreach ($rates as $key => $value) {
            $out[$key] = round($value - ($averages[$key] ?? 0.0), 4);
        }

        return $out;
    }

    // ------------------------------------------------------------------ timeseries

    /**
     * Hourly points for the 72 h after `sent_at`, then daily points.
     *
     * @return array{granularity: string, points: list<array<string, mixed>>}
     */
    public function timeseries(Campaign $campaign): array
    {
        $start = $campaign->sent_at !== null ? Carbon::instance($campaign->sent_at)->utc() : null;
        $hourly = $this->rawPoints($campaign, 'hour');
        $start ??= isset($hourly[0]) ? Carbon::parse($hourly[0]['t'], 'UTC') : null;

        if ($start === null) {
            return ['granularity' => 'hour', 'points' => []];
        }

        $cutoff = $start->copy()->startOfHour()->addHours(self::HOURLY_WINDOW_HOURS);
        $head = array_values(array_filter($hourly, fn (array $p): bool => Carbon::parse($p['t'], 'UTC')->lt($cutoff)));

        // Daily points after the hourly window. With the CampaignStatsService the partial day that contains the
        // cutoff is skipped (its hours are already shown); the rollup fallback buckets only hours >= cutoff.
        $tail = $this->statsService() !== null
            ? array_values(array_filter(
                $this->rawPoints($campaign, 'day'),
                fn (array $p): bool => Carbon::parse($p['t'], 'UTC')->gte($cutoff->copy()->startOfDay()->addDay()),
            ))
            : $this->hourlyBuckets($campaign, 'day', $cutoff);

        $points = array_merge($head, array_map(fn (array $p): array => $p + ['granularity' => 'day'], $tail));

        return ['granularity' => $head !== [] ? 'hour' : 'day', 'points' => $points];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function rawPoints(Campaign $campaign, string $granularity): array
    {
        $service = $this->statsService();
        if ($service !== null) {
            $result = $service->timeseries($campaign, $granularity);
            $points = is_array($result) && isset($result['points']) ? $result['points'] : (array) $result;

            return array_map(fn (array $p): array => $p + ($granularity === 'hour' ? ['granularity' => 'hour'] : []), array_values($points));
        }

        return array_map(
            fn (array $p): array => $granularity === 'hour' ? $p + ['granularity' => 'hour'] : $p,
            $this->hourlyBuckets($campaign, $granularity),
        );
    }

    /**
     * Sums `campaign_stats_hourly` into hour or day buckets. With `$after`, day buckets only cover hours at or
     * after that instant.
     *
     * @return list<array<string, mixed>>
     */
    private function hourlyBuckets(Campaign $campaign, string $granularity, ?Carbon $after = null): array
    {
        if (! $this->hasTable('campaign_stats_hourly')) {
            return [];
        }

        $query = DB::table('campaign_stats_hourly')->where('campaign_id', $campaign->id)->orderBy('hour');
        if ($after !== null) {
            $query->where('hour', '>=', $after->format('Y-m-d H:i:s'));
        }

        $buckets = [];
        foreach ($query->get() as $row) {
            $t = Carbon::parse($row->hour, 'UTC');
            $key = $granularity === 'day' ? $t->copy()->startOfDay() : $t->copy()->startOfHour();
            $k = $key->format('Y-m-d\TH:i:s\Z');
            $buckets[$k] ??= [
                't' => $k, 'sent' => 0, 'delivered' => 0, 'viewed' => 0, 'uniqueViews' => 0, 'uniqueClicks' => 0,
                'unsubscriptions' => 0, 'hardBounces' => 0, 'softBounces' => 0, 'complaints' => 0,
            ];
            $buckets[$k]['sent'] += (int) $row->sent;
            $buckets[$k]['delivered'] += (int) $row->delivered;
            $buckets[$k]['viewed'] += (int) $row->opens;
            $buckets[$k]['uniqueViews'] += (int) $row->unique_opens;
            $buckets[$k]['uniqueClicks'] += (int) $row->unique_clicks;
            $buckets[$k]['unsubscriptions'] += (int) $row->unsubscriptions;
            $buckets[$k]['hardBounces'] += (int) $row->hard_bounces;
            $buckets[$k]['softBounces'] += (int) $row->soft_bounces;
            $buckets[$k]['complaints'] += (int) $row->complaints;
        }

        return array_values($buckets);
    }

    // ------------------------------------------------------------------ links

    /**
     * @return list<array<string, mixed>> ReportLinkRow, sorted by unique clicks desc
     */
    public function links(Campaign $campaign, ?int $limit = null): array
    {
        if (! $this->hasTable('campaign_links')) {
            return [];
        }

        $rows = DB::table('campaign_links')->where('campaign_id', $campaign->id)
            ->orderByDesc('unique_clicks')->orderByDesc('total_clicks')->orderBy('id')->get();
        $total = (int) $rows->sum('total_clicks');

        $mapped = $rows->map(fn (object $r): array => [
            'id' => $r->uuid ?? (string) ($r->id ?? $r->link_id),
            'link_id' => $r->link_id,
            'url' => $r->url,
            'label' => $r->label,
            'unique_clicks' => (int) $r->unique_clicks,
            'total_clicks' => (int) $r->total_clicks,
            'share' => self::ratio((int) $r->total_clicks, $total),
        ])->all();

        return $limit === null ? $mapped : array_slice($mapped, 0, $limit);
    }

    // ------------------------------------------------------------------ engagement breakdowns (rollup cache)

    /**
     * Device / browser / country breakdowns cached by the rollup ({@see CampaignStats::statsByDevice()} …).
     *
     * @return array{device: array<string, mixed>, browser: array<string, array<string, int>>, country: array<string, array<string, int>>}
     */
    private function engagement(Campaign $campaign): array
    {
        $device = ['desktop' => [], 'mobile' => [], 'tablet' => [], 'unknown' => []];
        $service = $this->statsService();
        if ($service === null) {
            return ['device' => array_map(fn (array $v) => self::obj($v), $device), 'browser' => [], 'country' => []];
        }

        return [
            'device' => array_map(fn (array $v) => self::obj($v), $service->statsByDevice($campaign) + $device),
            'browser' => $service->statsByBrowser($campaign),
            'country' => $service->statsByCountry($campaign),
        ];
    }

    /**
     * @return array<string, array<string, int>> BrevoDomainStats keyed by domain (top 25 by sent), from the rollup cache
     */
    private function domains(Campaign $campaign): array
    {
        $service = $this->statsService();

        return $service === null ? [] : array_slice($service->statsByDomain($campaign), 0, 25, true);
    }

    // ------------------------------------------------------------------ tabs

    /**
     * @return array<string, mixed>
     */
    public function overview(Campaign $campaign): array
    {
        $stats = $this->globalStats($campaign);
        $rates = self::rates($stats);
        $averages = $this->averages();
        $engagement = $this->engagement($campaign);

        return [
            'campaign' => $this->header($campaign),
            'globalStats' => $stats,
            'rates' => $rates,
            'averages' => $averages,
            'vs_account_average' => $this->versus($rates, $averages),
            'mirrorClick' => (int) $campaign->mirror_clicks,
            'remaining' => max(0, (int) $campaign->recipients_count - (int) $campaign->sent_count),
            'timeseries' => $this->timeseries($campaign),
            'campaignStats' => $this->campaignStats($campaign),
            'audience' => $this->audience($campaign),
            'timeline' => $this->timeline($campaign),
            'links' => $this->links($campaign, 10),
            'statsByDevice' => $engagement['device'],
            'ab_test' => app(AbTestService::class)->report($campaign),
            'apple_mpp_note' => (int) $stats['appleMppOpens'] > 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function deliverability(Campaign $campaign): array
    {
        $stats = $this->globalStats($campaign);

        $blocked = DB::table('campaign_recipients')->where('campaign_id', $campaign->id)
            ->where('status', 'skipped')->where('skip_reason', 'suppressed')->count();

        $queued = $this->messages($campaign)->whereIn('m.status', ['queued', 'sending', 'accepted'])->count();

        $reasons = $this->messages($campaign)->whereIn('m.status', ['hard_bounced', 'soft_bounced'])
            ->selectRaw('m.status, m.last_provider_response as reason, COUNT(*) as total')
            ->groupBy('m.status', 'm.last_provider_response')->orderByDesc('total')->limit(50)->get()
            ->map(fn (object $r): array => [
                'type' => $r->status === 'hard_bounced' ? 'hard' : 'soft',
                'reason' => $r->reason !== null ? Str::limit($r->reason, 200, '') : 'Inconnu',
                'count' => (int) $r->total,
            ])->all();

        $smtp = $this->messages($campaign)->leftJoin('smtp_accounts as s', 's.id', '=', 'm.smtp_account_id')
            ->whereNotNull('m.smtp_account_id')
            ->selectRaw('s.uuid, s.name, COUNT(*) as sent, SUM(CASE WHEN '.Message::deliveredSql('m').' THEN 1 ELSE 0 END) as delivered, '
                .'SUM(CASE WHEN m.status = \'hard_bounced\' THEN 1 ELSE 0 END) as hard, '
                .'SUM(CASE WHEN m.status = \'soft_bounced\' THEN 1 ELSE 0 END) as soft')
            ->groupBy('s.uuid', 's.name')->get()
            ->map(fn (object $r): array => [
                'smtp_account_id' => $r->uuid, 'name' => $r->name, 'sent' => (int) $r->sent,
                'delivered' => (int) $r->delivered, 'hardBounces' => (int) $r->hard, 'softBounces' => (int) $r->soft,
                'deferred' => 0,
            ])->all();

        return [
            'campaign' => $this->header($campaign),
            'globalStats' => $stats,
            'rates' => self::rates($stats),
            'blocked_by_suppression' => $blocked,
            'queued' => $queued,
            'bounce_reasons' => $reasons,
            'by_smtp_account' => $smtp,
            'statsByDomain' => self::obj($this->domains($campaign)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function opens(Campaign $campaign): array
    {
        $stats = $this->globalStats($campaign);
        $engagement = $this->engagement($campaign);

        return [
            'campaign' => $this->header($campaign),
            'viewed' => $stats['viewed'],
            'uniqueViews' => $stats['uniqueViews'],
            'trackableViews' => $stats['trackableViews'],
            'estimatedViews' => $stats['estimatedViews'],
            'appleMppOpens' => $stats['appleMppOpens'],
            'opensRate' => $stats['opensRate'],
            'averages' => $this->averages(),
            'timeseries' => $this->timeseries($campaign),
            'by_hour_of_day' => $this->byHourOfDay($campaign),
            'statsByDomain' => self::obj($this->domains($campaign)),
            'statsByDevice' => $engagement['device'],
            'statsByBrowser' => self::obj($engagement['browser']),
            'statsByCountry' => self::obj($engagement['country']),
        ];
    }

    /**
     * @return list<array{hour: int, uniqueViews: int}>
     */
    private function byHourOfDay(Campaign $campaign): array
    {
        $hours = array_fill(0, 24, 0);
        if ($this->hasTable('campaign_stats_hourly')) {
            $tz = $campaign->timezone ?: self::DEFAULT_TIMEZONE;
            $rows = DB::table('campaign_stats_hourly')->where('campaign_id', $campaign->id)
                ->where('unique_opens', '>', 0)->get(['hour', 'unique_opens']);
            foreach ($rows as $row) {
                $hours[(int) Carbon::parse($row->hour, 'UTC')->setTimezone($tz)->format('G')] += (int) $row->unique_opens;
            }
        }

        $out = [];
        foreach ($hours as $hour => $views) {
            $out[] = ['hour' => $hour, 'uniqueViews' => $views];
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    public function clicks(Campaign $campaign): array
    {
        $stats = $this->globalStats($campaign);
        $rates = self::rates($stats);
        $links = $this->links($campaign);
        $engagement = $this->engagement($campaign);

        return [
            'campaign' => $this->header($campaign),
            'uniqueClicks' => $stats['uniqueClicks'],
            'clickers' => $stats['clickers'],
            'total_clicks' => (int) $campaign->total_clicks,
            'click_rate' => $rates['click_rate'],
            'click_to_open_rate' => $rates['click_to_open_rate'],
            'averages' => $this->averages(),
            'linksStats' => self::obj(collect($links)->pluck('total_clicks', 'url')->all()),
            'links' => $links,
            'click_map' => array_map(fn (array $l): array => [
                'link_id' => $l['link_id'], 'url' => $l['url'], 'unique_clicks' => $l['unique_clicks'], 'share' => $l['share'],
            ], $links),
            // Campaign HTML with UTM + `data-link-id` only (no merge tags, no tracking), probed by the click map.
            'html' => filled($campaign->html_body) ? app(RenderingPipeline::class)->clickMapHtml($campaign) : null,
            'timeseries' => $this->timeseries($campaign),
            'statsByDevice' => $engagement['device'],
            'statsByBrowser' => self::obj($engagement['browser']),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function unsubscriptions(Campaign $campaign): array
    {
        $stats = $this->globalStats($campaign);
        $rates = self::rates($stats);

        $byList = [];
        if ($this->hasTable('campaign_stats_by_list')) {
            $byList = DB::table('campaign_stats_by_list as s')
                ->join('recipient_lists as l', 'l.id', '=', 's.target_id')
                ->where('s.campaign_id', $campaign->id)->where('s.target_type', 'list')
                ->select('l.uuid', 'l.name', 's.unsubscriptions', 's.complaints')->orderBy('l.name')->get()
                ->map(fn (object $r): array => [
                    'list_id' => $r->uuid, 'name' => $r->name,
                    'unsubscriptions' => (int) $r->unsubscriptions, 'complaints' => (int) $r->complaints,
                ])->all();
        }

        return [
            'campaign' => $this->header($campaign),
            'unsubscriptions' => $stats['unsubscriptions'],
            'complaints' => $stats['complaints'],
            'unsubscribe_rate' => $rates['unsubscribe_rate'],
            'complaint_rate' => $rates['complaint_rate'],
            'by_list' => $byList,
            'reasons' => [],
            'recent' => $this->contacts($campaign, 'unsubscribed', null, null, null, 20)['data'],
        ];
    }

    // ------------------------------------------------------------------ overview parts

    /**
     * @return list<array<string, mixed>> BrevoCampaignStatsEntry per targeted list / segment
     */
    private function campaignStats(Campaign $campaign): array
    {
        if (! $this->hasTable('campaign_stats_by_list')) {
            return [];
        }

        $out = [];
        foreach (DB::table('campaign_stats_by_list')->where('campaign_id', $campaign->id)->orderBy('target_type')->orderBy('target_id')->get() as $row) {
            $isSegment = $row->target_type === 'segment';
            $target = DB::table($isSegment ? 'segments' : 'recipient_lists')->where('id', $row->target_id)->first(['uuid', 'name']);
            $out[] = [
                'listId' => ! $isSegment ? $target?->uuid : null,
                'segmentId' => $isSegment ? $target?->uuid : null,
                'name' => $target?->name ?? '—',
            ] + self::brevoStats([
                'sent' => $row->sent, 'delivered' => $row->delivered, 'hard_bounces' => $row->hard_bounces,
                'soft_bounces' => $row->soft_bounces, 'complaints' => $row->complaints,
                'unsubscriptions' => $row->unsubscriptions, 'opens' => $row->opens, 'unique_opens' => $row->unique_opens,
                'proxy_opens' => $row->proxy_opens, 'unique_clicks' => $row->unique_clicks, 'deferred' => $row->deferred,
            ]);
        }

        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function audience(Campaign $campaign): array
    {
        $out = ['lists' => [], 'exclusion_lists' => [], 'segments' => [], 'excluded_segments' => [], 'recipients' => (int) $campaign->recipients_count];

        foreach ($campaign->audiences()->get() as $audience) {
            $isSegment = $audience->target_type === 'segment';
            $row = DB::table($isSegment ? 'segments' : 'recipient_lists')->where('id', $audience->target_id)->first();
            if ($row === null) {
                continue;
            }
            $ref = [
                'id' => $row->uuid, 'name' => $row->name,
                'contact_count' => (int) ($row->contact_count ?? 0),
            ];
            $exclude = $audience->mode === 'exclude';
            $out[$isSegment ? ($exclude ? 'excluded_segments' : 'segments') : ($exclude ? 'exclusion_lists' : 'lists')][] = $ref;
        }

        return $out;
    }

    /**
     * Timeline from the audit log entries of the campaign (`campaign.<action>`), completed with the
     * `created` / `sent` / `completed` moments of the campaign when no entry was logged.
     *
     * @return list<array<string, mixed>>
     */
    private function timeline(Campaign $campaign): array
    {
        $items = [];
        $logs = AuditLog::query()->with('user')
            ->where('auditable_type', Campaign::class)->where('auditable_id', $campaign->id)
            ->where('action', 'like', 'campaign.%')->orderBy('created_at')->orderBy('id')->get();

        foreach ($logs as $log) {
            $action = Str::after($log->action, 'campaign.');
            if (! in_array($action, self::TIMELINE_ACTIONS, true)) {
                continue;
            }
            $items[] = [
                'action' => $action,
                'occurred_at' => self::iso($log->created_at),
                'actor' => $log->user === null ? null : ['id' => $log->user->uuid, 'name' => $log->user->name],
                'details' => $log->new_values ?: null,
            ];
        }

        $present = array_column($items, 'action');
        foreach (['created' => $campaign->created_at, 'sent' => $campaign->sent_at, 'completed' => $campaign->completed_at] as $action => $at) {
            if ($at !== null && ! in_array($action, $present, true)) {
                $items[] = ['action' => $action, 'occurred_at' => self::iso($at), 'actor' => null, 'details' => null];
            }
        }

        usort($items, fn (array $a, array $b): int => strcmp((string) $a['occurred_at'], (string) $b['occurred_at']));

        return $items;
    }

    // ------------------------------------------------------------------ compare

    /**
     * @param  Collection<int, Campaign>  $campaigns
     * @return list<array<string, mixed>>
     */
    public function compare(Collection $campaigns): array
    {
        return $campaigns->map(function (Campaign $c): array {
            $stats = $this->globalStats($c);

            return ['campaign' => $this->header($c), 'globalStats' => $stats, 'rates' => self::rates($stats)];
        })->values()->all();
    }

    // ------------------------------------------------------------------ contacts drill-down

    /**
     * @return array{data: list<array<string, mixed>>, next_cursor: string|null}
     */
    public function contacts(Campaign $campaign, string $metric, ?string $linkId, ?string $listUuid, ?string $cursor, int $perPage): array
    {
        $q = $this->messages($campaign)->join('recipients as r', 'r.id', '=', 'm.recipient_id');
        $eventsOk = $this->hasTable('message_events');

        $q->when($listUuid !== null, fn ($qq) => $qq->whereIn('m.recipient_id', fn ($s) => $s->select('lr.recipient_id')
            ->from('recipient_list_recipient as lr')->join('recipient_lists as l', 'l.id', '=', 'lr.recipient_list_id')
            ->where('l.uuid', $listUuid)));

        $clickedByLink = fn ($qq) => $qq->whereExists(fn ($s) => $s->selectRaw('1')->from('message_events as e')
            ->whereColumn('e.message_id', 'm.id')->where('e.event_type', 'clicked')
            ->when($linkId !== null, fn ($x) => $x->where('e.link_id', $linkId)));

        $hasEvent = fn (string $type) => fn ($qq) => $qq->whereExists(fn ($s) => $s->selectRaw('1')->from('message_events as e')
            ->whereColumn('e.message_id', 'm.id')->where('e.event_type', $type));

        $column = match ($metric) {
            'sent' => 'm.sent_at', 'delivered' => 'COALESCE(m.delivered_at, m.sent_at)', 'opened' => 'm.opened_at', 'clicked' => 'm.clicked_at',
            'soft_bounced', 'hard_bounced' => 'm.bounced_at', 'blocked' => 'm.failed_at', default => 'm.updated_at',
        };

        match ($metric) {
            'sent' => $q->whereNotNull('m.sent_at'),
            'delivered' => Message::whereDelivered($q, 'm'),
            'opened' => $q->whereNotNull('m.opened_at'),
            'not_opened' => Message::whereDelivered($q, 'm')->whereNull('m.opened_at'),
            'clicked' => $linkId !== null && $eventsOk ? $clickedByLink($q) : $q->whereNotNull('m.clicked_at'),
            'not_clicked' => Message::whereDelivered($q, 'm')->whereNull('m.clicked_at'),
            'soft_bounced' => $q->where('m.status', 'soft_bounced'),
            'hard_bounced' => $q->where('m.status', 'hard_bounced'),
            'unsubscribed' => $q->where(fn ($w) => $w->where('m.status', 'unsubscribed')
                ->when($eventsOk, fn ($x) => $x->orWhere($hasEvent('unsubscribed')))),
            'complained' => $q->where('m.status', 'spam_complaint'),
            'deferred' => $eventsOk ? $hasEvent('deferred')($q) : $q->whereRaw('1 = 0'),
            'blocked' => $q->whereNotNull('m.failed_at'),
        };

        $after = $this->decodeCursor($cursor);
        if ($after !== null) {
            $q->where('m.id', '>', $after);
        }

        $rows = $q->orderBy('m.id')->limit($perPage + 1)
            ->get(['m.id as message_id', 'r.uuid as recipient_uuid', 'r.email', 'r.first_name', 'r.last_name',
                'm.open_count', 'm.click_count', 'm.last_provider_response', 'm.status',
                DB::raw($column.' as occurred_at')]);

        $hasMore = $rows->count() > $perPage;
        $rows = $rows->take($perPage);

        $events = $this->latestEvents($rows->pluck('message_id')->all(), $metric);

        $data = $rows->map(function (object $r) use ($metric, $events): array {
            $e = $events[$r->message_id] ?? null;

            return [
                'recipient_id' => $r->recipient_uuid,
                'email' => $r->email,
                'first_name' => $r->first_name,
                'last_name' => $r->last_name,
                'occurred_at' => self::iso($r->occurred_at),
                'count' => $metric === 'clicked' ? (int) $r->click_count : ($metric === 'opened' ? (int) $r->open_count : 1),
                'url' => $e?->url,
                'reason' => in_array($metric, ['soft_bounced', 'hard_bounced', 'blocked'], true) ? $r->last_provider_response : null,
                'device_type' => $e?->device_type,
                'email_client' => $e?->email_client,
                'ip_country' => $e?->ip_country,
            ];
        })->values()->all();

        return [
            'data' => $data,
            'next_cursor' => $hasMore && $rows->isNotEmpty() ? base64_encode((string) $rows->last()->message_id) : null,
        ];
    }

    private function decodeCursor(?string $cursor): ?int
    {
        if ($cursor === null || $cursor === '') {
            return null;
        }
        $id = base64_decode($cursor, true);

        return $id !== false && ctype_digit($id) ? (int) $id : null;
    }

    /**
     * @param  list<int>  $messageIds
     * @return array<int, object>
     */
    private function latestEvents(array $messageIds, string $metric): array
    {
        $type = match ($metric) {
            'opened' => 'opened', 'clicked' => 'clicked', default => null,
        };
        if ($type === null || $messageIds === [] || ! $this->hasTable('message_events') || ! Schema::hasColumn('message_events', 'device_type')) {
            return [];
        }

        $out = [];
        foreach (DB::table('message_events')->whereIn('message_id', $messageIds)->where('event_type', $type)->orderBy('id')
            ->get(['message_id', 'url', 'device_type', 'email_client', 'ip_country']) as $e) {
            $out[$e->message_id] = $e;
        }

        return $out;
    }

    // ------------------------------------------------------------------ segment

    /**
     * @return array{rules: array<string, mixed>, default_name: string}
     */
    public function segmentDefinition(Campaign $campaign, string $metric, ?string $linkId): array
    {
        [$event, $operator, $label] = match ($metric) {
            'opened' => ['opened', 'has', 'A ouvert'],
            'not_opened' => ['opened', 'has_not', 'N’a pas ouvert'],
            'clicked' => ['clicked', 'has', 'A cliqué'],
            'not_clicked' => ['clicked', 'has_not', 'N’a pas cliqué'],
            'unsubscribed' => ['unsubscribed', 'has', 'Désinscrit de'],
            'hard_bounced' => ['hard_bounced', 'has', 'Hard bounce sur'],
            'soft_bounced' => ['soft_bounced', 'has', 'Soft bounce sur'],
        };

        $url = null;
        if ($linkId !== null) {
            $url = $this->hasTable('campaign_links')
                ? DB::table('campaign_links')->where('campaign_id', $campaign->id)->where('link_id', $linkId)->value('url') : null;
            if ($url === null) {
                throw ValidationException::withMessages(['link_id' => 'Lien inconnu pour cette campagne.']);
            }
        }

        return [
            'rules' => [
                'version' => 2, 'type' => 'group', 'operator' => 'and',
                'children' => [[
                    'type' => 'condition',
                    'field' => 'engagement.'.$event,
                    'operator' => $operator,
                    'value' => [
                        'campaign_ids' => [$campaign->uuid], 'within_days' => null, 'from' => null, 'to' => null,
                        'link_url' => in_array($metric, ['clicked', 'not_clicked'], true) ? $url : null,
                        'min_count' => null, 'automation_step_id' => null,
                    ],
                ]],
            ],
            'default_name' => Str::limit($label.' « '.$campaign->name.' »', 150, ''),
        ];
    }
}
