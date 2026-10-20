<?php

namespace App\Modules\DeliveryEngine\Scheduling\Services;

use App\Modules\Campaigns\Contracts\AudienceResolver;
use App\Modules\Campaigns\Enums\CampaignRecipientStatus;
use App\Modules\Campaigns\Models\Campaign;
use App\Modules\Campaigns\Models\CampaignRecipient;
use App\Modules\DeliveryEngine\Scheduling\Contracts\BestSendTimeService as BestSendTimeContract;
use App\Modules\Recipients\Models\Recipient;
use App\Modules\Tracking\Models\MessageEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * docs/43 §43.6.4 / docs/45 §45.7.3 — WP-81 send at best time.
 *
 * Per contact: hour of week with the most (non-proxy) opens over the
 * contact's last 10 campaigns (precomputed nightly into
 * `recipients.best_send_hour` by `ComputeBestSendHoursJob`, computed live
 * when missing or stale). Fallbacks: the account-wide best hour of day over
 * the last 90 days (cached), then the send time. The release time is the
 * next occurrence of that hour of day within 24 h of the send time.
 * Ties are broken by the lowest hour (deterministic).
 */
class BestSendTimeService implements BestSendTimeContract
{
    public const ACCOUNT_HOUR_CACHE_KEY = 'best_send_time:account_hour';

    /** A precomputed `best_send_hour` older than this is recomputed live. */
    public const STALE_AFTER_HOURS = 48;

    public const CHUNK = 500;

    public function __construct(private readonly AudienceResolver $audience) {}

    public function plan(Campaign $campaign): void
    {
        $start = $this->windowStart($campaign);
        $accountHour = $this->accountBestHour();

        CampaignRecipient::query()
            ->where('campaign_id', $campaign->id)
            ->where('status', CampaignRecipientStatus::Pending->value)
            ->select(['id', 'recipient_id'])
            ->chunkById(self::CHUNK, function ($rows) use ($start, $accountHour): void {
                $hours = $this->resolveHours($rows->pluck('recipient_id')->unique()->values()->all());

                $byRelease = [];
                foreach ($rows as $row) {
                    $hourOfWeek = $hours[$row->recipient_id] ?? null;
                    $hourOfDay = $hourOfWeek !== null ? $hourOfWeek % 24 : $accountHour;
                    $release = $hourOfDay !== null ? $this->releaseAt($hourOfDay, $start) : $start->copy()->utc();
                    $byRelease[$release->format('Y-m-d H:i:s')][] = $row->id;
                }

                foreach ($byRelease as $release => $ids) {
                    CampaignRecipient::query()->whereIn('id', $ids)
                        ->update(['scheduled_for' => Carbon::parse($release, 'UTC')]);
                }
            });
    }

    public function bestHourFor(Recipient $recipient): ?int
    {
        return $this->computeHours([$recipient->id])[$recipient->id] ?? null;
    }

    public function accountBestHour(): ?int
    {
        $cached = Cache::get(self::ACCOUNT_HOUR_CACHE_KEY);
        if (is_array($cached) && array_key_exists('hour', $cached)) {
            return $cached['hour'];
        }

        return $this->refreshAccountBestHour();
    }

    /**
     * Recomputes the account-wide best hour and caches it (nightly job).
     */
    public function refreshAccountBestHour(): ?int
    {
        $hour = $this->computeAccountBestHour();
        Cache::put(self::ACCOUNT_HOUR_CACHE_KEY, ['hour' => $hour, 'computed_at' => now()->toIso8601ZuluString()], now()->addHours(26));

        return $hour;
    }

    public function explain(Campaign $campaign): array
    {
        $start = $this->windowStart($campaign);
        $accountHour = $this->accountBestHour();
        $startHour = (int) $start->copy()->setTimezone(self::ACCOUNT_TIMEZONE)->hour;

        $recipientIds = $campaign->isAudienceMaterialised()
            ? CampaignRecipient::query()->where('campaign_id', $campaign->id)
                ->where('status', CampaignRecipientStatus::Pending->value)->pluck('recipient_id')->all()
            : $this->audience->recipientIds($campaign);

        $withHistory = 0;
        $fallback = 0;
        $distribution = [];

        foreach (array_chunk(array_values(array_unique($recipientIds)), self::CHUNK) as $chunk) {
            $hours = $this->resolveHours($chunk);
            foreach ($chunk as $id) {
                $hourOfWeek = $hours[$id] ?? null;
                if ($hourOfWeek !== null) {
                    $withHistory++;
                    $hour = $hourOfWeek % 24;
                } else {
                    $fallback++;
                    $hour = $accountHour ?? $startHour;
                }
                $distribution[$hour] = ($distribution[$hour] ?? 0) + 1;
            }
        }

        ksort($distribution);

        return [
            'contacts_with_history' => $withHistory,
            'contacts_fallback' => $fallback,
            'fallback_hour' => $accountHour ?? $startHour,
            'distribution' => array_map(
                fn (int $hour, int $count): array => ['hour' => $hour, 'count' => $count],
                array_keys($distribution),
                array_values($distribution),
            ),
            'window_hours' => self::WINDOW_HOURS,
        ];
    }

