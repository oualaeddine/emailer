import axios from 'axios';
import type { DataItem } from '@/Lib/types/common';
import type { BestTimePreview } from '@/Lib/types/campaigns';

/**
 * docs/45-brevo-api-contracts.md §45.7.3 — WP-81 send at best time:
 * `GET /campaigns/{id}/best-time-preview` (permission `campaigns.create`).
 */
export async function fetchBestTimePreview(campaignId: string): Promise<BestTimePreview> {
    const response = await axios.get<DataItem<BestTimePreview>>(`/api/v1/campaigns/${campaignId}/best-time-preview`);

    return response.data.data;
}
