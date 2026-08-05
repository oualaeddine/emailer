import axios from 'axios';
import type { AuditLog, AuditLogFilters, CursorPaginatedResponse } from '@/Lib/types/audit';

/**
 * docs/29-api-specification.md §29.2 (lines 177-178).
 */

export async function fetchAuditLogs(
    filters: AuditLogFilters = {},
    cursor: string | null = null,
): Promise<CursorPaginatedResponse<AuditLog>> {
    const response = await axios.get<CursorPaginatedResponse<AuditLog>>('/api/v1/audit-logs', {
        params: { ...filters, ...(cursor ? { cursor } : {}) },
    });

    return response.data;
}

/**
 * Streams the filtered audit log as CSV and triggers a browser download.
 * Uses `responseType: 'blob'` since this is a file download, not a JSON
 * payload — there is no existing download helper elsewhere in the
 * codebase to reuse (checked `Lib/api/**` and `Lib/**` before adding this).
 */
export async function exportAuditLogs(filters: AuditLogFilters = {}): Promise<void> {
    const response = await axios.post('/api/v1/audit-logs/export', filters, { responseType: 'blob' });

    const url = window.URL.createObjectURL(new Blob([response.data as BlobPart]));
    const link = document.createElement('a');
    link.href = url;
    link.download = `audit-log-${new Date().toISOString().slice(0, 10)}.csv`;
    document.body.appendChild(link);
    link.click();
    link.remove();
    window.URL.revokeObjectURL(url);
}
