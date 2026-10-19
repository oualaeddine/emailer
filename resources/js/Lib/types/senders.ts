/**
 * docs/45-brevo-api-contracts.md §45.4.1 — WP-41 Senders & domains.
 * /api/v1/senders, /api/v1/sending-domains.
 */
import type { IsoDateTime, Uuid } from '@/Lib/types/common';

/** Labels: fr.domains.statusLabels. */
export type SendingDomainStatus = 'pending' | 'checking' | 'verified' | 'partially_verified' | 'failed';

/** Labels: fr.domains.recordKinds. */
export type DnsRecordKind = 'spf' | 'dkim' | 'dmarc';

/** Labels: fr.domains.recordStatusLabels. */
export type DnsRecordStatus = 'pending' | 'valid' | 'invalid' | 'missing';

/** Labels: fr.senders.verificationMethods. */
export type SenderVerificationMethod = 'email_link' | 'domain' | 'system';

export interface Sender {
    id: Uuid;
    name: string;
    email: string;
    domain: string;
    domain_id: Uuid | null;
    domain_status: SendingDomainStatus | null;
    is_default: boolean;
    is_verified: boolean;
    verification_method: SenderVerificationMethod | null;
    verified_at: IsoDateTime | null;
    verification_sent_at: IsoDateTime | null;
    reply_to: string | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

/** Trimmed projection embedded in campaigns (`CampaignV2.sender`). */
export interface SenderRef {
    id: Uuid;
    name: string;
    email: string;
    is_verified: boolean;
}

export interface SenderInput {
    name: string;
    email: string;
    reply_to?: string | null;
    is_default?: boolean;
}

/** Email is immutable (422). */
export interface SenderUpdateInput {
    name?: string;
    reply_to?: string | null;
}

export interface SendersQuery {
    verified?: 0 | 1;
    q?: string;
}

export interface DnsRecord {
    kind: DnsRecordKind;
    host: string;
    type: 'TXT' | 'CNAME';
    value: string;
    status: DnsRecordStatus;
    found_value: string | null;
    checked_at: IsoDateTime | null;
}

export interface SendingDomain {
    id: Uuid;
    domain: string;
    status: SendingDomainStatus;
    is_authenticated: boolean;
    dkim_selector: string;
    has_private_key: boolean;
    records: DnsRecord[];
    senders_count: number;
    last_checked_at: IsoDateTime | null;
    created_at: IsoDateTime;
}

export interface SendingDomainInput {
    domain: string;
}
