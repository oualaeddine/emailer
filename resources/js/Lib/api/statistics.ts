import axios from 'axios';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type {
    StatisticsCampaignRow,
    StatisticsCampaignsQuery,
    StatisticsListRow,
    StatisticsQuery,
    StatisticsSummary,
    StatisticsTimeseries,
    StatisticsTimeseriesQuery,
} from '@/Lib/types/statistics';

/** docs/45 §45.6.5 — GET /api/v1/statistics/*. */

export async function fetchStatisticsSummary(query: StatisticsQuery): Promise<StatisticsSummary> {
    const response = await axios.get<{ data: StatisticsSummary }>('/api/v1/statistics/summary', { params: query });

    return response.data.data;
}

export async function fetchStatisticsTimeseries(query: StatisticsTimeseriesQuery): Promise<StatisticsTimeseries> {
    const response = await axios.get<{ data: StatisticsTimeseries }>('/api/v1/statistics/timeseries', {
        params: query,
    });

    return response.data.data;
}

export async function fetchStatisticsCampaigns(
    query: StatisticsCampaignsQuery,
): Promise<PaginatedResponse<StatisticsCampaignRow>> {
    const response = await axios.get<PaginatedResponse<StatisticsCampaignRow>>('/api/v1/statistics/campaigns', {
        params: query,
    });

    return response.data;
}

export async function fetchStatisticsLists(query: StatisticsQuery): Promise<StatisticsListRow[]> {
    const response = await axios.get<{ data: StatisticsListRow[] }>('/api/v1/statistics/lists', { params: query });

    return response.data.data;
}

export function statisticsExportUrl(query: StatisticsQuery, table: 'campaigns' | 'lists'): string {
    const params = new URLSearchParams({ date_from: query.date_from, date_to: query.date_to, table });
    if (query.scope) params.set('scope', query.scope);

    return `/api/v1/statistics/export?${params.toString()}`;
}
