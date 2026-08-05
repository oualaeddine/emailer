import type { CampaignStatus } from '@/Lib/types/campaigns';

/**
 * Mirrors `App\Modules\Dashboard\Http\Controllers\DashboardController::widgets()`
 * (docs/09-dashboard.md §9.2, docs/42-parallel-execution-plan.md §42.8 —
 * WP-33). `quota_usage`/`recent_campaigns` are permission-gated server-side
 * (`smtp.view`/`campaigns.view`) and simply absent from the response for a
 * caller who lacks the relevant permission — hence optional here.
 */

export interface SendVolumePoint {
    date: string;
    count: number;
}

export interface SendVolumeWidget {
    days: number;
    series: SendVolumePoint[];
}

export interface DeliverabilityWidget {
    sent: number;
    delivered: number;
    /** Ratio (0-1), not a percentage. */
    rate: number;
}

export interface QuotaUsageEntry {
    smtp_account_id: string;
    name: string;
    daily_quota: number | null;
    used_today: number;
    /** 0-100, or `null` when the account has no configured daily quota. */
    percent_used: number | null;
}

/** Trimmed projection of `Campaign` — id/name/status/dates only (§9.2 "recent campaigns"). */
export interface RecentCampaign {
    id: string;
    name: string;
    status: CampaignStatus;
    scheduled_at: string | null;
    sent_at: string | null;
    created_at: string | null;
}

export interface DashboardWidgets {
    send_volume: SendVolumeWidget;
    deliverability: DeliverabilityWidget;
    quota_usage?: QuotaUsageEntry[];
    recent_campaigns?: RecentCampaign[];
}
