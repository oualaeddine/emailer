import axios from 'axios';
import type { Message, MessageDetail, MessageFolder, MessageListResponse } from '@/Lib/types/messages';

/**
 * docs/29-api-specification.md §29.7 — /api/v1/messages.
 * Built against the documented contract; reconcile against WP-12's actual
 * `MessageController` once merged (docs/42-parallel-execution-plan.md §42.6).
 */
export interface ListMessagesParams {
    status?: string;
    campaign_id?: string;
    smtp_account_id?: string;
    date_from?: string;
    date_to?: string;
    recipient_q?: string;
    /** doc 10 §10.8 — team-wide view, only meaningful with `mailbox.view_all`. */
    scope?: 'own' | 'all';
}

export async function listMessages(
    folder: MessageFolder,
    params: ListMessagesParams = {},
): Promise<MessageListResponse> {
    const response = await axios.get<MessageListResponse>('/api/v1/messages', {
        params: { folder, ...params },
    });

    return response.data;
}

/** Follows a cursor-paginated `links.next`/`links.prev` URL as-is. */
export async function fetchMessagesPage(url: string): Promise<MessageListResponse> {
    const response = await axios.get<MessageListResponse>(url);

    return response.data;
}

export async function getMessage(uuid: string): Promise<MessageDetail> {
    const response = await axios.get<{ data: MessageDetail }>(`/api/v1/messages/${uuid}`);

    return response.data.data;
}

/** docs/10-mailbox.md §10.5 — Outbox row actions. */
export async function cancelMessage(uuid: string): Promise<Message> {
    const response = await axios.post<{ data: Message }>(`/api/v1/messages/${uuid}/cancel`);

    return response.data.data;
}

export async function retryMessage(uuid: string): Promise<Message> {
    const response = await axios.post<{ data: Message }>(`/api/v1/messages/${uuid}/retry`);

    return response.data.data;
}
