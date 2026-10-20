import { Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount, formatRate } from '@/Components/Campaigns/campaignUi';
import { ChartDataTable } from '@/Components/Reports/ChartChrome';
import { countryName, ratio, reportMissingText } from '@/Components/Reports/reportUi';
import type { BrevoEngagementBreakdown } from '@/Lib/types/reports';

const useStyles = makeStyles({
    scroll: { maxHeight: '420px', overflowY: 'auto' },
    empty: { color: tokens.colorNeutralForeground3 },
});

export interface BreakdownTableProps {
    stats: Record<string, BrevoEngagementBreakdown> | null | undefined;
    /** First column header. */
    dimension: string;
    /** Formats a key for display (country code → name). */
    formatKey?: (key: string) => string;
    caption: string;
}

/** Brevo engagement breakdown (`statsByBrowser`, `statsByCountry`) sorted by unique opens. */
export function BreakdownTable({ stats, dimension, formatKey, caption }: BreakdownTableProps) {
    const styles = useStyles();
    const t = useText();
    const entries = Object.entries(stats ?? {}).sort(([, a], [, b]) => b.uniqueViews - a.uniqueViews);
    const totalOpens = entries.reduce((sum, [, b]) => sum + b.uniqueViews, 0);

    if (entries.length === 0) {
        return <Text className={styles.empty}>{reportMissingText.noData}</Text>;
    }

    return (
        <div className={styles.scroll}>
            <ChartDataTable
                caption={caption}
                columns={[
                    { label: dimension },
                    { label: t.campaignReport.kpis.uniqueOpens, numeric: true },
                    { label: reportMissingText.shareOfOpens, numeric: true },
                    { label: t.campaignReport.kpis.totalOpens, numeric: true },
                    { label: t.campaignReport.kpis.uniqueClicks, numeric: true },
                ]}
                rows={entries.map(([key, b]) => [
                    formatKey ? formatKey(key) : key || t.common.unknown,
                    formatCount(b.uniqueViews),
                    formatRate(ratio(b.uniqueViews, totalOpens)),
                    formatCount(b.viewed),
                    formatCount(b.uniqueClicks),
                ])}
            />
        </div>
    );
}

/** `statsByCountry` (GeoIP, ISO-3166 alpha-2) with French country names. */
export function CountryTable({ stats }: { stats: Record<string, BrevoEngagementBreakdown> | null | undefined }) {
    const t = useText();

    return <BreakdownTable stats={stats} dimension={reportMissingText.country} formatKey={countryName} caption={t.campaignReport.opens.byCountry} />;
}
