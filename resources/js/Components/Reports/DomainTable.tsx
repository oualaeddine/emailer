import { Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount, formatRate } from '@/Components/Campaigns/campaignUi';
import { ChartDataTable } from '@/Components/Reports/ChartChrome';
import { ratio, reportMissingText } from '@/Components/Reports/reportUi';
import type { BrevoStatsByDomain } from '@/Lib/types/reports';

const useStyles = makeStyles({
    scroll: { maxHeight: '420px', overflowY: 'auto' },
    empty: { color: tokens.colorNeutralForeground3 },
});

export interface DomainTableProps {
    stats: BrevoStatsByDomain | null | undefined;
    /** Deliverability shows bounces; Opens shows opens/clicks. */
    variant: 'deliverability' | 'engagement';
}

/** Brevo `statsByDomain` (gmail.com, yahoo.fr…) sorted by volume. */
export function DomainTable({ stats, variant }: DomainTableProps) {
    const styles = useStyles();
    const t = useText();
    const k = t.campaignReport.kpis;
    const entries = Object.entries(stats ?? {}).sort(([, a], [, b]) => b.sent - a.sent);

    if (entries.length === 0) {
        return <Text className={styles.empty}>{reportMissingText.noData}</Text>;
    }

    const columns =
        variant === 'deliverability'
            ? [
                  { label: reportMissingText.domain },
                  { label: k.sent, numeric: true },
                  { label: k.delivered, numeric: true },
                  { label: k.hardBounces, numeric: true },
                  { label: k.softBounces, numeric: true },
                  { label: k.complaints, numeric: true },
                  { label: t.campaignReport.rates.delivery_rate, numeric: true },
              ]
            : [
                  { label: reportMissingText.domain },
                  { label: k.delivered, numeric: true },
                  { label: k.uniqueOpens, numeric: true },
                  { label: t.campaignReport.rates.open_rate, numeric: true },
                  { label: k.uniqueClicks, numeric: true },
                  { label: t.campaignReport.rates.click_rate, numeric: true },
                  { label: k.unsubscriptions, numeric: true },
              ];

    const rows = entries.map(([domain, s]) =>
        variant === 'deliverability'
            ? [
                  domain,
                  formatCount(s.sent),
                  formatCount(s.delivered),
                  formatCount(s.hardBounces),
                  formatCount(s.softBounces),
                  formatCount(s.complaints),
                  formatRate(ratio(s.delivered, s.sent)),
              ]
            : [
                  domain,
                  formatCount(s.delivered),
                  formatCount(s.uniqueViews),
                  formatRate(ratio(s.uniqueViews, s.delivered)),
                  formatCount(s.uniqueClicks),
                  formatRate(ratio(s.uniqueClicks, s.delivered)),
                  formatCount(s.unsubscriptions),
              ],
    );

    return (
        <div className={styles.scroll}>
            <ChartDataTable caption={reportMissingText.domain} columns={columns} rows={rows} />
        </div>
    );
}
