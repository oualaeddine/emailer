import type { IsoDateTime, UserRef, Uuid } from '@/Lib/types/common';
import type { EditorType, EmailDesign } from '@/Lib/types/editor';
export type { HtmlImportAsset, HtmlImportResult } from '@/Lib/types/editor';

export interface Template {
    id: string;
    name: string;
    category: string | null;
    thumbnail_path: string | null;
    html_content: string;
    is_archived: boolean;
    usage_count: number;
    updated_at: string | null;
}

// ===========================================================================
// Brevo parity v2 — docs/45-brevo-api-contracts.md §45.4.4 (WP-44 picker +
// code path) and §45.6.2 (WP-62 folders, gallery, versions, thumbnails).
// `Template` above stays unchanged for the legacy pages.
// ===========================================================================

/** Labels: fr.templates.scopes. */
export type TemplateScope = 'marketing' | 'transactional';

/** Labels: fr.templates.galleryCategories. */
export type TemplateGalleryCategory =
    | 'newsletter'
    | 'promotion'
    | 'event'
    | 'welcome'
    | 'announcement'
    | 'seasonal'
    | 'transactional'
    | 'basic';

/** List item — legacy `Template` keys kept; `html_content` is null in list responses. */
export interface TemplateV2 extends Omit<Template, 'html_content'> {
    html_content: string | null;
    editor_type: EditorType;
    scope: TemplateScope;
    folder_id: Uuid | null;
    is_gallery: boolean;
    gallery_category: TemplateGalleryCategory | null;
    thumbnail_url: string | null;
    subject: string | null;
    created_by: UserRef | null;
    created_at: IsoDateTime;
}

export interface TemplateV2Detail extends TemplateV2 {
    html_content: string;
    design_json: EmailDesign | null;
    versions_count: number;
}

export type TemplateSortField = 'updated_at' | 'name' | 'created_at' | 'usage_count';

export interface TemplateListQuery {
    scope?: TemplateScope;
    folder_id?: Uuid | 'none';
    q?: string;
    is_gallery?: boolean;
    editor_type?: EditorType;
    is_archived?: boolean;
    category?: string;
    sort?: TemplateSortField | `-${TemplateSortField}`;
    page?: number;
    per_page?: number;
}

export interface TemplateInput {
    name: string;
    category?: string | null;
    editor_type?: EditorType;
    scope?: TemplateScope;
    folder_id?: Uuid | null;
    html_content?: string;
    design_json?: EmailDesign | null;
    subject?: string | null;
}

export interface TemplateDuplicateInput {
    name?: string;
    folder_id?: Uuid | null;
    scope?: TemplateScope;
}

/** POST /templates/{id}/test-send (1–5 emails) → TestSendResult. */
export interface TemplateTestSendInput {
    emails: string[];
    contact_id?: Uuid;
    subject?: string;
}

export interface TemplateImportFromUrlInput {
    url: string;
}

export interface TemplateGalleryCategoryCount {
    key: TemplateGalleryCategory;
    count: number;
}

/** GET /templates/gallery. */
export interface TemplateGalleryResponse {
    data: TemplateV2[];
    meta: { categories: TemplateGalleryCategoryCount[] };
}

export interface TemplateVersion {
    id: Uuid;
    version_number: number;
    author: UserRef | null;
    editor_type: EditorType;
    created_at: IsoDateTime;
    is_current: boolean;
}
