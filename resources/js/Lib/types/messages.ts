/**
 * Mirrors App\Modules\Tracking\Http\Resources\MessageResource 1:1
 * (docs/10-mailbox.md, docs/29-api-specification.md §29.7).
 *
 * Built against the documented API contract ahead of WP-12 (Tracking
 * module) landing — see docs/42-parallel-execution-plan.md §42.6. The
 * folders that actually query `/api/v1/messages` (Sent/Outbox/Scheduled);
 * Drafts is served by the already-committed Composer module's
 * `/api/v1/drafts` (see `Lib/types/composer.ts`'s `Draft`), and Inbox has
 * no data source in v1 (doc 10 §10.3 note / doc 01 §1.4 out-of-scope).
 */
export type MessageFolder = 'sent' | 'outbox' | 'scheduled';

export interface Message {
    id: string;
    recipient_email: string;
    subject: string;
    status: string;
    smtp_account: string | null;
    queued_at: string | null;
    sent_at: string | null;
    delivered_at: string | null;
    opened_at: string | null;
    clicked_at: string | null;
    bounced_at: string | null;
    failed_at: string | null;
    open_count: number;
    click_count: number;
    last_provider_response: string | null;
}

/**
 * `GET /api/v1/messages/{uuid}` additionally includes `message_events` and
 * `send_attempts` (doc 29 §29.7). Their exact shape is owned by WP-12
 * (Tracking module — uncommitted at the time this page was built), so they
 * are typed loosely here and rendered defensively; the reading pane's
 * primary timeline is built from `Message`'s documented `*_at` fields.
 */
export interface MessageEvent {
    id?: string | number;
    type?: string;
    event_type?: string;
    occurred_at?: string | null;
    created_at?: string | null;
    [key: string]: unknown;
}

export interface SendAttempt {
    id?: string | number;
    smtp_account?: string | null;
    attempted_at?: string | null;
    outcome?: string;
    error_message?: string | null;
    [key: string]: unknown;
}

export interface MessageDetail extends Message {
    message_events?: MessageEvent[];
    send_attempts?: SendAttempt[];
}

/**
 * docs/29-api-specification.md §29.1 — `messages` uses cursor-based
 * pagination (unlike the page-based `PaginatedResponse<T>` in
 * `Lib/types/identity.ts`), so `meta`/`links` are typed loosely rather than
 * assuming `current_page`/`last_page`/`total` are present.
 */
export interface MessageListResponse {
    data: Message[];
    meta: Record<string, unknown>;
    links: {
        first: string | null;
        last: string | null;
        prev: string | null;
        next: string | null;
    };
}
