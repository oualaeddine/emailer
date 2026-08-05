<?php

namespace App\Modules\Dashboard\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Models\QuotaLedgerEntry;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Tracking\Models\Message;
use Illuminate\Support\Carbon;

/**
 * docs/09-dashboard.md §9.2 — Widget Catalog (WP-33).
 *
 * Each widget is queried straight off its module's model rather than the
 * materialized aggregates (`mv_smtp_account_daily_stats`, Horizon metrics
 * API, ...) doc 09 describes — those aggregates/roadmap items don't exist
 * yet (docs/34-roadmap.md build order), so this reads the live tables
 * directly, mirroring `CampaignController::analytics()`'s
 * `selectRaw(...)->groupBy(...)` style. Only the four widgets explicitly
 * scoped by WP-33 are implemented: send volume, deliverability rate, SMTP
 * quota usage, and recent campaigns.
 */
class DashboardService
{
    /**
     * §9.2 "Send volume (last 7/30 days, sparkline)" — daily message count
     * for the trailing `$days` days (inclusive of today), zero-filled so
     * the frontend sparkline has one point per day even on quiet days.
     *
     * @return array{days: int, series: list<array{date: string, count: int}>}
     */
    public function sendVolume(int $days = 7): array
    {
        $start = Carbon::today()->subDays($days - 1);

        $counts = Message::query()
            ->selectRaw('DATE(queued_at) as day, count(*) as total')
            ->whereNotNull('queued_at')
            ->where('queued_at', '>=', $start)
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = [];
        for ($i = 0; $i < $days; $i++) {
            $date = $start->copy()->addDays($i)->toDateString();
            $series[] = ['date' => $date, 'count' => (int) ($counts[$date] ?? 0)];
        }

        return ['days' => $days, 'series' => $series];
    }

    /**
     * §9.2 "Delivery funnel (sent -> delivered -> opened -> clicked)" —
     * WP-33 narrows this to the single deliverability ratio the brief asks
     * for (delivered/sent). `sent_at`/`delivered_at` are stamped exactly
     * once by `Message::markStatus()` (docs/04-database-design.md §4.10),
     * so counting non-null timestamps avoids re-deriving the funnel from
     * the `status` string.
     *
     * @return array{sent: int, delivered: int, rate: float}
     */
    public function deliverability(): array
    {
        $sent = Message::query()->whereNotNull('sent_at')->count();
        $delivered = Message::query()->whereNotNull('delivered_at')->count();

        return [
            'sent' => $sent,
            'delivered' => $delivered,
            'rate' => $sent > 0 ? round($delivered / $sent, 4) : 0.0,
        ];
    }

    /**
     * §9.2 "SMTP health overview ... quota usage bars" — today's `day`
     * window usage per active account, read straight off the durable
     * `quota_ledger` reconciliation table (docs/04-database-design.md
     * §4.9) rather than `QuotaManager`'s in-request `Cache` counters,
     * which aren't queryable in aggregate without duplicating its private
     * key-naming logic. `whereDate` (rather than an exact `window_start`
     * match) tolerates whatever time-of-day the ledger writer stamps the
     * day-window row with.
     *
     * @return list<array{smtp_account_id: string, name: string, daily_quota: int|null, used_today: int, percent_used: float|null}>
     */
    public function quotaUsage(): array
    {
        $today = Carbon::today();

        $usedByAccount = QuotaLedgerEntry::query()
            ->where('window_type', 'day')
            ->whereDate('window_start', $today)
            ->pluck('sent_count', 'smtp_account_id');

        return SmtpAccount::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function (SmtpAccount $account) use ($usedByAccount): array {
                $used = (int) ($usedByAccount[$account->id] ?? 0);

                return [
                    'smtp_account_id' => $account->uuid,
                    'name' => $account->name,
                    'daily_quota' => $account->daily_quota,
                    'used_today' => $used,
                    'percent_used' => $account->daily_quota
                        ? round(min($used / $account->daily_quota, 1) * 100, 1)
                        : null,
                ];
            })
            ->all();
    }

    /**
     * §9.2 "Active campaigns" widget, broadened to the brief's "recent
     * campaigns (last N campaigns with status)" — most recently created
     * first.
     *
     * @return list<array{id: string, name: string, status: string, scheduled_at: string|null, sent_at: string|null, created_at: string|null}>
     */
    public function recentCampaigns(int $limit = 5): array
    {
        return Campaign::query()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(fn (Campaign $campaign): array => [
                'id' => $campaign->uuid,
                'name' => $campaign->name,
                'status' => $campaign->status,
                'scheduled_at' => $campaign->scheduled_at?->toIso8601String(),
                'sent_at' => $campaign->sent_at?->toIso8601String(),
                'created_at' => $campaign->created_at?->toIso8601String(),
            ])
            ->all();
    }
}
