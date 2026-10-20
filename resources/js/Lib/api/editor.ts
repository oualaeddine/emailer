import axios from 'axios';
import type { DataItem, DataList, Uuid } from '@/Lib/types/common';
import type { MergeTag } from '@/Lib/types/attributes';
import type { EmailDesign, MjmlValidationError, MjmlValidationResult } from '@/Lib/types/editor';
import type { TemplateV2Detail, TemplateVersion } from '@/Lib/types/templates';
import { FALLBACK_MERGE_TAGS } from '@/Lib/api/campaigns';

/**
 * docs/45-brevo-api-contracts.md §45.6.1 (WP-60) — EmailBuilder calls:
 * server MJML validation, template design save, versions (read-only) and the
 * personalisation menu. Campaign load / design save reuse
 * `fetchCampaignV2` (Lib/api/campaigns) and `saveCampaignDesign` (Lib/api/templates).
 */

/** POST /templates/mjml/validate — `html` is the browser compile, used by the server only as a trusted fallback. */
export async function validateMjml(mjml: string, html?: string): Promise<MjmlValidationResult> {
    const response = await axios.post<DataItem<MjmlValidationResult>>('/api/v1/templates/mjml/validate', { mjml, html });

    return response.data.data;
}

/** PATCH /templates/{id} with the builder output (creates a `template_versions` row). */
export async function saveTemplateDesign(id: Uuid, design: EmailDesign, html: string): Promise<TemplateV2Detail> {
    const response = await axios.patch<DataItem<TemplateV2Detail>>(`/api/v1/templates/${id}`, {
        editor_type: 'dnd',
        design_json: design,
        html_content: html,
    });

    return response.data.data;
}

/** GET /templates/{id}/versions — null when the API is unavailable. */
export async function fetchTemplateVersions(id: Uuid): Promise<TemplateVersion[] | null> {
    try {
        const response = await axios.get<DataList<TemplateVersion>>(`/api/v1/templates/${id}/versions`);

        return Array.isArray(response.data.data) ? response.data.data : null;
    } catch {
        return null;
    }
}

/** GET /contact-attributes/merge-tags; static contact tags when the endpoint fails. */
export async function fetchEditorMergeTags(): Promise<MergeTag[]> {
    try {
        const response = await axios.get<DataList<MergeTag>>('/api/v1/contact-attributes/merge-tags');
        const rows = (response.data.data ?? []).filter(
            (row): row is MergeTag => typeof row === 'object' && row !== null && typeof row.tag === 'string',
        );
        if (rows.length > 0) {
            return rows.map((row) => ({ ...row, label: row.label || row.tag, group: row.group ?? 'contact' }));
        }
    } catch {
        // Fall through to the static list.
    }

    return FALLBACK_MERGE_TAGS.map((tag) => ({
        tag,
        label: tag.replace(/^\{\{\s*contact\.|\s*\}\}$/g, ''),
        group: 'contact',
        attribute: tag.replace(/^\{\{\s*contact\.|\s*\}\}$/g, ''),
    }));
}

/** Server error of a design save, normalised for the builder's MessageBar. */
export interface DesignSaveError {
    status: number | null;
    message: string | null;
    errors: MjmlValidationError[];
}

/** Reads a 422 (`design_json.mjml` and other field errors), 403, 404 or 409 response. */
export function toDesignSaveError(error: unknown): DesignSaveError {
    if (!axios.isAxiosError(error) || !error.response) {
        return { status: null, message: null, errors: [] };
    }
    const data = error.response.data as { message?: unknown; errors?: Record<string, unknown> } | undefined;
    const errors: MjmlValidationError[] = [];
    const bag = data?.errors ?? {};
    const keys = Object.keys(bag).sort((a, b) => Number(b === 'design_json.mjml') - Number(a === 'design_json.mjml'));
    for (const key of keys) {
        const messages = bag[key];
        for (const message of Array.isArray(messages) ? messages : [messages]) {
            if (typeof message === 'string') {
                errors.push({ line: null, message, tag_name: null });
            }
        }
    }

    return {
        status: error.response.status,
        message: typeof data?.message === 'string' ? data.message : null,
        errors,
    };
}
