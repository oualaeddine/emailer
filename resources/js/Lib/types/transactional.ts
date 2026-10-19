/**
 * docs/45-brevo-api-contracts.md §45.7.6 — WP-100 API keys, transactional
 * send, logs, settings (`Transactional/Logs`, `Transactional/Settings`).
 */
import type { IsoDateTime, UserRef, Uuid } from '@/Lib/types/common';
import type { DeviceType } from '@/Lib/types/reports';

/** Labels: fr.apiKeys.scopes. */
export type ApiKeyScope =
    | 'transactional:send'
    | 'transactional:read'
    | 'contacts:read'
    | 'contacts:write'
    | 'campaigns:read'
    | 'statistics:read';

export interface ApiKey {
    id: Uuid;
    name: string;
    scopes: ApiKeyScope[];
    token_prefix: string;
    last_used_at: IsoDateTime | null;
    last_used_ip: string | null;
    expires_at: IsoDateTime | null;
    created_by: UserRef | null;
    created_at: IsoDateTime;
}

export interface ApiKeyInput {
    name: string;
    scopes: ApiKeyScope[];
    expires_at?: IsoDateTime | null;
}

/** 201 — the plain token is shown once. */
export interface ApiKeyCreated {
    api_key: ApiKey;
    plain_text_token: string;
}

export interface EmailAddress {
    email: string;
    name?: string | null;
}

export type TransactionalAttachment = { name: string; content: string } | { name: string; url: string };

/** POST /transactional/send — snake_case equivalent of Brevo's POST /smtp/email. */
export interface TransactionalSendInput {
    sender?: EmailAddress;
    sender_id?: Uuid;
    to: EmailAddress[];
    cc?: EmailAddress[];
    bcc?: EmailAddress[];
    reply_to?: EmailAddress;
    subject?: string;
    html_content?: string;
    text_content?: string | null;
    template_id?: Uuid | null;
    params?: Record<string, unknown>;
    tags?: string[];
    headers?: Record<string, string>;
    attachments?: TransactionalAttachment[];
    scheduled_at?: IsoDateTime | null;
}

export interface TransactionalSendResult {
    message_id: string;
    message_ids: Uuid[];
}

/** Brevo's event wording for logs. Labels: fr.transactional.events. */
export type BrevoEventLabel =
    | 'sent'
    | 'delivered'
    | 'opened'
    | 'clicked'
    | 'soft_bounce'
    | 'hard_bounce'
    | 'invalid'
    | 'deferred'
    | 'complaint'
    | 'unsubscribed'
    | 'blocked'
    | 'error';

/** Labels: fr.transactional.sources. */
export type TransactionalSource = 'composer' | 'api' | 'automation' | 'form';

export interface TransactionalLogQuery {
    event?: BrevoEventLabel;
    q?: string;
    tag?: string;
    template_id?: Uuid | null;
    source?: TransactionalSource;
    date_from?: IsoDateTime;
    date_to?: IsoDateTime;
    cursor?: string;
    per_page?: number;
}

export interface TransactionalLog {
    id: Uuid;
    /** RFC 5322 Message-ID. */
    message_id: string;
    recipient_email: string;
    subject: string;
    tags: string[];
    sender_email: string;
    template_id: Uuid | null;
    source: TransactionalSource;
    /** Existing MessageStatus value. */
    status: string;
    last_event: BrevoEventLabel | null;
    created_at: IsoDateTime;
    last_event_at: IsoDateTime | null;
}

export interface TransactionalLogEvent {
    event: BrevoEventLabel;
    occurred_at: IsoDateTime;
    reason: string | null;
    link: string | null;
    ip: string | null;
    user_agent: string | null;
    device_type: DeviceType | null;
}

export interface TransactionalLogDetail extends TransactionalLog {
    events: TransactionalLogEvent[];
}

export interface TransactionalSettings {
    api_base_url: string;
    rate_limit_per_minute: number;
    smtp_relay: {
        enabled: boolean;
        host: string | null;
        port: number | null;
        login: string | null;
    };
}

/** Transactional/Settings tabs. Labels: fr.transactional.settingsTabs. */
export type TransactionalSettingsTab = 'smtp' | 'api_keys' | 'webhooks';
