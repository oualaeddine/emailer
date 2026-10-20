import { useState } from 'react';
import { makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount } from '@/Components/Campaigns/campaignUi';
import { ChartDataTable, TableToggle } from '@/Components/Reports/ChartChrome';
import { HorizontalBars } from '@/Components/Reports/HorizontalBars';
import { DEVICE_ORDER, reportMissingText } from '@/Components/Reports/reportUi';
import type { BrevoEngagementBreakdown, BrevoStatsByDevice } from '@/Lib/types/reports';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    controls: {
        display: 'flex',
        justifyContent: 'flex-end',
    },
});

function sum(breakdowns: Record<string, BrevoEngagementBreakdown> | undefined, key: keyof BrevoEngagementBreakdown): number {
    return Object.values(breakdowns ?? {}).reduce((total, b) => total + (b[key] ?? 0), 0);
}

export interface DeviceSplitProps {
    stats: BrevoStatsByDevice | null | undefined;
    /** Opens tab / overview use unique opens; the Clicks tab uses unique clicks. */
    metric: 'uniqueViews' | 'uniqueClicks';
}

/**
 * Brevo `statsByDevice` (desktop / mobile / tablet / unknown → client or OS).
 * Bars show the device split; the table twin lists every client.
 */
export function DeviceSplit({ stats, metric }: DeviceSplitProps) {
    const styles = useStyles();
    const t = useText();
    const [table, setTable] = useState(false);
    const other: 'uniqueViews' | 'uniqueClicks' = metric === 'uniqueViews' ? 'uniqueClicks' : 'uniqueViews';
    const metricLabel = (key: 'uniqueViews' | 'uniqueClicks') =>
        key === 'uniqueViews' ? t.campaignReport.kpis.uniqueOpens : t.campaignReport.kpis.uniqueClicks;

    const items = DEVICE_ORDER.map((device) => ({
        key: device,
        label: t.campaignReport.devices[device],
        value: sum(stats?.[device], metric),
        detail: `${metricLabel(other)} : ${formatCount(sum(stats?.[device], other))}`,
    }));

    const clientRows = DEVICE_ORDER.flatMap((device) =>
        Object.entries(stats?.[device] ?? {})
            .sort(([, a], [, b]) => b[metric] - a[metric])
            .map(([client, b]) => [
                t.campaignReport.devices[device],
                client || t.common.unknown,
                formatCount(b.uniqueViews),
                formatCount(b.uniqueClicks),
            ]),
    );

    return (
        <div className={styles.root}>
            <div className={styles.controls}>
                <TableToggle table={table} onToggle={() => setTable((v) => !v)} />
            </div>
            {table ? (
                <ChartDataTable
                    caption={t.campaignReport.devices.title}
                    columns={[
                        { label: reportMissingText.device },
                        { label: reportMissingText.client },
                        { label: t.campaignReport.kpis.uniqueOpens, numeric: true },
                        { label: t.campaignReport.kpis.uniqueClicks, numeric: true },
                    ]}
                    rows={clientRows}
                />
            ) : (
                <HorizontalBars items={items} ariaLabel={`${t.campaignReport.devices.title} — ${metricLabel(metric)}`} />
            )}
        </div>
    );
}
