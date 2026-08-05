import axios from 'axios';
import type { SuppressionEntry, SuppressionReason } from '@/Lib/types/suppression';

/**
 * docs/23-suppression-list.md §23.5 — Suppression List Management UI.
 * Endpoint shape assumed per REST convention matching the
 * `suppression_entries` table (WP-11 backend, not yet merged at the time
 * this client was written) — `{ data: ... }` envelope mirroring
 * Lib/api/smtp.ts / Lib/api/recipients.ts.
 */
export interface ListSuppressionEntriesParams {
    search?: string;
    reason?: SuppressionReason | '';
}

export interface CreateSuppressionEntryPayload {
    email: string;
    reason: SuppressionReason;
    notes?: string;
}

export async function listSuppressionEntries(params: ListSuppressionEntriesParams = {}): Promise<SuppressionEntry[]> {
    const response = await axios.get<{ data: SuppressionEntry[] }>('/api/v1/suppression-entries', {
        params: { search: params.search || undefined, reason: params.reason || undefined },
    });

    return response.data.data;
}

export async function createSuppressionEntry(data: CreateSuppressionEntryPayload): Promise<SuppressionEntry> {
    const response = await axios.post<{ data: SuppressionEntry }>('/api/v1/suppression-entries', data);

    return response.data.data;
}

export async function deleteSuppressionEntry(id: string): Promise<void> {
    await axios.delete(`/api/v1/suppression-entries/${id}`);
}
