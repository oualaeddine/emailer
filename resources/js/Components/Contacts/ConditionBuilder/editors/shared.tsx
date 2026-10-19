import { useState, type KeyboardEvent } from 'react';
import { Input, Tag, TagGroup, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';

/** `format('%d contacts', 3)` — positional `%d` / `%s` replacement for fr.ts strings. */
export function format(template: string, ...args: Array<string | number>): string {
    let index = 0;

    return template.replace(/%[ds]/g, (match) => (index < args.length ? String(args[index++]) : match));
}

export const useEditorStyles = makeStyles({
    inline: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        minWidth: 0,
    },
    stack: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        minWidth: 0,
    },
    input: {
        minWidth: '160px',
        maxWidth: '100%',
    },
    narrow: {
        width: '110px',
    },
    chips: {
        flexWrap: 'wrap',
        rowGap: tokens.spacingVerticalXXS,
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
});

export interface EditorBaseProps {
    disabled?: boolean;
    invalid?: boolean;
    /** Accessible name of the value control (field label). */
    ariaLabel: string;
}

interface ChipsInputProps<V extends string | number> extends EditorBaseProps {
    values: V[];
    onChange: (values: V[]) => void;
    /** Converts typed text into a value; return `null` to reject it. */
    parse: (raw: string) => V | null;
    labelFor?: (value: V) => string;
    placeholder?: string;
    max?: number;
}

/**
 * Free-entry multi-value input: type a value then Enter (or `,` / `;`) to add
 * it; pasted comma-separated text is split. Chips are dismissible from the
 * keyboard (Delete / Backspace on a focused chip).
 */
export function ChipsInput<V extends string | number>({
    values,
    onChange,
    parse,
    labelFor,
    placeholder,
    max = 500,
    disabled,
    invalid,
    ariaLabel,
}: ChipsInputProps<V>) {
    const styles = useEditorStyles();
    const t = useText();
    const [draft, setDraft] = useState('');

    function commit(raw: string) {
        const parsed = raw
            .split(/[,;\n]/)
            .map((part) => part.trim())
            .filter((part) => part !== '')
            .map(parse)
            .filter((v): v is V => v !== null);
        const next = [...values];
        for (const v of parsed) {
            if (!next.includes(v) && next.length < max) next.push(v);
        }
        if (next.length !== values.length) onChange(next);
        setDraft('');
    }

    function onKeyDown(event: KeyboardEvent<HTMLInputElement>) {
        if (event.key === 'Enter' || event.key === ',' || event.key === ';') {
            event.preventDefault();
            commit(draft);
        } else if (event.key === 'Backspace' && draft === '' && values.length > 0) {
            onChange(values.slice(0, -1));
        }
    }

    return (
        <div className={styles.stack}>
            <Input
                className={styles.input}
                value={draft}
                disabled={disabled}
                aria-label={ariaLabel}
                aria-invalid={invalid || undefined}
                placeholder={placeholder ?? t.conditionBuilder.chooseValue}
                onChange={(_, data) => {
                    if (/[,;\n]/.test(data.value)) commit(data.value);
                    else setDraft(data.value);
                }}
                onKeyDown={onKeyDown}
                onBlur={() => draft.trim() !== '' && commit(draft)}
            />
            {values.length > 0 && (
                <TagGroup
                    className={styles.chips}
                    size="small"
                    disabled={disabled}
                    aria-label={ariaLabel}
                    onDismiss={(_, data) => onChange(values.filter((v) => String(v) !== String(data.value)))}
                >
                    {values.map((v) => (
                        <Tag key={String(v)} value={String(v)} dismissible dismissIcon={{ 'aria-label': t.common.remove }}>
                            {labelFor ? labelFor(v) : String(v)}
                        </Tag>
                    ))}
                </TagGroup>
            )}
        </div>
    );
}
