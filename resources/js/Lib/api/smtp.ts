import axios from 'axios';
import type { SmtpAccount } from '@/Lib/types/smtp';

/**
 * docs/29-api-specification.md §29.6 — /api/v1/smtp-accounts.
 */
export interface CreateSmtpAccountPayload {
    name: string;
    provider: string;
    host: string;
    port: number;
    encryption: string;
    username: string;
    password: string;
    from_email: string;
    from_name?: string;
    daily_quota?: number;
    hourly_quota?: number;
    minute_quota?: number;
    priority?: number;
    rotation_weight?: number;
}

export async function fetchSmtpAccounts(): Promise<SmtpAccount[]> {
    const response = await axios.get<{ data: SmtpAccount[] }>('/api/v1/smtp-accounts');

    return response.data.data;
}

export async function createSmtpAccount(payload: CreateSmtpAccountPayload): Promise<SmtpAccount> {
    const response = await axios.post<{ data: SmtpAccount }>('/api/v1/smtp-accounts', payload);

    return response.data.data;
}

export async function testSmtpAccount(id: string, testEmail?: string): Promise<{ success: boolean; raw_response: string }> {
    const response = await axios.post<{ success: boolean; raw_response: string }>(`/api/v1/smtp-accounts/${id}/test`, {
        test_email: testEmail,
    });

    return response.data;
}

export async function deleteSmtpAccount(id: string): Promise<void> {
    await axios.delete(`/api/v1/smtp-accounts/${id}`);
}
