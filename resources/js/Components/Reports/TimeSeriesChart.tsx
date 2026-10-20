import { useMemo, useRef, useState, type KeyboardEvent, type PointerEvent } from 'react';
import { Tab, TabList, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount } from '@/Components/Campaigns/campaignUi';
import {
    ChartDataTable,
    ChartLegend,
    ChartTooltip,
    TableToggle,
    useElementWidth,
} from '@/Components/Reports/ChartChrome';
import { formatCompact, niceTicks, reportMissingText, useChartPalette } from '@/Components/Reports/reportUi';
import type { ReportTimeseries, ReportTimeseriesPoint } from '@/Lib/types/reports';

type NumericKey = Exclude<keyof ReportTimeseriesPoint, 't'>;
type View = 'hour' | 'day';

export interface TimeSeriesDef {
    key: NumericKey;
    label: string;
}

const PLOT_HEIGHT = 220;
const AXIS_BAND = 28;
const PAD_TOP = 12;
const PAD_LEFT = 44;
const PAD_RIGHT = 56;
const HOURLY_WINDOW_MS = 72 * 3600 * 1000;

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    controls: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalS,
    },
    plot: {
        position: 'relative',
        width: '100%',
    },
    svg: {
        display: 'block',
        overflow: 'visible',
        ':focus-visible': {
            outline: `${tokens.strokeWidthThick} solid ${tokens.colorStrokeFocus2}`,
            borderRadius: tokens.borderRadiusSmall,
        },
    },
    empty: {
        color: tokens.colorNeutralForeground3,
        padding: `${tokens.spacingVerticalXL} 0`,
    },
});

interface Bucket {
    t: Date;
    values: Record<NumericKey, number>;
}

