import axios from 'axios';
import type { CursorPaginatedResponse } from '@/Lib/types/audit';
import type { DataItem, DataList } from '@/Lib/types/common';
import type {
    TransactionalLogDetail,
    TransactionalLogQuery,
    TransactionalLog,
} from '@/Lib/types/transactional';
import type {
    Webhook,
    WebhookCreated,
    WebhookDeliveriesQuery,
    WebhookDelivery,
    WebhookInput,
    WebhookSubscriptionEvent,
    WebhookType,
} from '@/Lib/types/webhooks';

/**
 * docs/45-brevo-api-contracts.md §45.7.7 — /api/v1/webhooks, /api/v1/webhook-deliveries.
 */
export async function fetchWebhooks(type?: WebhookType): Promise<Webhook[]> {
    const response = await axios.get<DataList<Webhook>>('/api/v1/webhooks', { params: type ? { type } : {} });

    return response.data.data;
}

export async function createWebhook(payload: WebhookInput): Promise<WebhookCreated> {
    const response = await axios.post<DataItem<WebhookCreated>>('/api/v1/webhooks', payload);

    return response.data.data;
}

export async function updateWebhook(id: string, payload: Partial<WebhookInput>): Promise<Webhook> {
    const response = await axios.patch<DataItem<Webhook>>(`/api/v1/webhooks/${id}`, payload);

    return response.data.data;
}

export async function deleteWebhook(id: string): Promise<void> {
    await axios.delete(`/api/v1/webhooks/${id}`);
}

export async function rotateWebhookSecret(id: string): Promise<WebhookCreated> {
    const response = await axios.post<DataItem<WebhookCreated>>(`/api/v1/webhooks/${id}/rotate-secret`);

    return response.data.data;
}

export async function testWebhook(id: string, event: WebhookSubscriptionEvent): Promise<WebhookDelivery> {
    const response = await axios.post<DataItem<WebhookDelivery>>(`/api/v1/webhooks/${id}/test`, { event });

    return response.data.data;
}

export async function fetchWebhookDeliveries(
    id: string,
    query: WebhookDeliveriesQuery = {},
): Promise<CursorPaginatedResponse<WebhookDelivery>> {
    const response = await axios.get<CursorPaginatedResponse<WebhookDelivery>>(`/api/v1/webhooks/${id}/deliveries`, {
        params: query,
    });

    return response.data;
}

export async function retryWebhookDelivery(id: string): Promise<WebhookDelivery> {
    const response = await axios.post<DataItem<WebhookDelivery>>(`/api/v1/webhook-deliveries/${id}/retry`);

    return response.data.data;
}

/** docs/45 §45.7.6 (WP-100) — GET /transactional/logs, consumed by the Logs page. */
export async function fetchTransactionalLogs(
    query: TransactionalLogQuery = {},
): Promise<CursorPaginatedResponse<TransactionalLog>> {
    const response = await axios.get<CursorPaginatedResponse<TransactionalLog>>('/api/v1/transactional/logs', {
        params: query,
    });

    return response.data;
}

export async function fetchTransactionalLog(id: string): Promise<TransactionalLogDetail> {
    const response = await axios.get<DataItem<TransactionalLogDetail>>(`/api/v1/transactional/logs/${id}`);

    return response.data.data;
}

/** Extracts the French message of a doc 29 §29.1 error envelope. */
export function apiErrorMessage(error: unknown, fallback: string): string {
    const e = error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } };
    const first = Object.values(e?.response?.data?.errors ?? {})[0]?.[0];

    return first ?? e?.response?.data?.message ?? fallback;
}
