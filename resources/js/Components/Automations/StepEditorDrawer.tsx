import {
    Button,
    DrawerBody,
    DrawerFooter,
    DrawerHeader,
    DrawerHeaderTitle,
    Dropdown,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Option,
    OverlayDrawer,
    SpinButton,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { DeleteRegular, DismissRegular } from '@fluentui/react-icons';
import ConditionBuilder from '@/Components/Contacts/ConditionBuilder';
import { useText } from '@/Hooks/useText';
import type { AutomationTrigger, AutomationWaitUnit } from '@/Lib/types/automations';
import { AttributeValueEditor } from './AttributeValueEditor';
import { conditionRuleErrors, stepIssue, type DraftStep } from './graph';
import { EntityPicker, useAutomationLookups } from './lookups';
import { STEP_ICONS } from './StepList';
import { TRIGGER_ICONS, TriggerConfigEditor } from './TriggerCard';

const WAIT_UNITS: AutomationWaitUnit[] = ['minutes', 'hours', 'days'];

/** Attributes an automation may not overwrite (contact identity). */
const READ_ONLY_ATTRIBUTES = new Set(['EMAIL', 'EXT_ID']);

const useStyles = makeStyles({
    body: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        paddingBottom: tokens.spacingVerticalL,
    },
    title: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    row: {
        display: 'grid',
        gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1fr)',
        gap: tokens.spacingHorizontalM,
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
    builderSurface: {
        backgroundColor: tokens.colorNeutralBackground2,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalS,
    },
    footer: {
        display: 'flex',
        justifyContent: 'space-between',
        width: '100%',
        gap: tokens.spacingHorizontalS,
    },
});

export type DrawerTarget = { kind: 'trigger' } | { kind: 'step'; step: DraftStep } | null;

export interface StepEditorDrawerProps {
    target: DrawerTarget;
    onClose: () => void;
    trigger: AutomationTrigger | null;
    onTriggerChange: (trigger: AutomationTrigger) => void;
    /** Server errors of the automation resource (`trigger.config.*`). */
    triggerErrors: Record<string, string>;
    triggerLocked: boolean;
    onStepChange: (step: DraftStep) => void;
    onRemoveStep: (key: string) => void;
    /** Server errors of the open step, keyed relative to it (`config.template_id`). */
    stepErrors: Record<string, string>;
    readOnly: boolean;
    showRequired: boolean;
}

/** Side panel editing the trigger or one step (changes apply to the draft live). */
export function StepEditorDrawer({
    target,
    onClose,
    trigger,
    onTriggerChange,
    triggerErrors,
    triggerLocked,
    onStepChange,
    onRemoveStep,
    stepErrors,
    readOnly,
    showRequired,
}: StepEditorDrawerProps) {
    const styles = useStyles();
    const t = useText();

    let title = '';
    let Icon = STEP_ICONS.end;
    if (target?.kind === 'trigger') {
        title = t.automations.trigger;
        Icon = trigger ? TRIGGER_ICONS[trigger.type] : TRIGGER_ICONS.list_added;
    } else if (target?.kind === 'step') {
        title = t.automations.steps[target.step.type];
        Icon = STEP_ICONS[target.step.type];
    }

    return (
        <OverlayDrawer open={target !== null} position="end" size="medium" onOpenChange={(_, data) => !data.open && onClose()}>
            <DrawerHeader>
                <DrawerHeaderTitle
                    action={<Button appearance="subtle" aria-label={t.common.close} icon={<DismissRegular />} onClick={onClose} />}
                >
                    <span className={styles.title}>
                        <Icon aria-hidden="true" />
                        {title}
                    </span>
                </DrawerHeaderTitle>
            </DrawerHeader>
            <DrawerBody>
                <div className={styles.body}>
                    {target?.kind === 'trigger' && (
                        <>
                            {triggerLocked && !readOnly && (
                                <MessageBar intent="info">
                                    <MessageBarBody>{t.automations.editor.trigger.cannotChangeActive}</MessageBarBody>
                                </MessageBar>
                            )}
                            <TriggerConfigEditor
                                trigger={trigger}
                                onChange={onTriggerChange}
                                errors={triggerErrors}
                                showRequired={showRequired}
                                disabled={readOnly || triggerLocked}
                            />
                        </>
                    )}
                    {target?.kind === 'step' && (
                        <StepConfigEditor
                            step={target.step}
                            onChange={onStepChange}
                            errors={stepErrors}
                            disabled={readOnly}
                            showRequired={showRequired}
                        />
                    )}
                </div>
            </DrawerBody>
            <DrawerFooter>
                <div className={styles.footer}>
                    {target?.kind === 'step' && !readOnly ? (
                        <Button appearance="subtle" icon={<DeleteRegular />} onClick={() => onRemoveStep(target.step.key)}>
                            {t.automations.removeStep}
                        </Button>
                    ) : (
                        <span />
                    )}
                    <Button appearance="primary" onClick={onClose}>
                        {t.common.close}
                    </Button>
                </div>
            </DrawerFooter>
        </OverlayDrawer>
    );
}

