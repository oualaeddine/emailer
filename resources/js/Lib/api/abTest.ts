import axios from 'axios';
import type { DataItem } from '@/Lib/types/common';
import type { AbTestInput, AbTestPickWinnerInput, CampaignV2Detail } from '@/Lib/types/campaigns';

/**
 * docs/45-brevo-api-contracts.md §45.7.2 (WP-80) — A/B test endpoints.
 */

/** `PUT /campaigns/{id}/ab-test` — enable / configure / disable (draft only). */
export async function saveAbTest(campaignId: string, payload: AbTestInput): Promise<CampaignV2Detail> {
    const response = await axios.put<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${campaignId}/ab-test`, payload);

    return response.data.data;
}

/** `POST /campaigns/{id}/ab-test/winner` — manual pick during the test phase (sends the remainder). */
export async function pickAbWinner(campaignId: string, variantId: string): Promise<CampaignV2Detail> {
    const payload: AbTestPickWinnerInput = { variant_id: variantId };
    const response = await axios.post<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${campaignId}/ab-test/winner`, payload);

    return response.data.data;
}
