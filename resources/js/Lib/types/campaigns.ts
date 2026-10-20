/**
 * docs/15-campaign-management.md §15.1 (lifecycle), §15.2 (wizard),
 * §15.6/§15.7 (actions), §15.8 (detail tabs).
 * docs/29-api-specification.md §29.5 — /api/v1/campaigns.
 *
 * Reconciled against the actual WP-20 backend (`CampaignStatus` enum,
 * `CampaignResource`, `CampaignController`) after both sides landed —
 * WP-23 was originally built against an assumed contract (see
 * docs/42-parallel-execution-plan.md §42.6/§42.7) that didn't match on
 * status values, the target representation, or the analytics shape.
 *
 * Status values mirror `App\Modules\Campaigns\Enums\CampaignStatus`
 * exactly (`running`/`completed`, not `sending`/`sent`) — doc 07 §7.12:
 * "status enums stay English internally, always mapped to a French
 * display label" (see the existing `campaigns.statusX` keys in `fr.ts`,
 * which the display-label mapping still uses unchanged).
 */
export type CampaignStatus = 'draft' | 'scheduled' | 'running' | 'paused' | 'completed' | 'cancelled';

/** UI-only concept for the wizard's radio choice — not sent to the API as-is; converted to `recipient_list_id`/`segment_id`. */
export type CampaignTargetType = 'list' | 'segment';

/** Mirrors `App\Modules\Campaigns\Http\Resources\CampaignResource` 1:1. */
export interface Campaign {
    id: string;
    name: string;
    status: CampaignStatus;
    template_id: string | null;
    recipient_list_id: string | null;
    segment_id: string | null;
    scheduled_at: string | null;
    sent_at: string | null;
    approved_by: string | null;
    cloned_from_campaign_id: string | null;
    created_at: string | null;
    updated_at: string | null;
}

/** GET /api/v1/campaigns/{uuid} — same resource; subject/html_body are the Content tab's source (§15.8). */
export interface CampaignDetail extends Campaign {
    subject: string | null;
    html_body: string | null;
}

/**
 * `CampaignController::analytics()` — a delivery-funnel breakdown, not the
 * `{sent, delivered, opened, clicked, bounced, complained}` shape this file
 * originally assumed. `by_status` is keyed by `App\Domain\Enums\MessageStatus`
 * values (queued/sending/accepted/delivered/opened/clicked/soft_bounced/
 * hard_bounced/failed/rejected/spam_complaint/unsubscribed).
 */
export interface CampaignAnalytics {
    targeted: number;
    by_status: Record<string, number>;
    open_count: number;
    click_count: number;
}

export interface CreateCampaignPayload {
    name: string;
    template_id?: string;
    /** Exactly one of `recipient_list_id`/`segment_id` — mirrors `StoreCampaignRequest`'s `required_without`/`prohibits` rule. */
    recipient_list_id?: string;
    segment_id?: string;
}

export interface UpdateCampaignPayload {
    name?: string;
    template_id?: string;
    recipient_list_id?: string;
    segment_id?: string;
}

// ===========================================================================
// Brevo parity v2 — docs/45-brevo-api-contracts.md §45.4.2 (WP-42 campaigns
// v2), §45.4.5 (WP-45 checklist), §45.7.2 (WP-80 A/B), §45.7.3 (WP-81).
// Everything above stays unchanged for the legacy pages; v2 types extend it.
// ===========================================================================

/**
 * `CampaignStatus` plus `suspended` (paused by the system: quota / SMTP /
 * approval). Archive is not a status (`archived_at`). Labels:
 * fr.campaigns.statusLabels.
 */
export type CampaignStatusV2 = CampaignStatus | 'suspended';

/** Read-only Brevo mapping (`brevo_status`). Labels: fr.campaigns.brevoStatusLabels. */
export type CampaignBrevoStatus =
    | 'draft'
    | 'queued'
    | 'in_review'
    | 'in_process'
    | 'sent'
    | 'suspended'
    | 'cancelling'
    | 'cancelled'
    | 'archive';

/** Campaign list tabs. Labels: fr.campaigns.tabs. */
export type CampaignStatusTab = 'all' | 'draft' | 'scheduled' | 'running' | 'sent' | 'suspended' | 'archived';

/** Labels: fr.campaigns.types. */
export type CampaignType = 'classic' | 'ab_test';

/** Labels: fr.campaigns.suspendedReasons. */
export type CampaignSuspendedReason = 'quota_exhausted' | 'smtp_unavailable' | 'approval_revoked' | 'manual';

