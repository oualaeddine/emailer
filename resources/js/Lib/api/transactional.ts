import axios from 'axios';
import type { DataItem, DataList } from '@/Lib/types/common';
import type {
    ApiKey,
    ApiKeyCreated,
    ApiKeyInput,
    TransactionalSettings,
} from '@/Lib/types/transactional';

/**
 * docs/45-brevo-api-contracts.md §45.7.6 — /api/v1/api-keys, /api/v1/transactional/settings.
 */
export async function fetchApiKeys(): Promise<ApiKey[]> {
    const response = await axios.get<DataList<ApiKey>>('/api/v1/api-keys');

    return response.data.data;
}

/** The plain token is returned once, on creation only. */
export async function createApiKey(payload: ApiKeyInput): Promise<ApiKeyCreated> {
    const response = await axios.post<DataItem<ApiKeyCreated>>('/api/v1/api-keys', payload);

    return response.data.data;
}

export async function revokeApiKey(id: string): Promise<void> {
    await axios.delete(`/api/v1/api-keys/${id}`);
}

export async function fetchTransactionalSettings(): Promise<TransactionalSettings> {
    const response = await axios.get<DataItem<TransactionalSettings>>('/api/v1/transactional/settings');

    return response.data.data;
}

/** Extracts the French message of a doc 29 §29.1 error envelope. */
export function apiErrorMessage(error: unknown, fallback: string): string {
    const e = error as { response?: { data?: { message?: string } } };

    return e?.response?.data?.message ?? fallback;
}
