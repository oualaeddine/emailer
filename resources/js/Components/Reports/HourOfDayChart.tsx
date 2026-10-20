import { useRef, useState } from 'react';
import { makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { formatCount } from '@/Components/Campaigns/campaignUi';
import { ChartDataTable, ChartTooltip, TableToggle, useElementWidth } from '@/Components/Reports/ChartChrome';
import { formatCompact, niceTicks, reportMissingText, useChartPalette } from '@/Components/Reports/reportUi';
import type { ReportHourOfDayRow } from '@/Lib/types/reports';

const PLOT_HEIGHT = 180;
const AXIS_BAND = 24;
const PAD_TOP = 18;
const PAD_LEFT = 44;
const PAD_RIGHT = 8;
const GAP = 2;

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        position: 'relative',
    },
    controls: {
        display: 'flex',
        justifyContent: 'flex-end',
    },
    svg: {
        display: 'block',
        overflow: 'visible',
    },
    bar: {
        cursor: 'default',
        outlineStyle: 'none',
    },
});

/** Path of a column with a 4px rounded data-end, square at the baseline. */
function columnPath(x: number, y: number, w: number, h: number): string {
    if (h <= 0) {
        return '';
    }
    const r = Math.min(4, w / 2, h);

    return `M ${x} ${y + h} L ${x} ${y + r} Q ${x} ${y} ${x + r} ${y} L ${x + w - r} ${y} Q ${x + w} ${y} ${x + w} ${y + r} L ${x + w} ${y + h} Z`;
}

/** Unique opens by hour of the day (0–23, account timezone) — single-series column chart. */
export function HourOfDayChart({ rows, label }: { rows: ReportHourOfDayRow[]; label: string }) {
    const styles = useStyles();
    const t = useText();
    const rootRef = useRef<HTMLDivElement>(null);
    const width = useElementWidth(rootRef);
    const palette = useChartPalette(rootRef);
    const [table, setTable] = useState(false);
    const [active, setActive] = useState<number | null>(null);

    const byHour = Array.from({ length: 24 }, (_, hour) => rows.find((row) => row.hour === hour)?.uniqueViews ?? 0);
    const max = Math.max(0, ...byHour);
    const ticks = niceTicks(max);
    const top = ticks[ticks.length - 1] || 1;
    const innerWidth = Math.max(240, width - PAD_LEFT - PAD_RIGHT);
    const slot = innerWidth / 24;
    const barWidth = Math.min(24, Math.max(4, slot - GAP));
    const peak = max > 0 ? byHour.indexOf(max) : -1;
    const color = palette.series[0];
    const hourLabel = (hour: number) => `${String(hour).padStart(2, '0')}h`;

    return (
        <div ref={rootRef} className={styles.root}>
            <div className={styles.controls}>
                <TableToggle table={table} onToggle={() => setTable((v) => !v)} />
            </div>
            {table ? (
                <ChartDataTable
                    caption={label}
                    columns={[{ label: reportMissingText.hour }, { label: t.campaignReport.kpis.uniqueOpens, numeric: true }]}
                    rows={byHour.map((value, hour) => [hourLabel(hour), formatCount(value)])}
                />
            ) : (
                <svg className={styles.svg} width={width} height={PAD_TOP + PLOT_HEIGHT + AXIS_BAND} role="img" aria-label={label}>
                    {ticks.map((tick) => {
                        const y = PAD_TOP + PLOT_HEIGHT - (tick / top) * PLOT_HEIGHT;

                        return (
                            <g key={tick}>
                                <line
                                    x1={PAD_LEFT}
                                    x2={PAD_LEFT + innerWidth}
                                    y1={y}
                                    y2={y}
                                    stroke={tick === 0 ? tokens.colorNeutralStroke1 : tokens.colorNeutralStroke2}
                                    strokeWidth={1}
                                />
                                <text x={PAD_LEFT - 8} y={y} dy="0.32em" textAnchor="end" fontSize={11} fill={tokens.colorNeutralForeground3}>
                                    {formatCompact(tick)}
                                </text>
                            </g>
                        );
                    })}
                    {byHour.map((value, hour) => {
                        const h = (value / top) * PLOT_HEIGHT;
                        const x = PAD_LEFT + hour * slot + (slot - barWidth) / 2;
                        const y = PAD_TOP + PLOT_HEIGHT - h;

                        return (
                            <g
                                key={hour}
                                className={styles.bar}
                                tabIndex={0}
                                role="img"
                                aria-label={`${hourLabel(hour)} : ${formatCount(value)}`}
                                onPointerEnter={() => setActive(hour)}
                                onPointerLeave={() => setActive(null)}
                                onFocus={() => setActive(hour)}
                                onBlur={() => setActive(null)}
                            >
                                {/* Hit target: the whole slot, taller than the mark. */}
                                <rect x={PAD_LEFT + hour * slot} y={PAD_TOP} width={slot} height={PLOT_HEIGHT} fill="transparent" />
                                <path d={columnPath(x, y, barWidth, h)} fill={color} fillOpacity={active === null || active === hour ? 1 : 0.55} />
                                {hour === peak && (
                                    <text x={x + barWidth / 2} y={y - 6} textAnchor="middle" fontSize={11} fontWeight={600} fill={tokens.colorNeutralForeground2}>
                                        {formatCompact(value)}
                                    </text>
                                )}
                                {hour % 3 === 0 && (
                                    <text
                                        x={PAD_LEFT + hour * slot + slot / 2}
                                        y={PAD_TOP + PLOT_HEIGHT + 16}
                                        textAnchor="middle"
                                        fontSize={11}
                                        fill={tokens.colorNeutralForeground3}
                                    >
                                        {hourLabel(hour)}
                                    </text>
                                )}
                            </g>
                        );
                    })}
                </svg>
            )}
            {!table && active !== null && (
                <ChartTooltip
                    title={hourLabel(active)}
                    rows={[{ label: t.campaignReport.kpis.uniqueOpens, value: formatCount(byHour[active]), color, shape: 'rect' }]}
                    left={Math.min(PAD_LEFT + active * slot + slot, Math.max(0, width - 180))}
                    top={48}
                />
            )}
        </div>
    );
}