/** Computed server-side from the state machine. Labels: fr.campaigns.actions. */
export type CampaignAction =
    | 'edit'
    | 'report'
    | 'duplicate'
    | 'test_send'
    | 'schedule'
    | 'unschedule'
    | 'send_now'
    | 'pause'
    | 'resume'
    | 'cancel'
    | 'send_to_new_contacts'
    | 'archive'
    | 'unarchive'
    | 'move'
    | 'delete';

/** The four delivery options of "Confirmer l'envoi". Labels: fr.campaignSetup.sendModes. */
export type CampaignSendMode = 'now' | 'scheduled' | 'best_time' | 'batches';

/** Labels: fr.campaignSetup.expirationUnits. */
export type EmailExpirationUnit = 'days' | 'weeks' | 'months';

/** Labels: fr.campaignSetup.smtpStrategies. */
export type SmtpStrategy = 'rotation' | 'single';

/** Labels: fr.campaignSetup.checklistStatus. */
export type CampaignChecklistStatus = 'complete' | 'incomplete' | 'blocking';

export interface CampaignSenderRef {
    id: string;
    name: string;
    email: string;
    is_verified: boolean;
}

export interface CampaignOwnerRef {
    id: string;
    name: string;
}

/** Cached counters (null before sending). Rates are ratios 0–1. */
export interface CampaignStatsSummary {
    recipients: number;
    sent: number;
    delivered: number;
    unique_opens: number;
    unique_clicks: number;
    unsubscriptions: number;
    hard_bounces: number;
    soft_bounces: number;
    complaints: number;
    open_rate: number;
    click_rate: number;
}

/** List item of GET /campaigns — superset of the legacy `Campaign` resource. */
export interface CampaignV2 extends Omit<Campaign, 'status'> {
    status: CampaignStatusV2;
    type: CampaignType;
    brevo_status: CampaignBrevoStatus;
    is_archived: boolean;
    archived_at: string | null;
    suspended_reason: CampaignSuspendedReason | null;
    folder_id: string | null;
    tags: string[];
    subject: string | null;
    preview_text: string | null;
    sender: CampaignSenderRef | null;
    editor_type: 'dnd' | 'html' | 'rich_text' | null;
    thumbnail_url: string | null;
    timezone: string | null;
    send_mode: CampaignSendMode | null;
    parent_campaign_id: string | null;
    owner: CampaignOwnerRef | null;
    stats_summary: CampaignStatsSummary | null;
    ab_test_enabled: boolean;
    available_actions: CampaignAction[];
}

export interface CampaignAudienceTarget {
    id: string;
    name: string;
    contact_count: number;
}

export interface CampaignAudience {
    lists: CampaignAudienceTarget[];
    exclusion_lists: CampaignAudienceTarget[];
    segments: CampaignAudienceTarget[];
    excluded_segments: CampaignAudienceTarget[];
    estimated_count: number | null;
    estimated_at: string | null;
    materialised_count: number | null;
}

export interface CampaignUtm {
    enabled: boolean;
    source: string | null;
    medium: string | null;
    campaign: string | null;
    content: string | null;
    term: string | null;
    id: string | null;
}

export interface CampaignAttachmentRef {
    id: string;
    filename: string;
    size: number;
}

export interface EmailExpiration {
    duration: number;
    unit: EmailExpirationUnit;
}

/** Business-hours window (weekday 0 = Sunday … 6 = Saturday, JS getDay / Carbon dayOfWeek). */
export interface CampaignSendWindow {
    days: number[];
    start: string;
    end: string;
}

export interface CampaignBatch {
    size: number;
    interval_minutes: number;
}

/** GET /campaigns/{id} — setup + design. */
export interface CampaignV2Detail extends CampaignV2 {
    html_body: string | null;
    design_json: import('@/Lib/types/editor').EmailDesign | null;
    reply_to: string | null;
    to_field: string | null;
    audience: CampaignAudience;
    utm: CampaignUtm;
    mirror_link_enabled: boolean;
    inline_images: boolean;
    attachment: CampaignAttachmentRef | null;
    email_expiration: EmailExpiration | null;
    unsubscribe_page_id: string | null;
    update_profile_form_id: string | null;
    tracking_enabled: boolean;
    smtp_strategy: SmtpStrategy;
    smtp_account_id: string | null;
    send_window: CampaignSendWindow | null;
    requires_approval: boolean;
    send_time_optimized: boolean;
    batch: CampaignBatch | null;
    ab_test: CampaignAbTest | null;
    test_sent_at: string | null;
    share_link: string | null;
    checklist_status: CampaignChecklistStatus;
}

