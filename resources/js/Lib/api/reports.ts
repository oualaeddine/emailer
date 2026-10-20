import axios from 'axios';
import type { CursorPaginatedResponse } from '@/Lib/types/audit';
import type { DataItem, DataList, Uuid } from '@/Lib/types/common';
import type {
    CampaignComparisonRow,
    ReportContactRow,
    ReportContactsQuery,
    ReportSegmentInput,
    ReportTab,
    ReportTabPayloads,
} from '@/Lib/types/reports';
import type { Segment } from '@/Lib/types/segments';

/**
 * docs/45-brevo-api-contracts.md §45.6.5 — campaign report endpoints (WP-72),
 * consumed by Campaigns/Report (WP-71).
 */
export async function fetchReportTab<T extends ReportTab>(campaignId: Uuid, tab: T): Promise<ReportTabPayloads[T]> {
    const response = await axios.get<DataItem<ReportTabPayloads[T]>>(`/api/v1/campaigns/${campaignId}/report/${tab}`);

    return response.data.data;
}

/** GET /campaigns/{id}/report/contacts — cursor-paginated contacts behind a metric. */
export async function fetchReportContacts(
    campaignId: Uuid,
    query: ReportContactsQuery,
): Promise<CursorPaginatedResponse<ReportContactRow>> {
    const response = await axios.get<CursorPaginatedResponse<ReportContactRow>>(
        `/api/v1/campaigns/${campaignId}/report/contacts`,
        { params: query },
    );

    return response.data;
}

/** POST /campaigns/{id}/report/segment — "Voir les contacts" → 201 Segment. */
export async function createReportSegment(campaignId: Uuid, payload: ReportSegmentInput): Promise<Segment> {
    const response = await axios.post<DataItem<Segment>>(`/api/v1/campaigns/${campaignId}/report/segment`, payload);

    return response.data.data;
}

export function reportExportUrl(campaignId: Uuid, tab: ReportTab): string {
    return `/api/v1/campaigns/${campaignId}/report/export?tab=${encodeURIComponent(tab)}`;
}

function filenameFromDisposition(header: unknown): string | null {
    if (typeof header !== 'string') {
        return null;
    }
    const encoded = /filename\*=UTF-8''([^;]+)/i.exec(header);
    if (encoded) {
        try {
            return decodeURIComponent(encoded[1]);
        } catch {
            // fall through to the plain filename
        }
    }
    const plain = /filename="?([^";]+)"?/i.exec(header);

    return plain ? plain[1] : null;
}

/**
 * GET /campaigns/{id}/report/export?tab= — `text/csv` (`;`, UTF-8 BOM). Downloaded
 * through axios (session cookie + XSRF) and saved with the server's filename.
 */
export async function downloadReportExport(campaignId: Uuid, tab: ReportTab, fallbackName: string): Promise<void> {
    const response = await axios.get<Blob>(reportExportUrl(campaignId, tab), { responseType: 'blob' });
    const filename = filenameFromDisposition(response.headers['content-disposition']) ?? fallbackName;
    const url = URL.createObjectURL(response.data);
    const anchor = document.createElement('a');
    anchor.href = url;
    anchor.download = filename;
    document.body.appendChild(anchor);
    anchor.click();
    anchor.remove();
    window.setTimeout(() => URL.revokeObjectURL(url), 1000);
}

/** GET /campaigns/compare?ids=a,b (2–5 campaigns). */
export async function fetchCampaignComparison(ids: Uuid[]): Promise<CampaignComparisonRow[]> {
    const response = await axios.get<DataList<CampaignComparisonRow>>('/api/v1/campaigns/compare', {
        params: { ids: ids.join(',') },
    });

    return response.data.data;
}
