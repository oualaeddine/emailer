/**
 * docs/45-brevo-api-contracts.md §45.6.5 (Statistics) — WP-72 API, WP-73
 * `Statistics/Index`. /api/v1/statistics/*.
 */
import type { CampaignType } from '@/Lib/types/campaigns';
import type { IsoDate, IsoDateTime, Uuid } from '@/Lib/types/common';
import type { BrevoStats, RateSet, ReportGranularity } from '@/Lib/types/reports';

export type { ReportTimeseries as StatisticsTimeseries } from '@/Lib/types/reports';

/** Labels: fr.statistics.scopes. */
export type StatisticsScope = 'marketing' | 'transactional' | 'all';

export interface StatisticsQuery {
    date_from: IsoDate;
    date_to: IsoDate;
    scope?: StatisticsScope;
    compare?: 'previous_period';
}

export interface StatisticsTimeseriesQuery extends StatisticsQuery {
    granularity: ReportGranularity;
}

export type StatisticsCampaignSortField = 'sent_at' | 'name' | 'open_rate' | 'click_rate' | 'sent';

export interface StatisticsCampaignsQuery extends StatisticsQuery {
    sort?: StatisticsCampaignSortField | `-${StatisticsCampaignSortField}`;
    tag?: string;
    page?: number;
    per_page?: number;
}

export interface StatisticsPeriod {
    from: IsoDate;
    to: IsoDate;
}

export interface StatisticsPreviousPeriod {
    period: StatisticsPeriod;
    globalStats: BrevoStats;
    rates: RateSet;
}

/** GET /statistics/summary. */
export interface StatisticsSummary {
    period: StatisticsPeriod;
    scope: StatisticsScope;
    globalStats: BrevoStats;
    rates: RateSet;
    campaigns_sent: number;
    previous: StatisticsPreviousPeriod | null;
}

/** Row of GET /statistics/campaigns. */
export interface StatisticsCampaignRow {
    campaign_id: Uuid;
    name: string;
    sent_at: IsoDateTime;
    tags: string[];
    type: CampaignType;
    globalStats: BrevoStats;
    rates: RateSet;
}

/** Row of GET /statistics/lists. */
export interface StatisticsListRow {
    list_id: Uuid;
    name: string;
    contact_count: number;
    globalStats: BrevoStats;
    rates: RateSet;
}

export interface StatisticsExportQuery extends StatisticsQuery {
    table: 'campaigns' | 'lists';
}