export type CampaignSortField = 'updated_at' | 'name' | 'sent_at' | 'scheduled_at' | 'created_at';

export interface CampaignListQuery {
    status_tab?: CampaignStatusTab;
    /** Legacy filter, still honoured. */
    status?: CampaignStatusV2;
    folder_id?: string | 'none';
    tag?: string;
    type?: CampaignType;
    q?: string;
    sort?: CampaignSortField | `-${CampaignSortField}`;
    page?: number;
    per_page?: number;
}

export type CampaignStatusCounts = Record<CampaignStatusTab, number>;

/** GET /campaigns — PaginatedResponse<CampaignV2> + meta.status_counts. */
export interface CampaignListResponse {
    data: CampaignV2[];
    meta: {
        current_page: number;
        from: number | null;
        last_page: number;
        path: string;
        per_page: number;
        to: number | null;
        total: number;
        status_counts: CampaignStatusCounts;
    };
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
}

/** POST /campaigns — Brevo asks only for the internal name. */
export interface CampaignCreateInput {
    name: string;
    type?: CampaignType;
    folder_id?: string | null;
    /** Legacy keys, converted to an audience. */
    template_id?: string;
    recipient_list_id?: string;
    segment_id?: string;
}

/** Replaces the one-list-or-one-segment rule (≤ 50 targets). */
export interface CampaignAudienceInput {
    lists: string[];
    exclusion_lists: string[];
    segments: string[];
    excluded_segments: string[];
}

/** PATCH /campaigns/{id} — every key optional. */
export interface CampaignUpdateInput {
    name?: string;
    subject?: string;
    preview_text?: string | null;
    sender_id?: string;
    reply_to?: string | null;
    to_field?: string | null;
    folder_id?: string | null;
    tags?: string[];
    audience?: CampaignAudienceInput;
    utm?: Partial<CampaignUtm>;
    mirror_link_enabled?: boolean;
    inline_images?: boolean;
    attachment_id?: string | null;
    email_expiration?: EmailExpiration | null;
    unsubscribe_page_id?: string | null;
    update_profile_form_id?: string | null;
    tracking_enabled?: boolean;
    smtp_strategy?: SmtpStrategy;
    smtp_account_id?: string | null;
    send_window?: CampaignSendWindow | null;
    requires_approval?: boolean;
}

/** PUT /campaigns/{id}/design. */
export interface CampaignDesignInput {
    editor_type: 'dnd' | 'html' | 'rich_text';
    html_body: string;
    design_json: import('@/Lib/types/editor').EmailDesign | null;
}

export type CampaignDesignSourceType = 'template' | 'gallery' | 'campaign' | 'blank';

/** POST /campaigns/{id}/design/copy (template picker). */
export interface CampaignDesignCopyInput {
    source_type: CampaignDesignSourceType;
    source_id?: string | null;
}

export interface CampaignPreviewQuery {
    contact_id?: string;
    variant?: AbVariantLabel;
}

/** GET /campaigns/{id}/preview. */
export interface CampaignPreview {
    subject: string;
    preview_text: string | null;
    from: { name: string; email: string };
    to: string;
    html: string;
    contact_id: string | null;
    /** True when the contact was picked automatically from the audience (no `contact_id` requested). */
    sample?: boolean;
}

export type AudienceTargetType = 'list' | 'segment';
export type AudienceMode = 'include' | 'exclude';

export interface AudienceEstimateTarget {
    type: AudienceTargetType;
    mode: AudienceMode;
    id: string;
    name: string;
    count: number;
}

/** POST /campaigns/{id}/audience/estimate. Ratios 0–1. */
export interface AudienceEstimate {
    included: number;
    excluded: number;
    suppressed: number;
    duplicates: number;
    total: number;
    unengaged: number;
    unengaged_percent: number;
    unengaged_warning: boolean;
    by_target: AudienceEstimateTarget[];
    computed_at: string;
}

export interface AudienceEstimateInput {
    audience?: CampaignAudienceInput;
}

/** POST /campaigns/{id}/test-send (1–5 emails). */
export interface CampaignTestSendInput {
    emails: string[];
    contact_id?: string | null;
    variant?: AbVariantLabel | null;
}

export interface TestSendResult {
    queued: number;
    message_ids: string[];
    test_sent_at: string;
}

