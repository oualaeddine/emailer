import axios from 'axios';
import type { ImportJob, ImportRow } from '@/Lib/types/imports';
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
