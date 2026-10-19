import axios from 'axios';
import type {
    ImportHistoryQuery,
    ImportJob,
    ImportJobV2,
    ImportMappingInput,
    ImportPreview,
    ImportRow,
    ImportSettingsInput,
} from '@/Lib/types/imports';
import type { ContactAttribute, ContactAttributeInput } from '@/Lib/types/attributes';
import type { DataList } from '@/Lib/types/common';
import type { ContactList, ContactListInput } from '@/Lib/types/lists';
import type { PaginatedResponse } from '@/Lib/types/identity';

/**
 * docs/29-api-specification.md §29.4 — /api/v1/import-jobs.
 */
export async function uploadImportFile(
    file: File,
    sourceType: 'csv' | 'excel',
): Promise<{ job: ImportJob; detectedHeaders: string[] }> {
    const formData = new FormData();
    formData.append('source_type', sourceType);
    formData.append('file', file);

    const response = await axios.post<{ data: ImportJob; detected_headers: string[] }>('/api/v1/import-jobs', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return { job: response.data.data, detectedHeaders: response.data.detected_headers };
}

export async function submitColumnMapping(
    jobId: string,
    columnMapping: Record<string, string>,
): Promise<ImportJob> {
    const response = await axios.put<{ data: ImportJob }>(`/api/v1/import-jobs/${jobId}/mapping`, {
        column_mapping: columnMapping,
    });

    return response.data.data;
}

export async function fetchImportRows(jobId: string): Promise<PaginatedResponse<ImportRow>> {
    const response = await axios.get<PaginatedResponse<ImportRow>>(`/api/v1/import-jobs/${jobId}/rows`);

    return response.data;
}

export async function commitImport(jobId: string, excludedRowIds: number[] = []): Promise<ImportJob> {
    const response = await axios.post<{ data: ImportJob }>(`/api/v1/import-jobs/${jobId}/commit`, {
        excluded_row_ids: excludedRowIds,
    });

    return response.data.data;
}

// ===========================================================================
// Brevo parity v2 — docs/45-brevo-api-contracts.md §45.5.7 (WP-55).
// ===========================================================================

/** Best-effort French message out of a Laravel error response. */
export function importErrorMessage(error: unknown, fallback: string): string {
    const response = (error as { response?: { data?: { message?: string; errors?: Record<string, string[]> } } })?.response;
    const first = response?.data?.errors ? Object.values(response.data.errors)[0]?.[0] : undefined;

    return first ?? response?.data?.message ?? fallback;
}

/** POST /import-jobs — multipart file (csv, xlsx, txt). */
export async function createImportFromFile(file: File): Promise<ImportJobV2> {
    const lower = file.name.toLowerCase();
    const sourceType = lower.endsWith('.xlsx') ? 'xlsx' : lower.endsWith('.txt') ? 'txt' : 'csv';
    const formData = new FormData();
    formData.append('source_type', sourceType);
    formData.append('file', file);

    const response = await axios.post<{ data: ImportJobV2 }>('/api/v1/import-jobs', formData, {
        headers: { 'Content-Type': 'multipart/form-data' },
    });

    return response.data.data;
}

/** POST /import-jobs — pasted content. */
export async function createImportFromPaste(content: string): Promise<ImportJobV2> {
    const response = await axios.post<{ data: ImportJobV2 }>('/api/v1/import-jobs', { source_type: 'paste', content });

    return response.data.data;
}

/** POST /import-jobs — PagesJaunes companies (`contacts` carries the resolved e-mails). */
export async function createImportFromPageJaunes(
    externalIds: number[],
    contacts: { email: string; company_name: string | null; external_id: number }[],
): Promise<ImportJobV2> {
    const response = await axios.post<{ data: ImportJobV2 }>('/api/v1/import-jobs', {
        source_type: 'pagejaunes',
        external_ids: externalIds,
        contacts,
    });

    return response.data.data;
}

export async function fetchImportJob(id: string): Promise<ImportJobV2> {
    const response = await axios.get<{ data: ImportJobV2 }>(`/api/v1/import-jobs/${id}`);

    return response.data.data;
}

export async function fetchImportPreview(id: string): Promise<ImportPreview> {
    const response = await axios.get<{ data: ImportPreview }>(`/api/v1/import-jobs/${id}/preview`);

    return response.data.data;
}

export async function saveImportMapping(id: string, input: ImportMappingInput): Promise<ImportJobV2> {
    const response = await axios.put<{ data: ImportJobV2 }>(`/api/v1/import-jobs/${id}/mapping`, input);

    return response.data.data;
}

export async function saveImportSettings(id: string, input: ImportSettingsInput): Promise<ImportJobV2> {
    const response = await axios.put<{ data: ImportJobV2 }>(`/api/v1/import-jobs/${id}/settings`, input);

    return response.data.data;
}

export async function launchImport(id: string): Promise<ImportJobV2> {
    const response = await axios.post<{ data: ImportJobV2 }>(`/api/v1/import-jobs/${id}/commit`, {});

    return response.data.data;
}

export async function fetchImportHistory(query: ImportHistoryQuery = {}): Promise<PaginatedResponse<ImportJobV2>> {
    const response = await axios.get<PaginatedResponse<ImportJobV2>>('/api/v1/import-jobs', {
        params: { sort: '-created_at', ...query },
    });

    return response.data;
}

/** GET /contact-attributes (WP-50 contract). */
export async function fetchContactAttributes(): Promise<ContactAttribute[]> {
    const response = await axios.get<DataList<ContactAttribute>>('/api/v1/contact-attributes');

    return response.data.data;
}

/** POST /contact-attributes (WP-50 contract) — inline "Créer un attribut". */
export async function createContactAttribute(input: ContactAttributeInput): Promise<ContactAttribute> {
    const response = await axios.post<{ data: ContactAttribute }>('/api/v1/contact-attributes', input);

    return response.data.data;
}

/** GET /recipient-lists (WP-53 contract). */
export async function fetchRecipientLists(q = ''): Promise<ContactList[]> {
    const response = await axios.get<PaginatedResponse<ContactList>>('/api/v1/recipient-lists', {
        params: { q: q || undefined, per_page: 100, sort: 'name' },
    });

    return response.data.data;
}

/** POST /recipient-lists (WP-53 contract) — kept for callers that create the list up-front. */
export async function createRecipientList(input: ContactListInput): Promise<ContactList> {
    const response = await axios.post<{ data: ContactList }>('/api/v1/recipient-lists', input);

    return response.data.data;
}
