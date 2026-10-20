import { useState } from 'react';
import { Input, Text } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { ConditionValue } from '@/Lib/types/segments';
import { MAX_DAYS, MIN_DAYS, isIsoDate } from '../tree';
import { ChipsInput, useEditorStyles, type EditorBaseProps } from './shared';

interface ScalarEditorProps extends EditorBaseProps {
    value: ConditionValue;
    onChange: (value: ConditionValue) => void;
}

/** text: single string (`''` → `null` so incomplete rows carry `value: null`). */
export function TextValueEditor({ value, onChange, disabled, invalid, ariaLabel }: ScalarEditorProps) {
    const styles = useEditorStyles();
    const t = useText();

    return (
        <Input
            className={styles.input}
            value={typeof value === 'string' ? value : ''}
            disabled={disabled}
            aria-label={ariaLabel}
            aria-invalid={invalid || undefined}
            placeholder={t.conditionBuilder.value}
            onChange={(_, data) => onChange(data.value === '' ? null : data.value)}
        />
    );
}

/** text in / not_in: string[] (≤ 500). */
export function TextListEditor({ value, onChange, disabled, invalid, ariaLabel }: ScalarEditorProps) {
    const values = Array.isArray(value) ? (value as string[]).filter((v) => typeof v === 'string') : [];

    return (
        <ChipsInput<string>
            values={values}
            onChange={(next) => onChange(next.length > 0 ? next : null)}
            parse={(raw) => raw}
            disabled={disabled}
            invalid={invalid}
            ariaLabel={ariaLabel}
        />
    );
}

function parseNumber(raw: string): number | null {
    if (raw.trim() === '') return null;
    const n = Number(raw.replace(',', '.'));

    return Number.isFinite(n) ? n : null;
}

/** number: single number. Local text state lets the user type "-" or "1." freely. */
export function NumberValueEditor({ value, onChange, disabled, invalid, ariaLabel }: ScalarEditorProps) {
    const styles = useEditorStyles();
    const t = useText();
    const [text, setText] = useState(typeof value === 'number' ? String(value) : '');

    return (
        <Input
            className={styles.narrow}
            type="number"
            inputMode="decimal"
            value={text}
            disabled={disabled}
            aria-label={ariaLabel}
            aria-invalid={invalid || undefined}
            placeholder={t.conditionBuilder.value}
            onChange={(_, data) => {
                setText(data.value);
                onChange(parseNumber(data.value));
            }}
        />
    );
}

/** number between: `[min, max]`, emitted only when both bounds are valid. */
export function NumberRangeEditor({ value, onChange, disabled, invalid, ariaLabel }: ScalarEditorProps) {
    const styles = useEditorStyles();
    const t = useText();
    const initial = Array.isArray(value) && value.length === 2 ? value.map((v) => String(v)) : ['', ''];
    const [bounds, setBounds] = useState<string[]>(initial);

    function update(index: 0 | 1, raw: string) {
        const next = [...bounds];
        next[index] = raw;
        setBounds(next);
        const min = parseNumber(next[0]);
        const max = parseNumber(next[1]);
        onChange(min !== null && max !== null ? [min, max] : null);
    }

    return (
        <span className={styles.inline}>
            <Input
                className={styles.narrow}
                type="number"
                value={bounds[0]}
                disabled={disabled}
                aria-label={`${ariaLabel} — ${t.conditionBuilder.from}`}
                aria-invalid={invalid || undefined}
                placeholder={t.conditionBuilder.from}
                onChange={(_, data) => update(0, data.value)}
            />
            <Text>{t.conditionBuilder.and.toLowerCase()}</Text>
            <Input
                className={styles.narrow}
                type="number"
                value={bounds[1]}
                disabled={disabled}
                aria-label={`${ariaLabel} — ${t.conditionBuilder.to}`}
                aria-invalid={invalid || undefined}
                placeholder={t.conditionBuilder.to}
                onChange={(_, data) => update(1, data.value)}
            />
        </span>
    );
}

/** date on / before / after: absolute `YYYY-MM-DD`. */
export function DateValueEditor({ value, onChange, disabled, invalid, ariaLabel }: ScalarEditorProps) {
    const styles = useEditorStyles();

    return (
        <Input
            className={styles.input}
            type="date"
            value={typeof value === 'string' ? value : ''}
            disabled={disabled}
            aria-label={ariaLabel}
            aria-invalid={invalid || undefined}
            onChange={(_, data) => onChange(isIsoDate(data.value) ? data.value : null)}
        />
    );
}

/** date between: `["YYYY-MM-DD", "YYYY-MM-DD"]`, emitted only when both dates are set. */
export function DateRangeEditor({ value, onChange, disabled, invalid, ariaLabel }: ScalarEditorProps) {
    const styles = useEditorStyles();
    const t = useText();
    const initial = Array.isArray(value) && value.length === 2 ? value.map((v) => String(v)) : ['', ''];
    const [dates, setDates] = useState<string[]>(initial);

    function update(index: 0 | 1, raw: string) {
        const next = [...dates];
        next[index] = raw;
        setDates(next);
        onChange(isIsoDate(next[0]) && isIsoDate(next[1]) ? [next[0], next[1]] : null);
    }

    return (
        <span className={styles.inline}>
            <Text>{t.conditionBuilder.from}</Text>
            <Input
                type="date"
                value={dates[0]}
                disabled={disabled}
                aria-label={`${ariaLabel} — ${t.conditionBuilder.from}`}
                aria-invalid={invalid || undefined}
                onChange={(_, data) => update(0, data.value)}
            />
            <Text>{t.conditionBuilder.to}</Text>
            <Input
                type="date"
                value={dates[1]}
                disabled={disabled}
                aria-label={`${ariaLabel} — ${t.conditionBuilder.to}`}
                aria-invalid={invalid || undefined}
                onChange={(_, data) => update(1, data.value)}
            />
        </span>
    );
}

/** Relative date (`in_last_days`, `more_than_days_ago`, `in_next_days`) and engagement windows: integer 1–3650. */
export function DaysEditor({ value, onChange, disabled, invalid, ariaLabel }: ScalarEditorProps) {
    const styles = useEditorStyles();
    const t = useText();
    const [text, setText] = useState(typeof value === 'number' ? String(value) : '');

    return (
        <span className={styles.inline}>
            <Input
                className={styles.narrow}
                type="number"
                inputMode="numeric"
                min={MIN_DAYS}
                max={MAX_DAYS}
                step={1}
                value={text}
                disabled={disabled}
                aria-label={`${ariaLabel} (${t.conditionBuilder.days})`}
                aria-invalid={invalid || undefined}
                onChange={(_, data) => {
                    setText(data.value);
                    const n = parseNumber(data.value);
                    onChange(n !== null && Number.isInteger(n) ? n : null);
                }}
            />
            <Text>{t.conditionBuilder.days}</Text>
        </span>
    );
}
