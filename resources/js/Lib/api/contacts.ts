import axios from 'axios';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type { AsyncOperation, BulkResult, DataList, Folder, Uuid } from '@/Lib/types/common';
import type {
    ColumnPreferences,
    ColumnPreferencesInput,
    ContactBulkInput,
    ContactExportInput,
    ContactExportOperation,
    ContactIndexQuery,
    ContactIndexResponse,
    ContactView,
    ContactViewInput,
} from '@/Lib/types/contacts';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type { ContactList, ContactListQuery } from '@/Lib/types/lists';
import type { RuleTree, Segment, SegmentCount, SegmentInput, SegmentListQuery } from '@/Lib/types/segments';

/**
 * docs/45-brevo-api-contracts.md §45.5.4 (contacts index, views, bulk,
 * export — WP-57), §45.5.1 (GET /contact-attributes), §45.5.6
 * (GET /recipient-lists, GET /list-folders) and §45.5.2 (GET /segments,
 * POST /segments, POST /segments/count) — everything the v2 Contacts page
 * (WP-51) needs, in one module.
 */

const BASE = '/api/v1';

/** §45.1 — structured GET filters above 6 KB go through the POST …/search twin. */
const MAX_GET_FILTERS_LENGTH = 6 * 1024;

/** GET /recipients?pagination=cursor, or POST /recipients/search when the encoded filters are too long. */
export async function fetchContacts(query: ContactIndexQuery, signal?: AbortSignal): Promise<ContactIndexResponse> {
    const encodedFilters = query.filters ? JSON.stringify(query.filters) : null;

    if (encodedFilters && encodeURIComponent(encodedFilters).length > MAX_GET_FILTERS_LENGTH) {
        const response = await axios.post<ContactIndexResponse>(`${BASE}/recipients/search`, query, { signal });

        return response.data;
    }

    const params: Record<string, string | number> = { pagination: 'cursor' };
    if (query.cursor) params.cursor = query.cursor;
    if (query.per_page) params.per_page = query.per_page;
    if (query.q) params.q = query.q;
    if (query.scope) params.scope = query.scope;
    if (query.list_id) params.list_id = query.list_id;
    if (query.segment_id) params.segment_id = query.segment_id;
    if (query.view_id) params.view_id = query.view_id;
    if (encodedFilters) params.filters = encodedFilters;
    if (query.columns && query.columns.length > 0) params.columns = query.columns.join(',');
    if (query.sort) params.sort = query.sort;

    const response = await axios.get<ContactIndexResponse>(`${BASE}/recipients`, { params, signal });

    return response.data;
}

/**
 * Live count for the filter drawer: the index's `meta.total` for the current
 * scope + rule tree (one row requested). When the server could not count in
 * time (`total: null`) and no list / segment / search scope applies, falls
 * back to POST /segments/count with the bare tree.
 */
export async function countContacts(query: Omit<ContactIndexQuery, 'pagination'>, signal?: AbortSignal): Promise<number> {
    const response = await fetchContacts({ ...query, pagination: 'cursor', cursor: null, per_page: 1, columns: [] }, signal);

    if (response.meta.total !== null) {
        return response.meta.total;
    }

    if (query.filters && !query.list_id && !query.segment_id && !query.q && query.scope !== 'mine') {
        return countSegmentRules(query.filters, signal);
    }

    return response.data.length;
}

// --- Views ------------------------------------------------------------------

export async function fetchContactViews(): Promise<ContactView[]> {
    const response = await axios.get<DataList<ContactView>>(`${BASE}/contact-views`);

    return response.data.data;
}

export async function createContactView(input: ContactViewInput): Promise<ContactView> {
    const response = await axios.post<{ data: ContactView }>(`${BASE}/contact-views`, input);

    return response.data.data;
}

export async function updateContactView(id: Uuid, input: Partial<ContactViewInput>): Promise<ContactView> {
    const response = await axios.patch<{ data: ContactView }>(`${BASE}/contact-views/${id}`, input);

    return response.data.data;
}

export async function deleteContactView(id: Uuid): Promise<void> {
    await axios.delete(`${BASE}/contact-views/${id}`);
}

/** PATCH /me/column-preferences — columns of the system views ("all" / "mine"). */
export async function saveColumnPreferences(input: ColumnPreferencesInput): Promise<ColumnPreferences> {
    const response = await axios.patch<{ data: ColumnPreferences }>(`${BASE}/me/column-preferences`, input);

    return response.data.data;
}

// --- Bulk, export, operations -----------------------------------------------

export type ContactBulkResponse = { kind: 'done'; result: BulkResult } | { kind: 'queued'; operation: AsyncOperation };

