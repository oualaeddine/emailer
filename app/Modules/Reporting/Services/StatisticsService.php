<?php

namespace App\Modules\Reporting\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * docs/45-brevo-api-contracts.md §45.6.5 — global statistics page (WP-72 / WP-73 UI).
 *
 * Marketing figures come from the cached `campaigns` counters and the
 * `campaign_stats_hourly` / `campaign_stats_by_list` rollups, never from raw
 * `message_events`. The `transactional` scope (messages without campaign) has
 * no rollup and is a plain aggregate over `messages` for the period.
 */
class StatisticsService
{
    public const SORTS = ['-sent_at', 'sent_at', 'name', '-name', '-open_rate', '-click_rate', '-sent'];

    public const PERIODS = ['7d' => 7, '30d' => 30, '90d' => 90, '180d' => 180, '365d' => 365];

    /**
     * Resolves the `[from, to]` window from `date_from`/`date_to` (or `from`/`to`), else from `period`.
     *
     * @param  array<string, mixed>  $input
     * @return array{0: Carbon, 1: Carbon}
     */
    public function window(array $input): array
    {
        $from = $input['date_from'] ?? $input['from'] ?? null;
        $to = $input['date_to'] ?? $input['to'] ?? null;

        if ($from === null && $to === null) {
            $days = self::PERIODS[$input['period'] ?? '30d'];
            $end = now()->utc()->endOfDay();

            return [$end->copy()->subDays($days - 1)->startOfDay(), $end];
        }

        $start = Carbon::parse($from ?? $to, 'UTC')->startOfDay();
        $end = Carbon::parse($to ?? $from, 'UTC')->endOfDay();

        return [$start, $end];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function summary(array $input): array
    {
        [$from, $to] = $this->window($input);
        $scope = $input['scope'] ?? 'marketing';

        $stats = $this->periodStats($from, $to, $scope);
        $out = [
            'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            'scope' => $scope,
            'globalStats' => $stats,
            'rates' => ReportQueryService::rates($stats),
            'campaigns_sent' => $scope === 'transactional' ? 0 : $this->campaignsQuery($from, $to)->count(),
            'previous' => null,
        ];

        if (($input['compare'] ?? null) === 'previous_period') {
            $days = (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
            $pTo = $from->copy()->subDay()->endOfDay();
            $pFrom = $pTo->copy()->subDays($days - 1)->startOfDay();
            $pStats = $this->periodStats($pFrom, $pTo, $scope);
            $out['previous'] = [
                'period' => ['from' => $pFrom->toDateString(), 'to' => $pTo->toDateString()],
                'globalStats' => $pStats,
                'rates' => ReportQueryService::rates($pStats),
            ];
        }

        return $out;
    }

    /**
     * @return array<string, int|float>
     */
    private function periodStats(Carbon $from, Carbon $to, string $scope): array
    {
        $totals = array_fill_keys(['sent', 'delivered', 'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions',
            'opens', 'unique_opens', 'proxy_opens', 'unique_clicks', 'deferred'], 0);

        if ($scope !== 'transactional') {
            $row = $this->campaignsQuery($from, $to)->selectRaw(
                'COALESCE(SUM(sent_count),0) as sent, COALESCE(SUM(delivered_count),0) as delivered, '
                .'COALESCE(SUM(hard_bounces),0) as hard_bounces, COALESCE(SUM(soft_bounces),0) as soft_bounces, '
                .'COALESCE(SUM(complaints),0) as complaints, COALESCE(SUM(unsubscribes),0) as unsubscriptions, '
                .'COALESCE(SUM(total_opens),0) as opens, COALESCE(SUM(unique_opens),0) as unique_opens, '
                .'COALESCE(SUM(proxy_opens),0) as proxy_opens, COALESCE(SUM(unique_clicks),0) as unique_clicks'
            )->first();
            foreach ((array) $row as $k => $v) {
                $totals[$k] += (int) $v;
            }

            if (Schema::hasTable('campaign_stats_hourly')) {
                $totals['deferred'] += (int) DB::table('campaign_stats_hourly')
                    ->whereIn('campaign_id', $this->campaignsQuery($from, $to)->select('id'))->sum('deferred');
            }
        }

        if ($scope !== 'marketing') {
            $row = DB::table('messages')->whereNull('campaign_id')->where('is_test', false)
                ->whereBetween('created_at', [$from, $to])
                ->selectRaw('COUNT(sent_at) as sent, COUNT(delivered_at) as delivered, '
                    ."SUM(CASE WHEN status = 'hard_bounced' THEN 1 ELSE 0 END) as hard_bounces, "
                    ."SUM(CASE WHEN status = 'soft_bounced' THEN 1 ELSE 0 END) as soft_bounces, "
                    ."SUM(CASE WHEN status = 'spam_complaint' THEN 1 ELSE 0 END) as complaints, "
                    ."SUM(CASE WHEN status = 'unsubscribed' THEN 1 ELSE 0 END) as unsubscriptions, "
                    .'COALESCE(SUM(open_count),0) as opens, COUNT(opened_at) as unique_opens, COUNT(clicked_at) as unique_clicks')
                ->first();
            foreach ((array) $row as $k => $v) {
                $totals[$k] += (int) $v;
            }
        }

        return ReportQueryService::brevoStats($totals);
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function campaignsQuery(Carbon $from, Carbon $to)
    {
        return DB::table('campaigns')->whereBetween('sent_at', [$from, $to]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @return array{granularity: string, points: list<array<string, mixed>>}
     */
    public function timeseries(array $input): array
    {
        [$from, $to] = $this->window($input);
        $granularity = $input['granularity'] ?? 'day';
        $scope = $input['scope'] ?? 'marketing';

        if ($scope === 'transactional' || ! Schema::hasTable('campaign_stats_hourly')) {
            return ['granularity' => $granularity, 'points' => []];
        }

        $buckets = [];
        $rows = DB::table('campaign_stats_hourly')->whereBetween('hour', [$from, $to])->orderBy('hour')->get();
        foreach ($rows as $row) {
            $t = Carbon::parse($row->hour, 'UTC');
            $key = match ($granularity) {
                'hour' => $t->copy()->startOfHour(),
                'week' => $t->copy()->startOfWeek(),
                'month' => $t->copy()->startOfMonth(),
                default => $t->copy()->startOfDay(),
            };
            $k = $key->format('Y-m-d\TH:i:s\Z');
            $buckets[$k] ??= [
                't' => $k, 'sent' => 0, 'delivered' => 0, 'viewed' => 0, 'uniqueViews' => 0, 'uniqueClicks' => 0,
                'unsubscriptions' => 0, 'hardBounces' => 0, 'softBounces' => 0, 'complaints' => 0,
            ];
            foreach (['sent' => 'sent', 'delivered' => 'delivered', 'viewed' => 'opens', 'uniqueViews' => 'unique_opens',
                'uniqueClicks' => 'unique_clicks', 'unsubscriptions' => 'unsubscriptions', 'hardBounces' => 'hard_bounces',
                'softBounces' => 'soft_bounces', 'complaints' => 'complaints'] as $out => $col) {
                $buckets[$k][$out] += (int) $row->{$col};
            }
        }

        return ['granularity' => $granularity, 'points' => array_values($buckets)];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function campaigns(array $input, int $perPage): LengthAwarePaginator
    {
        [$from, $to] = $this->window($input);
        $query = $this->filteredCampaigns($from, $to, $input['tag'] ?? null);

        $sort = $input['sort'] ?? '-sent_at';
        $ratio = fn (string $num) => "COALESCE({$num} * 1.0 / NULLIF(delivered_count, 0), 0)";
        match ($sort) {
            'sent_at' => $query->orderBy('sent_at'),
            'name' => $query->orderBy('name'),
            '-name' => $query->orderByDesc('name'),
            '-open_rate' => $query->orderByRaw($ratio('unique_opens').' desc'),
            '-click_rate' => $query->orderByRaw($ratio('unique_clicks').' desc'),
            '-sent' => $query->orderByDesc('sent_count'),
            default => $query->orderByDesc('sent_at'),
        };

        return $query->orderByDesc('id')->paginate($perPage)->through(fn (object $c): array => $this->campaignRow($c));
    }

    /**
     * @return list<array<string, mixed>>  every campaign of the period (CSV export)
     */
    public function allCampaignRows(array $input): array
    {
        [$from, $to] = $this->window($input);

        return $this->filteredCampaigns($from, $to, $input['tag'] ?? null)->orderByDesc('sent_at')->limit(5000)->get()
            ->map(fn (object $c): array => $this->campaignRow($c))->all();
    }

    /**
     * @return \Illuminate\Database\Query\Builder
     */
    private function filteredCampaigns(Carbon $from, Carbon $to, ?string $tag)
    {
        $query = $this->campaignsQuery($from, $to);
        if ($tag !== null && $tag !== '') {
            $query->whereJsonContains('tags', $tag);
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function campaignRow(object $c): array
    {
        $stats = ReportQueryService::brevoStats([
            'sent' => $c->sent_count, 'delivered' => $c->delivered_count, 'hard_bounces' => $c->hard_bounces,
            'soft_bounces' => $c->soft_bounces, 'complaints' => $c->complaints, 'unsubscriptions' => $c->unsubscribes,
            'opens' => $c->total_opens, 'unique_opens' => $c->unique_opens, 'proxy_opens' => $c->proxy_opens,
            'unique_clicks' => $c->unique_clicks,
        ]);

        return [
            'campaign_id' => $c->uuid,
            'name' => $c->name,
            'sent_at' => ReportQueryService::iso($c->sent_at),
            'tags' => json_decode((string) $c->tags, true) ?: [],
            'type' => $c->type ?? 'classic',
            'globalStats' => $stats,
            'rates' => ReportQueryService::rates($stats),
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     * @return list<array<string, mixed>>
     */
    public function lists(array $input): array
    {
        [$from, $to] = $this->window($input);
        if (! Schema::hasTable('campaign_stats_by_list')) {
            return [];
        }

        $sum = fn (string $c) => "COALESCE(SUM(s.{$c}),0) as {$c}";
        $rows = DB::table('campaign_stats_by_list as s')
            ->join('campaigns as c', 'c.id', '=', 's.campaign_id')
            ->join('recipient_lists as l', 'l.id', '=', 's.target_id')
            ->where('s.target_type', 'list')
            ->whereBetween('c.sent_at', [$from, $to])
            ->groupBy('l.id', 'l.uuid', 'l.name', 'l.contact_count')
            ->selectRaw('l.uuid, l.name, l.contact_count, '.implode(', ', array_map($sum, [
                'sent', 'delivered', 'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions', 'opens',
                'unique_opens', 'proxy_opens', 'unique_clicks', 'deferred',
            ])))
            ->orderBy('l.name')->get();

        return $rows->map(function (object $r): array {
            $stats = ReportQueryService::brevoStats((array) $r);

            return [
                'list_id' => $r->uuid, 'name' => $r->name, 'contact_count' => (int) $r->contact_count,
                'globalStats' => $stats, 'rates' => ReportQueryService::rates($stats),
            ];
        })->all();
    }
}
