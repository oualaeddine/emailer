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