/** POST /campaigns/{id}/schedule. */
export interface CampaignScheduleInput {
    mode: CampaignSendMode;
    scheduled_at?: string | null;
    timezone?: string;
    batch_size?: number;
    batch_interval_minutes?: number;
}

export interface CampaignDuplicateInput {
    name?: string;
    folder_id?: string | null;
}

export interface CampaignSendToNewContactsInput {
    name?: string;
}

export type CampaignBulkAction = 'archive' | 'unarchive' | 'move' | 'delete';

/** POST /campaigns/bulk → BulkResult. */
export interface CampaignBulkInput {
    action: CampaignBulkAction;
    ids: string[];
    folder_id?: string | null;
}

// --- WP-45 checklist -------------------------------------------------------

/** Labels: fr.campaignSetup.checklistItems. */
export type CampaignChecklistKey =
    | 'sender_verified'
    | 'recipients_selected'
    | 'subject_filled'
    | 'design_ready'
    | 'merge_tags'
    | 'unsubscribe_link'
    | 'test_sent'
    | 'links_valid'
    | 'email_size'
    | 'spam_score';

/** Labels: fr.campaignSetup.checklistItemStatus. */
export type CampaignChecklistItemStatus = 'ok' | 'warning' | 'error' | 'pending' | 'skipped';

export interface CampaignChecklistItem {
    key: CampaignChecklistKey;
    status: CampaignChecklistItemStatus;
    blocking: boolean;
    message: string;
    details: Record<string, unknown> | null;
}

/** GET /campaigns/{id}/checklist. */
export interface CampaignChecklist {
    campaign_id: string;
    can_schedule: boolean;
    status: CampaignChecklistStatus;
    items: CampaignChecklistItem[];
    checked_at: string;
}

// --- WP-80 A/B testing -----------------------------------------------------

export type AbVariantLabel = 'A' | 'B';
/** Labels: fr.campaignSetup.abTestTypes. */
export type AbTestType = 'subject' | 'content';
/** Labels: fr.campaignSetup.abWinnerMetrics. */
export type AbWinnerMetric = 'opens' | 'clicks';
/** Labels: fr.campaignSetup.abPhases. */
export type AbTestPhase = 'not_started' | 'testing' | 'winner_picked' | 'completed';

/** `campaigns.ab_test_config` (doc 43 §43.6.2). */
export interface AbTestConfig {
    type: AbTestType;
    /** 5–50 (Brevo splitRule). */
    sample_percent: number;
    winner_metric: AbWinnerMetric;
    /** 60–10080 (Brevo winnerDelay × 60). */
    test_duration_minutes: number;
    auto_send_winner: boolean;
}

export interface AbTestVariant {
    id: string;
    label: AbVariantLabel;
    subject: string;
    preview_text: string | null;
    html_body: string | null;
    design_json: import('@/Lib/types/editor').EmailDesign | null;
    is_winner: boolean;
}

export interface AbTestVariantInput {
    label: AbVariantLabel;
    subject: string;
    preview_text?: string | null;
    html_body?: string | null;
    design_json?: import('@/Lib/types/editor').EmailDesign | null;
}

/** PUT /campaigns/{id}/ab-test. */
export interface AbTestInput {
    enabled: boolean;
    config: AbTestConfig;
    variants: AbTestVariantInput[];
}

/** CampaignV2Detail.ab_test. */
export interface CampaignAbTest {
    config: AbTestConfig;
    variants: AbTestVariant[];
    winner_variant_id: string | null;
    phase: AbTestPhase;
}

export interface AbTestPickWinnerInput {
    variant_id: string;
}

export interface AbTestVariantReport {
    variant_id: string;
    label: AbVariantLabel;
    subject: string;
    globalStats: import('@/Lib/types/reports').BrevoStats;
    rates: import('@/Lib/types/reports').RateSet;
}

/** ReportOverview.ab_test. */
export interface AbTestReport {
    config: AbTestConfig;
    phase: AbTestPhase;
    winner_variant_id: string | null;
    picked_at: string | null;
    picked_by: 'auto' | 'manual' | null;
    variants: AbTestVariantReport[];
}

// --- WP-81 send at best time -----------------------------------------------

export interface BestTimeHourBucket {
    hour: number;
    count: number;
}

/** GET /campaigns/{id}/best-time-preview. */
export interface BestTimePreview {
    contacts_with_history: number;
    contacts_fallback: number;
    fallback_hour: number;
    distribution: BestTimeHourBucket[];
    window_hours: number;
}
