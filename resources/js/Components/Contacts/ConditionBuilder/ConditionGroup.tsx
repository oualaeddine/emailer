import { Button, Dropdown, Option, Text, Tooltip, makeStyles, tokens } from '@fluentui/react-components';
import { AddRegular, DismissRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import type { ConditionOptionSources, FieldDefinition, RuleCondition, RuleGroup, RuleLogicalOperator } from '@/Lib/types/segments';
import { ConditionRow } from './ConditionRow';
import { errorsAt, inheritKey, nodeKey, nodePath } from './tree';

const useStyles = makeStyles({
    card: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        padding: tokens.spacingVerticalM,
        borderRadius: tokens.borderRadiusMedium,
        border: `1px solid ${tokens.colorNeutralStroke2}`,
        backgroundColor: tokens.colorNeutralBackground1,
        minWidth: 0,
    },
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalS,
        flexWrap: 'wrap',
    },
    footer: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        paddingLeft: '40px',
    },
    error: {
        color: tokens.colorPaletteRedForeground1,
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
});

export interface ConditionGroupProps {
    group: RuleGroup;
    /** Index of the group among the root children (dot path `children.<index>`). */
    index: number;
    fields: FieldDefinition[];
    findField: (key: RuleCondition['field']) => FieldDefinition | undefined;
    onChange: (group: RuleGroup) => void;
    /** Removes the whole group. */
    onRemove: () => void;
    /** Field used for a newly added row. */
    newCondition: () => RuleCondition | null;
    canAddCondition: boolean;
    optionSources?: ConditionOptionSources;
    errors?: Record<string, string>;
    showValidation: boolean;
    disabled?: boolean;
    /** Accessible name of the group ("Groupe 2"). */
    label: string;
}

/** A group of conditions joined by the group's AND/OR operator. */
export function ConditionGroup({
    group,
    index,
    fields,
    findField,
    onChange,
    onRemove,
    newCondition,
    canAddCondition,
    optionSources,
    errors,
    showValidation,
    disabled,
    label,
}: ConditionGroupProps) {
    const styles = useStyles();
    const t = useText();
    const path = nodePath(index);
    const groupErrors = errorsAt(errors, path, false);
    const connector = group.operator === 'and' ? t.conditionBuilder.and : t.conditionBuilder.or;
    const operatorLabels: Record<RuleLogicalOperator, string> = {
        and: t.conditionBuilder.matchAll,
        or: t.conditionBuilder.matchAny,
    };

    function updateChild(childIndex: number, condition: RuleCondition) {
        onChange({ ...group, children: group.children.map((c, i) => (i === childIndex ? inheritKey(c, condition) : c)) });
    }

    function removeChild(childIndex: number) {
        const children = group.children.filter((_, i) => i !== childIndex);
        if (children.length === 0) onRemove();
        else onChange({ ...group, children });
    }

    function addChild() {
        const condition = newCondition();
        if (condition) onChange({ ...group, children: [...group.children, condition] });
    }

    return (
        <section className={styles.card} aria-label={label}>
            <div className={styles.header}>
                <Dropdown
                    disabled={disabled}
                    aria-label={`${label} — ${t.conditionBuilder.chooseOperator}`}
                    selectedOptions={[group.operator]}
                    value={operatorLabels[group.operator]}
                    onOptionSelect={(_, data) => {
                        const op = data.optionValue === 'or' ? 'or' : 'and';
                        if (op !== group.operator) onChange({ ...group, operator: op });
                    }}
                >
                    <Option value="and">{operatorLabels.and}</Option>
                    <Option value="or">{operatorLabels.or}</Option>
                </Dropdown>
                <Tooltip content={t.conditionBuilder.removeGroup} relationship="label">
                    <Button appearance="subtle" icon={<DismissRegular />} disabled={disabled} onClick={onRemove} />
                </Tooltip>
            </div>
            {groupErrors.length > 0 && (
                <Text size={200} className={styles.error} aria-live="polite">
                    {groupErrors.join(' ')}
                </Text>
            )}
            {group.children.map((child, childIndex) => {
                const childPath = nodePath(index, childIndex);

                return child.type === 'condition' ? (
                    <ConditionRow
                        key={nodeKey(child)}
                        condition={child}
                        path={childPath}
                        fields={fields}
                        findField={findField}
                        connector={childIndex === 0 ? null : connector}
                        onChange={(c) => updateChild(childIndex, c)}
                        onRemove={() => removeChild(childIndex)}
                        optionSources={optionSources}
                        serverErrors={errorsAt(errors, childPath)}
                        showValidation={showValidation}
                        disabled={disabled}
                    />
                ) : null;
            })}
            <div className={styles.footer}>
                <Button appearance="subtle" icon={<AddRegular />} disabled={disabled || !canAddCondition} onClick={addChild}>
                    {t.conditionBuilder.addCondition}
                </Button>
            </div>
        </section>
    );
}
