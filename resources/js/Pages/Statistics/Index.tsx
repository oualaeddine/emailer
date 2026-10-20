import { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { Button, MessageBar, MessageBarBody, Spinner, Text, Title3, makeStyles, tokens } from '@fluentui/react-components';
import { ArrowDownloadRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { PageHelp } from '@/Components/Help/PageHelp';
import { PeriodPicker, periodForDays, type PeriodValue } from '@/Components/Statistics/PeriodPicker';
import { KpiTiles } from '@/Components/Statistics/KpiTiles';
import { TimeSeriesChart } from '@/Components/Statistics/TimeSeriesChart';
import { CampaignPerformanceTable, type CampaignSort } from '@/Components/Statistics/CampaignPerformanceTable';
import { ListPerformanceTable } from '@/Components/Statistics/ListPerformanceTable';
import { useText } from '@/Hooks/useText';
import { hasPermission } from '@/Lib/permissions';
import {
    fetchStatisticsCampaigns,
    fetchStatisticsLists,
    fetchStatisticsSummary,
    fetchStatisticsTimeseries,
    statisticsExportUrl,
} from '@/Lib/api/statistics';
import type { AuthenticatedUser } from '@/Lib/types/identity';
import type { ReportGranularity } from '@/Lib/types/reports';
import type {
    StatisticsCampaignRow,
    StatisticsListRow,
    StatisticsSummary,
    StatisticsTimeseries,
} from '@/Lib/types/statistics';

const useStyles = makeStyles({
    toolbar: { marginBottom: tokens.spacingVerticalL },
    section: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalL,
        marginBottom: tokens.spacingVerticalL,
    },
    sectionHeader: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalM,
    },
    muted: { color: tokens.colorNeutralForeground3 },
});

function granularityFor(from: string, to: string): ReportGranularity {
    const days = (new Date(to).getTime() - new Date(from).getTime()) / 86_400_000 + 1;

    return days <= 2 ? 'hour' : days <= 120 ? 'day' : 'week';
}

/** docs/43 §43.4.16 — Statistics (replaces Reporting/Index). */
export default function StatisticsIndex() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth: { user: AuthenticatedUser; permissions: string[] } }>();
    const canExport = hasPermission(props.auth.permissions, 'reporting.export');

    const [period, setPeriod] = useState<PeriodValue>(() => periodForDays(30));
    const [sort, setSort] = useState<CampaignSort>('-sent_at');
    const [summary, setSummary] = useState<StatisticsSummary | null>(null);
    const [series, setSeries] = useState<StatisticsTimeseries | null>(null);
    const [lists, setLists] = useState<StatisticsListRow[]>([]);
    const [campaigns, setCampaigns] = useState<StatisticsCampaignRow[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(false);

    const query = { date_from: period.date_from, date_to: period.date_to, scope: 'marketing' as const };

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        setError(false);
        Promise.all([
            fetchStatisticsSummary(query),
            fetchStatisticsTimeseries({ ...query, granularity: granularityFor(period.date_from, period.date_to) }),
            fetchStatisticsLists(query),
        ])
            .then(([s, ts, l]) => {
                if (cancelled) return;
                setSummary(s);
                setSeries(ts);
                setLists(l);
            })
            .catch(() => !cancelled && setError(true))
            .finally(() => !cancelled && setLoading(false));

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [period.date_from, period.date_to]);

    useEffect(() => {
        let cancelled = false;
        fetchStatisticsCampaigns({ ...query, sort })
            .then((page) => !cancelled && setCampaigns(page.data))
            .catch(() => !cancelled && setError(true));

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [period.date_from, period.date_to, sort]);

    return (
        <AppShell>
            <Head title={t.statistics.title} />
            <PageHelp topic="reporting" title={t.statistics.title} />
            <Text className={styles.muted} block>
                {t.statistics.subtitle}
            </Text>
            <div className={styles.toolbar}>
                <PeriodPicker value={period} onChange={setPeriod} />
            </div>

            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{t.statistics.noData}</MessageBarBody>
                </MessageBar>
            )}

            {loading || !summary ? (
                <Spinner label={t.common.loading} />
            ) : (
                <>
                    <KpiTiles summary={summary} />
                    <div className={styles.section}>
                        <Title3>{t.statistics.timeseries}</Title3>
                        <TimeSeriesChart points={series?.points ?? []} granularity={series?.granularity ?? 'day'} />
                    </div>
                    <div className={styles.section}>
                        <div className={styles.sectionHeader}>
                            <Title3>{t.statistics.campaignPerformance}</Title3>
                            {canExport && (
                                <Button as="a" href={statisticsExportUrl(query, 'campaigns')} icon={<ArrowDownloadRegular />}>
                                    {t.statistics.export}
                                </Button>
                            )}
                        </div>
                        <CampaignPerformanceTable rows={campaigns} sort={sort} onSortChange={setSort} />
                    </div>
                    <div className={styles.section}>
                        <div className={styles.sectionHeader}>
                            <Title3>{t.statistics.listPerformance}</Title3>
                            {canExport && (
                                <Button as="a" href={statisticsExportUrl(query, 'lists')} icon={<ArrowDownloadRegular />}>
                                    {t.statistics.export}
                                </Button>
                            )}
                        </div>
                        <ListPerformanceTable rows={lists} />
                    </div>
                </>
            )}
        </AppShell>
    );
}
