import axios from 'axios';
import type { SuppressionEntry, SuppressionReason } from '@/Lib/types/suppression';

/**
 * docs/45 §45.5.8 — Contacts/Blocklist reuses `GET /suppression-entries`,
 * which filters server-side on `reason[]`, `q`, `sort` and paginates.
 */
export interface BlocklistPage {
    data: SuppressionEntry[];
    meta: { current_page: number; last_page: number; total: number };
}

export const BLOCKLIST_PER_PAGE = 25;

export async function listBlocklistEntries(reasons: SuppressionReason[], q = '', page = 1): Promise<BlocklistPage> {
    const response = await axios.get<BlocklistPage>('/api/v1/suppression-entries', {
        params: { reason: reasons, q: q || undefined, sort: '-created_at', page, per_page: BLOCKLIST_PER_PAGE },
    });

    return response.data;
}

export interface SoftBounce {
    id: string;
    recipient_email: string;
    bounced_at: string | null;
}

/** Soft bounces are message statuses (not suppression entries): `GET /messages?status=soft_bounced`. */
export async function listSoftBounces(q = ''): Promise<SoftBounce[]> {
    try {
        const response = await axios.get<{ data: SoftBounce[] }>('/api/v1/messages', {
            params: { status: 'soft_bounced', q: q || undefined },
        });

        return response.data.data;
    } catch {
        // mailbox permission missing: the soft-bounce block is simply omitted.
        return [];
    }
}

/** Réactiver = delete the suppression entry (existing endpoint). */
export async function reactivateEntry(id: string): Promise<void> {
    await axios.delete(`/api/v1/suppression-entries/${id}`, { data: { confirm: true } });
}
