import { Dropdown, Input, Option, Radio, RadioGroup } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { ContactAttribute, ContactAttributeValue } from '@/Lib/types/attributes';

export interface AttributeValueEditorProps {
    attribute: ContactAttribute | undefined;
    value: ContactAttributeValue;
    onChange: (value: ContactAttributeValue) => void;
    disabled?: boolean;
    invalid?: boolean;
    ariaLabel: string;
}

/**
 * Value editor by attribute type (§45.5.1 value encoding): text → string,
 * number → number, date → "YYYY-MM-DD", boolean → true|false, category →
 * option value, multiple_choice → option values, user → user uuid.
 */
export function AttributeValueEditor({ attribute, value, onChange, disabled, invalid, ariaLabel }: AttributeValueEditorProps) {
    const t = useText();
    const type = attribute?.type ?? 'text';
    const common = { disabled: disabled || !attribute, 'aria-label': ariaLabel, 'aria-invalid': invalid || undefined };

    switch (type) {
        case 'number':
            return (
                <Input
                    {...common}
                    type="number"
                    value={typeof value === 'number' ? String(value) : ''}
                    onChange={(_, data) => onChange(data.value === '' ? null : Number(data.value))}
                />
            );
        case 'date':
            return (
                <Input
                    {...common}
                    type="date"
                    value={typeof value === 'string' ? value : ''}
                    onChange={(_, data) => onChange(data.value === '' ? null : data.value)}
                />
            );
        case 'boolean':
            return (
                <RadioGroup
                    {...common}
                    layout="horizontal"
                    value={value === true ? 'true' : value === false ? 'false' : ''}
                    onChange={(_, data) => onChange(data.value === 'true')}
                >
                    <Radio value="true" label={t.conditionBuilder.booleanValues.true} />
                    <Radio value="false" label={t.conditionBuilder.booleanValues.false} />
                </RadioGroup>
            );
        case 'category': {
            const options = attribute?.options ?? [];
            const selected = typeof value === 'string' ? value : '';

            return (
                <Dropdown
                    {...common}
                    placeholder={t.conditionBuilder.chooseValue}
                    selectedOptions={selected ? [selected] : []}
                    value={options.find((o) => o.value === selected)?.label ?? selected}
                    onOptionSelect={(_, data) => onChange(data.optionValue ?? null)}
                >
                    {options.map((option) => (
                        <Option key={option.value} value={option.value}>
                            {option.label}
                        </Option>
                    ))}
                </Dropdown>
            );
        }
        case 'multiple_choice': {
            const options = attribute?.options ?? [];
            const selected = Array.isArray(value) ? value : [];

            return (
                <Dropdown
                    {...common}
                    multiselect
                    placeholder={t.conditionBuilder.chooseValue}
                    selectedOptions={selected}
                    value={selected.map((v) => options.find((o) => o.value === v)?.label ?? v).join(', ')}
                    onOptionSelect={(_, data) => onChange(data.selectedOptions)}
                >
                    {options.map((option) => (
                        <Option key={option.value} value={option.value}>
                            {option.label}
                        </Option>
                    ))}
                </Dropdown>
            );
        }
        default:
            return (
                <Input
                    {...common}
                    value={typeof value === 'string' ? value : value === null ? '' : String(value)}
                    onChange={(_, data) => onChange(data.value === '' ? null : data.value)}
                />
            );
    }
}

/** Display form of an attribute value in card summaries. */
export function formatAttributeValue(attribute: ContactAttribute | undefined, value: ContactAttributeValue, yes: string, no: string): string {
    if (value === null || value === '') return '—';
    if (typeof value === 'boolean') return value ? yes : no;
    const labelOf = (v: string) => attribute?.options.find((o) => o.value === v)?.label ?? v;
    if (Array.isArray(value)) return value.map(labelOf).join(', ');
    if (typeof value === 'number') return value.toLocaleString('fr-FR');
    if (attribute?.type === 'date' && /^\d{4}-\d{2}-\d{2}$/.test(value)) {
        return new Date(`${value}T00:00:00`).toLocaleDateString('fr-FR');
    }

    return labelOf(value);
}
