<?php

namespace App\Modules\Tracking\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler;
use App\Modules\Tracking\Models\Message;
use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * docs/43 §43.4.15 (backend) / doc 45 §45.6.5, §45.8 Slot L, §45.9 (WP-70) —
 * the campaign rollup run by `RollupCampaignStatsJob` (and inline by
 * `CampaignStatsService` for campaigns below the live threshold). It is the
 * only code that walks a campaign's raw `messages` + `message_events`.
 *
 * One pass over the campaign's non-test messages (chunked by id, events
 * loaded per chunk) fills:
 * - `campaign_stats_hourly` — per UTC hour (and A/B variant, 0 = none): every total
 *   counter on the hour it happened, every *unique* counter on the hour of
 *   the message's first occurrence, so summing all hours gives the campaign's
 *   unique figures;
 * - `campaign_stats_by_list` — the same totals per included audience target
 *   (`campaign_audiences` list / segment, membership evaluated at rollup time);
 * - the cached `campaigns` counters (`sent_count`, `delivered_count`,
 *   `unique_opens`, `total_opens`, `unique_clicks`, `total_clicks`,
 *   `unsubscribes`, `hard_bounces`, `soft_bounces`, `complaints`,
 *   `proxy_opens`, `stats_updated_at`) — never `recipients_count` (WP-42's
 *   materialisation marker) nor `mirror_clicks` (WP-63);
 * - the breakdown cache (`statsByDomain`, `statsByDevice`, `statsByBrowser`,
 *   `statsByCountry`, `returnBounce`).
 *
 * Message-level definitions: sent = reached the SMTP stage (sent / delivered /
 * bounced / engaged); delivered = sent and not bounced / failed (or a
 * provider `delivered_at`); blocked = failed / rejected messages plus
 * `skipped` audience members; viewed / clicks = max(event rows,
 * `open_count` / `click_count`) so pre-WP-70 history still counts; an Apple
 * MPP open is unique-counted in `proxy_opens` when the message has proxy
 * opens only.
 */
class CampaignStatsAggregator
{
    public const COUNTERS = [
        'sent', 'delivered', 'opens', 'unique_opens', 'proxy_opens', 'clicks', 'unique_clicks',
        'hard_bounces', 'soft_bounces', 'complaints', 'unsubscriptions', 'deferred', 'blocked',
    ];

    private const CHUNK = 2000;

    private const NOT_DELIVERED = Message::NOT_DELIVERED_STATUSES;

    private const EVENT_TYPES = ['opened', 'clicked', 'unsubscribed', 'spam_complaint', 'complaint', 'deferred'];

    /** @var array<string, array<string, mixed>> */
    private array $hourly = [];

    /** @var array<string, array<string, int>> */
    private array $byTarget = [];

    /** @var array<string, array<string, int>> */
    private array $domains = [];

    /** @var array<string, array<string, array<string, int>>> */
    private array $devices = [];

    /** @var array<string, array<string, int>> */
    private array $browsers = [];

    /** @var array<string, array<string, int>> */
    private array $countries = [];

    private int $returnBounce = 0;

    public static function breakdownCacheKey(int $campaignId): string
    {
        return "campaign-stats:{$campaignId}:breakdowns";
    }

    /**
     * Recomputes every rollup of the campaign and returns the breakdowns
     * stored in the cache.
     *
     * @return array{statsByDomain: array<string, mixed>, statsByDevice: array<string, mixed>, statsByBrowser: array<string, mixed>, statsByCountry: array<string, mixed>, returnBounce: int, computed_at: string}
     */
    public function rollup(Campaign $campaign): array
    {
        // Concurrent rollups of one campaign (hourly fan-out, completion job,
        // inline refresh) are serialised: each one deletes and rewrites the
        // campaign's rollup rows.
        return Cache::lock('stats-rollup:'.$campaign->id, 1800)
            ->block(60, fn (): array => $this->compute($campaign));
    }

