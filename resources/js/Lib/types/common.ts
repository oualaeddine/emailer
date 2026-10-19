/**
 * docs/45-brevo-api-contracts.md §45.1 / §45.3 — shared shapes used by every
 * Brevo-parity resource. Frozen by WP-40 (doc 44 §44.12).
 */

/** A row `uuid` as exposed by the API (internal bigint ids never leave the server). */
export type Uuid = string;
/** ISO-8601 UTC timestamp, e.g. `2026-10-19T09:30:00Z`. */
export type IsoDateTime = string;
/** Date-only value `YYYY-MM-DD`. */
export type IsoDate = string;

/** Small non-paginated list envelope: `{ "data": [ … ] }`. */
export interface DataList<T> {
    data: T[];
}

/** Single-resource envelope: `{ "data": { … } }`. */
export interface DataItem<T> {
    data: T;
}

export interface UserRef {
    id: Uuid;
    name: string;
}

export type FolderKind = 'campaign' | 'template' | 'list' | 'segment';

/** campaign_folders, template_folders, list_folders (kind list|segment). */
export interface Folder {
    id: Uuid;
    name: string;
    kind: FolderKind;
    items_count: number;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface FolderInput {
    name: string;
}

/** POST /list-folders additionally takes the kind. */
export interface ListFolderInput extends FolderInput {
    kind?: Extract<FolderKind, 'list' | 'segment'>;
}

export type AsyncOperationStatus = 'queued' | 'running' | 'completed' | 'failed';

export type AsyncOperationType =
    | 'contacts_export'
    | 'contacts_bulk'
    | 'list_delete'
    | 'segment_count'
    | 'report_export'
    | 'template_thumbnail';

/** Export-type operations put this in `result`. */
export interface AsyncExportResult {
    download_url: string;
    expires_at: IsoDateTime;
    row_count: number;
}

/** Every non domain-specific 202 response; polled at `status_url` (GET /operations/{id}). */
export interface AsyncOperation<TResult = Record<string, unknown>> {
    id: Uuid;
    type: AsyncOperationType;
    status: AsyncOperationStatus;
    progress: number;
    total: number;
    processed: number;
    failed: number;
    result: TResult | null;
    error: string | null;
    status_url: string;
    created_at: IsoDateTime;
    completed_at: IsoDateTime | null;
}

export interface BulkSkippedItem {
    id: Uuid;
    reason: string;
}

/** Synchronous bulk responses. */
export interface BulkResult {
    processed: number;
    skipped: BulkSkippedItem[];
    failed: number;
}

/** `sort=field` / `sort=-field`. */
export type SortParam<F extends string> = F | `-${F}`;

/** Visual settings shared by unsubscribe pages, update-profile forms and subscription forms (§45.6.6). */
export interface PageDesign {
    logo_url: string | null;
    primary_color: string;
    background_color: string;
    text_color: string;
    font_family: string;
    button_text_color: string;
    border_radius: number;
}