    /**
     * Nightly precompute: every contact with at least one open (or a stored
     * hour to clear) gets `best_send_hour` + `best_send_hour_computed_at`.
     *
     * @return int contacts updated
     */
    public function precompute(): int
    {
        $updated = 0;
        $computedAt = now();

        Recipient::query()
            ->select('recipients.id')
            ->where(fn ($q) => $q->whereNotNull('best_send_hour')->orWhereExists(
                fn ($e) => $e->selectRaw('1')->from('message_events')
                    ->whereColumn('message_events.recipient_id', 'recipients.id')
                    ->where('message_events.event_type', MessageEvent::OPENED)
                    ->where('message_events.is_proxy_open', false),
            ))
            ->chunkById(self::CHUNK, function ($recipients) use (&$updated, $computedAt): void {
                $ids = $recipients->pluck('id')->all();
                $hours = $this->computeHours($ids);

                $byHour = [];
                foreach ($ids as $id) {
                    $byHour[$hours[$id] ?? 'null'][] = $id;
                }

                foreach ($byHour as $hour => $group) {
                    DB::table('recipients')->whereIn('id', $group)->update([
                        'best_send_hour' => $hour === 'null' ? null : (int) $hour,
                        'best_send_hour_computed_at' => $computedAt,
                    ]);
                }

                $updated += count($ids);
            }, 'recipients.id', 'id');

        return $updated;
    }

    /**
     * Next start of `$hourOfDay` (account timezone) at or after `$start`;
     * when `$start` already lies inside that hour the message leaves at
     * `$start`. Always within [start, start + WINDOW_HOURS).
     */
    public function releaseAt(int $hourOfDay, CarbonInterface $start): Carbon
    {
        $startUtc = Carbon::instance($start)->utc();
        $local = $startUtc->copy()->setTimezone(self::ACCOUNT_TIMEZONE);

        if ($local->hour === $hourOfDay) {
            return $startUtc;
        }

        $slot = $local->copy()->setTime($hourOfDay, 0, 0);
        if ($slot->lt($local)) {
            $slot->addDay();
        }

        $slot = $slot->utc();
        $limit = $startUtc->copy()->addHours(self::WINDOW_HOURS);

        return $slot->gte($limit) ? $startUtc : $slot;
    }

    /**
     * Future `scheduled_at`, otherwise now.
     */
    public function windowStart(Campaign $campaign): Carbon
    {
        $scheduledAt = $campaign->scheduled_at;

        return $scheduledAt !== null && $scheduledAt->isFuture()
            ? Carbon::instance($scheduledAt)->utc()
            : now()->utc();
    }

    /**
     * Fresh precomputed hours, live computation for the others.
     *
     * @param  list<int>  $recipientIds
     * @return array<int, int|null>
     */
    private function resolveHours(array $recipientIds): array
    {
        if ($recipientIds === []) {
            return [];
        }

        $freshAfter = now()->subHours(self::STALE_AFTER_HOURS);
        $hours = [];
        $missing = [];

        $rows = DB::table('recipients')->whereIn('id', $recipientIds)
            ->get(['id', 'best_send_hour', 'best_send_hour_computed_at']);

        foreach ($rows as $row) {
            if ($row->best_send_hour_computed_at !== null && Carbon::parse($row->best_send_hour_computed_at)->gte($freshAfter)) {
                $hours[(int) $row->id] = $row->best_send_hour === null ? null : (int) $row->best_send_hour;
            } else {
                $missing[] = (int) $row->id;
            }
        }

        return $missing === [] ? $hours : $hours + $this->computeHours($missing);
    }

