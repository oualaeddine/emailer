/**
 * docs/45-brevo-api-contracts.md §45.3 (ContactSelection), §45.5.4 (WP-57
 * contacts index, views, bulk, export) and §45.5.5 (WP-52 contact detail +
 * timeline). The legacy `Recipient` type (Lib/types/recipients.ts) stays the
 * shape of the page-based GET /recipients used by the current pages.
 */
import type { ContactAttributeValue, ContactAttributeValues } from '@/Lib/types/attributes';
import type { AsyncOperation, AsyncExportResult, IsoDateTime, UserRef, Uuid } from '@/Lib/types/common';
import type { RecipientNote, Tag } from '@/Lib/types/recipients';
import type { DeviceType } from '@/Lib/types/reports';
import type { FieldKey, RuleTree } from '@/Lib/types/segments';
import type { SuppressionReason } from '@/Lib/types/suppression';

/** Labels: fr.contacts.statusLabels (existing values). */
export type ContactStatus = 'active' | 'suppressed' | 'invalid';

/** Labels: fr.contacts.sourceLabels. */
export type ContactSource = 'manual' | 'csv_import' | 'excel_import' | 'pagejaunes' | 'internal_contact' | 'form' | 'api';

/** Labels: fr.contactDetail.subscriptionStatus. */
export type ContactSubscriptionStatus = 'subscribed' | 'unsubscribed' | 'blocklisted' | 'pending_confirmation';

/** Index scope: "Tous les contacts" / "Mes contacts". */
export type ContactScope = 'all' | 'mine';

