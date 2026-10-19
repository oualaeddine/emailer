import { useMemo, useState } from 'react';
import { Combobox, Option, OptionGroup, makeStyles } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { FieldDefinition, FieldKey } from '@/Lib/types/segments';
import { FIELD_CATEGORIES } from './catalog';

const useStyles = makeStyles({
    root: {
        minWidth: '200px',
        maxWidth: '100%',
    },
});

const normalize = (s: string) => s.normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();

interface FieldPickerProps {
    fields: FieldDefinition[];
    value: FieldKey;
    onChange: (field: FieldDefinition) => void;
    disabled?: boolean;
    invalid?: boolean;
    ariaLabel: string;
}

/**
 * Searchable field picker grouped by category (*Attributs du contact*,
 * *Contact*, *Listes*, *Engagement e-mail*, *Dates*, *PagesJaunes*).
 * Typing filters; arrow keys + Enter select.
 */
export function FieldPicker({ fields, value, onChange, disabled, invalid, ariaLabel }: FieldPickerProps) {
    const styles = useStyles();
    const t = useText();
    const [query, setQuery] = useState<string | null>(null);
    const selected = fields.find((f) => f.key === value);

    const groups = useMemo(() => {
        const needle = query ? normalize(query.trim()) : '';
        const visible =
            needle === '' ? fields : fields.filter((f) => normalize(f.label).includes(needle) || f.key.toLowerCase().includes(needle));

        return FIELD_CATEGORIES.map((category) => ({
            category,
            fields: visible.filter((f) => f.category === category),
        })).filter((g) => g.fields.length > 0);
    }, [fields, query]);

    return (
        <Combobox
            className={styles.root}
            disabled={disabled}
            aria-label={ariaLabel}
            aria-invalid={invalid || undefined}
            placeholder={t.conditionBuilder.chooseField}
            value={query ?? selected?.label ?? value}
            selectedOptions={selected ? [selected.key] : []}
            onChange={(event) => setQuery(event.target.value)}
            onOpenChange={(_, data) => {
                if (!data.open) setQuery(null);
            }}
            onOptionSelect={(_, data) => {
                const field = fields.find((f) => f.key === data.optionValue);
                setQuery(null);
                if (field && field.key !== value) onChange(field);
            }}
        >
            {groups.length === 0 ? (
                <Option value="__none" text={t.common.noResults} disabled>
                    {t.common.noResults}
                </Option>
            ) : (
                groups.map((group) => (
                    <OptionGroup key={group.category} label={t.conditionBuilder.categories[group.category]}>
                        {group.fields.map((f) => (
                            <Option key={f.key} value={f.key} text={f.label}>
                                {f.label}
                            </Option>
                        ))}
                    </OptionGroup>
                ))
            )}
        </Combobox>
    );
}
