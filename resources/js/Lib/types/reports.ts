/**
 * docs/45-brevo-api-contracts.md §45.3 (Brevo statistics objects) and
 * §45.6.5 (WP-72 campaign report: GET /campaigns/{id}/report/{tab},
 * report/contacts, report/segment, report/export, /campaigns/compare).
 *
 * Objects reproducing Brevo's statistics model keep Brevo's camelCase keys
 * verbatim; everything else is snake_case. snake_case rates are ratios 0–1;
 * `opensRate` is a Brevo percentage 0–100.
 */
import type { AbTestReport, CampaignBrevoStatus, CampaignStatusV2, CampaignType } from '@/Lib/types/campaigns';
import type { IsoDateTime, UserRef, Uuid } from '@/Lib/types/common';

export interface BrevoStats {
    sent: number;
    delivered: number;
    hardBounces: number;
    softBounces: number;
    complaints: number;
    unsubscriptions: number;
    viewed: number;
    uniqueViews: number;
    trackableViews: number;
    estimatedViews: number;
    appleMppOpens: number;
    uniqueClicks: number;
    clickers: number;
    deferred: number;
    returnBounce: number;
    /** Percentage 0–100 (uniqueViews / delivered * 100). */
    opensRate: number;
}

/** One item of Brevo's `campaignStats` (per targeted list or segment). */
export interface BrevoCampaignStatsEntry extends BrevoStats {
    listId: Uuid | null;
    segmentId: Uuid | null;
    name: string;
}

export interface BrevoEngagementBreakdown {
    clickers: number;
    uniqueClicks: number;
    viewed: number;
    uniqueViews: number;
}

export interface BrevoDomainStats {
    sent: number;
    delivered: number;
    hardBounces: number;
    softBounces: number;
    complaints: number;
    unsubscriptions: number;
    uniqueViews: number;
    uniqueClicks: number;
}

/** `message_events.device_type`. Labels: fr.campaignReport.devices. */
export type DeviceType = 'desktop' | 'mobile' | 'tablet' | 'unknown';

/** Brevo `statsByDevice`: device → client/OS → breakdown. */
export type BrevoStatsByDevice = Record<DeviceType, Record<string, BrevoEngagementBreakdown>>;
/** Brevo `statsByBrowser`: browser / mail client → breakdown. */
export type BrevoStatsByBrowser = Record<string, BrevoEngagementBreakdown>;
/** Our addition, same casing: ISO-3166 alpha-2 (or `unknown`) → breakdown. */
export type BrevoStatsByCountry = Record<string, BrevoEngagementBreakdown>;
/** Brevo `statsByDomain`: recipient domain → stats. */
export type BrevoStatsByDomain = Record<string, BrevoDomainStats>;
/** Brevo `linksStats`: url → total clicks. */
export type BrevoLinksStats = Record<string, number>;

/** snake_case ratios 0–1 computed from a BrevoStats. */
export interface RateSet {
    delivery_rate: number;
    open_rate: number;
    click_rate: number;
    click_to_open_rate: number;
    unsubscribe_rate: number;
    bounce_rate: number;
    complaint_rate: number;
}

export type ReportTab = 'overview' | 'deliverability' | 'opens' | 'clicks' | 'unsubscriptions';

export type ReportGranularity = 'hour' | 'day' | 'week' | 'month';

export interface ReportTimeseriesPoint {
    t: IsoDateTime;
    sent: number;
    delivered: number;
    viewed: number;
    uniqueViews: number;
    uniqueClicks: number;
    unsubscriptions: number;
    hardBounces: number;
    softBounces: number;
    complaints: number;
}

export interface ReportTimeseries {
    granularity: ReportGranularity;
    points: ReportTimeseriesPoint[];
}

export interface ReportCampaignHeader {
    id: Uuid;
    name: string;
    subject: string;
    preview_text: string | null;
    status: CampaignStatusV2;
    brevo_status: CampaignBrevoStatus;
    sender: { name: string | null; email: string | null } | null;
    sent_at: IsoDateTime | null;
    completed_at: IsoDateTime | null;
    tags: string[];
    type: CampaignType;
    thumbnail_url: string | null;
    share_link: string | null;
}

export interface AudienceTargetRef {
    id: Uuid;
    name: string;
    contact_count: number;
}

export interface ReportAudience {
    lists: AudienceTargetRef[];
    exclusion_lists: AudienceTargetRef[];
    segments: AudienceTargetRef[];
    excluded_segments: AudienceTargetRef[];
    recipients: number;
}

/** Labels: fr.campaignReport.timelineActions. */
export type ReportTimelineAction =
    | 'created'
    | 'scheduled'
    | 'unscheduled'
    | 'sent'
    | 'paused'
    | 'resumed'
    | 'suspended'
    | 'cancelled'
    | 'completed'
    | 'ab_winner_picked'
    | 'archived'
    | 'unarchived'
    | 'test_sent';

export interface ReportTimelineItem {
    action: ReportTimelineAction;
    occurred_at: IsoDateTime;
    actor: UserRef | null;
    details: Record<string, unknown> | null;
}

