/**
 * docs/45-brevo-api-contracts.md §45.7.7 — WP-101 outgoing webhooks
 * (`WebhooksPanel`) with Brevo-shaped payloads.
 */
import type { IsoDateTime, Uuid } from '@/Lib/types/common';

/** Labels: fr.webhooks.types. */
export type WebhookType = 'transactional' | 'marketing';

/** Brevo subscription identifiers (transactional), verbatim. Labels: fr.webhooks.events. */
export type TransactionalWebhookEvent =
    | 'sent'
    | 'request'
    | 'delivered'
    | 'opened'
    | 'uniqueOpened'
    | 'click'
    | 'softBounce'
    | 'hardBounce'
    | 'invalid'
    | 'deferred'
    | 'spam'
    | 'unsubscribed'
    | 'blocked'
    | 'error'
    | 'proxyOpen';

/** Brevo subscription identifiers (marketing), verbatim. Labels: fr.webhooks.events. */
export type MarketingWebhookEvent =
    | 'delivered'
    | 'opened'
    | 'click'
    | 'softBounce'
    | 'hardBounce'
    | 'spam'
    | 'unsubscribed'
    | 'listAddition'
    | 'contactUpdated'
    | 'contactDeleted';

export type WebhookSubscriptionEvent = TransactionalWebhookEvent | MarketingWebhookEvent;

/** Payload `event` values (transactional). */
export type TransactionalWebhookPayloadEvent =
    | 'request'
    | 'delivered'
    | 'opened'
    | 'unique_opened'
    | 'click'
    | 'soft_bounce'
    | 'hard_bounce'
    | 'invalid_email'
    | 'deferred'
    | 'spam'
    | 'unsubscribed'
    | 'blocked'
    | 'error'
    | 'proxy_open';

/** Payload `event` values (marketing). */
export type MarketingWebhookPayloadEvent =
    | 'delivered'
    | 'opened'
    | 'click'
    | 'soft_bounce'
    | 'hard_bounce'
    | 'spam'
    | 'unsubscribe'
    | 'list_addition'
    | 'contact_updated'
    | 'contact_deleted';

export type WebhookPayloadEvent = TransactionalWebhookPayloadEvent | MarketingWebhookPayloadEvent;

/** Labels: fr.webhooks.deliveryStatus. */
export type WebhookDeliveryStatus = 'pending' | 'succeeded' | 'failed' | 'retrying';

export interface Webhook {
    id: Uuid;
    url: string;
    description: string | null;
    type: WebhookType;
    events: WebhookSubscriptionEvent[];
    is_active: boolean;
    secret_hint: string;
    headers: Record<string, string> | null;
    last_delivery_at: IsoDateTime | null;
    last_delivery_status: WebhookDeliveryStatus | null;
    failure_count: number;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface WebhookInput {
    url: string;
    description?: string | null;
    type: WebhookType;
    events: WebhookSubscriptionEvent[];
    is_active?: boolean;
    headers?: Record<string, string> | null;
}

/** 201 / rotate-secret — secret shown once. */
export interface WebhookCreated {
    webhook: Webhook;
    secret: string;
}

export interface WebhookTestInput {
    event: WebhookSubscriptionEvent;
}

export interface WebhookDelivery {
    id: Uuid;
    webhook_id: Uuid;
    event: WebhookPayloadEvent;
    status: WebhookDeliveryStatus;
    attempts: number;
    response_status: number | null;
    response_body_excerpt: string | null;
    duration_ms: number | null;
    payload: Record<string, unknown>;
    next_retry_at: IsoDateTime | null;
    created_at: IsoDateTime;
    delivered_at: IsoDateTime | null;
}

export interface WebhookDeliveriesQuery {
    status?: WebhookDeliveryStatus;
    event?: WebhookPayloadEvent;
    cursor?: string;
    per_page?: number;
}

/** Brevo transactional payload (keys verbatim; absent keys omitted). */
export interface TransactionalWebhookPayload {
    event: TransactionalWebhookPayloadEvent;
    email: string;
    id: Uuid;
    date: string;
    ts: number;
    'message-id': string;
    ts_event: number;
    ts_epoch: number;
    subject?: string;
    tag?: string;
    tags?: string[];
    sending_ip?: string;
    template_id?: Uuid;
    contact_id?: Uuid;
    link?: string;
    reason?: string;
    user_agent?: string;
    device_used?: string;
    mirror_link?: string;
}

/** Brevo marketing payload (keys verbatim, including "campaign name"). */
export interface MarketingWebhookPayload {
    id: Uuid;
    event: MarketingWebhookPayloadEvent;
    email: string;
    camp_id?: Uuid;
    'campaign name'?: string;
    tag?: string;
    date_sent?: string;
    date_event?: string;
    date?: string;
    ts_sent?: number;
    ts_event?: number;
    ts?: number;
    URL?: string;
    list_id?: Uuid[];
    segment_ids?: Uuid[];
    reason?: string;
    sending_ip?: string;
    contact_id?: Uuid;
    /** list_addition: contact uuid. */
    key?: Uuid;
    /** contact_updated / contact_deleted. */
    attributes?: Record<string, unknown>;
}