/** Row of the v2 contacts index; base of ContactDetail. */
export interface Contact {
    id: Uuid;
    email: string;
    first_name: string | null;
    last_name: string | null;
    company_name: string | null;
    ext_id: string | null;
    source: ContactSource | string;
    status: ContactStatus | string;
    blocklisted: boolean;
    blocklisted_at: IsoDateTime | null;
    opt_in_at: IsoDateTime | null;
    opt_in_source: string | null;
    last_opened_at: IsoDateTime | null;
    last_clicked_at: IsoDateTime | null;
    engagement_score: number | null;
    /** Only the requested attribute columns (all of them in ContactDetail). */
    attributes: ContactAttributeValues;
    /** Only when the "lists" column is requested. */
    list_ids?: Uuid[];
    tags: Tag[];
    created_by: UserRef | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

/** Column keys: any FieldKey usable as a column, plus "lists" and "tags". */
export type ContactColumnKey = FieldKey | 'lists' | 'tags';

export interface ContactColumn {
    key: ContactColumnKey;
    width?: number;
}

export interface ContactIndexQuery {
    pagination: 'cursor';
    cursor?: string | null;
    per_page?: number;
    q?: string;
    scope?: ContactScope;
    list_id?: Uuid | null;
    segment_id?: Uuid | null;
    view_id?: Uuid | null;
    /** GET: URL-encoded JSON; POST /recipients/search: JSON. */
    filters?: RuleTree | null;
    /** GET: comma-separated. */
    columns?: ContactColumnKey[];
    sort?: string;
}

/** GET /recipients?pagination=cursor and POST /recipients/search. */
export interface ContactIndexResponse {
    data: Contact[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        path: string;
        per_page: number;
        next_cursor: string | null;
        prev_cursor: string | null;
        /** null when counting exceeds 10 s. */
        total: number | null;
        columns: ContactColumnKey[];
    };
}

/** System views have id "all" / "mine". */
export type SystemContactViewId = 'all' | 'mine';

export interface ContactView {
    id: Uuid | SystemContactViewId;
    name: string;
    is_system: boolean;
    is_shared: boolean;
    filters: RuleTree | null;
    list_id: Uuid | null;
    segment_id: Uuid | null;
    columns: ContactColumn[];
    sort: string | null;
    owner: UserRef | null;
    created_at: IsoDateTime | null;
    updated_at: IsoDateTime | null;
}

export interface ContactViewInput {
    name: string;
    is_shared?: boolean;
    filters?: RuleTree | null;
    list_id?: Uuid | null;
    segment_id?: Uuid | null;
    columns?: ContactColumn[];
    sort?: string | null;
}

export interface ContactSelectionAllMatching {
    q?: string;
    list_id?: Uuid | null;
    segment_id?: Uuid | null;
    view_id?: Uuid | null;
    filters?: RuleTree | null;
    scope?: ContactScope;
}

/** Exactly one of `ids` (1–1000) / `all_matching`. */
export type ContactSelection =
    | { ids: Uuid[]; all_matching?: never; exclude_ids?: never }
    | { ids?: never; all_matching: ContactSelectionAllMatching; exclude_ids?: Uuid[] };

/** Labels: fr.contacts.bulkActions. */
export type ContactBulkAction =
    | 'add_to_list'
    | 'remove_from_list'
    | 'blocklist'
    | 'unblocklist'
    | 'update_attribute'
    | 'delete';

export interface ContactBulkParams {
    list_id?: Uuid;
    attribute?: string;
    value?: ContactAttributeValue;
    reason?: SuppressionReason;
}

/** POST /recipients/bulk → 200 BulkResult (≤ 1000) or 202 AsyncOperation. */
export interface ContactBulkInput {
    action: ContactBulkAction;
    selection: ContactSelection;
    params?: ContactBulkParams;
}

export type ContactExportFormat = 'csv' | 'xlsx';

/** POST /recipients/export → 202 AsyncOperation<AsyncExportResult>. */
export interface ContactExportInput {
    selection: ContactSelection;
    columns: ContactColumnKey[];
    format: ContactExportFormat;
}

export type ContactExportOperation = AsyncOperation<AsyncExportResult>;

/** PATCH /me/column-preferences. */
export interface ColumnPreferencesInput {
    page: string;
    columns: ContactColumn[];
}

export interface ColumnPreferences {
    page: string;
    columns: ContactColumn[];
}

// --- WP-52 contact detail ---------------------------------------------------

export interface ContactListMembership {
    id: Uuid;
    name: string;
    added_at: IsoDateTime | null;
}

export interface ContactCounters {
    messages_sent: number;
    delivered: number;
    opened: number;
    clicked: number;
    hard_bounces: number;
    soft_bounces: number;
    complaints: number;
    unsubscriptions: number;
}

/** GET /recipients/{id} (legacy Recipient keys kept). */
export interface ContactDetail extends Contact {
    custom_fields: Record<string, unknown> | null;
    notes: RecipientNote[];
    lists: ContactListMembership[];
    counters: ContactCounters;
    subscription_status: ContactSubscriptionStatus;
    pagejaunes_external_id: number | null;
    last_contacted_at: IsoDateTime | null;
}

/** PATCH /recipients/{id} — legacy fields + attributes. */
export interface ContactUpdateInput {
    email?: string;
    first_name?: string | null;
    last_name?: string | null;
    company_name?: string | null;
    ext_id?: string | null;
    attributes?: ContactAttributeValues;
}

/** Labels: fr.contactDetail.timelineTypes. */
export type TimelineEventType =
    | 'created'
    | 'sent'
    | 'delivered'
    | 'opened'
    | 'clicked'
    | 'soft_bounce'
    | 'hard_bounce'
    | 'deferred'
    | 'blocked'
    | 'unsubscribed'
    | 'complaint'
    | 'imported'
    | 'list_added'
    | 'list_removed'
    | 'attribute_updated'
    | 'form_submitted'
    | 'opt_in_confirmed'
    | 'blocklisted'
    | 'unblocklisted'
    | 'automation_entered'
    | 'note_added';

export interface TimelineEntry {
    id: string;
    type: TimelineEventType;
    occurred_at: IsoDateTime;
    campaign: { id: Uuid; name: string } | null;
    message_id: Uuid | null;
    subject: string | null;
    list: { id: Uuid; name: string } | null;
    import_job_id: Uuid | null;
    form_id: Uuid | null;
    automation_id: Uuid | null;
    url: string | null;
    device_type: DeviceType | null;
    email_client: string | null;
    ip_country: string | null;
    reason: string | null;
    actor: UserRef | null;
}

export interface TimelineQuery {
    cursor?: string;
    per_page?: number;
    types?: TimelineEventType[];
}
