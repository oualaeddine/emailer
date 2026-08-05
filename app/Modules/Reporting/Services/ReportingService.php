<?php

namespace App\Modules\Reporting\Services;

use App\Modules\Campaigns\Models\Campaign;
use App\Modules\DeliveryEngine\Models\SmtpAccount;
use App\Modules\Tracking\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * docs/25-reporting.md §25.1/§25.2 — cross-campaign, per-SMTP-account,
 * time-windowed aggregates against `messages`, mirroring
 * `App\Modules\Campaigns\Http\Controllers\CampaignController::analytics()`'s
 * `selectRaw('status, count(*) as total')->groupBy('status')` aggregation
 * style rather than a different approach.
 *
 * The materialized views described in docs/04-database-design.md §4.13
 * (`mv_campaign_daily_stats` etc.) are out of this work package's scope
 * (no migration for them exists yet in this build wave) — every figure
 * here is a live aggregate query against `messages`, which doc 25.1
 * explicitly allows as the fallback for "not-yet-materialized" data.
 *
 * `COUNT(<nullable timestamp column>)` is used throughout instead of
 * re-deriving funnel counts from the raw `status` column: `messages.status`
 * holds only the *current* status (docs/04-database-design.md §4.10), so a
 * message that has been opened is no longer counted as "delivered" if the
 * funnel were built from `status` alone. The `*_at` timestamp columns are
 * each stamped once and never cleared (`Message::markStatus()`), so
 * `COUNT(delivered_at)` etc. gives true cumulative funnel counts. This
 * still keeps the `by_status` snapshot breakdown available via the exact
 * `selectRaw('status, count(*) as total')->groupBy('status')` precedent for
 * callers that want the raw current-status distribution instead.
 */
class ReportingService
{
    /**
     * @param  array{date_from?: string|null, date_to?: string|null, campaign_id?: int|null, smtp_account_id?: int|null}  $filters
     * @return array<string, mixed>
     */
    public function summary(array $filters): array
    {
        $totals = $this->baseQuery($filters)
            ->selectRaw(
                'COUNT(*) as sent, '.
                'COUNT(delivered_at) as delivered, '.
                'COUNT(opened_at) as unique_opens, '.
                'COUNT(clicked_at) as unique_clicks, '.
                'COUNT(bounced_at) as bounced, '.
                'COUNT(failed_at) as failed, '.
                'COALESCE(SUM(open_count), 0) as open_count, '.
                'COALESCE(SUM(click_count), 0) as click_count',
            )
            ->first();

        $byStatus = $this->baseQuery($filters)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $sent = (int) $totals->sent;
        $delivered = (int) $totals->delivered;
        $uniqueOpens = (int) $totals->unique_opens;
        $uniqueClicks = (int) $totals->unique_clicks;
        $bounced = (int) $totals->bounced;

        return [
            'date_from' => $filters['date_from'] ?? null,
            'date_to' => $filters['date_to'] ?? null,
            'sent' => $sent,
            'delivered' => $delivered,
            'unique_opens' => $uniqueOpens,
            'unique_clicks' => $uniqueClicks,
            'bounced' => $bounced,
            'failed' => (int) $totals->failed,
            'open_count' => (int) $totals->open_count,
            'click_count' => (int) $totals->click_count,
            'by_status' => $byStatus,
            'delivery_rate' => $this->rate($delivered, $sent),
            'open_rate' => $this->rate($uniqueOpens, $delivered),
            'click_rate' => $this->rate($uniqueClicks, $delivered),
            'bounce_rate' => $this->rate($bounced, $sent),
        ];
    }

