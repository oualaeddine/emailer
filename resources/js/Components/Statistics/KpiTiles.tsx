import { Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { StatisticsSummary } from '@/Lib/types/statistics';

const useStyles = makeStyles({
    grid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalL,
    },
    tile: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
        backgroundColor: tokens.colorNeutralBackground1,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
    },
    muted: { color: tokens.colorNeutralForeground3 },
});

export function formatNumber(n: number): string {
    return new Intl.NumberFormat('fr-FR').format(n);
}

export function formatRate(rate: number): string {
    return `${new Intl.NumberFormat('fr-FR', { maximumFractionDigits: 1, minimumFractionDigits: 1 }).format(rate * 100)} %`;
}

interface Props {
    summary: StatisticsSummary;
}

export function KpiTiles({ summary }: Props) {
    const styles = useStyles();
    const t = useText();
    const g = summary.globalStats;
    const r = summary.rates;
    const tiles = [
        { key: 'sent', label: t.statistics.kpis.sent, value: g.sent, rate: null as number | null },
        { key: 'delivered', label: t.statistics.kpis.delivered, value: g.delivered, rate: r.delivery_rate },
        { key: 'opens', label: t.statistics.kpis.opens, value: g.uniqueViews, rate: r.open_rate },
        { key: 'clicks', label: t.statistics.kpis.clicks, value: g.uniqueClicks, rate: r.click_rate },
        { key: 'unsubs', label: t.statistics.kpis.unsubscriptions, value: g.unsubscriptions, rate: r.unsubscribe_rate },
        { key: 'bounces', label: t.statistics.kpis.bounces, value: g.hardBounces + g.softBounces, rate: r.bounce_rate },
        { key: 'complaints', label: t.statistics.kpis.complaints, value: g.complaints, rate: r.complaint_rate },
    ];
    const prev = summary.previous;
    const prevValues: Record<string, number> | null = prev
        ? {
              sent: prev.globalStats.sent,
              delivered: prev.globalStats.delivered,
              opens: prev.globalStats.uniqueViews,
              clicks: prev.globalStats.uniqueClicks,
              unsubs: prev.globalStats.unsubscriptions,
              bounces: prev.globalStats.hardBounces + prev.globalStats.softBounces,
              complaints: prev.globalStats.complaints,
          }
        : null;

    return (
        <div className={styles.grid}>
            {tiles.map((tile) => {
                const before = prevValues?.[tile.key];
                const delta = before !== undefined && before > 0 ? (tile.value - before) / before : null;

                return (
                    <div key={tile.key} className={styles.tile}>
                        <Text className={styles.muted}>{tile.label}</Text>
                        <Text size={700} weight="semibold">
                            {formatNumber(tile.value)}
                        </Text>
                        {tile.rate !== null && <Text className={styles.muted}>{formatRate(tile.rate)}</Text>}
                        {delta !== null && (
                            <Text size={200} className={styles.muted}>
                                {delta >= 0 ? '▲' : '▼'} {formatRate(Math.abs(delta))}
                            </Text>
                        )}
                    </div>
                );
            })}
        </div>
    );
}