    /**
     * Live computation of the best hour of week per contact.
     *
     * @param  list<int>  $recipientIds
     * @return array<int, int|null>
     */
    private function computeHours(array $recipientIds): array
    {
        $result = array_fill_keys($recipientIds, null);
        if ($recipientIds === []) {
            return $result;
        }

        // Last N campaigns per contact, ordered by their latest message.
        $campaigns = DB::table('messages')
            ->whereIn('recipient_id', $recipientIds)
            ->whereNotNull('campaign_id')
            ->where('is_test', false)
            ->groupBy('recipient_id', 'campaign_id')
            ->selectRaw('recipient_id, campaign_id, MAX(id) AS last_message_id')
            ->get();

        $recent = [];
        foreach ($campaigns->sortByDesc('last_message_id')->groupBy('recipient_id') as $recipientId => $rows) {
            $recent[(int) $recipientId] = array_flip(
                $rows->take(self::HISTORY_CAMPAIGNS)->pluck('campaign_id')->map(fn ($id) => (int) $id)->all(),
            );
        }

        if ($recent === []) {
            return $result;
        }

        $campaignIds = array_values(array_unique(array_merge(...array_map('array_keys', array_values($recent)))));

        $opens = DB::table('message_events')
            ->join('messages', 'messages.id', '=', 'message_events.message_id')
            ->where('message_events.event_type', MessageEvent::OPENED)
            ->where('message_events.is_proxy_open', false)
            ->whereIn('messages.recipient_id', array_keys($recent))
            ->whereIn('messages.campaign_id', $campaignIds)
            ->where('messages.is_test', false)
            ->get(['messages.recipient_id', 'messages.campaign_id', 'message_events.occurred_at']);

        $counts = [];
        foreach ($opens as $open) {
            $recipientId = (int) $open->recipient_id;
            if (! isset($recent[$recipientId][(int) $open->campaign_id]) || $open->occurred_at === null) {
                continue;
            }
            $how = self::hourOfWeek(Carbon::parse($open->occurred_at, 'UTC'));
            $counts[$recipientId][$how] = ($counts[$recipientId][$how] ?? 0) + 1;
        }

        foreach ($counts as $recipientId => $histogram) {
            $result[$recipientId] = self::argmax($histogram);
        }

        return $result;
    }

    private function computeAccountBestHour(): ?int
    {
        $hourExpression = match (DB::connection()->getDriverName()) {
            'pgsql' => 'CAST(EXTRACT(HOUR FROM message_events.occurred_at) AS INTEGER)',
            'sqlite' => "CAST(strftime('%H', message_events.occurred_at) AS INTEGER)",
            'sqlsrv' => 'DATEPART(HOUR, message_events.occurred_at)',
            default => 'HOUR(message_events.occurred_at)',
        };

        $rows = DB::table('message_events')
            ->join('messages', 'messages.id', '=', 'message_events.message_id')
            ->where('message_events.event_type', MessageEvent::OPENED)
            ->where('message_events.is_proxy_open', false)
            ->whereNotNull('messages.campaign_id')
            ->where('messages.is_test', false)
            ->where('message_events.occurred_at', '>=', now()->subDays(self::ACCOUNT_LOOKBACK_DAYS))
            ->groupByRaw($hourExpression)
            ->selectRaw("{$hourExpression} AS utc_hour, COUNT(*) AS opens")
            ->get();

        if ($rows->isEmpty()) {
            return null;
        }

        // Stored timestamps are UTC (config app.timezone); shift to the account zone.
        $offsetHours = intdiv(now()->setTimezone(self::ACCOUNT_TIMEZONE)->getOffset(), 3600);
        $histogram = [];
        foreach ($rows as $row) {
            $local = ((((int) $row->utc_hour) + $offsetHours) % 24 + 24) % 24;
            $histogram[$local] = ($histogram[$local] ?? 0) + (int) $row->opens;
        }

        return self::argmax($histogram);
    }

    public static function hourOfWeek(CarbonInterface $at): int
    {
        $local = Carbon::instance($at)->setTimezone(self::ACCOUNT_TIMEZONE);

        return ($local->dayOfWeekIso - 1) * 24 + $local->hour;
    }

    /**
     * @param  array<int, int>  $histogram
     */
    private static function argmax(array $histogram): ?int
    {
        $best = null;
        $bestCount = 0;
        ksort($histogram);
        foreach ($histogram as $key => $count) {
            if ($count > $bestCount) {
                $best = (int) $key;
                $bestCount = $count;
            }
        }

        return $best;
    }
}
