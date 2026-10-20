import axios from 'axios';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type { Message } from '@/Lib/types/messages';
import type { BulkResult, DataItem, DataList, Folder, FolderInput } from '@/Lib/types/common';
import type {
    AudienceEstimate,
    AudienceEstimateInput,
    Campaign,
    CampaignAnalytics,
    CampaignBulkInput,
    CampaignChecklist,
    CampaignCreateInput,
    CampaignDetail,
    CampaignDuplicateInput,
    CampaignListQuery,
    CampaignListResponse,
    CampaignPreview,
    CampaignPreviewQuery,
    CampaignScheduleInput,
    CampaignSendToNewContactsInput,
    CampaignTestSendInput,
    CampaignUpdateInput,
    CampaignV2Detail,
    CreateCampaignPayload,
    TestSendResult,
    UpdateCampaignPayload,
} from '@/Lib/types/campaigns';

/**
 * docs/29-api-specification.md §29.5 — /api/v1/campaigns (legacy client, kept
 * for Reporting/Index and the CampaignDetail "Rapport" drawer until Wave 3)
 * and docs/45-brevo-api-contracts.md §45.4.2 / §45.4.5 (campaigns v2 +
 * checklist, WP-43 UI).
 */
export async function fetchCampaigns(params: Record<string, string> = {}, page = 1): Promise<PaginatedResponse<Campaign>> {
    const response = await axios.get<PaginatedResponse<Campaign>>('/api/v1/campaigns', {
        params: { ...params, page },
    });

    return response.data;
}

export async function fetchCampaign(id: string): Promise<CampaignDetail> {
    const response = await axios.get<{ data: CampaignDetail }>(`/api/v1/campaigns/${id}`);

    return response.data.data;
}

export async function createCampaign(payload: CreateCampaignPayload): Promise<Campaign> {
    const response = await axios.post<{ data: Campaign }>('/api/v1/campaigns', payload);

    return response.data.data;
}

export async function updateCampaign(id: string, payload: UpdateCampaignPayload): Promise<Campaign> {
    const response = await axios.patch<{ data: Campaign }>(`/api/v1/campaigns/${id}`, payload);

    return response.data.data;
}

export async function scheduleCampaign(id: string, scheduledAt: string): Promise<Campaign> {
    const response = await axios.post<{ data: Campaign }>(`/api/v1/campaigns/${id}/schedule`, {
        scheduled_at: scheduledAt,
    });

    return response.data.data;
}

export async function sendCampaign(id: string): Promise<Campaign> {
    const response = await axios.post<{ data: Campaign }>(`/api/v1/campaigns/${id}/send`);

    return response.data.data;
}

export async function pauseCampaign(id: string): Promise<Campaign> {
    const response = await axios.post<{ data: Campaign }>(`/api/v1/campaigns/${id}/pause`);

    return response.data.data;
}

export async function resumeCampaign(id: string): Promise<Campaign> {
    const response = await axios.post<{ data: Campaign }>(`/api/v1/campaigns/${id}/resume`);

    return response.data.data;
}

export async function cancelCampaign(id: string): Promise<Campaign> {
    const response = await axios.post<{ data: Campaign }>(`/api/v1/campaigns/${id}/cancel`);

    return response.data.data;
}

export async function cloneCampaign(id: string): Promise<Campaign> {
    const response = await axios.post<{ data: Campaign }>(`/api/v1/campaigns/${id}/clone`);

    return response.data.data;
}

/** docs/29-api-specification.md §29.5 — Recipients tab (§15.8): `CampaignController::recipients()` returns `MessageResource::collection`, page-paginated. */
export async function fetchCampaignRecipients(id: string, page = 1): Promise<PaginatedResponse<Message>> {
    const response = await axios.get<PaginatedResponse<Message>>(`/api/v1/campaigns/${id}/recipients`, {
        params: { page },
    });

    return response.data;
}

/** docs/29-api-specification.md §29.5 — Analytics tab (§15.8): `CampaignController::analytics()` returns a plain JSON object, not a `{data: ...}` envelope. */
export async function fetchCampaignAnalytics(id: string): Promise<CampaignAnalytics> {
    const response = await axios.get<CampaignAnalytics>(`/api/v1/campaigns/${id}/analytics`);

    return response.data;
}

// ===========================================================================
// Campaigns v2 — docs/45-brevo-api-contracts.md §45.4.2.
// ===========================================================================

