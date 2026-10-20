import axios from 'axios';
import type {
    CampaignDesignCopyInput,
    CampaignDesignInput,
    CampaignListResponse,
    CampaignV2,
    CampaignV2Detail,
    TestSendResult,
} from '@/Lib/types/campaigns';
import type { DataItem, DataList, Folder, FolderInput, Uuid } from '@/Lib/types/common';
import type {
    HtmlImportResult,
    Template,
    TemplateDuplicateInput,
    TemplateGalleryResponse,
    TemplateImportFromUrlInput,
    TemplateListQuery,
    TemplateTestSendInput,
    TemplateV2,
    TemplateV2Detail,
    TemplateVersion,
} from '@/Lib/types/templates';

/**
 * docs/29-api-specification.md — /api/v1/templates.
 */
export async function fetchTemplates(): Promise<Template[]> {
    const response = await axios.get<{ data: Template[] }>('/api/v1/templates');

    return response.data.data;
}

export async function createTemplate(payload: { name: string; category?: string; html_content: string }): Promise<Template> {
    const response = await axios.post<{ data: Template }>('/api/v1/templates', payload);

    return response.data.data;
}

export async function archiveTemplate(id: string): Promise<Template> {
    const response = await axios.post<{ data: Template }>(`/api/v1/templates/${id}/archive`);

    return response.data.data;
}

export async function deleteTemplate(id: string): Promise<void> {
    await axios.delete(`/api/v1/templates/${id}`);
}

// ===========================================================================
// Brevo parity v2 — docs/45-brevo-api-contracts.md §45.4.4 (WP-44).
// ===========================================================================

export interface PaginatedList<T> {
    data: T[];
    meta: { current_page: number; last_page: number; per_page: number; total: number };
}

export async function fetchTemplatesV2(query: TemplateListQuery = {}): Promise<PaginatedList<TemplateV2>> {
    const params = { ...query, per_page: query.per_page ?? 50, is_gallery: query.is_gallery === undefined ? undefined : query.is_gallery ? 1 : 0 };
    const response = await axios.get<PaginatedList<TemplateV2>>('/api/v1/templates', { params });

    return response.data;
}

export async function fetchTemplate(id: Uuid): Promise<TemplateV2Detail> {
    const response = await axios.get<DataItem<TemplateV2Detail>>(`/api/v1/templates/${id}`);

    return response.data.data;
}

export async function duplicateTemplate(id: Uuid, input: TemplateDuplicateInput = {}): Promise<TemplateV2Detail> {
    const response = await axios.post<DataItem<TemplateV2Detail>>(`/api/v1/templates/${id}/duplicate`, input);

    return response.data.data;
}

export async function testSendTemplate(id: Uuid, input: TemplateTestSendInput): Promise<TestSendResult> {
    const response = await axios.post<DataItem<TestSendResult>>(`/api/v1/templates/${id}/test-send`, input);

    return response.data.data;
}

export async function importTemplateFromUrl(input: TemplateImportFromUrlInput): Promise<HtmlImportResult> {
    const response = await axios.post<DataItem<HtmlImportResult>>('/api/v1/templates/import-from-url', input);

    return response.data.data;
}

export async function importTemplateZip(file: File): Promise<HtmlImportResult> {
    const form = new FormData();
    form.append('file', file);
    const response = await axios.post<DataItem<HtmlImportResult>>('/api/v1/templates/import-zip', form);

    return response.data.data;
}

/** Template picker: GET /campaigns?status_tab=sent&sort=-sent_at. */
export async function fetchPreviousCampaigns(q?: string): Promise<CampaignV2[]> {
    const response = await axios.get<CampaignListResponse>('/api/v1/campaigns', {
        params: { status_tab: 'sent', sort: '-sent_at', per_page: 50, q: q || undefined },
    });

    return response.data.data;
}

/** PUT /campaigns/{id}/design (WP-42). */
export async function saveCampaignDesign(campaignId: Uuid, input: CampaignDesignInput): Promise<CampaignV2Detail> {
    const response = await axios.put<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${campaignId}/design`, input);

    return response.data.data;
}

/** POST /campaigns/{id}/design/copy (WP-42). */
export async function copyCampaignDesign(campaignId: Uuid, input: CampaignDesignCopyInput): Promise<CampaignV2Detail> {
    const response = await axios.post<DataItem<CampaignV2Detail>>(`/api/v1/campaigns/${campaignId}/design/copy`, input);

    return response.data.data;
}

// ===========================================================================
// WP-62 — docs/45-brevo-api-contracts.md §45.6.2 (folders, gallery, versions,
// thumbnails).
// ===========================================================================

export async function fetchTemplateGallery(category?: string): Promise<TemplateGalleryResponse> {
    const response = await axios.get<TemplateGalleryResponse>('/api/v1/templates/gallery', { params: { category } });

    return response.data;
}

export async function fetchTemplateFolders(): Promise<Folder[]> {
    const response = await axios.get<DataList<Folder>>('/api/v1/template-folders');

    return response.data.data;
}

export async function createTemplateFolder(input: FolderInput): Promise<Folder> {
    const response = await axios.post<DataItem<Folder>>('/api/v1/template-folders', input);

    return response.data.data;
}

export async function renameTemplateFolder(id: Uuid, input: FolderInput): Promise<Folder> {
    const response = await axios.patch<DataItem<Folder>>(`/api/v1/template-folders/${id}`, input);

    return response.data.data;
}

export async function deleteTemplateFolder(id: Uuid): Promise<void> {
    await axios.delete(`/api/v1/template-folders/${id}`);
}

/** "Déplacer": PATCH /templates/{id} { folder_id }. */
export async function moveTemplate(id: Uuid, folderId: Uuid | null): Promise<TemplateV2Detail> {
    const response = await axios.patch<DataItem<TemplateV2Detail>>(`/api/v1/templates/${id}`, { folder_id: folderId });

    return response.data.data;
}

export async function unarchiveTemplate(id: Uuid): Promise<TemplateV2> {
    const response = await axios.post<DataItem<TemplateV2>>(`/api/v1/templates/${id}/unarchive`);

    return response.data.data;
}

export async function fetchTemplateVersions(id: Uuid): Promise<TemplateVersion[]> {
    const response = await axios.get<DataList<TemplateVersion>>(`/api/v1/templates/${id}/versions`);

    return response.data.data;
}

/** `{version}` is the version_number. */
export async function restoreTemplateVersion(id: Uuid, versionNumber: number): Promise<TemplateV2Detail> {
    const response = await axios.post<DataItem<TemplateV2Detail>>(`/api/v1/templates/${id}/versions/${versionNumber}/restore`);

    return response.data.data;
}

export async function regenerateTemplateThumbnail(id: Uuid): Promise<TemplateV2> {
    const response = await axios.post<DataItem<TemplateV2>>(`/api/v1/templates/${id}/thumbnail`);

    return response.data.data;
}
