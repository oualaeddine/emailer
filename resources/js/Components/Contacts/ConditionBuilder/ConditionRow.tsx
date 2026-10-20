import { Button, Dropdown, Option, Text, Tooltip, makeStyles, tokens } from '@fluentui/react-components';
import { DeleteRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import type { ConditionOptionSources, FieldDefinition, RuleCondition } from '@/Lib/types/segments';
import { operatorLabel, operatorsFor } from './catalog';
import { FieldPicker } from './FieldPicker';
import { isConditionComplete, valueShape, withField, withOperator } from './tree';
import { ValueEditor } from './editors/ValueEditor';

const useStyles = makeStyles({
    row: {
        display: 'flex',
        alignItems: 'flex-start',
        gap: tokens.spacingHorizontalS,
        minWidth: 0,
    },
    connector: {
        flexShrink: 0,
        width: '32px',
        paddingTop: '6px',
        color: tokens.colorNeutralForeground3,
        fontWeight: tokens.fontWeightSemibold,
        textAlign: 'right',
    },
    controls: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'flex-start',
        gap: tokens.spacingHorizontalS,
        rowGap: tokens.spacingVerticalXS,
        flexGrow: 1,
        minWidth: 0,
    },
    operator: {
        minWidth: '170px',
    },
    body: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
        flexGrow: 1,
        minWidth: 0,
    },
    error: {
        color: tokens.colorPaletteRedForeground1,
    },
});

export interface ConditionRowProps {
    condition: RuleCondition;
    /** Dot path of the row ("children.0.children.1"), used for ids and error lookup. */
    path: string;
    /** Pickable fields (catalog filtered by `categories`). */
    fields: FieldDefinition[];
    /** Full catalog lookup (a row may reference a field hidden by `categories`). */
    findField: (key: RuleCondition['field']) => FieldDefinition | undefined;
    /** "ET" / "OU" shown before every row but the first; `null` for the first row. */
    connector: string | null;
    onChange: (condition: RuleCondition) => void;
    onRemove: () => void;
    optionSources?: ConditionOptionSources;
    serverErrors: string[];
    /** Show "incomplete" messages even on untouched rows (after a count / save attempt). */
    showValidation: boolean;
    disabled?: boolean;
}

/** One condition: field · operator · value, with its own validation message. */
export function ConditionRow({
    condition,
    path,
    fields,
    findField,
    connector,
    onChange,
    onRemove,
    optionSources,
    serverErrors,
    showValidation,
    disabled,
}: ConditionRowProps) {
    const styles = useStyles();
    const t = useText();
    const field = findField(condition.field);
    const operators = field ? operatorsFor(field) : [condition.operator];
    const complete = isConditionComplete(condition, field ? { fields: [field] } : undefined);
    const shape = valueShape(field?.type, condition.operator);
    const clientInvalid = !complete && (showValidation || condition.value !== null || !field);
    const messages =
        serverErrors.length > 0
            ? serverErrors
            : clientInvalid
              ? [field ? t.conditionBuilder.incomplete : t.conditionBuilder.chooseField]
              : [];
    const messageId = `cb-${path.replace(/\./g, '-')}-msg`;
    const fieldLabel = field?.label ?? condition.field;

    return (
        <div className={styles.row} role="group" aria-label={fieldLabel} aria-describedby={messages.length > 0 ? messageId : undefined}>
            <Text className={styles.connector} aria-hidden={connector === null}>
                {connector ?? ''}
            </Text>
            <div className={styles.body}>
                <div className={styles.controls}>
                    <FieldPicker
                        fields={field && !fields.includes(field) ? [field, ...fields] : fields}
                        value={condition.field}
                        onChange={(next) => onChange(withField(condition, next))}
                        disabled={disabled}
                        invalid={!field}
                        ariaLabel={t.conditionBuilder.chooseField}
                    />
                    <Dropdown
                        className={styles.operator}
                        disabled={disabled || !field}
                        aria-label={t.conditionBuilder.chooseOperator}
                        placeholder={t.conditionBuilder.chooseOperator}
                        selectedOptions={[condition.operator]}
                        value={operatorLabel(field?.type, condition.operator)}
                        onOptionSelect={(_, data) => {
                            const op = operators.find((o) => o === data.optionValue);
                            if (op && op !== condition.operator) onChange(withOperator(condition, field, op));
                        }}
                    >
                        {operators.map((op) => (
                            <Option key={op} value={op}>
                                {operatorLabel(field?.type, op)}
                            </Option>
                        ))}
                    </Dropdown>
                    {field && shape !== 'none' && (
                        <ValueEditor
                            key={`${condition.field}|${condition.operator}`}
                            field={field}
                            condition={condition}
                            onChange={(value) => onChange({ ...condition, value })}
                            optionSources={optionSources}
                            disabled={disabled}
                            invalid={messages.length > 0}
                            ariaLabel={`${fieldLabel} — ${t.conditionBuilder.value}`}
                        />
                    )}
                </div>
                {messages.length > 0 && (
                    <Text id={messageId} size={200} className={styles.error} aria-live="polite">
                        {messages.join(' ')}
                    </Text>
                )}
            </div>
            <Tooltip content={t.conditionBuilder.removeCondition} relationship="label">
                <Button appearance="subtle" icon={<DeleteRegular />} disabled={disabled} onClick={onRemove} />
            </Tooltip>
        </div>
    );
}