    /**
     * @return array{statsByDomain: array<string, mixed>, statsByDevice: array<string, mixed>, statsByBrowser: array<string, mixed>, statsByCountry: array<string, mixed>, returnBounce: int, computed_at: string}
     */
    private function compute(Campaign $campaign): array
    {
        $this->reset();
        $targets = $this->includeTargets($campaign);

        DB::table('messages as m')
            ->leftJoin('recipients as r', 'r.id', '=', 'm.recipient_id')
            ->leftJoin('campaign_recipients as cr', function ($join): void {
                $join->on('cr.message_id', '=', 'm.id')->on('cr.campaign_id', '=', 'm.campaign_id');
            })
            ->where('m.campaign_id', $campaign->id)
            ->where('m.is_test', false)
            ->select([
                'm.id', 'm.recipient_id', 'm.status', 'm.sent_at', 'm.delivered_at', 'm.opened_at',
                'm.clicked_at', 'm.bounced_at', 'm.failed_at', 'm.updated_at', 'm.open_count',
                'm.click_count', 'r.email', 'cr.variant_id',
            ])
            ->chunkById(self::CHUNK, function (Collection $rows) use ($targets): void {
                $this->consume($rows, $targets);
            }, 'm.id', 'id');

        // Audience members skipped at materialisation (suppressed / blocklisted).
        DB::table('campaign_recipients')
            ->where('campaign_id', $campaign->id)
            ->where('status', 'skipped')
            ->select(['id', 'recipient_id', 'variant_id', 'materialised_at'])
            ->chunkById(self::CHUNK, function (Collection $rows) use ($targets): void {
                $membership = $this->membership($targets, $rows->pluck('recipient_id')->all());
                foreach ($rows as $row) {
                    $this->addHourly($row->materialised_at, $row->variant_id, 'blocked');
                    foreach ($membership[$row->recipient_id] ?? [] as $targetKey) {
                        $this->byTarget[$targetKey]['blocked']++;
                    }
                }
            });

        $breakdowns = [
            'statsByDomain' => $this->domainStats(),
            'statsByDevice' => $this->deviceStats(),
            'statsByBrowser' => $this->sortBreakdown($this->browsers),
            'statsByCountry' => $this->sortBreakdown($this->countries),
            'returnBounce' => $this->returnBounce,
            'computed_at' => now()->toIso8601ZuluString(),
        ];

        $this->persist($campaign, $targets);

        Cache::put(self::breakdownCacheKey($campaign->id), $breakdowns, (int) config('tracking.stats.breakdown_ttl_seconds', 604800));

        $this->reset();

        return $breakdowns;
    }

    /**
     * @param  Collection<int, object>  $rows
     * @param  array<string, array{type: string, id: int, query: Closure|null}>  $targets
     */
    private function consume(Collection $rows, array $targets): void
    {
        $events = DB::table('message_events')
            ->whereIn('message_id', $rows->pluck('id')->all())
            ->whereIn('event_type', self::EVENT_TYPES)
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get(['message_id', 'event_type', 'occurred_at', 'is_proxy_open', 'device_type', 'email_client', 'ip_country', 'metadata'])
            ->groupBy('message_id');

        $membership = $this->membership($targets, $rows->pluck('recipient_id')->unique()->all());

        foreach ($rows as $m) {
            $totals = $this->consumeMessage($m, $events->get($m->id, collect()));

            foreach ($membership[$m->recipient_id] ?? [] as $targetKey) {
                foreach ($totals as $counter => $n) {
                    $this->byTarget[$targetKey][$counter] += $n;
                }
            }
        }
    }

