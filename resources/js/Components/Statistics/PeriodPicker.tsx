import { useState } from 'react';
import { Button, Input, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';

export type PeriodPreset = 7 | 30 | 90 | 'custom';

export interface PeriodValue {
    preset: PeriodPreset;
    date_from: string;
    date_to: string;
}

const useStyles = makeStyles({
    root: { display: 'flex', flexWrap: 'wrap', alignItems: 'flex-end', gap: tokens.spacingHorizontalS },
    group: { display: 'flex', gap: tokens.spacingHorizontalXS },
    field: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalXXS },
});

function iso(date: Date): string {
    return date.toISOString().slice(0, 10);
}

export function periodForDays(days: 7 | 30 | 90): PeriodValue {
    const to = new Date();
    const from = new Date();
    from.setDate(from.getDate() - (days - 1));

    return { preset: days, date_from: iso(from), date_to: iso(to) };
}

interface Props {
    value: PeriodValue;
    onChange: (value: PeriodValue) => void;
}

export function PeriodPicker({ value, onChange }: Props) {
    const styles = useStyles();
    const t = useText();
    const [from, setFrom] = useState(value.date_from);
    const [to, setTo] = useState(value.date_to);
    const presets: Array<{ days: 7 | 30 | 90; label: string }> = [
        { days: 7, label: t.statistics.presets.last7 },
        { days: 30, label: t.statistics.presets.last30 },
        { days: 90, label: t.statistics.presets.last90 },
    ];

    return (
        <div className={styles.root} role="group" aria-label={t.statistics.period}>
            <div className={styles.group}>
                {presets.map((preset) => (
                    <Button
                        key={preset.days}
                        appearance={value.preset === preset.days ? 'primary' : 'secondary'}
                        onClick={() => {
                            const next = periodForDays(preset.days);
                            setFrom(next.date_from);
                            setTo(next.date_to);
                            onChange(next);
                        }}
                    >
                        {preset.label}
                    </Button>
                ))}
                <Button
                    appearance={value.preset === 'custom' ? 'primary' : 'secondary'}
                    onClick={() => onChange({ preset: 'custom', date_from: from, date_to: to })}
                >
                    {t.statistics.presets.custom}
                </Button>
            </div>
            {value.preset === 'custom' && (
                <>
                    <div className={styles.field}>
                        <Text size={200}>{t.reporting.dateFrom}</Text>
                        <Input
                            type="date"
                            value={from}
                            max={to}
                            onChange={(_, d) => {
                                setFrom(d.value);
                                if (d.value) onChange({ preset: 'custom', date_from: d.value, date_to: to });
                            }}
                        />
                    </div>
                    <div className={styles.field}>
                        <Text size={200}>{t.reporting.dateTo}</Text>
                        <Input
                            type="date"
                            value={to}
                            min={from}
                            onChange={(_, d) => {
                                setTo(d.value);
                                if (d.value) onChange({ preset: 'custom', date_from: from, date_to: d.value });
                            }}
                        />
                    </div>
                </>
            )}
        </div>
    );
}
