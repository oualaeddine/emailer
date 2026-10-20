import { useRef } from 'react';
import { Text, Tooltip, makeStyles, tokens } from '@fluentui/react-components';
import { formatCount, formatRate } from '@/Components/Campaigns/campaignUi';
import { reportMissingText, useChartPalette } from '@/Components/Reports/reportUi';

const useStyles = makeStyles({
    list: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        margin: 0,
        padding: 0,
        listStyleType: 'none',
    },
    row: {
        display: 'grid',
        gridTemplateColumns: 'minmax(90px, 160px) minmax(0, 1fr)',
        alignItems: 'center',
        gap: tokens.spacingHorizontalM,
        borderRadius: tokens.borderRadiusSmall,
        ':focus-visible': {
            outline: `${tokens.strokeWidthThick} solid ${tokens.colorStrokeFocus2}`,
        },
        ':hover': {
            backgroundColor: tokens.colorNeutralBackground1Hover,
        },
    },
    label: {
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
        color: tokens.colorNeutralForeground1,
    },
    track: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        minWidth: 0,
        minHeight: '24px',
    },
    bar: {
        height: '16px',
        borderRadius: '0 4px 4px 0',
        flexShrink: 0,
    },
    value: {
        whiteSpace: 'nowrap',
        color: tokens.colorNeutralForeground2,
        fontVariantNumeric: 'tabular-nums',
    },
    empty: {
        color: tokens.colorNeutralForeground3,
    },
});

export interface HorizontalBarItem {
    key: string;
    label: string;
    value: number;
    /** Extra tooltip line (e.g. clicks next to opens). */
    detail?: string;
}

/**
 * Single-series horizontal bars (slot 1), value + share at the bar tip, hover
 * and focus tooltip per bar. Used for the device split and short rankings.
 */
export function HorizontalBars({ items, ariaLabel }: { items: HorizontalBarItem[]; ariaLabel: string }) {
    const styles = useStyles();
    const ref = useRef<HTMLUListElement>(null);
    const palette = useChartPalette(ref);
    const total = items.reduce((sum, item) => sum + item.value, 0);
    const max = Math.max(0, ...items.map((item) => item.value));

    if (total === 0) {
        return <Text className={styles.empty}>{reportMissingText.noData}</Text>;
    }

    return (
        <ul ref={ref} className={styles.list} aria-label={ariaLabel}>
            {items.map((item) => {
                const share = item.value / total;
                const text = `${formatCount(item.value)} (${formatRate(share)})`;

                return (
                    <Tooltip
                        key={item.key}
                        relationship="description"
                        content={item.detail ? `${item.label} · ${text} · ${item.detail}` : `${item.label} · ${text}`}
                    >
                        <li className={styles.row} tabIndex={0}>
                            <Text className={styles.label}>{item.label}</Text>
                            <span className={styles.track}>
                                <span
                                    className={styles.bar}
                                    style={{
                                        width: `calc((100% - 110px) * ${max > 0 ? item.value / max : 0})`,
                                        minWidth: item.value > 0 ? '2px' : 0,
                                        backgroundColor: palette.series[0],
                                    }}
                                />
                                <Text size={200} className={styles.value}>
                                    {text}
                                </Text>
                            </span>
                        </li>
                    </Tooltip>
                );
            })}
        </ul>
    );
}