    /**
     * @param  Collection<int, object>  $events
     * @return array<string, int> this message's totals per counter
     */
    private function consumeMessage(object $m, Collection $events): array
    {
        $totals = array_fill_keys(self::COUNTERS, 0);
        $variant = $m->variant_id;
        $add = function (mixed $at, string $counter, int $n = 1) use (&$totals, $variant): void {
            if ($n <= 0) {
                return;
            }
            $this->addHourly($at, $variant, $counter, $n);
            $totals[$counter] += $n;
        };

        $status = (string) $m->status;
        $sentAt = $m->sent_at ?? $m->delivered_at ?? $m->bounced_at ?? $m->opened_at ?? $m->clicked_at;
        $sent = $sentAt !== null;
        $delivered = $m->delivered_at !== null || ($sent && ! in_array($status, self::NOT_DELIVERED, true));

        if ($sent) {
            $add($sentAt, 'sent');
        }
        if ($delivered) {
            $add($m->delivered_at ?? $m->sent_at ?? $m->opened_at ?? $m->clicked_at ?? $sentAt, 'delivered');
        }
        if ($status === 'hard_bounced') {
            $add($m->bounced_at ?? $m->updated_at, 'hard_bounces');
        }
        if ($status === 'soft_bounced') {
            $add($m->bounced_at ?? $m->updated_at, 'soft_bounces');
        }
        if ($m->delivered_at !== null && in_array($status, ['hard_bounced', 'soft_bounced'], true)) {
            $this->returnBounce++;
        }
        if (in_array($status, ['failed', 'rejected'], true)) {
            $add($m->failed_at ?? $m->updated_at, 'blocked');
        }

        $byType = $events->groupBy('event_type');

        // Opens.
        $opens = $byType->get('opened', collect());
        foreach ($opens as $event) {
            $add($event->occurred_at, 'opens');
        }
        $firstOpenAt = $m->opened_at ?? $opens->first()?->occurred_at;
        $add($firstOpenAt, 'opens', max(0, (int) $m->open_count - $opens->count()) ?: ($opens->isEmpty() && $m->opened_at !== null ? 1 : 0));
        if ($firstOpenAt !== null) {
            $add($firstOpenAt, 'unique_opens');
            if ($opens->isNotEmpty() && $opens->every(fn (object $e): bool => (bool) $e->is_proxy_open)) {
                $add($firstOpenAt, 'proxy_opens');
            }
        }

        // Clicks.
        $clicks = $byType->get('clicked', collect());
        foreach ($clicks as $event) {
            $add($event->occurred_at, 'clicks');
        }
        $firstClickAt = $m->clicked_at ?? $clicks->first()?->occurred_at;
        $add($firstClickAt, 'clicks', max(0, (int) $m->click_count - $clicks->count()) ?: ($clicks->isEmpty() && $m->clicked_at !== null ? 1 : 0));
        if ($firstClickAt !== null) {
            $add($firstClickAt, 'unique_clicks');
        }

        // Unsubscriptions, complaints, deferrals (once per message).
        $unsubscribed = $byType->get('unsubscribed')?->first();
        if ($unsubscribed !== null || $status === 'unsubscribed') {
            $add($unsubscribed?->occurred_at ?? $m->updated_at, 'unsubscriptions');
        }
        $complaint = $byType->get('spam_complaint')?->first() ?? $byType->get('complaint')?->first();
        if ($complaint !== null || $status === 'spam_complaint') {
            $add($complaint?->occurred_at ?? $m->updated_at, 'complaints');
        }
        $deferred = $byType->get('deferred')?->first();
        if ($deferred !== null) {
            $add($deferred->occurred_at, 'deferred');
        }

        $this->collectBreakdowns($m, $totals, $opens, $clicks);

        return $totals;
    }

    /**
     * @param  array<string, int>  $totals
     * @param  Collection<int, object>  $opens
     * @param  Collection<int, object>  $clicks
     */
    private function collectBreakdowns(object $m, array $totals, Collection $opens, Collection $clicks): void
    {
        $email = (string) ($m->email ?? '');
        $at = strrpos($email, '@');
        $domain = $at !== false ? mb_strtolower(substr($email, $at + 1)) : '';
        $domain = $domain !== '' ? $domain : 'unknown';

        $d = $this->domains[$domain] ??= [
            'sent' => 0, 'delivered' => 0, 'hardBounces' => 0, 'softBounces' => 0, 'complaints' => 0,
            'unsubscriptions' => 0, 'uniqueViews' => 0, 'uniqueClicks' => 0,
        ];
        $d['sent'] += $totals['sent'];
        $d['delivered'] += $totals['delivered'];
        $d['hardBounces'] += $totals['hard_bounces'];
        $d['softBounces'] += $totals['soft_bounces'];
        $d['complaints'] += $totals['complaints'];
        $d['unsubscriptions'] += $totals['unsubscriptions'];
        $d['uniqueViews'] += $totals['unique_opens'];
        $d['uniqueClicks'] += $totals['unique_clicks'];
        $this->domains[$domain] = $d;

        $seen = [];
        foreach ([['viewed', 'uniqueViews', $opens], ['clickers', 'uniqueClicks', $clicks]] as [$totalKey, $uniqueKey, $events]) {
            foreach ($events as $event) {
                $meta = is_string($event->metadata) ? (json_decode($event->metadata, true) ?: []) : (array) ($event->metadata ?? []);
                $device = in_array($event->device_type, ['desktop', 'mobile', 'tablet'], true) ? $event->device_type : 'unknown';
                $client = $event->email_client !== null && $event->email_client !== '' ? (string) $event->email_client : 'Unknown';
                $inner = isset($meta['os']) && is_string($meta['os']) && $meta['os'] !== '' ? $meta['os'] : $client;
                $country = $event->ip_country !== null && $event->ip_country !== '' ? strtoupper((string) $event->ip_country) : 'unknown';

                $slots = [
                    'd|'.$device.'|'.$inner => &$this->devices[$device][$inner],
                    'b|'.$client => &$this->browsers[$client],
                    'c|'.$country => &$this->countries[$country],
                ];
                foreach ($slots as $slotKey => &$slot) {
                    $slot ??= ['clickers' => 0, 'uniqueClicks' => 0, 'viewed' => 0, 'uniqueViews' => 0];
                    if ($totalKey === 'viewed') {
                        $slot['viewed']++;
                    }
                    if (! isset($seen[$uniqueKey][$slotKey])) {
                        $seen[$uniqueKey][$slotKey] = true;
                        $slot[$uniqueKey]++;
                        if ($uniqueKey === 'uniqueClicks') {
                            $slot['clickers']++;
                        }
                    }
                }
                unset($slot, $slots);
            }
        }
    }

