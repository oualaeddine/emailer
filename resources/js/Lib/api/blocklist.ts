import axios from 'axios';
import type { SuppressionEntry, SuppressionReason } from '@/Lib/types/suppression';

/**
 * docs/45 §45.5.8 — Contacts/Blocklist reuses `GET /suppression-entries`.
 * The endpoint filters on a single scalar `reason` (and `q` for email), so a
 * tab spanning several reasons issues one request per reason and merges the
 * results client-side (newest first). Pages are followed up to MAX_PAGES.
 */
const MAX_PAGES = 10;

interface Page {
    data: SuppressionEntry[];
    meta?: { current_page: number; last_page: number };
}

async function fetchReason(reason: SuppressionReason, q: string): Promise<SuppressionEntry[]> {
    const rows: SuppressionEntry[] = [];
    for (let page = 1; page <= MAX_PAGES; page += 1) {
        const response = await axios.get<Page>('/api/v1/suppression-entries', {
            params: { reason, q: q || undefined, page },
        });
        rows.push(...response.data.data);
        const meta = response.data.meta;
        if (!meta || meta.current_page >= meta.last_page) {
            break;
        }
    }

    return rows;
}

export async function listBlocklistEntries(reasons: SuppressionReason[], q = ''): Promise<SuppressionEntry[]> {
    const chunks = await Promise.all(reasons.map((reason) => fetchReason(reason, q)));

    return chunks.flat().sort((a, b) => b.created_at.localeCompare(a.created_at));
}

/** Réactiver = delete the suppression entry (existing endpoint). */
export async function reactivateEntry(id: string): Promise<void> {
    await axios.delete(`/api/v1/suppression-entries/${id}`, { data: { confirm: true } });
}
