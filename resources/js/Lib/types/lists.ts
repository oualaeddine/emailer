/**
 * docs/45-brevo-api-contracts.md §45.5.6 — WP-53 Lists & folders.
 * /api/v1/recipient-lists, /api/v1/recipient-lists/{id}/contacts,
 * /api/v1/list-folders. Members: GET /recipients?pagination=cursor&list_id=.
 */
import type { IsoDateTime, UserRef, Uuid } from '@/Lib/types/common';
import type { ContactSelection } from '@/Lib/types/contacts';

/** Labels: fr.lists.types. `dynamic_segment` is legacy, read-only. */
export type ContactListType = 'static' | 'dynamic_segment';

export interface ContactList {
    id: Uuid;
    name: string;
    description: string | null;
    folder_id: Uuid | null;
    type: ContactListType;
    contact_count: number;
    blocklisted_count: number;
    last_import_at: IsoDateTime | null;
    created_by: UserRef | null;
    created_at: IsoDateTime;
    updated_at: IsoDateTime;
}

export interface ContactListInput {
    name: string;
    description?: string | null;
    folder_id?: Uuid | null;
}

export type ContactListSortField = 'name' | 'created_at' | 'contact_count' | 'last_import_at';

export interface ContactListQuery {
    q?: string;
    folder_id?: Uuid | 'none';
    sort?: ContactListSortField | `-${ContactListSortField}`;
    page?: number;
    per_page?: number;
}

export type ListMembershipAction = 'add' | 'remove';

/** POST /recipient-lists/{id}/contacts (one of recipient_ids / emails / selection). */
export interface ListMembershipInput {
    action: ListMembershipAction;
    recipient_ids?: Uuid[];
    emails?: string[];
    selection?: ContactSelection | null;
}

export interface ListMembershipResult {
    added: number;
    removed: number;
    already: number;
    not_found: string[];
}

export interface ContactListDeleteQuery {
    delete_contacts?: 0 | 1;
}
