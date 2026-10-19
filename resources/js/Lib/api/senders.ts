import axios from 'axios';
import type { DataItem, DataList } from '@/Lib/types/common';
import type {
    Sender,
    SenderInput,
    SenderUpdateInput,
    SendersQuery,
    SendingDomain,
    SendingDomainInput,
} from '@/Lib/types/senders';

/**
 * docs/45-brevo-api-contracts.md §45.4.1 — /api/v1/senders, /api/v1/sending-domains.
 */
export async function fetchSenders(query: SendersQuery = {}): Promise<Sender[]> {
    const response = await axios.get<DataList<Sender>>('/api/v1/senders', { params: query });

    return response.data.data;
}

export async function createSender(payload: SenderInput): Promise<Sender> {
    const response = await axios.post<DataItem<Sender>>('/api/v1/senders', payload);

    return response.data.data;
}

export async function updateSender(id: string, payload: SenderUpdateInput): Promise<Sender> {
    const response = await axios.patch<DataItem<Sender>>(`/api/v1/senders/${id}`, payload);

    return response.data.data;
}

export async function deleteSender(id: string): Promise<void> {
    await axios.delete(`/api/v1/senders/${id}`);
}

export async function setDefaultSender(id: string): Promise<Sender> {
    const response = await axios.post<DataItem<Sender>>(`/api/v1/senders/${id}/default`);

    return response.data.data;
}

export async function resendSenderVerification(id: string): Promise<Sender> {
    const response = await axios.post<DataItem<Sender>>(`/api/v1/senders/${id}/resend-verification`);

    return response.data.data;
}

export async function fetchSendingDomains(): Promise<SendingDomain[]> {
    const response = await axios.get<DataList<SendingDomain>>('/api/v1/sending-domains');

    return response.data.data;
}

export async function fetchSendingDomain(id: string): Promise<SendingDomain> {
    const response = await axios.get<DataItem<SendingDomain>>(`/api/v1/sending-domains/${id}`);

    return response.data.data;
}

export async function createSendingDomain(payload: SendingDomainInput): Promise<SendingDomain> {
    const response = await axios.post<DataItem<SendingDomain>>('/api/v1/sending-domains', payload);

    return response.data.data;
}

export async function verifySendingDomain(id: string): Promise<SendingDomain> {
    const response = await axios.post<DataItem<SendingDomain>>(`/api/v1/sending-domains/${id}/verify`);

    return response.data.data;
}

export async function deleteSendingDomain(id: string): Promise<void> {
    await axios.delete(`/api/v1/sending-domains/${id}`);
}

/** Extracts the French message of a doc 29 §29.1 error envelope. */
export function apiErrorMessage(error: unknown, fallback: string): string {
    const e = error as { response?: { data?: { message?: string } } };

    return e?.response?.data?.message ?? fallback;
}
