import { useEffect, useRef, useState } from 'react';
import { Combobox, Dropdown, Option, Spinner, Tag, TagGroup } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { ConditionOption, FieldOption } from '@/Lib/types/segments';
import { useEditorStyles, type EditorBaseProps } from './shared';

interface StaticOptionsPickerProps extends EditorBaseProps {
    options: FieldOption[];
    values: string[];
    onChange: (values: string[]) => void;
}

/**
 * Category / multiple-choice / contact status / source: multi-select over the
 * field's options (`Dropdown multiselect`, fully keyboard operable).
 */
export function StaticOptionsPicker({ options, values, onChange, disabled, invalid, ariaLabel }: StaticOptionsPickerProps) {
    const styles = useEditorStyles();
    const t = useText();
    const labelOf = (v: string) => options.find((o) => String(o.value) === v)?.label ?? v;

    return (
        <Dropdown
            className={styles.input}
            multiselect
            disabled={disabled}
            aria-label={ariaLabel}
            aria-invalid={invalid || undefined}
            placeholder={t.conditionBuilder.chooseValue}
            selectedOptions={values}
            value={values.map(labelOf).join(', ')}
            onOptionSelect={(_, data) => onChange(data.selectedOptions)}
        >
            {options.map((o) => (
                <Option key={String(o.value)} value={String(o.value)}>
                    {o.label}
                </Option>
            ))}
        </Dropdown>
    );
}

interface AsyncOptionsPickerProps<V extends string | number> extends EditorBaseProps {
    source: (q: string) => Promise<ConditionOption<V>[]>;
    values: V[];
    onChange: (values: V[]) => void;
    /** Converts the Option's string value back to the typed value (tags are numbers). */
    decode: (raw: string) => V;
    max?: number;
}

/**
 * Lists / campaigns / users / tags: searchable multi-select fed by an injected
 * source (§45.5.3 `optionSources`). Selected items are shown as dismissible
 * chips; labels of selected values are cached so they survive new searches.
 */
export function AsyncOptionsPicker<V extends string | number>({
    source,
    values,
    onChange,
    decode,
    max = 500,
    disabled,
    invalid,
    ariaLabel,
}: AsyncOptionsPickerProps<V>) {
    const styles = useEditorStyles();
    const t = useText();
    const [query, setQuery] = useState('');
    const [options, setOptions] = useState<ConditionOption<V>[]>([]);
    const [loading, setLoading] = useState(false);
    const labels = useRef(new Map<string, string>());
    const [, setLabelsVersion] = useState(0);
    const sourceRef = useRef(source);
    sourceRef.current = source;

    useEffect(() => {
        let cancelled = false;
        const timer = window.setTimeout(() => {
            setLoading(true);
            sourceRef
                .current(query)
                .then((result) => {
                    if (cancelled) return;
                    result.forEach((o) => labels.current.set(String(o.value), o.label));
                    setOptions(result);
                    setLabelsVersion((n) => n + 1);
                })
                .catch(() => {
                    if (!cancelled) setOptions([]);
                })
                .finally(() => {
                    if (!cancelled) setLoading(false);
                });
        }, 250);

        return () => {
            cancelled = true;
            window.clearTimeout(timer);
        };
    }, [query]);

    const selected = values.map((v) => String(v));
    const labelOf = (raw: string) => labels.current.get(raw) ?? raw;

    return (
        <div className={styles.stack}>
            <Combobox
                className={styles.input}
                multiselect
                freeform
                disabled={disabled}
                aria-label={ariaLabel}
                aria-invalid={invalid || undefined}
                placeholder={t.common.searchPlaceholder}
                value={query}
                selectedOptions={selected}
                onChange={(event) => setQuery(event.target.value)}
                onOptionSelect={(_, data) => {
                    const next = data.selectedOptions.slice(0, max).map(decode);
                    onChange(next);
                    setQuery('');
                }}
            >
                {loading && options.length === 0 ? (
                    <Option value="__loading" text={t.common.loading} disabled>
                        <Spinner size="extra-tiny" label={t.common.loading} />
                    </Option>
                ) : options.length === 0 ? (
                    <Option value="__empty" text={t.common.noResults} disabled>
                        {t.common.noResults}
                    </Option>
                ) : (
                    options.map((o) => (
                        <Option key={String(o.value)} value={String(o.value)} text={o.label}>
                            {o.label}
                        </Option>
                    ))
                )}
            </Combobox>
            {values.length > 0 && (
                <TagGroup
                    className={styles.chips}
                    size="small"
                    disabled={disabled}
                    aria-label={ariaLabel}
                    onDismiss={(_, data) => onChange(values.filter((v) => String(v) !== String(data.value)))}
                >
                    {selected.map((raw) => (
                        <Tag key={raw} value={raw} dismissible dismissIcon={{ 'aria-label': t.common.remove }}>
                            {labelOf(raw)}
                        </Tag>
                    ))}
                </TagGroup>
            )}
        </div>
    );
}
