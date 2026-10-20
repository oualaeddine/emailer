import axios from 'axios';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type { CursorPaginatedResponse } from '@/Lib/types/audit';
import type { DataItem, DataList, Uuid } from '@/Lib/types/common';
import type {
    FormListQuery,
    FormSubmission,
    FormSubmissionsQuery,
    SubscriptionForm,
    SubscriptionFormInput,
} from '@/Lib/types/forms';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type { ContactList } from '@/Lib/types/lists';
import type { TemplateV2 } from '@/Lib/types/templates';

/**
 * docs/45-brevo-api-contracts.md §45.7.4 — subscription forms
 * (/api/v1/forms) plus the read-only lookups the builder needs:
 * §45.5.1 GET /contact-attributes (field picker), §45.5.6 GET
 * /recipient-lists (target lists), §45.4.4 GET /templates?scope=transactional
 * (double opt-in confirmation e-mail).
 */

const BASE = '/api/v1';
const LOOKUP_PAGE_SIZE = 100;

// ---------------------------------------------------------------------------
// Forms
// ---------------------------------------------------------------------------

export async function fetchForms(query: FormListQuery = {}): Promise<PaginatedResponse<SubscriptionForm>> {
    const response = await axios.get<PaginatedResponse<SubscriptionForm>>(`${BASE}/forms`, { params: query });

    return response.data;
}

export async function fetchForm(id: Uuid): Promise<SubscriptionForm> {
    const response = await axios.get<DataItem<SubscriptionForm>>(`${BASE}/forms/${id}`);

    return response.data.data;
}

export async function createForm(input: SubscriptionFormInput): Promise<SubscriptionForm> {
    const response = await axios.post<DataItem<SubscriptionForm>>(`${BASE}/forms`, input);

    return response.data.data;
}

export async function updateForm(id: Uuid, input: Partial<SubscriptionFormInput>): Promise<SubscriptionForm> {
    const response = await axios.patch<DataItem<SubscriptionForm>>(`${BASE}/forms/${id}`, input);

    return response.data.data;
}

/** `204`; the public URL then answers 404. */
export async function deleteForm(id: Uuid): Promise<void> {
    await axios.delete(`${BASE}/forms/${id}`);
}

/** The copy is created as a draft. */
export async function duplicateForm(id: Uuid): Promise<SubscriptionForm> {
    const response = await axios.post<DataItem<SubscriptionForm>>(`${BASE}/forms/${id}/duplicate`);

    return response.data.data;
}

/** `422` without a target list or an EMAIL field. */
export async function publishForm(id: Uuid): Promise<SubscriptionForm> {
    const response = await axios.post<DataItem<SubscriptionForm>>(`${BASE}/forms/${id}/publish`);

    return response.data.data;
}

export async function unpublishForm(id: Uuid): Promise<SubscriptionForm> {
    const response = await axios.post<DataItem<SubscriptionForm>>(`${BASE}/forms/${id}/unpublish`);

    return response.data.data;
}

export async function fetchFormSubmissions(id: Uuid, query: FormSubmissionsQuery = {}): Promise<CursorPaginatedResponse<FormSubmission>> {
    const response = await axios.get<CursorPaginatedResponse<FormSubmission>>(`${BASE}/forms/${id}/submissions`, { params: query });

    return response.data;
}

// ---------------------------------------------------------------------------
// Builder lookups
// ---------------------------------------------------------------------------

/** §45.5.1 — attributes offered in the *Champs* picker. */
export async function fetchFormAttributes(): Promise<ContactAttribute[]> {
    const response = await axios.get<DataList<ContactAttribute>>(`${BASE}/contact-attributes`);

    return response.data.data;
}

/** §45.5.6 — target lists, sorted by name. */
export async function fetchFormLists(q = ''): Promise<ContactList[]> {
    const response = await axios.get<PaginatedResponse<ContactList>>(`${BASE}/recipient-lists`, {
        params: { ...(q ? { q } : {}), sort: 'name', per_page: LOOKUP_PAGE_SIZE },
    });

    return response.data.data;
}

/** §45.4.4 — any non-archived template (marketing or transactional) for the double opt-in confirmation e-mail. */
export async function fetchDoiTemplates(): Promise<TemplateV2[]> {
    const response = await axios.get<PaginatedResponse<TemplateV2>>(`${BASE}/templates`, {
        params: { is_gallery: 0, is_archived: 0, sort: 'name', per_page: LOOKUP_PAGE_SIZE },
    });

    return response.data.data;
}

// ---------------------------------------------------------------------------
// Errors (doc 29 §29.1 envelope)
// ---------------------------------------------------------------------------

interface ErrorBody {
    message?: string;
    errors?: Record<string, string[] | string>;
}

export function apiErrorStatus(error: unknown): number | null {
    return axios.isAxiosError(error) ? (error.response?.status ?? null) : null;
}

export function apiErrorMessage(error: unknown, fallback: string): string {
    if (axios.isAxiosError<ErrorBody>(error)) {
        const body = error.response?.data;
        const first = body?.errors ? Object.values(body.errors)[0] : undefined;
        const detail = Array.isArray(first) ? first[0] : first;

        return detail ?? body?.message ?? fallback;
    }

    return fallback;
}

/** `422` field errors keyed by dot path (`fields.0.label`), first message each. */
export function apiValidationErrors(error: unknown): Record<string, string> | null {
    if (!axios.isAxiosError<ErrorBody>(error) || error.response?.status !== 422 || !error.response.data?.errors) {
        return null;
    }
    const result: Record<string, string> = {};
    for (const [key, value] of Object.entries(error.response.data.errors)) {
        const message = Array.isArray(value) ? value[0] : value;
        if (message) result[key] = message;
    }

    return result;
}

export const forms = {
    list: fetchForms,
    get: fetchForm,
    create: createForm,
    update: updateForm,
    remove: deleteForm,
    duplicate: duplicateForm,
    publish: publishForm,
    unpublish: unpublishForm,
    submissions: fetchFormSubmissions,
    attributes: fetchFormAttributes,
    lists: fetchFormLists,
    doiTemplates: fetchDoiTemplates,
};
