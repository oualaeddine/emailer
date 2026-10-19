import { Fragment, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Button, Dropdown, Option, Spinner, Text, makeStyles, mergeClasses, tokens } from '@fluentui/react-components';
import { AddRegular, CalculatorRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import type { ConditionBuilderProps, FieldDefinition, RuleCondition, RuleGroup, RuleLogicalOperator, RuleTree } from '@/Lib/types/segments';
import { ConditionGroup } from './ConditionGroup';
import { ConditionRow } from './ConditionRow';
import { format } from './editors/shared';
import {
    DEFAULT_MAX_CONDITIONS,
    countConditions,
    createCondition,
    createGroup,
    emptyRuleTree,
    errorsAt,
    inheritKey,
    isRuleTreeComplete,
    nodeKey,
    nodePath,
} from './tree';

const COUNT_DEBOUNCE_MS = 600;

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        minWidth: 0,
    },
    compact: {
        gap: tokens.spacingVerticalXS,
    },
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
    emptyCard: {
        border: `1px dashed ${tokens.colorNeutralStroke1}`,
        alignItems: 'flex-start',
    },
    connector: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        paddingLeft: tokens.spacingHorizontalL,
        borderLeft: `2px solid ${tokens.colorNeutralStroke2}`,
        marginLeft: tokens.spacingHorizontalL,
    },
    connectorDropdown: {
        minWidth: '80px',
    },
    toolbar: {
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
    },
    footer: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        paddingTop: tokens.spacingVerticalXS,
    },
    count: {
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
    },
    countValue: {
        fontWeight: tokens.fontWeightSemibold,
    },
    footerSlot: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        marginLeft: 'auto',
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
    error: {
        color: tokens.colorPaletteRedForeground1,
    },
});

/**
 * docs/45-brevo-api-contracts.md §45.5.3 — shared condition builder
 * (segments, Contacts *Ajouter un filtre*, automation conditions).
 *
 * Controlled: renders `value` (or one empty group when `null`) and emits a new
 * immutable `RuleTree` on every change. Never calls the API itself: the field
 * catalog, option sources and the live count are injected.
 *
 * Layout follows Brevo: groups of conditions (rows joined by the group's
 * operator) joined by the root operator; a root child may also be a bare
 * condition. `variant="compact"` keeps one level (root conditions only).
 */