export interface ReportLinkRow {
    id: Uuid;
    /** Matches the `data-link-id` attribute injected in the HTML (click map). */
    link_id: string;
    url: string;
    label: string | null;
    unique_clicks: number;
    total_clicks: number;
    /** Share of all clicks, 0–1. */
    share: number;
}

export interface ReportOverview {
    campaign: ReportCampaignHeader;
    globalStats: BrevoStats;
    rates: RateSet;
    averages: RateSet;
    mirrorClick: number;
    /** Still to dispatch or queued (not `recipients - sent`: skipped recipients are reported apart). */
    remaining: number;
    /** Audience members skipped at dispatch (liste noire). */
    skipped: number;
    timeseries: ReportTimeseries;
    campaignStats: BrevoCampaignStatsEntry[];
    audience: ReportAudience;
    timeline: ReportTimelineItem[];
    links: ReportLinkRow[];
    statsByDevice: BrevoStatsByDevice;
    ab_test: AbTestReport | null;
    apple_mpp_note: boolean;
}

export type BounceType = 'hard' | 'soft';

export interface BounceReasonRow {
    type: BounceType;
    reason: string;
    count: number;
}

export interface ReportSmtpAccountRow {
    smtp_account_id: Uuid;
    name: string;
    sent: number;
    delivered: number;
    hardBounces: number;
    softBounces: number;
    deferred: number;
}

export interface ReportDeliverability {
    campaign: ReportCampaignHeader;
    globalStats: BrevoStats;
    rates: RateSet;
    blocked_by_suppression: number;
    queued: number;
    bounce_reasons: BounceReasonRow[];
    by_smtp_account: ReportSmtpAccountRow[];
    statsByDomain: BrevoStatsByDomain;
}

export interface ReportHourOfDayRow {
    hour: number;
    uniqueViews: number;
}

export interface ReportOpens {
    campaign: ReportCampaignHeader;
    viewed: number;
    uniqueViews: number;
    trackableViews: number;
    estimatedViews: number;
    appleMppOpens: number;
    opensRate: number;
    averages: RateSet;
    timeseries: ReportTimeseries;
    by_hour_of_day: ReportHourOfDayRow[];
    statsByDomain: BrevoStatsByDomain;
    statsByDevice: BrevoStatsByDevice;
    statsByBrowser: BrevoStatsByBrowser;
    statsByCountry: BrevoStatsByCountry;
}

export interface ReportClicks {
    campaign: ReportCampaignHeader;
    uniqueClicks: number;
    clickers: number;
    total_clicks: number;
    click_rate: number;
    click_to_open_rate: number;
    averages: RateSet;
    linksStats: BrevoLinksStats;
    links: ReportLinkRow[];
    timeseries: ReportTimeseries;
    statsByDevice: BrevoStatsByDevice;
    statsByBrowser: BrevoStatsByBrowser;
}

export interface ReportUnsubscribeByList {
    list_id: Uuid;
    name: string;
    unsubscriptions: number;
    complaints: number;
}

export interface ReportUnsubscribeReason {
    reason: string;
    count: number;
}

/** Labels: fr.campaignReport.contactMetrics. */
export type ReportContactMetric =
    | 'sent'
    | 'delivered'
    | 'opened'
    | 'not_opened'
    | 'clicked'
    | 'not_clicked'
    | 'soft_bounced'
    | 'hard_bounced'
    | 'unsubscribed'
    | 'complained'
    | 'deferred'
    | 'blocked';

export interface ReportContactRow {
    recipient_id: Uuid | null;
    email: string;
    first_name: string | null;
    last_name: string | null;
    occurred_at: IsoDateTime | null;
    count: number;
    url: string | null;
    reason: string | null;
    device_type: DeviceType | null;
    email_client: string | null;
    ip_country: string | null;
}

export interface ReportUnsubscriptions {
    campaign: ReportCampaignHeader;
    unsubscriptions: number;
    complaints: number;
    unsubscribe_rate: number;
    complaint_rate: number;
    by_list: ReportUnsubscribeByList[];
    reasons: ReportUnsubscribeReason[];
    recent: ReportContactRow[];
}

/** Maps a tab to its payload type (GET /campaigns/{id}/report/{tab}). */
export interface ReportTabPayloads {
    overview: ReportOverview;
    deliverability: ReportDeliverability;
    opens: ReportOpens;
    clicks: ReportClicks;
    unsubscriptions: ReportUnsubscriptions;
}

export interface ReportContactsQuery {
    metric: ReportContactMetric;
    link_id?: string;
    list_id?: Uuid;
    cursor?: string;
    per_page?: number;
}

export type ReportSegmentMetric =
    | 'opened'
    | 'not_opened'
    | 'clicked'
    | 'not_clicked'
    | 'unsubscribed'
    | 'hard_bounced'
    | 'soft_bounced';

/** POST /campaigns/{id}/report/segment → 201 Segment. */
export interface ReportSegmentInput {
    metric: ReportSegmentMetric;
    link_id?: string | null;
    name?: string;
    folder_id?: Uuid | null;
}

/** GET /campaigns/compare?ids=… */
export interface CampaignComparisonRow {
    campaign: ReportCampaignHeader;
    globalStats: BrevoStats;
    rates: RateSet;
}
