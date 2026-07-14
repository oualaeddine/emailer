import axios from 'axios';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type { Recipient, Tag } from '@/Lib/types/recipients';

/**
 * docs/29-api-specification.md §29.4 — /api/v1/recipients, /api/v1/tags.
 */
export interface CreateRecipientPayload {
    email: string;
    first_name?: string;
    last_name?: string;
    company_name?: string;
    source: string;
}

export async function fetchRecipients(params: Record<string, string> = {}, page = 1): Promise<PaginatedResponse<Recipient>> {
    const response = await axios.get<PaginatedResponse<Recipient>>('/api/v1/recipients', {
        params: { ...params, page },
    });

    return response.data;
}

export async function createRecipient(payload: CreateRecipientPayload): Promise<Recipient> {
    const response = await axios.post<{ data: Recipient }>('/api/v1/recipients', payload);

    return response.data.data;
}

export async function updateRecipient(
    id: string,
    payload: Partial<{ first_name: string; last_name: string; company_name: string; status: string; tag_ids: number[] }>,
): Promise<Recipient> {
    const response = await axios.patch<{ data: Recipient }>(`/api/v1/recipients/${id}`, payload);

    return response.data.data;
}

export async function fetchTags(): Promise<Tag[]> {
    const response = await axios.get<{ data: Tag[] }>('/api/v1/tags');

    return response.data.data;
}