    /**
     * docs/25-reporting.md §25.2 — Campaign Comparison Report (a reduced,
     * live-query version; no chart/trend line, per this work package's
     * "no complex charting library" scope).
     *
     * @param  array{date_from?: string|null, date_to?: string|null, campaign_id?: int|null, smtp_account_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function byCampaign(array $filters): Collection
    {
        $rows = $this->baseQuery($filters)
            ->whereNotNull('campaign_id')
            ->selectRaw(
                'campaign_id, '.
                'COUNT(*) as sent, '.
                'COUNT(delivered_at) as delivered, '.
                'COUNT(opened_at) as unique_opens, '.
                'COUNT(clicked_at) as unique_clicks, '.
                'COUNT(bounced_at) as bounced',
            )
            ->groupBy('campaign_id')
            ->orderByDesc('sent')
            ->limit(50)
            ->get();

        $campaigns = Campaign::query()
            ->whereIn('id', $rows->pluck('campaign_id'))
            ->get(['id', 'uuid', 'name'])
            ->keyBy('id');

        return $rows->map(function (Message $row) use ($campaigns): array {
            $campaign = $campaigns->get($row->campaign_id);
            $sent = (int) $row->sent;
            $delivered = (int) $row->delivered;
            $uniqueOpens = (int) $row->unique_opens;
            $uniqueClicks = (int) $row->unique_clicks;
            $bounced = (int) $row->bounced;

            return [
                'campaign_id' => $campaign?->uuid,
                'campaign_name' => $campaign?->name ?? '—',
                'sent' => $sent,
                'delivered' => $delivered,
                'unique_opens' => $uniqueOpens,
                'unique_clicks' => $uniqueClicks,
                'bounced' => $bounced,
                'delivery_rate' => $this->rate($delivered, $sent),
                'open_rate' => $this->rate($uniqueOpens, $delivered),
                'click_rate' => $this->rate($uniqueClicks, $delivered),
                'bounce_rate' => $this->rate($bounced, $sent),
            ];
        })->values();
    }

    /**
     * docs/25-reporting.md §25.2 — SMTP Performance Report (volume +
     * deliverability + current health status; quota-utilization trend and
     * health-status *history* are out of scope here, no such history table
     * exists yet).
     *
     * @param  array{date_from?: string|null, date_to?: string|null, campaign_id?: int|null, smtp_account_id?: int|null}  $filters
     * @return Collection<int, array<string, mixed>>
     */
    public function bySmtpAccount(array $filters): Collection
    {
        $rows = $this->baseQuery($filters)
            ->whereNotNull('smtp_account_id')
            ->selectRaw(
                'smtp_account_id, '.
                'COUNT(*) as sent, '.
                'COUNT(delivered_at) as delivered, '.
                'COUNT(bounced_at) as bounced',
            )
            ->groupBy('smtp_account_id')
            ->orderByDesc('sent')
            ->limit(50)
            ->get();

        $accounts = SmtpAccount::query()
            ->withTrashed()
            ->whereIn('id', $rows->pluck('smtp_account_id'))
            ->get(['id', 'uuid', 'name', 'health_status', 'bounce_rate', 'complaint_rate', 'is_active'])
            ->keyBy('id');

        return $rows->map(function (Message $row) use ($accounts): array {
            $account = $accounts->get($row->smtp_account_id);
            $sent = (int) $row->sent;
            $delivered = (int) $row->delivered;
            $bounced = (int) $row->bounced;

            return [
                'smtp_account_id' => $account?->uuid,
                'smtp_account_name' => $account?->name ?? '—',
                'sent' => $sent,
                'delivered' => $delivered,
                'bounced' => $bounced,
                'delivery_rate' => $this->rate($delivered, $sent),
                'bounce_rate' => $this->rate($bounced, $sent),
                'health_status' => $account?->health_status,
                'is_active' => $account?->is_active ?? false,
            ];
        })->values();
    }

    /**
     * @param  array{date_from?: string|null, date_to?: string|null, campaign_id?: int|null, smtp_account_id?: int|null}  $filters
     * @return Builder<Message>
     */
    private function baseQuery(array $filters): Builder
    {
        // Filtered on `queued_at` (message's entry into the pipeline),
        // mirroring the existing `date_from`/`date_to` precedent in
        // App\Modules\Tracking\Services\MessageService::applyFilters().
        return Message::query()
            ->when(
                $filters['date_from'] ?? null,
                fn (Builder $query, string $date): Builder => $query->whereDate('queued_at', '>=', $date),
            )
            ->when(
                $filters['date_to'] ?? null,
                fn (Builder $query, string $date): Builder => $query->whereDate('queued_at', '<=', $date),
            )
            ->when(
                $filters['campaign_id'] ?? null,
                fn (Builder $query, int $id): Builder => $query->where('campaign_id', $id),
            )
            ->when(
                $filters['smtp_account_id'] ?? null,
                fn (Builder $query, int $id): Builder => $query->where('smtp_account_id', $id),
            );
    }

    private function rate(int $numerator, int $denominator): float
    {
        return $denominator > 0 ? round($numerator / $denominator, 4) : 0.0;
    }
}
