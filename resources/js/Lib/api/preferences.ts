import axios from 'axios';
import type { DataItem, DataList } from '@/Lib/types/common';
import type {
    UnsubscribePage,
    UnsubscribePageInput,
    UpdateProfileForm,
    UpdateProfileFormInput,
} from '@/Lib/types/preferences';

/**
 * docs/45-brevo-api-contracts.md §45.6.6 — /api/v1/unsubscribe-pages,
 * /api/v1/update-profile-forms.
 */
export async function fetchUnsubscribePages(): Promise<UnsubscribePage[]> {
    const response = await axios.get<DataList<UnsubscribePage>>('/api/v1/unsubscribe-pages');

    return response.data.data;
}

export async function createUnsubscribePage(payload: UnsubscribePageInput): Promise<UnsubscribePage> {
    const response = await axios.post<DataItem<UnsubscribePage>>('/api/v1/unsubscribe-pages', payload);

    return response.data.data;
}

export async function updateUnsubscribePage(id: string, payload: Partial<UnsubscribePageInput>): Promise<UnsubscribePage> {
    const response = await axios.patch<DataItem<UnsubscribePage>>(`/api/v1/unsubscribe-pages/${id}`, payload);

    return response.data.data;
}

export async function deleteUnsubscribePage(id: string): Promise<void> {
    await axios.delete(`/api/v1/unsubscribe-pages/${id}`);
}

export async function setDefaultUnsubscribePage(id: string): Promise<UnsubscribePage> {
    const response = await axios.post<DataItem<UnsubscribePage>>(`/api/v1/unsubscribe-pages/${id}/default`);

    return response.data.data;
}

export function unsubscribePagePreviewUrl(id: string): string {
    return `/api/v1/unsubscribe-pages/${id}/preview`;
}

export async function fetchUpdateProfileForms(): Promise<UpdateProfileForm[]> {
    const response = await axios.get<DataList<UpdateProfileForm>>('/api/v1/update-profile-forms');

    return response.data.data;
}

export async function createUpdateProfileForm(payload: UpdateProfileFormInput): Promise<UpdateProfileForm> {
    const response = await axios.post<DataItem<UpdateProfileForm>>('/api/v1/update-profile-forms', payload);

    return response.data.data;
}

export async function updateUpdateProfileForm(
    id: string,
    payload: Partial<UpdateProfileFormInput>,
): Promise<UpdateProfileForm> {
    const response = await axios.patch<DataItem<UpdateProfileForm>>(`/api/v1/update-profile-forms/${id}`, payload);

    return response.data.data;
}

export async function deleteUpdateProfileForm(id: string): Promise<void> {
    await axios.delete(`/api/v1/update-profile-forms/${id}`);
}

export async function setDefaultUpdateProfileForm(id: string): Promise<UpdateProfileForm> {
    const response = await axios.post<DataItem<UpdateProfileForm>>(`/api/v1/update-profile-forms/${id}/default`);

    return response.data.data;
}

export function updateProfileFormPreviewUrl(id: string): string {
    return `/api/v1/update-profile-forms/${id}/preview`;
}

/** Extracts the French message of a doc 29 §29.1 error envelope. */
export function apiErrorMessage(error: unknown, fallback: string): string {
    const e = error as { response?: { data?: { message?: string } } };

    return e?.response?.data?.message ?? fallback;
}
