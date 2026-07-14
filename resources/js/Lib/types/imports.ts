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
