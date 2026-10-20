import { useMemo, useState } from 'react';
import { Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { ReportTimeseriesPoint } from '@/Lib/types/reports';

/**
 * Minimal SVG line chart (no chart library in the codebase). Dataviz rules:
 * one axis, 2px lines, categorical hues in fixed order drawn from Fluent
 * palette tokens (adapt to light/dark), legend always shown, crosshair +
 * tooltip, recessive grid, and a visually hidden table as the accessible view.
 */

const W = 800;
const H = 260;
const PAD = { l: 44, r: 12, t: 12, b: 28 };

type SeriesKey = 'delivered' | 'uniqueViews' | 'uniqueClicks';

const SERIES: Array<{ key: SeriesKey; color: string }> = [
    { key: 'delivered', color: tokens.colorPaletteBlueForeground2 },
    { key: 'uniqueViews', color: tokens.colorPaletteDarkOrangeForeground1 },
    { key: 'uniqueClicks', color: tokens.colorPaletteTealForeground2 },
];

const useStyles = makeStyles({
    wrap: { position: 'relative', width: '100%' },
    svg: { width: '100%', height: 'auto', display: 'block', touchAction: 'pan-y' },
    legend: { display: 'flex', flexWrap: 'wrap', gap: tokens.spacingHorizontalL, marginBottom: tokens.spacingVerticalS },
    legendItem: { display: 'flex', alignItems: 'center', gap: tokens.spacingHorizontalXS },
    swatch: { width: '12px', height: '2px', borderRadius: '1px', display: 'inline-block' },
    tooltip: {
        position: 'absolute',
        top: 0,
        pointerEvents: 'none',
        backgroundColor: tokens.colorNeutralBackground1,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke1}`,
        borderRadius: tokens.borderRadiusMedium,
        boxShadow: tokens.shadow8,
        padding: tokens.spacingVerticalS,
        minWidth: '150px',
        zIndex: 1,
    },
    srOnly: {
        position: 'absolute',
        width: '1px',
        height: '1px',
        overflow: 'hidden',
        clip: 'rect(0 0 0 0)',
        whiteSpace: 'nowrap',
    },
});

function niceMax(v: number): number {
    if (v <= 0) return 1;
    const p = Math.pow(10, Math.floor(Math.log10(v)));
    const n = v / p;

    return (n <= 1 ? 1 : n <= 2 ? 2 : n <= 5 ? 5 : 10) * p;
}

function labelFor(t: string, granularity: string): string {
    const d = new Date(t);
    return granularity === 'hour'
        ? d.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })
        : d.toLocaleDateString('fr-FR', { day: '2-digit', month: 'short' });
}

interface Props {
    points: ReportTimeseriesPoint[];
    granularity: string;
}

export function TimeSeriesChart({ points, granularity }: Props) {
    const styles = useStyles();
    const t = useText();
    const [hover, setHover] = useState<number | null>(null);
    const labels: Record<SeriesKey, string> = {
        delivered: t.statistics.kpis.delivered,
        uniqueViews: t.statistics.kpis.opens,
        uniqueClicks: t.statistics.kpis.clicks,
    };

    const { max, x, y } = useMemo(() => {
        const m = niceMax(Math.max(0, ...points.flatMap((p) => SERIES.map((s) => p[s.key]))));
        const n = Math.max(points.length - 1, 1);

        return {
            max: m,
            x: (i: number) => PAD.l + (i / n) * (W - PAD.l - PAD.r),
            y: (v: number) => PAD.t + (1 - v / m) * (H - PAD.t - PAD.b),
        };
    }, [points]);

    if (points.length === 0) {
        return <Text>{t.statistics.noData}</Text>;
    }

    const ticks = [0, 0.25, 0.5, 0.75, 1].map((f) => f * max);
    const xTickEvery = Math.ceil(points.length / 8);

    function onMove(e: React.PointerEvent<SVGSVGElement>) {
        const rect = e.currentTarget.getBoundingClientRect();
        const px = ((e.clientX - rect.left) / rect.width) * W;
        const i = Math.round(((px - PAD.l) / (W - PAD.l - PAD.r)) * Math.max(points.length - 1, 1));
        setHover(Math.min(points.length - 1, Math.max(0, i)));
    }

    const hp = hover !== null ? points[hover] : null;
    const leftPct = hover !== null ? (x(hover) / W) * 100 : 0;

    return (
        <div>
            <div className={styles.legend}>
                {SERIES.map((s) => (
                    <span key={s.key} className={styles.legendItem}>
                        <span className={styles.swatch} style={{ backgroundColor: s.color }} />
                        <Text size={200}>{labels[s.key]}</Text>
                    </span>
                ))}
            </div>
            <div className={styles.wrap}>
                <svg
                    className={styles.svg}
                    viewBox={`0 0 ${W} ${H}`}
                    role="img"
                    aria-label={t.statistics.timeseries}
                    onPointerMove={onMove}
                    onPointerLeave={() => setHover(null)}
                >
                    {ticks.map((tick) => (
                        <g key={tick}>
                            <line
                                x1={PAD.l}
                                x2={W - PAD.r}
                                y1={y(tick)}
                                y2={y(tick)}
                                stroke={tokens.colorNeutralStroke2}
                                strokeWidth={1}
                            />
                            <text
                                x={PAD.l - 6}
                                y={y(tick) + 4}
                                textAnchor="end"
                                fontSize={11}
                                fill={tokens.colorNeutralForeground3}
                            >
                                {new Intl.NumberFormat('fr-FR', { notation: 'compact' }).format(tick)}
                            </text>
                        </g>
                    ))}
                    {points.map((p, i) =>
                        i % xTickEvery === 0 ? (
                            <text
                                key={p.t}
                                x={x(i)}
                                y={H - 8}
                                textAnchor="middle"
                                fontSize={11}
                                fill={tokens.colorNeutralForeground3}
                            >
                                {labelFor(p.t, granularity)}
                            </text>
                        ) : null,
                    )}
                    {SERIES.map((s) => (
                        <polyline
                            key={s.key}
                            fill="none"
                            stroke={s.color}
                            strokeWidth={2}
                            strokeLinejoin="round"
                            strokeLinecap="round"
                            points={points.map((p, i) => `${x(i)},${y(p[s.key])}`).join(' ')}
                        />
                    ))}
                    {hp && hover !== null && (
                        <g>
                            <line
                                x1={x(hover)}
                                x2={x(hover)}
                                y1={PAD.t}
                                y2={H - PAD.b}
                                stroke={tokens.colorNeutralStroke1}
                                strokeWidth={1}
                            />
                            {SERIES.map((s) => (
                                <circle
                                    key={s.key}
                                    cx={x(hover)}
                                    cy={y(hp[s.key])}
                                    r={4}
                                    fill={s.color}
                                    stroke={tokens.colorNeutralBackground1}
                                    strokeWidth={2}
                                />
                            ))}
                        </g>
                    )}
                </svg>
                {hp && (
                    <div
                        className={styles.tooltip}
                        style={{ left: `${leftPct}%`, transform: leftPct > 60 ? 'translateX(-105%)' : 'translateX(8px)' }}
                    >
                        <Text size={200} weight="semibold" block>
                            {labelFor(hp.t, granularity)}
                        </Text>
                        {SERIES.map((s) => (
                            <div key={s.key} className={styles.legendItem}>
                                <span className={styles.swatch} style={{ backgroundColor: s.color }} />
                                <Text size={200}>
                                    {labels[s.key]} : {new Intl.NumberFormat('fr-FR').format(hp[s.key])}
                                </Text>
                            </div>
                        ))}
                    </div>
                )}
                <table className={styles.srOnly}>
                    <caption>{t.statistics.timeseries}</caption>
                    <thead>
                        <tr>
                            <th>{t.statistics.period}</th>
                            {SERIES.map((s) => (
                                <th key={s.key}>{labels[s.key]}</th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {points.map((p) => (
                            <tr key={p.t}>
                                <td>{labelFor(p.t, granularity)}</td>
                                {SERIES.map((s) => (
                                    <td key={s.key}>{p[s.key]}</td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