/** POST /recipients/bulk → 200 BulkResult (≤ 1000 contacts) or 202 AsyncOperation. */
export async function bulkContacts(input: ContactBulkInput): Promise<ContactBulkResponse> {
    const response = await axios.post<{ data: BulkResult | AsyncOperation }>(`${BASE}/recipients/bulk`, input);

    if (response.status === 202) {
        return { kind: 'queued', operation: response.data.data as AsyncOperation };
    }

    return { kind: 'done', result: response.data.data as BulkResult };
}

/** POST /recipients/export → 202 AsyncOperation (type contacts_export). */
export async function exportContacts(input: ContactExportInput): Promise<ContactExportOperation> {
    const response = await axios.post<{ data: ContactExportOperation }>(`${BASE}/recipients/export`, input);

    return response.data.data;
}

/** GET /operations/{id} via the operation's own `status_url`. */
export async function fetchOperation<TResult = Record<string, unknown>>(
    operation: Pick<AsyncOperation, 'id' | 'status_url'>,
    signal?: AbortSignal,
): Promise<AsyncOperation<TResult>> {
    const url = operation.status_url || `${BASE}/operations/${operation.id}`;
    const response = await axios.get<{ data: AsyncOperation<TResult> }>(url, { signal });

    return response.data.data;
}

/**
 * Polls an AsyncOperation until it is completed or failed. `onUpdate` sees
 * every intermediate state (progress bar). Rejects with an AbortError when
 * `signal` aborts.
 */
export async function pollOperation<TResult = Record<string, unknown>>(
    operation: AsyncOperation<TResult>,
    onUpdate?: (operation: AsyncOperation<TResult>) => void,
    signal?: AbortSignal,
    intervalMs = 2000,
): Promise<AsyncOperation<TResult>> {
    let current = operation;

    while (current.status === 'queued' || current.status === 'running') {
        await wait(intervalMs, signal);
        current = await fetchOperation<TResult>(current, signal);
        onUpdate?.(current);
    }

    return current;
}

function wait(ms: number, signal?: AbortSignal): Promise<void> {
    return new Promise((resolve, reject) => {
        if (signal?.aborted) {
            reject(new DOMException('Aborted', 'AbortError'));
            return;
        }
        const timer = window.setTimeout(() => {
            signal?.removeEventListener('abort', onAbort);
            resolve();
        }, ms);
        function onAbort() {
            window.clearTimeout(timer);
            reject(new DOMException('Aborted', 'AbortError'));
        }
        signal?.addEventListener('abort', onAbort, { once: true });
    });
}

// --- Lookups: attributes, lists, segments, folders --------------------------

/** GET /contact-attributes — column chooser + condition builder source. */
export async function fetchContactAttributes(): Promise<ContactAttribute[]> {
    const response = await axios.get<DataList<ContactAttribute>>(`${BASE}/contact-attributes`);

    return response.data.data;
}

/** GET /recipient-lists. */
export async function fetchContactLists(query: ContactListQuery = {}, signal?: AbortSignal): Promise<PaginatedResponse<ContactList>> {
    const response = await axios.get<PaginatedResponse<ContactList>>(`${BASE}/recipient-lists`, { params: query, signal });

    return response.data;
}

/** GET /list-folders?kind=list|segment. */
export async function fetchListFolders(kind: 'list' | 'segment', signal?: AbortSignal): Promise<Folder[]> {
    const response = await axios.get<DataList<Folder>>(`${BASE}/list-folders`, { params: { kind }, signal });

    return response.data.data;
}

/** GET /segments. */
export async function fetchSegments(query: SegmentListQuery = {}, signal?: AbortSignal): Promise<PaginatedResponse<Segment>> {
    const response = await axios.get<PaginatedResponse<Segment>>(`${BASE}/segments`, { params: query, signal });

    return response.data;
}

/** POST /segments — "Enregistrer comme segment". */
export async function createSegment(input: SegmentInput): Promise<Segment> {
    const response = await axios.post<{ data: Segment }>(`${BASE}/segments`, input);

    return response.data.data;
}

/**
 * POST /segments/count — 200 SegmentCount, or 202 AsyncOperation
 * (type segment_count) polled until it completes.
 */
export async function countSegmentRules(rules: RuleTree, signal?: AbortSignal): Promise<number> {
    const response = await axios.post<{ data: SegmentCount | AsyncOperation }>(`${BASE}/segments/count`, { rules }, { signal });

    if (response.status !== 202) {
        return (response.data.data as SegmentCount).count;
    }

    const operation = await pollOperation(response.data.data as AsyncOperation, undefined, signal, 1000);
    const count = operation.result?.count;

    return typeof count === 'number' ? count : operation.processed;
}
