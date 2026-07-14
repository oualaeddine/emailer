import axios from 'axios';
import type { Draft, DraftVersion, Signature } from '@/Lib/types/composer';

/**
 * docs/29-api-specification.md §29.3 — /api/v1/drafts, /api/v1/signatures.
 */
export async function createDraft(payload: { subject?: string; html_body?: string }): Promise<Draft> {
    const response = await axios.post<{ data: Draft }>('/api/v1/drafts', payload);

    return response.data.data;
}

export async function autosaveDraft(
    id: string,
    payload: { subject?: string; html_body?: string; signature_id?: string | null },
): Promise<Draft> {
    const response = await axios.patch<{ data: Draft }>(`/api/v1/drafts/${id}`, payload);

    return response.data.data;
}

export async function saveDraftVersion(id: string): Promise<DraftVersion> {
    const response = await axios.post<{ data: DraftVersion }>(`/api/v1/drafts/${id}/versions`);

    return response.data.data;
}

export async function fetchDraftVersions(id: string): Promise<DraftVersion[]> {
    const response = await axios.get<{ data: DraftVersion[] }>(`/api/v1/drafts/${id}/versions`);

    return response.data.data;
}

export async function restoreDraftVersion(id: string, versionId: number): Promise<Draft> {
    const response = await axios.post<{ data: Draft }>(`/api/v1/drafts/${id}/versions/${versionId}/restore`);

    return response.data.data;
}

export async function fetchSignatures(): Promise<Signature[]> {
    const response = await axios.get<{ data: Signature[] }>('/api/v1/signatures');

    return response.data.data;
}
