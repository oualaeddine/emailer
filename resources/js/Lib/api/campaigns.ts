import axios from 'axios';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type {
    Campaign,
    CampaignAnalytics,
    CampaignDetail,
    CampaignRecipient,
    CreateCampaignPayload,
    UpdateCampaignPayload,
} from '@/Lib/types/campaigns';

/**
 * docs/29-api-specification.md §29.5 — /api/v1/campaigns.
 * WP-20 (sibling agent) owns the backend implementation; this client is
 * built against the documented contract (§29.5) plus the REST actions the
 * WP-23 brief assumes (schedule/send/pause/resume/cancel/clone) — not yet
 * verified against WP-20's landed code.
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

/** docs/29-api-specification.md §29.5 — Recipients tab (§15.8). */
export async function fetchCampaignRecipients(id: string, page = 1): Promise<PaginatedResponse<CampaignRecipient>> {
    const response = await axios.get<PaginatedResponse<CampaignRecipient>>(`/api/v1/campaigns/${id}/recipients`, {
        params: { page },
    });

    return response.data;
}

/** docs/29-api-specification.md §29.5 — Analytics tab (§15.8). */
export async function fetchCampaignAnalytics(id: string): Promise<CampaignAnalytics> {
    const response = await axios.get<{ data: CampaignAnalytics }>(`/api/v1/campaigns/${id}/analytics`);

    return response.data.data;
}
