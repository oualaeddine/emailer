import { useEffect, useState, type ReactNode, type RefObject } from 'react';
import {
    Button,
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableHeaderCell,
    TableRow,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { ChartMultipleRegular, TableRegular } from '@fluentui/react-icons';
import { reportMissingText } from '@/Components/Reports/reportUi';

const useStyles = makeStyles({
    legend: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalL,
        fontSize: tokens.fontSizeBase200,
        color: tokens.colorNeutralForeground2,
    },
    legendItem: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
    },
    tooltip: {
        position: 'absolute',
        pointerEvents: 'none',
        zIndex: 2,
        minWidth: '140px',
        padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalM}`,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground1,
        boxShadow: tokens.shadow16,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        fontSize: tokens.fontSizeBase200,
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
    tooltipTitle: {
        color: tokens.colorNeutralForeground3,
    },
    tooltipRow: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    tooltipValue: {
        fontWeight: tokens.fontWeightSemibold,
        color: tokens.colorNeutralForeground1,
        fontVariantNumeric: 'tabular-nums',
    },
    tooltipLabel: {
        color: tokens.colorNeutralForeground3,
    },
    numeric: {
        fontVariantNumeric: 'tabular-nums',
        textAlign: 'right',
        '& .fui-TableHeaderCell__button': {
            justifyContent: 'flex-end',
            textAlign: 'right',
        },
    },
});

/** Short stroke of the series color (legend / tooltip key for lines). */
export function LineKey({ color }: { color: string }) {
    return (
        <svg width="14" height="8" aria-hidden="true">
            <line x1="1" y1="4" x2="13" y2="4" stroke={color} strokeWidth="2" strokeLinecap="round" />
        </svg>
    );
}

/** Rect key (legend for bars). */
export function RectKey({ color }: { color: string }) {
    return (
        <svg width="10" height="10" aria-hidden="true">
            <rect x="0" y="0" width="10" height="10" rx="2" fill={color} />
        </svg>
    );
}

export interface LegendItem {
    label: string;
    color: string;
    shape?: 'line' | 'rect';
}

export function ChartLegend({ items }: { items: LegendItem[] }) {
    const styles = useStyles();

    return (
        <div className={styles.legend}>
            {items.map((item) => (
                <span key={item.label} className={styles.legendItem}>
                    {item.shape === 'rect' ? <RectKey color={item.color} /> : <LineKey color={item.color} />}
                    {item.label}
                </span>
            ))}
        </div>
    );
}

export interface ChartTooltipRow {
    label: string;
    value: string;
    color: string;
    shape?: 'line' | 'rect';
}

/** One tooltip for every series at the hovered X — values lead, labels follow. */
export function ChartTooltip({ title, rows, left, top }: { title: string; rows: ChartTooltipRow[]; left: number; top: number }) {
    const styles = useStyles();

    return (
        <div className={styles.tooltip} style={{ left, top }} role="status">
            <span className={styles.tooltipTitle}>{title}</span>
            {rows.map((row) => (
                <span key={row.label} className={styles.tooltipRow}>
                    {row.shape === 'rect' ? <RectKey color={row.color} /> : <LineKey color={row.color} />}
                    <span className={styles.tooltipValue}>{row.value}</span>
                    <span className={styles.tooltipLabel}>{row.label}</span>
                </span>
            ))}
        </div>
    );
}

/** Button switching a chart and its table view (every chart has a table twin). */
export function TableToggle({ table, onToggle }: { table: boolean; onToggle: () => void }) {
    return (
        <Button
            size="small"
            appearance="subtle"
            icon={table ? <ChartMultipleRegular /> : <TableRegular />}
            onClick={onToggle}
            aria-pressed={table}
        >
            {table ? reportMissingText.showChart : reportMissingText.showTable}
        </Button>
    );
}

export interface DataTableColumn {
    label: string;
    numeric?: boolean;
}

/** Plain table twin of a chart. */
export function ChartDataTable({ caption, columns, rows }: { caption: string; columns: DataTableColumn[]; rows: ReactNode[][] }) {
    const styles = useStyles();

    return (
        <Table size="small" aria-label={caption}>
            <TableHeader>
                <TableRow>
                    {columns.map((column) => (
                        <TableHeaderCell key={column.label} className={column.numeric ? styles.numeric : undefined}>
                            {column.label}
                        </TableHeaderCell>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row, index) => (
                    <TableRow key={index}>
                        {row.map((cell, cellIndex) => (
                            <TableCell key={cellIndex} className={columns[cellIndex]?.numeric ? styles.numeric : undefined}>
                                {cell}
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

/** Tracks an element's content width (charts are drawn in px). */
export function useElementWidth(ref: RefObject<HTMLElement | null>, fallback = 640): number {
    const [width, setWidth] = useState(fallback);

    useEffect(() => {
        const element = ref.current;
        if (!element) {
            return;
        }
        setWidth(element.clientWidth || fallback);
        if (typeof ResizeObserver === 'undefined') {
            return;
        }
        const observer = new ResizeObserver((entries) => {
            const next = Math.floor(entries[0]?.contentRect.width ?? 0);
            if (next > 0) {
                setWidth(next);
            }
        });
        observer.observe(element);

        return () => observer.disconnect();
    }, [ref, fallback]);

    return width;
}
