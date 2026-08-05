import axios from 'axios';
import type {
    ReportingCampaignRow,
    ReportingFilters,
    ReportingSmtpAccountRow,
    ReportingSummary,
} from '@/Lib/types/reporting';

/**
 * docs/25-reporting.md, docs/29-api-specification.md — /api/v1/reporting.
 * `fetchCampaignAnalytics`'s convention (Lib/api/campaigns.ts): the summary
 * endpoint returns a plain JSON object, not a `{data: ...}` envelope; the
 * breakdown endpoints do use `{data: [...]}`.
 */

function cleanParams(filters: ReportingFilters): Record<string, string> {
    return Object.fromEntries(
        Object.entries(filters).filter(([, value]) => value !== undefined && value !== ''),
    ) as Record<string, string>;
}

export async function fetchReportingSummary(filters: ReportingFilters = {}): Promise<ReportingSummary> {
    const response = await axios.get<ReportingSummary>('/api/v1/reporting/summary', {
        params: cleanParams(filters),
    });

    return response.data;
}

export async function fetchReportingByCampaign(filters: ReportingFilters = {}): Promise<ReportingCampaignRow[]> {
    const response = await axios.get<{ data: ReportingCampaignRow[] }>('/api/v1/reporting/campaigns', {
        params: cleanParams(filters),
    });

    return response.data.data;
}

export async function fetchReportingBySmtpAccount(filters: ReportingFilters = {}): Promise<ReportingSmtpAccountRow[]> {
    const response = await axios.get<{ data: ReportingSmtpAccountRow[] }>('/api/v1/reporting/smtp-accounts', {
        params: cleanParams(filters),
    });

    return response.data.data;
}

/**
 * CSV export (docs/25-reporting.md §25.4) is a plain file download, not a
 * JSON fetch — the caller navigates the browser to this URL (e.g. via an
 * anchor's `href`) rather than calling this function with `axios`, so the
 * browser handles the `Content-Disposition` response itself. Exposed as a
 * URL builder (not a `window.location` side effect) so the calling
 * component stays declarative.
 */
export function reportingExportUrl(filters: ReportingFilters = {}): string {
    const params = new URLSearchParams(cleanParams(filters));
    const query = params.toString();

    return query ? `/api/v1/reporting/export?${query}` : '/api/v1/reporting/export';
}
