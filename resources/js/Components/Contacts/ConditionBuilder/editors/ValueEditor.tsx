import type { ConditionOptionSources, ConditionValue, EngagementValue, FieldDefinition, RuleCondition } from '@/Lib/types/segments';
import { valueShape } from '../tree';
import { EngagementEditor } from './EngagementEditor';
import { AsyncOptionsPicker, StaticOptionsPicker } from './OptionPickers';
import {
    DateRangeEditor,
    DateValueEditor,
    DaysEditor,
    NumberRangeEditor,
    NumberValueEditor,
    TextListEditor,
    TextValueEditor,
} from './ScalarEditors';
import { ChipsInput } from './shared';

export interface ValueEditorProps {
    field: FieldDefinition | undefined;
    condition: RuleCondition;
    onChange: (value: ConditionValue) => void;
    optionSources?: ConditionOptionSources;
    disabled?: boolean;
    invalid?: boolean;
    ariaLabel: string;
}

const asStrings = (value: ConditionValue): string[] =>
    Array.isArray(value) ? (value as unknown[]).filter((v): v is string => typeof v === 'string') : [];
const asNumbers = (value: ConditionValue): number[] =>
    Array.isArray(value) ? (value as unknown[]).filter((v): v is number => typeof v === 'number') : [];
const orNull = <T,>(list: T[]): T[] | null => (list.length > 0 ? list : null);

/** Picks the value editor for the condition's (field type, operator) pair (§45.5.2 operator table). */
export function ValueEditor({ field, condition, onChange, optionSources, disabled, invalid, ariaLabel }: ValueEditorProps) {
    const shape = valueShape(field?.type, condition.operator);
    const common = { disabled, invalid, ariaLabel };
    const value = condition.value;

    switch (shape) {
        case 'none':
            return null;
        case 'text':
            return <TextValueEditor value={value} onChange={onChange} {...common} />;
        case 'text_list':
            return <TextListEditor value={value} onChange={onChange} {...common} />;
        case 'number':
            return <NumberValueEditor value={value} onChange={onChange} {...common} />;
        case 'number_range':
            return <NumberRangeEditor value={value} onChange={onChange} {...common} />;
        case 'date':
            return <DateValueEditor value={value} onChange={onChange} {...common} />;
        case 'date_range':
            return <DateRangeEditor value={value} onChange={onChange} {...common} />;
        case 'days':
            return <DaysEditor value={value} onChange={onChange} {...common} />;
        case 'options':
            return (
                <StaticOptionsPicker
                    options={field?.options ?? []}
                    values={asStrings(value)}
                    onChange={(v) => onChange(orNull(v))}
                    {...common}
                />
            );
        case 'lists':
        case 'users': {
            const source = shape === 'lists' ? optionSources?.lists : optionSources?.users;
            if (source) {
                return (
                    <AsyncOptionsPicker<string>
                        source={source}
                        values={asStrings(value)}
                        onChange={(v) => onChange(orNull(v))}
                        decode={(raw) => raw}
                        {...common}
                    />
                );
            }

            return <ChipsInput<string> values={asStrings(value)} onChange={(v) => onChange(orNull(v))} parse={(raw) => raw} {...common} />;
        }
        case 'tags': {
            const source = optionSources?.tags;
            if (source) {
                return (
                    <AsyncOptionsPicker<number>
                        source={source}
                        values={asNumbers(value)}
                        onChange={(v) => onChange(orNull(v))}
                        decode={(raw) => Number(raw)}
                        {...common}
                    />
                );
            }
            if (field?.options && field.options.length > 0) {
                return (
                    <StaticOptionsPicker
                        options={field.options}
                        values={asNumbers(value).map(String)}
                        onChange={(v) => onChange(orNull(v.map(Number)))}
                        {...common}
                    />
                );
            }

            return (
                <ChipsInput<number>
                    values={asNumbers(value)}
                    onChange={(v) => onChange(orNull(v))}
                    parse={(raw) => (/^\d+$/.test(raw) ? Number(raw) : null)}
                    {...common}
                />
            );
        }
        case 'engagement':
            return (
                <EngagementEditor
                    field={condition.field}
                    value={value && typeof value === 'object' && !Array.isArray(value) ? (value as EngagementValue) : null}
                    onChange={onChange}
                    campaigns={optionSources?.campaigns}
                    {...common}
                />
            );
    }
}
