/**
 * docs/25-reporting.md — Reporting module (WP-31).
 * Mirrors `App\Modules\Reporting\Services\ReportingService` /
 * `App\Modules\Reporting\Http\Controllers\ReportingController` 1:1.
 * `by_status` is keyed by the raw `App\Domain\Enums\MessageStatus` value
 * (English, per doc 07 §7.12 — same convention already used by
 * `CampaignAnalytics['by_status']` in Lib/types/campaigns.ts).
 */
export interface ReportingFilters {
    date_from?: string;
    date_to?: string;
    /** Campaign uuid, resolved server-side to the internal id. */
    campaign_id?: string;
    /** SmtpAccount uuid, resolved server-side to the internal id. */
    smtp_account_id?: string;
}

/** GET /api/v1/reporting/summary. */
export interface ReportingSummary {
    date_from: string;
    date_to: string;
    sent: number;
    delivered: number;
    unique_opens: number;
    unique_clicks: number;
    bounced: number;
    failed: number;
    /** Total (non-unique) opens/clicks — sums of `messages.open_count`/`click_count`. */
    open_count: number;
    click_count: number;
    by_status: Record<string, number>;
    /** 0-1 ratios, not percentages — multiply by 100 for display. */
    delivery_rate: number;
    open_rate: number;
    click_rate: number;
    bounce_rate: number;
}

/** One row of GET /api/v1/reporting/campaigns's `data` array. */
export interface ReportingCampaignRow {
    campaign_id: string | null;
    campaign_name: string;
    sent: number;
    delivered: number;
    unique_opens: number;
    unique_clicks: number;
    bounced: number;
    delivery_rate: number;
    open_rate: number;
    click_rate: number;
    bounce_rate: number;
}

/** One row of GET /api/v1/reporting/smtp-accounts's `data` array. */
export interface ReportingSmtpAccountRow {
    smtp_account_id: string | null;
    smtp_account_name: string;
    sent: number;
    delivered: number;
    bounced: number;
    delivery_rate: number;
    bounce_rate: number;
    health_status: 'healthy' | 'degraded' | 'unhealthy' | 'disabled' | null;
    is_active: boolean;
}