interface StepConfigEditorProps {
    step: DraftStep;
    onChange: (step: DraftStep) => void;
    errors: Record<string, string>;
    disabled: boolean;
    showRequired: boolean;
}

function StepConfigEditor({ step, onChange, errors, disabled, showRequired }: StepConfigEditorProps) {
    const styles = useStyles();
    const t = useText();
    const { attributes, fieldCatalog, optionSources, hint } = useAutomationLookups();
    const issue = stepIssue(step, fieldCatalog);

    const state = (key: string, empty = false) => {
        const message = errors[`config.${key}`] ?? (showRequired && empty ? t.common.required : undefined);

        return { validationMessage: message, validationState: message ? ('error' as const) : ('none' as const) };
    };
    const setConfig = (patch: Record<string, unknown>) => onChange({ ...step, config: { ...step.config, ...patch } } as DraftStep);

    const general = [errors[''], errors.type, errors.config, errors.position].filter((m): m is string => Boolean(m));

    let editor = null;
    switch (step.type) {
        case 'send_email': {
            const templateSubject = hint('templates', step.config.template_id);
            editor = (
                <>
                    <Field label={t.automations.template} required hint={t.automations.editor.step.templateHint} {...state('template_id', !step.config.template_id)}>
                        <EntityPicker
                            kind="templates"
                            single
                            ariaLabel={t.automations.template}
                            disabled={disabled}
                            values={step.config.template_id ? [step.config.template_id] : []}
                            onChange={(values) => setConfig({ template_id: values[0] ?? '' })}
                        />
                    </Field>
                    <Field label={t.automations.editor.step.subject} {...state('subject')}>
                        <Input
                            disabled={disabled}
                            maxLength={255}
                            placeholder={templateSubject ?? undefined}
                            value={step.config.subject ?? ''}
                            onChange={(_, data) => setConfig({ subject: data.value === '' ? null : data.value })}
                        />
                    </Field>
                    <Field label={t.automations.editor.step.previewText} {...state('preview_text')}>
                        <Input
                            disabled={disabled}
                            maxLength={255}
                            value={step.config.preview_text ?? ''}
                            onChange={(_, data) => setConfig({ preview_text: data.value === '' ? null : data.value })}
                        />
                    </Field>
                </>
            );
            break;
        }
        case 'wait': {
            const rangeMessage = errors['config.amount'] ?? (issue === 'wait_range' ? t.automations.editor.step.waitRange : undefined);
            editor = (
                <div className={styles.row}>
                    <Field label={t.automations.editor.step.amount} required validationMessage={rangeMessage} validationState={rangeMessage ? 'error' : 'none'}>
                        <SpinButton
                            disabled={disabled}
                            min={1}
                            value={step.config.amount}
                            onChange={(_, data) => {
                                const raw = data.value ?? Number.parseInt(data.displayValue ?? '', 10);
                                if (raw !== undefined && raw !== null && !Number.isNaN(raw)) setConfig({ amount: Math.max(1, Math.round(raw)) });
                            }}
                        />
                    </Field>
                    <Field label={t.automations.editor.step.unit} {...state('unit')}>
                        <Dropdown
                            disabled={disabled}
                            aria-label={t.automations.editor.step.unit}
                            selectedOptions={[step.config.unit]}
                            value={t.automations.waitUnits[step.config.unit]}
                            onOptionSelect={(_, data) => data.optionValue && setConfig({ unit: data.optionValue })}
                        >
                            {WAIT_UNITS.map((unit) => (
                                <Option key={unit} value={unit}>
                                    {t.automations.waitUnits[unit]}
                                </Option>
                            ))}
                        </Dropdown>
                    </Field>
                </div>
            );
            break;
        }
        case 'condition':
            editor = (
                <>
                    <Text className={styles.muted}>{t.automations.editor.step.conditionHint}</Text>
                    <div className={styles.builderSurface}>
                        <ConditionBuilder
                            value={step.config.rules}
                            onChange={(rules) => setConfig({ rules })}
                            fieldCatalog={fieldCatalog}
                            optionSources={optionSources}
                            errors={conditionRuleErrors(errors)}
                            disabled={disabled}
                            variant="compact"
                        />
                    </div>
                    {showRequired && issue && <Text className={styles.muted}>{t.conditionBuilder.incomplete}</Text>}
                </>
            );
            break;
        case 'add_to_list':
        case 'remove_from_list':
            editor = (
                <Field label={t.automations.editor.step.list} required {...state('list_id', !step.config.list_id)}>
                    <EntityPicker
                        kind="lists"
                        single
                        ariaLabel={t.automations.editor.step.list}
                        disabled={disabled}
                        values={step.config.list_id ? [step.config.list_id] : []}
                        onChange={(values) => setConfig({ list_id: values[0] ?? '' })}
                    />
                </Field>
            );
            break;
        case 'update_attribute': {
            const editable = attributes.filter((a) => !READ_ONLY_ATTRIBUTES.has(a.name));
            const definition = attributes.find((a) => a.name === step.config.attribute);
            const value = step.config.value;
            const valueEmpty = value === null || value === '' || (Array.isArray(value) && value.length === 0);
            editor = (
                <>
                    <Field label={t.automations.editor.trigger.attribute} required {...state('attribute', !step.config.attribute)}>
                        <Dropdown
                            disabled={disabled}
                            aria-label={t.automations.editor.trigger.attribute}
                            placeholder={t.conditionBuilder.chooseField}
                            selectedOptions={step.config.attribute ? [step.config.attribute] : []}
                            value={definition?.label ?? step.config.attribute}
                            onOptionSelect={(_, data) => data.optionValue && setConfig({ attribute: data.optionValue, value: null })}
                        >
                            {editable.map((attribute) => (
                                <Option key={attribute.name} value={attribute.name} text={attribute.label}>
                                    {attribute.label}
                                </Option>
                            ))}
                        </Dropdown>
                    </Field>
                    <Field label={t.automations.editor.step.value} required {...state('value', Boolean(step.config.attribute) && valueEmpty)}>
                        <AttributeValueEditor
                            attribute={definition}
                            ariaLabel={t.automations.editor.step.value}
                            disabled={disabled}
                            value={value}
                            onChange={(next) => setConfig({ value: next })}
                        />
                    </Field>
                </>
            );
            break;
        }
        case 'end':
            editor = <Text>{t.automations.editor.step.endSummary}</Text>;
            break;
    }

    return (
        <>
            {general.length > 0 && (
                <MessageBar intent="error">
                    <MessageBarBody>{general.join(' ')}</MessageBarBody>
                </MessageBar>
            )}
            {editor}
        </>
    );
}