    private function addHourly(mixed $at, mixed $variantId, string $counter, int $n = 1): void
    {
        if ($at === null || $n <= 0) {
            return;
        }

        $hour = Carbon::parse($at)->utc()->startOfHour()->format('Y-m-d H:i:s');
        // `variant_id` 0 = no A/B variant (NOT NULL so the unique key holds).
        $variant = $variantId !== null ? (int) $variantId : 0;
        $key = $hour.'|'.$variant;

        $this->hourly[$key] ??= ['hour' => $hour, 'variant_id' => $variant] + array_fill_keys(self::COUNTERS, 0);
        $this->hourly[$key][$counter] += $n;
    }

    /**
     * Included audience targets of the campaign (`campaign_audiences`,
     * mode `include`), keyed `type:id`. Segments carry a compiled recipient
     * query when the WP-54 rule-tree compiler can evaluate them.
     *
     * @return array<string, array{type: string, id: int, query: Closure|null}>
     */
    private function includeTargets(Campaign $campaign): array
    {
        $targets = [];
        $rows = DB::table('campaign_audiences')
            ->where('campaign_id', $campaign->id)
            ->where('mode', 'include')
            ->whereIn('target_type', ['list', 'segment'])
            ->get(['target_type', 'target_id']);

        foreach ($rows as $row) {
            $key = $row->target_type.':'.$row->target_id;
            $targets[$key] = [
                'type' => $row->target_type,
                'id' => (int) $row->target_id,
                'query' => $row->target_type === 'segment' ? $this->segmentQuery((int) $row->target_id) : null,
            ];
            $this->byTarget[$key] = array_fill_keys(self::COUNTERS, 0);
        }

        return $targets;
    }