export function ConditionBuilder({
    value,
    onChange,
    fieldCatalog,
    onCount,
    optionSources,
    maxConditions = DEFAULT_MAX_CONDITIONS,
    categories,
    errors,
    disabled,
    variant = 'full',
    footer,
}: ConditionBuilderProps) {
    const styles = useStyles();
    const t = useText();
    const tree: RuleTree = value ?? emptyRuleTree();
    const compact = variant === 'compact';

    const fields = useMemo(
        () => (categories ? fieldCatalog.fields.filter((f) => categories.includes(f.category)) : fieldCatalog.fields),
        [fieldCatalog, categories],
    );
    const fieldIndex = useMemo(() => new Map(fieldCatalog.fields.map((f) => [f.key, f])), [fieldCatalog]);
    const findField = useCallback((key: RuleCondition['field']): FieldDefinition | undefined => fieldIndex.get(key), [fieldIndex]);

    const total = countConditions(tree);
    const canAdd = !disabled && total < maxConditions && fields.length > 0;
    const complete = isRuleTreeComplete(tree, fieldCatalog, maxConditions);
    const [showValidation, setShowValidation] = useState(false);
    const hasServerErrors = errors != null && Object.keys(errors).length > 0;
    const rootErrors = errors
        ? Object.entries(errors)
              .filter(([key]) => !key.startsWith('children.'))
              .map(([, message]) => message)
        : [];

    const newCondition = useCallback((): RuleCondition | null => (fields[0] ? createCondition(fields[0]) : null), [fields]);

    // ------------------------------------------------------------------ tree updates

    const emit = (children: RuleTree['children'], operator: RuleLogicalOperator = tree.operator) =>
        onChange({ version: 2, type: 'group', operator, children });

    function updateRootChild(index: number, node: RuleGroup | RuleCondition) {
        emit(tree.children.map((c, i) => (i === index ? inheritKey(c, node) : c)));
    }

    function removeRootChild(index: number) {
        emit(tree.children.filter((_, i) => i !== index));
    }

    function addGroup() {
        if (!canAdd || !fields[0]) return;
        emit([...tree.children, createGroup(fields[0])]);
    }

    function addRootCondition() {
        const condition = newCondition();
        if (canAdd && condition) emit([...tree.children, condition]);
    }

    function setRootOperator(operator: RuleLogicalOperator) {
        if (operator !== tree.operator) emit(tree.children, operator);
    }

    // ------------------------------------------------------------------ live count

    const [count, setCount] = useState<number | null>(null);
    const [counting, setCounting] = useState(false);
    const [countFailed, setCountFailed] = useState(false);
    const abortRef = useRef<AbortController | null>(null);
    const onCountRef = useRef(onCount);
    onCountRef.current = onCount;
    const treeJson = JSON.stringify(tree);

    const runCount = useCallback((target: RuleTree) => {
        const counter = onCountRef.current;
        if (!counter) return;
        abortRef.current?.abort();
        const controller = new AbortController();
        abortRef.current = controller;
        setCounting(true);
        setCountFailed(false);
        counter(target, controller.signal)
            .then((n) => {
                if (!controller.signal.aborted) setCount(n);
            })
            .catch(() => {
                if (!controller.signal.aborted) {
                    setCount(null);
                    setCountFailed(true);
                }
            })
            .finally(() => {
                if (abortRef.current === controller) {
                    setCounting(false);
                    abortRef.current = null;
                }
            });
    }, []);

    useEffect(() => {
        if (!onCountRef.current) return undefined;
        if (!complete) {
            abortRef.current?.abort();
            abortRef.current = null;
            setCounting(false);
            setCount(null);

            return undefined;
        }
        const timer = window.setTimeout(() => runCount(JSON.parse(treeJson) as RuleTree), COUNT_DEBOUNCE_MS);

        return () => window.clearTimeout(timer);
    }, [treeJson, complete, runCount]);

    useEffect(() => () => abortRef.current?.abort(), []);

    function handleCountClick() {
        if (!complete) {
            setShowValidation(true);

            return;
        }
        runCount(tree);
    }

    // ------------------------------------------------------------------ rendering

    const rootConnectorLabels: Record<RuleLogicalOperator, string> = { and: t.conditionBuilder.and, or: t.conditionBuilder.or };

    const renderRootConnector = (index: number) => (
        <div className={styles.connector} key={`connector-${index}`}>
            <Dropdown
                className={styles.connectorDropdown}
                size="small"
                disabled={disabled}
                aria-label={t.conditionBuilder.chooseOperator}
                selectedOptions={[tree.operator]}
                value={rootConnectorLabels[tree.operator]}
                onOptionSelect={(_, data) => setRootOperator(data.optionValue === 'or' ? 'or' : 'and')}
            >
                <Option value="and">{t.conditionBuilder.and}</Option>
                <Option value="or">{t.conditionBuilder.or}</Option>
            </Dropdown>
        </div>
    );

    const renderBareCondition = (condition: RuleCondition, index: number, connector: string | null) => (
        <ConditionRow
            condition={condition}
            path={nodePath(index)}
            fields={fields}
            findField={findField}
            connector={connector}
            onChange={(c) => updateRootChild(index, c)}
            onRemove={() => removeRootChild(index)}
            optionSources={optionSources}
            serverErrors={errorsAt(errors, nodePath(index))}
            showValidation={showValidation || hasServerErrors}
            disabled={disabled}
        />
    );

    const renderGroup = (group: RuleGroup, index: number) => (
        <ConditionGroup
            group={group}
            index={index}
            fields={fields}
            findField={findField}
            onChange={(g) => updateRootChild(index, g)}
            onRemove={() => removeRootChild(index)}
            newCondition={newCondition}
            canAddCondition={canAdd}
            optionSources={optionSources}
            errors={errors}
            showValidation={showValidation || hasServerErrors}
            disabled={disabled}
            label={`${t.segments.conditions} ${index + 1}`}
        />
    );

    const countArea = onCount ? (
        <div className={styles.count} aria-live="polite">
            <Button appearance="secondary" icon={<CalculatorRegular />} disabled={disabled || counting} onClick={handleCountClick}>
                {t.conditionBuilder.liveCount}
            </Button>
            {counting ? (
                <Spinner size="tiny" label={t.conditionBuilder.counting} />
            ) : count !== null ? (
                <Text className={styles.countValue}>{format(t.conditionBuilder.contactsMatch, count.toLocaleString('fr-FR'))}</Text>
            ) : countFailed ? (
                <Text className={styles.error}>{t.common.genericError}</Text>
            ) : null}
        </div>
    ) : null;

    const messages = (
        <>
            {rootErrors.length > 0 && (
                <Text size={200} className={styles.error} aria-live="polite">
                    {rootErrors.join(' ')}
                </Text>
            )}
            {total >= maxConditions && (
                <Text size={200} className={styles.muted} aria-live="polite">
                    {format(t.conditionBuilder.maxConditions, maxConditions)}
                </Text>
            )}
            {showValidation && !complete && total <= maxConditions && (
                <Text size={200} className={styles.error} aria-live="polite">
                    {t.conditionBuilder.incomplete}
                </Text>
            )}
        </>
    );

    const footerRow =
        countArea || footer ? (
            <div className={styles.footer}>
                {countArea}
                {footer && <div className={styles.footerSlot}>{footer}</div>}
            </div>
        ) : null;

    if (compact) {
        const connector = tree.operator === 'and' ? t.conditionBuilder.and : t.conditionBuilder.or;

        return (
            <div className={mergeClasses(styles.root, styles.compact)}>
                <div className={styles.toolbar}>
                    <Dropdown
                        size="small"
                        disabled={disabled}
                        aria-label={t.conditionBuilder.chooseOperator}
                        selectedOptions={[tree.operator]}
                        value={tree.operator === 'and' ? t.conditionBuilder.matchAll : t.conditionBuilder.matchAny}
                        onOptionSelect={(_, data) => setRootOperator(data.optionValue === 'or' ? 'or' : 'and')}
                    >
                        <Option value="and">{t.conditionBuilder.matchAll}</Option>
                        <Option value="or">{t.conditionBuilder.matchAny}</Option>
                    </Dropdown>
                    {tree.children.length === 0 && <Text className={styles.muted}>{t.conditionBuilder.empty}</Text>}
                </div>
                {tree.children.map((child, index) => (
                    <Fragment key={nodeKey(child)}>
                        {child.type === 'group'
                            ? renderGroup(child, index)
                            : renderBareCondition(child, index, index === 0 ? null : connector)}
                    </Fragment>
                ))}
                <div className={styles.toolbar}>
                    <Button appearance="subtle" icon={<AddRegular />} disabled={!canAdd} onClick={addRootCondition}>
                        {t.conditionBuilder.addCondition}
                    </Button>
                </div>
                {messages}
                {footerRow}
            </div>
        );
    }

    return (
        <div className={styles.root}>
            {tree.children.length === 0 ? (
                <section className={mergeClasses(styles.card, styles.emptyCard)}>
                    <Text className={styles.muted}>{t.conditionBuilder.empty}</Text>
                    <Button appearance="subtle" icon={<AddRegular />} disabled={!canAdd} onClick={addGroup}>
                        {t.conditionBuilder.addCondition}
                    </Button>
                </section>
            ) : (
                tree.children.map((child, index) => (
                    <Fragment key={nodeKey(child)}>
                        {index > 0 && renderRootConnector(index)}
                        {child.type === 'group' ? (
                            renderGroup(child, index)
                        ) : (
                            <section className={styles.card}>{renderBareCondition(child, index, null)}</section>
                        )}
                    </Fragment>
                ))
            )}
            {tree.children.length > 0 && (
                <div className={styles.toolbar}>
                    <Button appearance="secondary" icon={<AddRegular />} disabled={!canAdd} onClick={addGroup}>
                        {t.conditionBuilder.addGroup}
                    </Button>
                </div>
            )}
            {messages}
            {footerRow}
        </div>
    );
}

export default ConditionBuilder;

// §45.5.3 — helpers "exported by the component module".
export { isRuleTreeComplete } from './tree';
export { buildFieldCatalog } from './catalog';