/** GET /campaigns with `status_tab`, folder, tag, type, search, sort → list + `meta.status_counts`. */
export async function fetchCampaignList(query: CampaignListQuery = {}): Promise<CampaignListResponse> {
    const params: Record<string, string | number> = {};
    for (const [key, value] of Object.entries(query)) {
        if (value !== undefined && value !== null && value !== '') {
            params[key] = value as string | number;
        }
    }
    const response = await axios.get<CampaignListResponse>('/api/v1/campaigns', { params });

    return response.data;
}

export async function fetchCampaignV2(id: string): Promise<CampaignV2Detail> {
    const response = await axios.get<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${id}`);

    return response.data.data;
}

export async function createCampaignV2(payload: CampaignCreateInput): Promise<CampaignV2Detail> {
    const response = await axios.post<DataItem<CampaignV2Detail>>('/api/v1/campaigns', payload);

    return response.data.data;
}

export async function updateCampaignV2(id: string, payload: CampaignUpdateInput): Promise<CampaignV2Detail> {
    const response = await axios.patch<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${id}`, payload);

    return response.data.data;
}

export async function deleteCampaign(id: string): Promise<void> {
    await axios.delete(`/api/v1/campaigns/${id}`);
}

export async function fetchCampaignPreview(id: string, query: CampaignPreviewQuery = {}): Promise<CampaignPreview> {
    const response = await axios.get<DataItem<CampaignPreview>>(`/api/v1/campaigns/${id}/preview`, { params: query });

    return response.data.data;
}

export async function estimateCampaignAudience(id: string, payload: AudienceEstimateInput = {}): Promise<AudienceEstimate> {
    const response = await axios.post<DataItem<AudienceEstimate>>(`/api/v1/campaigns/${id}/audience/estimate`, payload);

    return response.data.data;
}

export async function sendCampaignTest(id: string, payload: CampaignTestSendInput): Promise<TestSendResult> {
    const response = await axios.post<DataItem<TestSendResult>>(`/api/v1/campaigns/${id}/test-send`, payload);

    return response.data.data;
}

/** The four delivery options of "Confirmer l'envoi" (`mode`: now | scheduled | best_time | batches). */
export async function scheduleCampaignV2(id: string, payload: CampaignScheduleInput): Promise<CampaignV2Detail> {
    const response = await axios.post<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${id}/schedule`, payload);

    return response.data.data;
}

export async function unscheduleCampaign(id: string): Promise<CampaignV2Detail> {
    const response = await axios.post<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${id}/unschedule`);

    return response.data.data;
}

export async function duplicateCampaign(id: string, payload: CampaignDuplicateInput = {}): Promise<CampaignV2Detail> {
    const response = await axios.post<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${id}/duplicate`, payload);

    return response.data.data;
}

export async function sendCampaignToNewContacts(
    id: string,
    payload: CampaignSendToNewContactsInput = {},
): Promise<CampaignV2Detail> {
    const response = await axios.post<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${id}/send-to-new-contacts`, payload);

    return response.data.data;
}

/** POST /campaigns/{id}/approve — needs `campaigns.approve`. */
export async function approveCampaign(id: string): Promise<CampaignV2Detail> {
    const response = await axios.post<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${id}/approve`);

    return response.data.data;
}

export async function archiveCampaign(id: string): Promise<CampaignV2Detail> {
    const response = await axios.post<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${id}/archive`);

    return response.data.data;
}

