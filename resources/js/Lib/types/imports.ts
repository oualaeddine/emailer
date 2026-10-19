import type { ContactAttributeType } from '@/Lib/types/attributes';
import type { UserRef, Uuid } from '@/Lib/types/common';

export interface ImportJob {
    id: string;
    source_type: string;
    original_filename: string | null;
    status: string;
    total_rows: number;
    processed_rows: number;
    imported_count: number;
    duplicate_count: number;
    error_count: number;
    column_mapping: Record<string, string> | null;
    started_at: string | null;
    completed_at: string | null;
    created_at: string;
}

export interface ImportRow {
    id: number;
    row_number: number;
    raw_data: Record<string, unknown>;
    parsed_email: string | null;
    validation_status: 'valid' | 'invalid' | 'duplicate';
}

// ===========================================================================
// Brevo parity v2 — docs/45-brevo-api-contracts.md §45.5.7 (WP-55 import
// wizard + history). `ImportJob` above stays unchanged; `status` keeps the
// existing pipeline values (labels fr.imports.statusLabels, raw fallback).
// ===========================================================================

/** Labels: fr.imports.wizard.sourceTypes. */
export type ImportSourceType = 'csv' | 'xlsx' | 'txt' | 'paste' | 'pagejaunes';

/** Wizard steps (Fichier → Correspondance → Listes → Paramètres → Lancer). Labels: fr.imports.wizard.steps. */
export type ImportWizardStep = 'file' | 'mapping' | 'lists' | 'settings' | 'launch';

export type ImportColumnTarget = { type: 'attribute'; attribute: string } | { type: 'ignore' };

export interface ImportColumnMapping {
    column: number;
    header: string;
    target: ImportColumnTarget;
}

export interface ImportPreview {
    headers: string[];
    /** First 5 rows. */
    rows: string[][];
    total_rows: number;
    delimiter: string | null;
    suggested_mapping: ImportColumnMapping[];
}

export interface ImportNewList {
    name: string;
    folder_id: Uuid | null;
}

export interface ImportSummary {
    created: number;
    updated: number;
    skipped: number;
    failed: number;
    blocklisted: number;
    added_to_lists: number;
}

export interface ImportJobV2 extends ImportJob {
    list_ids: Uuid[];
    new_list: ImportNewList | null;
    mapping: ImportColumnMapping[];
    update_existing: boolean;
    empty_values_overwrite: boolean;
    mark_blocklisted: boolean;
    mark_opt_in: boolean;
    summary: ImportSummary | null;
    user: UserRef | null;
    preview: ImportPreview | null;
    errors_url: string | null;
}

/** POST /import-jobs (JSON variants; file uploads are multipart `file` + `source_type`). */
export type ImportCreateInput =
    | { source_type: 'paste'; content: string }
    | { source_type: 'pagejaunes'; external_ids: number[] };

export interface ImportCreateAttribute {
    name: string;
    type: ContactAttributeType;
}

/** PUT /import-jobs/{id}/mapping — exactly one column must map to EMAIL. */
export interface ImportMappingInput {
    mapping: ImportColumnMapping[];
    /** "Créer un attribut" inline (needs attributes.manage). */
    create_attributes?: ImportCreateAttribute[];
}

/** PUT /import-jobs/{id}/settings. */
export interface ImportSettingsInput {
    list_ids: Uuid[];
    new_list?: ImportNewList | null;
    update_existing: boolean;
    empty_values_overwrite?: boolean;
    mark_blocklisted?: boolean;
    mark_opt_in?: boolean;
}

export interface ImportHistoryQuery {
    status?: string;
    page?: number;
    per_page?: number;
    sort?: '-created_at' | 'created_at';
}
