import axios from 'axios';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type { CursorPaginatedResponse } from '@/Lib/types/audit';
import type { CampaignListResponse } from '@/Lib/types/campaigns';
import type { AsyncOperation, DataItem, DataList, Folder, ListFolderInput, Uuid } from '@/Lib/types/common';
import type { Contact, ContactColumnKey } from '@/Lib/types/contacts';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type { ContactList } from '@/Lib/types/lists';
import type { ConditionOption, RuleTree, Segment, SegmentCount, SegmentInput, SegmentListQuery } from '@/Lib/types/segments';

/**
 * docs/45-brevo-api-contracts.md §45.5.2 — Segments v2 (/api/v1/segments,
 * /api/v1/list-folders?kind=segment) plus the read-only lookups the segment
 * builder needs: §45.5.1 GET /contact-attributes (field catalog), §45.5.6
 * GET /recipient-lists (list conditions), §45.4.2 GET /campaigns
 * (engagement conditions).
 */

const BASE = '/api/v1';
const OPERATION_POLL_MS = 1000;
const OPERATION_TIMEOUT_MS = 120_000;
const LOOKUP_PAGE_SIZE = 20;

// ---------------------------------------------------------------------------
// Segments
// ---------------------------------------------------------------------------

export async function fetchSegments(query: SegmentListQuery = {}): Promise<PaginatedResponse<Segment>> {
    const response = await axios.get<PaginatedResponse<Segment>>(`${BASE}/segments`, { params: query });

    return response.data;
}

export async function fetchSegment(id: Uuid): Promise<Segment> {
    const response = await axios.get<DataItem<Segment>>(`${BASE}/segments/${id}`);

    return response.data.data;
}

export async function createSegment(input: SegmentInput): Promise<Segment> {
    const response = await axios.post<DataItem<Segment>>(`${BASE}/segments`, input);

    return response.data.data;
}

export async function updateSegment(id: Uuid, input: Partial<SegmentInput>): Promise<Segment> {
    const response = await axios.patch<DataItem<Segment>>(`${BASE}/segments/${id}`, input);

    return response.data.data;
}

/** `409` when used by a scheduled/running campaign or an active automation. */
export async function deleteSegment(id: Uuid): Promise<void> {
    await axios.delete(`${BASE}/segments/${id}`);
}

export async function duplicateSegment(id: Uuid, name?: string): Promise<Segment> {
    const response = await axios.post<DataItem<Segment>>(`${BASE}/segments/${id}/duplicate`, name ? { name } : {});

    return response.data.data;
}

function sleep(ms: number, signal?: AbortSignal): Promise<void> {
    return new Promise((resolve, reject) => {
        const timer = window.setTimeout(resolve, ms);
        signal?.addEventListener(
            'abort',
            () => {
                window.clearTimeout(timer);
                reject(new DOMException('Aborted', 'AbortError'));
            },
            { once: true },
        );
    });
}

/**
 * A `202 {data: AsyncOperation}` (type `segment_count`) is polled at its
 * `status_url` until completed; its `result` is read as a SegmentCount.
 */
async function resolveCount(status: number, body: DataItem<SegmentCount | AsyncOperation>, signal?: AbortSignal): Promise<SegmentCount> {
    if (status !== 202) {
        return body.data as SegmentCount;
    }
    let operation = body.data as AsyncOperation;
    const started = Date.now();
    while (operation.status === 'queued' || operation.status === 'running') {
        if (Date.now() - started > OPERATION_TIMEOUT_MS) {
            throw new Error('segment_count timeout');
        }
        await sleep(OPERATION_POLL_MS, signal);
        const response = await axios.get<DataItem<AsyncOperation>>(operation.status_url, { signal });
        operation = response.data.data;
    }
    if (operation.status === 'failed' || !operation.result) {
        throw new Error(operation.error ?? 'segment_count failed');
    }
    const result = operation.result as Partial<SegmentCount>;

    return {
        count: Number(result.count ?? 0),
        computed_at: result.computed_at ?? operation.completed_at ?? new Date().toISOString(),
        duration_ms: Number(result.duration_ms ?? 0),
    };
}