export async function unarchiveCampaign(id: string): Promise<CampaignV2Detail> {
    const response = await axios.post<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${id}/unarchive`);

    return response.data.data;
}

export async function bulkCampaigns(payload: CampaignBulkInput): Promise<BulkResult> {
    const response = await axios.post<DataItem<BulkResult>>('/api/v1/campaigns/bulk', payload);

    return response.data.data;
}

// --- Campaign folders -------------------------------------------------------

export async function fetchCampaignFolders(): Promise<Folder[]> {
    const response = await axios.get<DataList<Folder>>('/api/v1/campaign-folders');

    return response.data.data;
}

export async function createCampaignFolder(payload: FolderInput): Promise<Folder> {
    const response = await axios.post<DataItem<Folder>>('/api/v1/campaign-folders', payload);

    return response.data.data;
}

export async function renameCampaignFolder(id: string, payload: FolderInput): Promise<Folder> {
    const response = await axios.patch<DataItem<Folder>>(`/api/v1/campaign-folders/${id}`, payload);

    return response.data.data;
}

export async function deleteCampaignFolder(id: string): Promise<void> {
    await axios.delete(`/api/v1/campaign-folders/${id}`);
}

// --- Checklist (WP-45, §45.4.5) ---------------------------------------------

export async function fetchCampaignChecklist(id: string): Promise<CampaignChecklist> {
    const response = await axios.get<DataItem<CampaignChecklist>>(`/api/v1/campaigns/${id}/checklist`);

    return response.data.data;
}

export async function checkCampaignLinks(id: string): Promise<CampaignChecklist> {
    const response = await axios.post<DataItem<CampaignChecklist>>(`/api/v1/campaigns/${id}/checklist/links`);

    return response.data.data;
}

// --- Setup-page lookups (§45.4.3) -------------------------------------------

/**
 * One selectable list or segment in the recipients dialog. The Wave 2 list /
 * segment resources are not part of this WP's contract, so the count is read
 * from whichever count key the current endpoints return.
 */
export interface AudienceSourceOption {
    id: string;
    name: string;
    count: number | null;
}

/** A selectable unsubscribe page / update-profile form (`GET /unsubscribe-pages`, `GET /update-profile-forms`). */
export interface NamedOption {
    id: string;
    name: string;
}

const COUNT_KEYS = ['contact_count', 'contacts_count', 'recipients_count', 'members_count', 'count', 'size'] as const;

function toAudienceOption(row: Record<string, unknown>): AudienceSourceOption {
    let count: number | null = null;
    for (const key of COUNT_KEYS) {
        if (typeof row[key] === 'number') {
            count = row[key] as number;
            break;
        }
    }

    return { id: String(row.id), name: String(row.name ?? row.id), count };
}

async function fetchAllRows(url: string, params: Record<string, string | number> = {}): Promise<Record<string, unknown>[]> {
    const rows: Record<string, unknown>[] = [];
    let page = 1;
    // Page-paginated (`meta.last_page`) or a plain DataList — at most 10 pages of 100.
    while (page <= 10) {
        const response = await axios.get<{ data: Record<string, unknown>[]; meta?: { last_page?: number } }>(url, {
            params: { ...params, page, per_page: 100 },
        });
        rows.push(...(response.data.data ?? []));
        const lastPage = response.data.meta?.last_page ?? 1;
        if (page >= lastPage) {
            break;
        }
        page += 1;
    }

    return rows;
}

export async function fetchAudienceLists(): Promise<AudienceSourceOption[]> {
    return (await fetchAllRows('/api/v1/recipient-lists')).map(toAudienceOption);
}

export async function fetchAudienceSegments(): Promise<AudienceSourceOption[]> {
    return (await fetchAllRows('/api/v1/segments')).map(toAudienceOption);
}

/** Static fallback of §45.4.3 when `GET /contact-attributes/merge-tags` is unavailable. */
export const FALLBACK_MERGE_TAGS: string[] = [
    '{{ contact.PRENOM }}',
    '{{ contact.NOM }}',
    '{{ contact.EMAIL }}',
    '{{ contact.SOCIETE }}',
];

/** Subject personalisation menu — merge tags from WP-50, static fallback on any error (404 until Wave 2). */
export async function fetchMergeTags(): Promise<string[]> {
    try {
        const response = await axios.get<{ data: unknown[] }>('/api/v1/contact-attributes/merge-tags');
        const tags = (response.data.data ?? [])
            .map((item) => {
                if (typeof item === 'string') {
                    return item;
                }
                if (item && typeof item === 'object') {
                    const row = item as Record<string, unknown>;
                    const value = row.merge_tag ?? row.tag ?? row.value;
                    if (typeof value === 'string') {
                        return value;
                    }
                    if (typeof row.name === 'string') {
                        return `{{ contact.${row.name} }}`;
                    }
                }

                return null;
            })
            .filter((tag): tag is string => tag !== null);

        return tags.length > 0 ? tags : FALLBACK_MERGE_TAGS;
    } catch {
        return FALLBACK_MERGE_TAGS;
    }
}

async function fetchNamedOptions(url: string): Promise<NamedOption[]> {
    try {
        const response = await axios.get<{ data: Record<string, unknown>[] }>(url);

        return (response.data.data ?? []).map((row) => ({ id: String(row.id), name: String(row.name ?? row.title ?? row.id) }));
    } catch {
        return [];
    }
}

/** Empty list until Wave 3 (§45.6.6). */
export function fetchUnsubscribePages(): Promise<NamedOption[]> {
    return fetchNamedOptions('/api/v1/unsubscribe-pages');
}

/** Empty list until Wave 3 (§45.6.6). */
export function fetchUpdateProfileForms(): Promise<NamedOption[]> {
    return fetchNamedOptions('/api/v1/update-profile-forms');
}
