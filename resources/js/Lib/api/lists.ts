import axios from 'axios';
import type { AsyncOperation, AsyncExportResult, DataItem, DataList, Folder, FolderInput, Uuid } from '@/Lib/types/common';
import type { ContactIndexResponse } from '@/Lib/types/contacts';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type {
    ContactList,
    ContactListDeleteQuery,
    ContactListInput,
    ContactListQuery,
    ListMembershipInput,
    ListMembershipResult,
} from '@/Lib/types/lists';

/**
 * docs/45-brevo-api-contracts.md §45.5.6 (WP-53) — lists & folders client.
 */
export async function fetchLists(query: ContactListQuery = {}): Promise<PaginatedResponse<ContactList>> {
    const response = await axios.get<PaginatedResponse<ContactList>>('/api/v1/recipient-lists', { params: query });

    return response.data;
}

export async function fetchList(id: Uuid): Promise<ContactList> {
    const response = await axios.get<DataItem<ContactList>>(`/api/v1/recipient-lists/${id}`);

    return response.data.data;
}

export async function createList(input: ContactListInput): Promise<ContactList> {
    const response = await axios.post<DataItem<ContactList>>('/api/v1/recipient-lists', input);

    return response.data.data;
}

export async function updateList(id: Uuid, input: Partial<ContactListInput>): Promise<ContactList> {
    const response = await axios.patch<DataItem<ContactList>>(`/api/v1/recipient-lists/${id}`, input);

    return response.data.data;
}

/** 204 → null; 202 → the list_delete operation. */
export async function deleteList(id: Uuid, query: ContactListDeleteQuery = {}): Promise<AsyncOperation | null> {
    const response = await axios.delete<DataItem<AsyncOperation> | ''>(`/api/v1/recipient-lists/${id}`, { params: query });

    return response.status === 202 ? (response.data as DataItem<AsyncOperation>).data : null;
}

/** 200 → result; 202 → the contacts_bulk operation. */
export async function updateListMembers(
    id: Uuid,
    input: ListMembershipInput,
): Promise<ListMembershipResult | AsyncOperation> {
    const response = await axios.post<DataItem<ListMembershipResult | AsyncOperation>>(
        `/api/v1/recipient-lists/${id}/contacts`,
        input,
    );

    return response.data.data;
}

export async function startListExport(id: Uuid): Promise<AsyncOperation<AsyncExportResult>> {
    const response = await axios.post<DataItem<AsyncOperation<AsyncExportResult>>>(`/api/v1/recipient-lists/${id}/export`);

    return response.data.data;
}

export async function fetchListOperation(statusUrl: string): Promise<AsyncOperation<AsyncExportResult>> {
    const response = await axios.get<DataItem<AsyncOperation<AsyncExportResult>>>(statusUrl);

    return response.data.data;
}

export async function fetchListFolders(): Promise<Folder[]> {
    const response = await axios.get<DataList<Folder>>('/api/v1/list-folders', { params: { kind: 'list' } });

    return response.data.data;
}

export async function createListFolder(input: FolderInput): Promise<Folder> {
    const response = await axios.post<DataItem<Folder>>('/api/v1/list-folders', { ...input, kind: 'list' });

    return response.data.data;
}

export async function renameListFolder(id: Uuid, input: FolderInput): Promise<Folder> {
    const response = await axios.patch<DataItem<Folder>>(`/api/v1/list-folders/${id}`, input);

    return response.data.data;
}

export async function deleteListFolder(id: Uuid, deleteChildren = false): Promise<void> {
    await axios.delete(`/api/v1/list-folders/${id}`, { params: { delete_children: deleteChildren ? 1 : 0 } });
}

/** Members of a list: the contacts index scoped to `list_id` (§45.5.4). */
export async function fetchListContacts(id: Uuid, cursor?: string | null): Promise<ContactIndexResponse> {
    const response = await axios.get<ContactIndexResponse>('/api/v1/recipients', {
        params: { pagination: 'cursor', list_id: id, per_page: 25, cursor: cursor ?? undefined },
    });

    return response.data;
}
