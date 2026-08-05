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
}
