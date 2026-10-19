/**
 * docs/04-database-design.md §4.12 — `suppression_entries`.
 * docs/23-suppression-list.md §23.2 — reason taxonomy (CHECK constraint).
 * Mirrors `App\Modules\Suppression\Models\SuppressionEntry`'s actual columns.
 */
export type SuppressionReason =
    | 'hard_bounce'
    | 'spam_complaint'
    | 'manual_unsubscribe'
    | 'global_unsubscribe'
    | 'invalid_address'
    | 'manual_block';

export interface SuppressionEntry {
    id: string;
    email: string;
    reason: SuppressionReason;
    source_message_id: number | null;
    notes: string | null;
    added_by: number | null;
    created_at: string;
    /** docs/45 §45.5.8 — optional v2 enrichment (rendered "—" when absent). */
    recipient_id?: string | null;
    source_campaign?: { id: string; name: string } | null;
}

/** docs/45 §45.5.8 — Contacts/Blocklist tabs. Labels: fr.blocklist.tabs. */
export type BlocklistTab = 'blocklisted' | 'bounces';

/** Reasons shown in each tab (query `reason[]=…`). */
export const BLOCKLIST_TAB_REASONS: Record<BlocklistTab, SuppressionReason[]> = {
    blocklisted: ['manual_unsubscribe', 'global_unsubscribe', 'spam_complaint', 'manual_block', 'invalid_address'],
    bounces: ['hard_bounce'],
};

export interface SuppressionListQuery {
    reason?: SuppressionReason[];
    q?: string;
    page?: number;
    per_page?: number;
}
