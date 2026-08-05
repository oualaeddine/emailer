import axios from 'axios';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type { Message } from '@/Lib/types/messages';
import type {
    Campaign,
    CampaignAnalytics,
    CampaignDetail,
    CreateCampaignPayload,
    UpdateCampaignPayload,
} from '@/Lib/types/campaigns';

/**
 * docs/29-api-specification.md §29.5 — /api/v1/campaigns.
 * Reconciled against the landed WP-20 backend (`CampaignController`) —
 * `recipients()` returns `MessageResource::collection` (reuses the
 * `Message` type from the Tracking module, WP-12), and `analytics()`
 * returns a `{targeted, by_status, open_count, click_count}` funnel
 * breakdown rather than the originally-assumed shape.
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