function bucketKey(date: Date): string {
    return `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
}

function toBuckets(points: ReportTimeseriesPoint[], view: View, sourceGranularity: string): Bucket[] {
    const sorted = [...points].sort((a, b) => Date.parse(a.t) - Date.parse(b.t));
    if (sorted.length === 0) {
        return [];
    }
    const asBucket = (point: ReportTimeseriesPoint): Bucket => {
        const { t, ...values } = point;

        return { t: new Date(t), values };
    };
    if (view === 'hour' || sourceGranularity !== 'hour') {
        if (view === 'hour') {
            const start = Date.parse(sorted[0].t);

            return sorted.filter((p) => Date.parse(p.t) - start < HOURLY_WINDOW_MS).map(asBucket);
        }

        return sorted.map(asBucket);
    }
    // Hourly payload shown per day: sum the hours of each local day.
    const byDay = new Map<string, Bucket>();
    for (const point of sorted) {
        const date = new Date(point.t);
        const key = bucketKey(date);
        const existing = byDay.get(key);
        const values = asBucket(point).values;
        if (!existing) {
            byDay.set(key, { t: new Date(date.getFullYear(), date.getMonth(), date.getDate()), values: { ...values } });
        } else {
            (Object.keys(values) as NumericKey[]).forEach((k) => {
                existing.values[k] += values[k];
            });
        }
    }

    return [...byDay.values()];
}

function formatBucket(date: Date, view: View, long = false): string {
    if (view === 'hour') {
        return date.toLocaleString('fr-FR', long ? { dateStyle: 'medium', timeStyle: 'short' } : { day: '2-digit', month: '2-digit', hour: '2-digit' });
    }

    return date.toLocaleDateString('fr-FR', long ? { dateStyle: 'full' } : { day: 'numeric', month: 'short' });
}

export interface TimeSeriesChartProps {
    timeseries: ReportTimeseries;
    series: TimeSeriesDef[];
    caption: string;
}

/**
 * Line chart of report time series (docs/43 §43.4.15: per hour over the first
 * 72 h, then per day). The payload's granularity is the default view; an
 * hourly payload can also be summed per day. Crosshair + one tooltip for every
 * series, keyboard arrows, direct end labels when they don't collide, legend,
 * and a table twin.
 */
export function TimeSeriesChart({ timeseries, series, caption }: TimeSeriesChartProps) {
    const styles = useStyles();
    const t = useText();
    const plotRef = useRef<HTMLDivElement>(null);
    const width = useElementWidth(plotRef);
    const palette = useChartPalette(plotRef);
    const hourlyAvailable = timeseries.granularity === 'hour';
    const [view, setView] = useState<View>(hourlyAvailable ? 'hour' : 'day');
    const [table, setTable] = useState(false);
    const [active, setActive] = useState<number | null>(null);

    const buckets = useMemo(() => toBuckets(timeseries.points, view, timeseries.granularity), [timeseries, view]);
    const colors = series.map((_, index) => palette.series[index] ?? palette.series[0]);

    const innerWidth = Math.max(120, width - PAD_LEFT - PAD_RIGHT);
    const max = Math.max(0, ...buckets.flatMap((b) => series.map((s) => b.values[s.key])));
    const ticks = niceTicks(max);
    const top = ticks[ticks.length - 1] || 1;
    const x = (index: number) => PAD_LEFT + (buckets.length <= 1 ? innerWidth / 2 : (index / (buckets.length - 1)) * innerWidth);
    const y = (value: number) => PAD_TOP + PLOT_HEIGHT - (value / top) * PLOT_HEIGHT;

    const xTickIndexes = useMemo(() => {
        const n = buckets.length;
        if (n === 0) {
            return [];
        }
        const maxTicks = Math.max(2, Math.floor(innerWidth / 90));
        const step = Math.max(1, Math.ceil(n / maxTicks));
        const result: number[] = [];
        for (let i = 0; i < n; i += step) {
            result.push(i);
        }

        return result;
    }, [buckets.length, innerWidth]);

    // Direct end labels only when they don't collide (legend + tooltip carry the rest).
    const endLabels = (() => {
        if (buckets.length === 0) {
            return [];
        }
        const last = buckets[buckets.length - 1];
        const labels = series.map((s, index) => ({ y: y(last.values[s.key]), text: formatCompact(last.values[s.key]), index }));
        const sortedY = labels.map((l) => l.y).sort((a, b) => a - b);
        const collide = sortedY.some((value, i) => i > 0 && value - sortedY[i - 1] < 14);

        return collide ? [] : labels;
    })();

    function indexFromPointer(event: PointerEvent<SVGSVGElement>): number | null {
        if (buckets.length === 0) {
            return null;
        }
        const rect = event.currentTarget.getBoundingClientRect();
        const px = event.clientX - rect.left - PAD_LEFT;
        const index = buckets.length <= 1 ? 0 : Math.round((px / innerWidth) * (buckets.length - 1));

        return Math.min(buckets.length - 1, Math.max(0, index));
    }

    function onKeyDown(event: KeyboardEvent<SVGSVGElement>) {
        if (buckets.length === 0) {
            return;
        }
        if (event.key === 'ArrowLeft' || event.key === 'ArrowRight') {
            event.preventDefault();
            const delta = event.key === 'ArrowLeft' ? -1 : 1;
            setActive((current) => Math.min(buckets.length - 1, Math.max(0, (current ?? buckets.length - 1) + delta)));
        } else if (event.key === 'Escape') {
            setActive(null);
        }
    }

    const tableColumns = [{ label: view === 'hour' ? reportMissingText.hour : t.common.date }, ...series.map((s) => ({ label: s.label, numeric: true }))];
    const tableRows = buckets.map((b) => [formatBucket(b.t, view, true), ...series.map((s) => formatCount(b.values[s.key]))]);

    const svgHeight = PAD_TOP + PLOT_HEIGHT + AXIS_BAND;
    const activeBucket = active !== null ? buckets[active] : null;

    return (
        <div ref={plotRef} className={styles.root}>
            <div className={styles.controls}>
                <TabList
                    size="small"
                    selectedValue={view}
                    onTabSelect={(_, data) => {
                        setView(data.value as View);
                        setActive(null);
                    }}
                >
                    <Tab value="hour" disabled={!hourlyAvailable}>
                        {t.campaignReport.perHour}
                    </Tab>
                    <Tab value="day">{t.campaignReport.perDay}</Tab>
                </TabList>
                <TableToggle table={table} onToggle={() => setTable((v) => !v)} />
            </div>
            {series.length > 1 && <ChartLegend items={series.map((s, index) => ({ label: s.label, color: colors[index] }))} />}
            {buckets.length === 0 ? (
                <Text className={styles.empty}>{reportMissingText.noData}</Text>
            ) : table ? (
                <ChartDataTable caption={caption} columns={tableColumns} rows={tableRows} />
            ) : (
                <div className={styles.plot}>
                    <svg
                        className={styles.svg}
                        width={width}
                        height={svgHeight}
                        role="img"
                        aria-label={caption}
                        tabIndex={0}
                        onPointerMove={(event) => setActive(indexFromPointer(event))}
                        onPointerLeave={() => setActive(null)}
                        onFocus={() => setActive((current) => current ?? buckets.length - 1)}
                        onBlur={() => setActive(null)}
                        onKeyDown={onKeyDown}
                    >
                        {ticks.map((tick) => (
                            <g key={tick}>
                                <line
                                    x1={PAD_LEFT}
                                    x2={PAD_LEFT + innerWidth}
                                    y1={y(tick)}
                                    y2={y(tick)}
                                    stroke={tick === 0 ? tokens.colorNeutralStroke1 : tokens.colorNeutralStroke2}
                                    strokeWidth={1}
                                />
                                <text
                                    x={PAD_LEFT - 8}
                                    y={y(tick)}
                                    dy="0.32em"
                                    textAnchor="end"
                                    fontSize={11}
                                    fill={tokens.colorNeutralForeground3}
                                    style={{ fontVariantNumeric: 'tabular-nums' }}
                                >
                                    {formatCompact(tick)}
                                </text>
                            </g>
                        ))}
                        {xTickIndexes.map((index) => (
                            <text
                                key={index}
                                x={x(index)}
                                y={PAD_TOP + PLOT_HEIGHT + 18}
                                textAnchor="middle"
                                fontSize={11}
                                fill={tokens.colorNeutralForeground3}
                            >
                                {formatBucket(buckets[index].t, view)}
                            </text>
                        ))}
                        {series.length === 1 && buckets.length > 1 && (
                            <path
                                d={`M ${x(0)} ${y(0)} ${buckets.map((b, i) => `L ${x(i)} ${y(b.values[series[0].key])}`).join(' ')} L ${x(buckets.length - 1)} ${y(0)} Z`}
                                fill={colors[0]}
                                fillOpacity={0.1}
                            />
                        )}
                        {series.map((s, index) => (
                            <path
                                key={s.key}
                                d={buckets.map((b, i) => `${i === 0 ? 'M' : 'L'} ${x(i)} ${y(b.values[s.key])}`).join(' ')}
                                fill="none"
                                stroke={colors[index]}
                                strokeWidth={2}
                                strokeLinejoin="round"
                                strokeLinecap="round"
                            />
                        ))}
                        {buckets.length === 1 &&
                            series.map((s, index) => (
                                <circle key={s.key} cx={x(0)} cy={y(buckets[0].values[s.key])} r={4} fill={colors[index]} stroke={tokens.colorNeutralBackground1} strokeWidth={2} />
                            ))}
                        {endLabels.map((label) => (
                            <text
                                key={label.index}
                                x={x(buckets.length - 1) + 8}
                                y={label.y}
                                dy="0.32em"
                                fontSize={11}
                                fontWeight={600}
                                fill={tokens.colorNeutralForeground2}
                            >
                                {label.text}
                            </text>
                        ))}
                        {activeBucket && active !== null && (
                            <g pointerEvents="none">
                                <line
                                    x1={x(active)}
                                    x2={x(active)}
                                    y1={PAD_TOP}
                                    y2={PAD_TOP + PLOT_HEIGHT}
                                    stroke={tokens.colorNeutralStroke1}
                                    strokeWidth={1}
                                />
                                {series.map((s, index) => (
                                    <circle
                                        key={s.key}
                                        cx={x(active)}
                                        cy={y(activeBucket.values[s.key])}
                                        r={4}
                                        fill={colors[index]}
                                        stroke={tokens.colorNeutralBackground1}
                                        strokeWidth={2}
                                    />
                                ))}
                            </g>
                        )}
                    </svg>
                    {activeBucket && active !== null && (
                        <ChartTooltip
                            title={formatBucket(activeBucket.t, view, true)}
                            rows={series.map((s, index) => ({ label: s.label, value: formatCount(activeBucket.values[s.key]), color: colors[index] }))}
                            left={Math.min(Math.max(0, x(active) + 12), Math.max(0, width - 200))}
                            top={PAD_TOP}
                        />
                    )}
                </div>
            )}
        </div>
    );
}
