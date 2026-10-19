import axios from 'axios';
import type { ContactAttribute, ContactAttributeValues } from '@/Lib/types/attributes';
import type { Uuid } from '@/Lib/types/common';
import type { ContactCounters, ContactDetail, TimelineEntry, TimelineEventType } from '@/Lib/types/contacts';
import type { ContactList, ListMembershipResult } from '@/Lib/types/lists';
import type { RecipientNote } from '@/Lib/types/recipients';
import type { SuppressionEntry } from '@/Lib/types/suppression';

/**
 * docs/45-brevo-api-contracts.md §45.5.5 — WP-52 contact detail client.
 * GET /recipients/{id} still returns the legacy keys until WP-57 ships
 * ContactDetail, hence the Partial: every v2 key is optional here.
 */
export type ContactDetailData = Pick<ContactDetail, 'id' | 'email' | 'first_name' | 'last_name' | 'company_name' | 'created_at'> &
    Partial<ContactDetail> & { updated_at?: string | null };

export interface TimelinePage {
    data: TimelineEntry[];
    nextCursor: string | null;
    counters: ContactCounters | null;
}

export async function fetchContactDetail(id: Uuid): Promise<ContactDetailData> {
    const response = await axios.get<{ data: ContactDetailData }>(`/api/v1/recipients/${id}`);

    return response.data.data;
}

export async function fetchTimeline(id: Uuid, cursor: string | null, types: TimelineEventType[] = []): Promise<TimelinePage> {
    const response = await axios.get<{
        data: TimelineEntry[];
        meta: { next_cursor: string | null; counters?: ContactCounters };
    }>(`/api/v1/recipients/${id}/timeline`, {
        params: { cursor: cursor ?? undefined, per_page: 25, types: types.length > 0 ? types : undefined },
    });

    return {
        data: response.data.data,
        nextCursor: response.data.meta.next_cursor,
        counters: response.data.meta.counters ?? null,
    };
}

export async function fetchContactAttributes(): Promise<ContactAttribute[]> {
    const response = await axios.get<{ data: ContactAttribute[] }>('/api/v1/contact-attributes');

    return response.data.data;
}

export interface ContactAttributesPayload {
    first_name?: string | null;
    last_name?: string | null;
    company_name?: string | null;
    custom_fields: Record<string, unknown>;
    attributes: ContactAttributeValues;
}

export async function saveContactAttributes(id: Uuid, payload: ContactAttributesPayload): Promise<ContactDetailData> {
    const response = await axios.patch<{ data: ContactDetailData }>(`/api/v1/recipients/${id}`, payload);

    return response.data.data;
}

export async function fetchAllLists(): Promise<ContactList[]> {
    const response = await axios.get<{ data: ContactList[] }>('/api/v1/recipient-lists', { params: { per_page: 100 } });

    return response.data.data;
}

export async function changeListMembership(listId: Uuid, recipientId: Uuid, action: 'add' | 'remove'): Promise<ListMembershipResult> {
    const response = await axios.post<{ data: ListMembershipResult }>(`/api/v1/recipient-lists/${listId}/contacts`, {
        action,
        recipient_ids: [recipientId],
    });

    return response.data.data;
}

export async function fetchNotes(id: Uuid): Promise<RecipientNote[]> {
    const response = await axios.get<{ data: RecipientNote[] }>(`/api/v1/recipients/${id}/notes`);

    return response.data.data;
}

export async function addNote(id: Uuid, body: string): Promise<RecipientNote> {
    const response = await axios.post<{ data: RecipientNote }>(`/api/v1/recipients/${id}/notes`, { body });

    return response.data.data;
}

/** Finds the suppression entry of an e-mail address (exact match), if any. */
export async function findSuppressionEntry(email: string): Promise<SuppressionEntry | null> {
    const response = await axios.get<{ data: SuppressionEntry[] }>('/api/v1/suppression-entries', {
        params: { search: email, q: email },
    });

    return response.data.data.find((entry) => entry.email.toLowerCase() === email.toLowerCase()) ?? null;
}

/** Blocklist toggle (§45.5.8): POST to block (manual_block), DELETE to reactivate. */
export async function setBlocklisted(email: string, blocked: boolean): Promise<void> {
    if (blocked) {
        await axios.post('/api/v1/suppression-entries', { email, reason: 'manual_block' });

        return;
    }

    const entry = await findSuppressionEntry(email);
    if (entry !== null) {
        await axios.delete(`/api/v1/suppression-entries/${entry.id}`, { data: { confirm: true } });
    }
}