    private function segmentQuery(int $segmentId): ?Closure
    {
        if (! app()->bound(RuleTreeCompiler::class)) {
            return null;
        }

        $segment = DB::table('segments')->where('id', $segmentId)->first(['rules', 'rules_version']);
        if ($segment === null) {
            return null;
        }

        try {
            $tree = is_string($segment->rules) ? json_decode($segment->rules, true) : (array) $segment->rules;
            if (! is_array($tree)) {
                return null;
            }
            $compiler = app(RuleTreeCompiler::class);
            if ((int) ($segment->rules_version ?? 2) === 1) {
                $tree = $compiler->upgrade($tree);
            }

            return fn () => $compiler->apply(Recipient::query(), $tree);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, array{type: string, id: int, query: Closure|null}>  $targets
     * @param  list<int|string|null>  $recipientIds
     * @return array<int, list<string>> recipient id => target keys
     */
    private function membership(array $targets, array $recipientIds): array
    {
        $recipientIds = array_values(array_filter($recipientIds, fn ($id): bool => $id !== null));
        if ($targets === [] || $recipientIds === []) {
            return [];
        }

        $map = [];
        $listIds = collect($targets)->where('type', 'list')->pluck('id')->all();
        if ($listIds !== []) {
            DB::table('recipient_list_recipient')
                ->whereIn('recipient_list_id', $listIds)
                ->whereIn('recipient_id', $recipientIds)
                ->get(['recipient_list_id', 'recipient_id'])
                ->each(function (object $row) use (&$map): void {
                    $map[(int) $row->recipient_id][] = 'list:'.$row->recipient_list_id;
                });
        }

        foreach ($targets as $key => $target) {
            if ($target['type'] !== 'segment' || $target['query'] === null) {
                continue;
            }
            try {
                ($target['query'])()
                    ->whereIn('recipients.id', $recipientIds)
                    ->pluck('recipients.id')
                    ->each(function ($id) use (&$map, $key): void {
                        $map[(int) $id][] = $key;
                    });
            } catch (Throwable) {
                // An unevaluable segment simply gets no per-target rollup.
            }
        }

        return $map;
    }

    /**
     * @param  array<string, array{type: string, id: int, query: Closure|null}>  $targets
     */
    private function persist(Campaign $campaign, array $targets): void
    {
        $now = now();
        $sum = array_fill_keys(self::COUNTERS, 0);
        foreach ($this->hourly as $row) {
            foreach (self::COUNTERS as $counter) {
                $sum[$counter] += $row[$counter];
            }
        }

        DB::transaction(function () use ($campaign, $targets, $now, $sum): void {
            DB::table('campaign_stats_hourly')->where('campaign_id', $campaign->id)->delete();
            $hourly = array_map(fn (array $row): array => ['campaign_id' => $campaign->id, 'created_at' => $now, 'updated_at' => $now] + $row, array_values($this->hourly));
            foreach (array_chunk($hourly, 500) as $chunk) {
                DB::table('campaign_stats_hourly')->insert($chunk);
            }

            DB::table('campaign_stats_by_list')->where('campaign_id', $campaign->id)->delete();
            $byList = [];
            foreach ($targets as $key => $target) {
                $byList[] = [
                    'campaign_id' => $campaign->id,
                    'target_type' => $target['type'],
                    'target_id' => $target['id'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ] + $this->byTarget[$key];
            }
            foreach (array_chunk($byList, 500) as $chunk) {
                DB::table('campaign_stats_by_list')->insert($chunk);
            }

            DB::table('campaigns')->where('id', $campaign->id)->update([
                'sent_count' => $sum['sent'],
                'delivered_count' => $sum['delivered'],
                'unique_opens' => $sum['unique_opens'],
                'total_opens' => $sum['opens'],
                'unique_clicks' => $sum['unique_clicks'],
                'total_clicks' => $sum['clicks'],
                'unsubscribes' => $sum['unsubscriptions'],
                'hard_bounces' => $sum['hard_bounces'],
                'soft_bounces' => $sum['soft_bounces'],
                'complaints' => $sum['complaints'],
                'proxy_opens' => $sum['proxy_opens'],
                'stats_updated_at' => $now,
            ]);
        });

        $campaign->forceFill([
            'sent_count' => $sum['sent'],
            'delivered_count' => $sum['delivered'],
            'unique_opens' => $sum['unique_opens'],
            'total_opens' => $sum['opens'],
            'unique_clicks' => $sum['unique_clicks'],
            'total_clicks' => $sum['clicks'],
            'unsubscribes' => $sum['unsubscriptions'],
            'hard_bounces' => $sum['hard_bounces'],
            'soft_bounces' => $sum['soft_bounces'],
            'complaints' => $sum['complaints'],
            'proxy_opens' => $sum['proxy_opens'],
            'stats_updated_at' => $now,
        ])->syncOriginal();
    }

    /**
     * @return array<string, array<string, int>>
     */
    private function domainStats(): array
    {
        $domains = $this->domains;
        uasort($domains, fn (array $a, array $b): int => [$b['sent'], $b['uniqueViews']] <=> [$a['sent'], $a['uniqueViews']]);

        return array_slice($domains, 0, max(1, (int) config('tracking.stats.domain_limit', 100)), true);
    }

    /**
     * @return array<string, array<string, array<string, int>>>
     */
    private function deviceStats(): array
    {
        $out = [];
        foreach (['desktop', 'mobile', 'tablet', 'unknown'] as $device) {
            $out[$device] = $this->sortBreakdown($this->devices[$device] ?? []);
        }

        return $out;
    }

    /**
     * @param  array<string, array<string, int>>  $rows
     * @return array<string, array<string, int>>
     */
    private function sortBreakdown(array $rows): array
    {
        uasort($rows, fn (array $a, array $b): int => [$b['uniqueViews'], $b['uniqueClicks']] <=> [$a['uniqueViews'], $a['uniqueClicks']]);

        return array_map(fn (array $r): array => [
            'clickers' => $r['clickers'], 'uniqueClicks' => $r['uniqueClicks'],
            'viewed' => $r['viewed'], 'uniqueViews' => $r['uniqueViews'],
        ], $rows);
    }

    private function reset(): void
    {
        $this->hourly = [];
        $this->byTarget = [];
        $this->domains = [];
        $this->devices = [];
        $this->browsers = [];
        $this->countries = [];
        $this->returnBounce = 0;
    }
}
