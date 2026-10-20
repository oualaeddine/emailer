import type { ReactNode } from 'react';
import { Text, Tooltip, makeStyles, mergeClasses, tokens } from '@fluentui/react-components';
import { ArrowDownRegular, ArrowUpRegular, SubtractRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { formatText, formatRate } from '@/Components/Campaigns/campaignUi';
import { LOWER_IS_BETTER } from '@/Components/Reports/reportUi';
import type { RateSet } from '@/Lib/types/reports';

const useStyles = makeStyles({
    row: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(150px, 1fr))',
        gap: tokens.spacingHorizontalM,
    },
    tile: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        padding: tokens.spacingVerticalM,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground1,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        minWidth: 0,
    },
    label: {
        color: tokens.colorNeutralForeground2,
    },
    valueRow: {
        display: 'flex',
        alignItems: 'baseline',
        flexWrap: 'wrap',
        columnGap: tokens.spacingHorizontalS,
    },
    value: {
        fontSize: tokens.fontSizeHero700,
        lineHeight: tokens.lineHeightHero700,
        fontWeight: tokens.fontWeightSemibold,
        color: tokens.colorNeutralForeground1,
    },
    rate: {
        color: tokens.colorNeutralForeground2,
        fontWeight: tokens.fontWeightSemibold,
    },
    delta: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXXS,
        fontSize: tokens.fontSizeBase200,
        color: tokens.colorNeutralForeground3,
    },
    good: { color: tokens.colorPaletteGreenForeground1 },
    bad: { color: tokens.colorPaletteRedForeground1 },
    hint: {
        color: tokens.colorNeutralForeground3,
    },
});

export interface KpiTileProps {
    label: string;
    value: string;
    /** Rate shown next to the value (e.g. "98,0 %"). */
    rateText?: string;
    /** Current ratio 0–1 and the account average for "vs moyenne du compte". */
    rate?: number | null;
    average?: number | null;
    /** Which RateSet key, to know whether higher is better. */
    rateKey?: keyof RateSet;
    hint?: ReactNode;
}

/** KPI tile: label · value · rate · delta vs account average (docs/43 §43.4.15). */
export function KpiTile({ label, value, rateText, rate, average, rateKey, hint }: KpiTileProps) {
    const styles = useStyles();
    const t = useText();
    const hasDelta = rate !== null && rate !== undefined && average !== null && average !== undefined;

    let deltaNode: ReactNode = null;
    if (hasDelta) {
        const diff = rate - average;
        const flat = Math.abs(diff) < 0.0005;
        const lowerIsBetter = rateKey ? LOWER_IS_BETTER.has(rateKey) : false;
        const good = flat ? null : diff > 0 !== lowerIsBetter;
        const directionLabel = flat ? undefined : diff > 0 ? t.campaignReport.aboveAverage : t.campaignReport.belowAverage;
        const Icon = flat ? SubtractRegular : diff > 0 ? ArrowUpRegular : ArrowDownRegular;
        const text = formatText(t.campaignReport.vsAverage, formatRate(average));

        deltaNode = (
            <Tooltip content={directionLabel ?? text} relationship="description">
                <span
                    className={mergeClasses(styles.delta, good === true && styles.good, good === false && styles.bad)}
                    tabIndex={0}
                    aria-label={directionLabel ? `${directionLabel}. ${text}` : text}
                >
                    <Icon aria-hidden="true" />
                    <span>{text}</span>
                </span>
            </Tooltip>
        );
    }

    return (
        <div className={styles.tile}>
            <Text size={300} className={styles.label}>
                {label}
            </Text>
            <div className={styles.valueRow}>
                <span className={styles.value}>{value}</span>
                {rateText && <span className={styles.rate}>{rateText}</span>}
            </div>
            {deltaNode}
            {hint && (
                <Text size={200} className={styles.hint}>
                    {hint}
                </Text>
            )}
        </div>
    );
}

/** Responsive row of KPI tiles. */
export function KpiRow({ children }: { children: ReactNode }) {
    return <div className={useStyles().row}>{children}</div>;
}
