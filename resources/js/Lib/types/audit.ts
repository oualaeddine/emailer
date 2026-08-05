/**
 * Mirrors App\Modules\Audit\Http\Resources\AuditLogResource 1:1
 * (docs/03-folder-structure.md §3.2).
 *
 * `id` is the raw internal `audit_logs.id` (not a `uuid` — that table has
 * no `uuid` column, see `AuditLogResource`'s docblock for why that's
 * acceptable for this specific, read-only, admin-only resource).
 */
export interface AuditLog {
    id: number;
    /** The actor's display name, or `null` for a system-initiated action. */
    user: string | null;
    action: string;
    auditable_type: string;
    auditable_id: number;
    old_values: Record<string, unknown> | null;
    new_values: Record<string, unknown> | null;
    ip_address: string;
    created_at: string;
}

export interface AuditLogFilters {
    user_id?: number;
    action?: string;
    auditable_type?: string;
    date_from?: string;
    date_to?: string;
    per_page?: number;
}

/**
 * docs/29-api-specification.md §29.1 — `audit_logs` is cursor-paginated
 * (unlike the page-based envelope used by `users`/`smtp_accounts`), so this
 * intentionally does NOT reuse `PaginatedResponse` from
 * `Lib/types/identity.ts` — Laravel's `CursorPaginator` JSON shape has no
 * `current_page`/`last_page`/`total` (a cursor paginator doesn't know the
 * total row count), only a `next`/`prev` cursor.
 */
export interface CursorPaginatedResponse<T> {
    data: T[];
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
    meta: {
        path: string;
        per_page: number;
        next_cursor: string | null;
        prev_cursor: string | null;
    };
}