/** POST /segments/count — counts an unsaved tree (not persisted). */
export async function countSegmentRules(rules: RuleTree, signal?: AbortSignal): Promise<SegmentCount> {
    const response = await axios.post<DataItem<SegmentCount | AsyncOperation>>(`${BASE}/segments/count`, { rules }, { signal });

    return resolveCount(response.status, response.data, signal);
}

/** POST /segments/{id}/count — refreshes the cached `contact_count`. */
export async function refreshSegmentCount(id: Uuid, signal?: AbortSignal): Promise<SegmentCount> {
    const response = await axios.post<DataItem<SegmentCount | AsyncOperation>>(`${BASE}/segments/${id}/count`, {}, { signal });

    return resolveCount(response.status, response.data, signal);
}

export interface SegmentMembersQuery {
    cursor?: string | null;
    per_page?: number;
    columns?: ContactColumnKey[];
}

export async function fetchSegmentMembers(id: Uuid, query: SegmentMembersQuery = {}): Promise<CursorPaginatedResponse<Contact>> {
    const params: Record<string, string | number> = {};
    if (query.cursor) params.cursor = query.cursor;
    if (query.per_page) params.per_page = query.per_page;
    if (query.columns && query.columns.length > 0) params.columns = query.columns.join(',');
    const response = await axios.get<CursorPaginatedResponse<Contact>>(`${BASE}/segments/${id}/members`, { params });

    return response.data;
}

// ---------------------------------------------------------------------------
// Segment folders (list_folders, kind = segment)
// ---------------------------------------------------------------------------

export async function fetchSegmentFolders(): Promise<Folder[]> {
    const response = await axios.get<DataList<Folder>>(`${BASE}/list-folders`, { params: { kind: 'segment' } });

    return response.data.data;
}

export async function createSegmentFolder(name: string): Promise<Folder> {
    const input: ListFolderInput = { name, kind: 'segment' };
    const response = await axios.post<DataItem<Folder>>(`${BASE}/list-folders`, input);

    return response.data.data;
}

// ---------------------------------------------------------------------------
// Builder lookups (field catalog + value pickers)
// ---------------------------------------------------------------------------

/** §45.5.1 GET /contact-attributes — source of the `attribute.<NAME>` fields. */
export async function fetchConditionAttributes(): Promise<ContactAttribute[]> {
    const response = await axios.get<DataList<ContactAttribute>>(`${BASE}/contact-attributes`);

    return response.data.data;
}

/** §45.5.6 GET /recipient-lists — options of `list` conditions. */
export async function searchListOptions(q: string): Promise<ConditionOption[]> {
    const response = await axios.get<PaginatedResponse<ContactList>>(`${BASE}/recipient-lists`, {
        params: { ...(q ? { q } : {}), sort: 'name', per_page: LOOKUP_PAGE_SIZE },
    });

    return response.data.data.map((list) => ({ value: list.id, label: list.name }));
}

/** §45.4.2 GET /campaigns — campaign options of engagement conditions. */
export async function searchCampaignOptions(q: string): Promise<ConditionOption[]> {
    const response = await axios.get<CampaignListResponse>(`${BASE}/campaigns`, {
        params: { ...(q ? { q } : {}), sort: '-updated_at', per_page: LOOKUP_PAGE_SIZE },
    });

    return response.data.data.map((campaign) => ({ value: campaign.id, label: campaign.name }));
}

/** Grouped client, e.g. `onCount={(tree, signal) => segments.count(tree, signal).then((r) => r.count)}`. */
export const segments = {
    list: fetchSegments,
    get: fetchSegment,
    create: createSegment,
    update: updateSegment,
    remove: deleteSegment,
    duplicate: duplicateSegment,
    count: countSegmentRules,
    refreshCount: refreshSegmentCount,
    members: fetchSegmentMembers,
    folders: fetchSegmentFolders,
    createFolder: createSegmentFolder,
    attributes: fetchConditionAttributes,
    listOptions: searchListOptions,
    campaignOptions: searchCampaignOptions,
};
