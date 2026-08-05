/**
 * docs/15-campaign-management.md §15.1 (lifecycle), §15.2 (wizard),
 * §15.6/§15.7 (actions), §15.8 (detail tabs).
 * docs/29-api-specification.md §29.5 — /api/v1/campaigns.
 *
 * Status values here follow the existing resources/js/Lib/i18n/fr.ts
 * `campaigns.statusX` keys (draft/scheduled/sending/sent/paused/cancelled)
 * rather than doc 15.1's raw state-machine names (draft/scheduled/running/
 * paused/completed/cancelled) — `sending` === `running`, `sent` === `completed`.
 */
export type CampaignStatus = 'draft' | 'scheduled' | 'sending' | 'sent' | 'paused' | 'cancelled';

/** A campaign targets exactly one of: an existing recipient list, or a segment. */
export type CampaignTargetType = 'list' | 'segment';

export interface CampaignTemplateRef {
    id: string;
    name: string;
}

export interface Campaign {
    id: string;
    name: string;
    status: CampaignStatus;
    template: CampaignTemplateRef | null;
    template_id: string | null;
    target_type: CampaignTargetType;
    target_id: string | null;
    target_name: string | null;
    audience_size: number | null;
    scheduled_at: string | null;
    sent_at: string | null;
    approved_by: string | null;
    cloned_from_campaign_id: string | null;
    created_at: string;
    updated_at: string;
}

export interface CampaignRecipient {
    id: string;
    recipient_id: string;
    email: string;
    message_status: string | null;
}

export interface CampaignAnalytics {
    sent: number;
    delivered: number;
    opened: number;
    clicked: number;
    bounced: number;
    complained: number;
}

/** GET /api/v1/campaigns/{uuid} — adds the Content/Recipients/Analytics tab data (§15.8). */
export interface CampaignDetail extends Campaign {
    subject: string | null;
    html_body: string | null;
    recipients?: CampaignRecipient[];
    analytics?: CampaignAnalytics;
}

export interface CreateCampaignPayload {
    name: string;
    template_id: string;
    target_type: CampaignTargetType;
    target_id: string;
}

export interface UpdateCampaignPayload {
    name?: string;
    template_id?: string;
    target_type?: CampaignTargetType;
    target_id?: string;
    scheduled_at?: string | null;
}
