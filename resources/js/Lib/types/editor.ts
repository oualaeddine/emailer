/**
 * docs/45-brevo-api-contracts.md §45.6.1 (WP-60 EmailBuilder core, MJML
 * validation) and §45.7.1 (WP-61 saved / pre-made sections, dynamic content).
 */
import type { IsoDateTime, UserRef, Uuid } from '@/Lib/types/common';
import type { RuleTree } from '@/Lib/types/segments';

/** How a design was authored. Labels: fr.editor.editorTypes. */
export type EditorType = 'dnd' | 'html' | 'rich_text';

/** Block palette keys. Labels: fr.editor.blocks. */
export type EmailBlockType =
    | 'title'
    | 'text'
    | 'image'
    | 'button'
    | 'divider'
    | 'spacer'
    | 'social'
    | 'columns'
    | 'html'
    | 'video'
    | 'logo'
    | 'navigation'
    | 'dynamic_content'
    | 'product'
    | 'saved_section';

export interface EmailButtonStyles {
    background_color: string;
    text_color: string;
    border_radius: number;
    font_weight: string;
}

export interface EmailMobileStyles {
    font_scale: number;
    hide_on_mobile_padding: boolean;
}

/** "Style" tab — global design settings set once for the whole email. */
export interface EmailGlobalStyles {
    font_family: string;
    text_color: string;
    link_color: string;
    link_underline: boolean;
    background_color: string;
    content_background_color: string;
    content_width: number;
    button: EmailButtonStyles;
    mobile: EmailMobileStyles;
}

/** Value stored in `design_json` (campaigns, templates, A/B variants). */
export interface EmailDesign {
    format: 'grapesjs-mjml';
    version: 1;
    /** Source of truth for server re-compilation. */
    mjml: string;
    /** GrapesJS project data (pages, styles, assets). */
    project: Record<string, unknown>;
    global_styles: EmailGlobalStyles;
}

export interface MjmlValidationInput {
    mjml: string;
}

export interface MjmlValidationError {
    line: number | null;
    message: string;
    tag_name: string | null;
}

export interface MjmlValidationResult {
    valid: boolean;
    html: string;
    errors: MjmlValidationError[];
    size_bytes: number;
}

export interface SavedSection {
    id: Uuid;
    name: string;
    mjml: string;
    component: Record<string, unknown>;
    thumbnail_url: string | null;
    created_by: UserRef | null;
    created_at: IsoDateTime;
}

export interface SavedSectionInput {
    name: string;
    mjml: string;
    component: Record<string, unknown>;
}

/** Labels: fr.editor.sectionCategories. */
export type PremadeSectionCategory = 'header' | 'content' | 'footer' | 'product';

export interface PremadeSection {
    key: string;
    label: string;
    category: PremadeSectionCategory;
    mjml: string;
    thumbnail_url: string;
}

export interface DynamicContentVariant {
    condition: RuleTree;
    mjml: string;
}

/** Dynamic content block configuration (evaluated by WP-63 at render time). */
export interface DynamicContentBlock {
    type: 'dynamic_content';
    variants: DynamicContentVariant[];
    fallback_mjml: string;
}

/** Result of the template picker's "Coder" path (import-from-url / import-zip). */
export interface HtmlImportAsset {
    filename: string;
    url: string;
}

export interface HtmlImportResult {
    html: string;
    title: string | null;
    assets: HtmlImportAsset[];
    warnings: string[];
}

/** Editor save payload (PUT /campaigns/{id}/design). */
export interface EditorSavePayload {
    editor_type: EditorType;
    html_body: string;
    design_json: EmailDesign | null;
}
