import type { ComponentType } from 'react';
import {
    Badge,
    Card,
    Dropdown,
    Field,
    Input,
    Option,
    Radio,
    RadioGroup,
    SpinButton,
    Switch,
    Text,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import {
    CalendarStarRegular,
    CursorClickRegular,
    FilterRegular,
    FlashRegular,
    FormNewRegular,
    MailReadRegular,
    PeopleAddRegular,
    PersonEditRegular,
} from '@fluentui/react-icons';
import ConditionBuilder, { emptyRuleTree } from '@/Components/Contacts/ConditionBuilder';
import { formatText } from '@/Components/Campaigns/campaignUi';
import { useText } from '@/Hooks/useText';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type { AutomationTrigger, AutomationTriggerType } from '@/Lib/types/automations';
import { AttributeValueEditor, formatAttributeValue } from './AttributeValueEditor';
import { TRIGGER_TYPES, defaultTrigger, triggerFilterErrors } from './graph';
import { EntityPicker, useAutomationLookups } from './lookups';
import { automationMissingText as mt } from './text';

export const TRIGGER_ICONS: Record<AutomationTriggerType, ComponentType> = {
    list_added: PeopleAddRegular,
    form_submitted: FormNewRegular,
    campaign_opened: MailReadRegular,
    campaign_clicked: CursorClickRegular,
    attribute_changed: PersonEditRegular,
    date_anniversary: CalendarStarRegular,
};

const HOURS = Array.from({ length: 24 }, (_, hour) => `${String(hour).padStart(2, '0')}:00`);

const useStyles = makeStyles({
    card: {
        width: '100%',
        maxWidth: '280px',
        boxSizing: 'border-box',
        borderTop: `${tokens.strokeWidthThickest} solid ${tokens.colorBrandStroke1}`,
    },
    selected: {
        boxShadow: `0 0 0 ${tokens.strokeWidthThick} ${tokens.colorBrandStroke1}`,
    },
    invalid: {
        borderTopColor: tokens.colorPaletteRedBorder2,
        outline: `${tokens.strokeWidthThin} solid ${tokens.colorPaletteRedBorder2}`,
    },
    header: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    icon: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: '32px',
        height: '32px',
        flexShrink: 0,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorBrandBackground2,
        color: tokens.colorBrandForeground2,
        fontSize: '18px',
    },
    titles: {
        display: 'flex',
        flexDirection: 'column',
        minWidth: 0,
    },
    eyebrow: {
        color: tokens.colorNeutralForeground3,
        textTransform: 'uppercase',
        letterSpacing: '0.04em',
    },
    summary: {
        color: tokens.colorNeutralForeground2,
        overflowWrap: 'anywhere',
    },
    filterNote: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
        color: tokens.colorNeutralForeground3,
    },
    editor: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    typeGroup: {
        display: 'grid',
        gridTemplateColumns: 'minmax(0, 1fr)',
        rowGap: tokens.spacingVerticalXXS,
    },
    typeLabel: {
        display: 'inline-flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    filterSurface: {
        backgroundColor: tokens.colorNeutralBackground2,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalS,
    },
    row: {
        display: 'grid',
        gridTemplateColumns: 'minmax(0, 1fr) minmax(0, 1fr)',
        gap: tokens.spacingHorizontalM,
        '@media (max-width: 480px)': {
            gridTemplateColumns: 'minmax(0, 1fr)',
        },
    },
});

function attributeLabel(attributes: ContactAttribute[], name: string): string {
    return attributes.find((a) => a.name === name)?.label ?? name;
}

/** One-line description of a configured trigger (card + list rows). */
export function useTriggerSummary(): (trigger: AutomationTrigger | null) => string {
    const t = useText();
    const { attributes, label } = useAutomationLookups();

    return (trigger) => {
        if (!trigger) return mt.trigger.notConfigured;
        switch (trigger.type) {
            case 'list_added': {
                const ids = trigger.config.list_ids;
                if (ids.length === 0) return mt.trigger.notConfigured;

                return ids.length === 1 ? (label('lists', ids[0]) ?? formatText(mt.trigger.summaryLists, 1)) : formatText(mt.trigger.summaryLists, ids.length);
            }
            case 'form_submitted': {
                const ids = trigger.config.form_ids;
                if (ids.length === 0) return mt.trigger.notConfigured;

                return ids.length === 1 ? (label('forms', ids[0]) ?? formatText(mt.trigger.summaryForms, 1)) : formatText(mt.trigger.summaryForms, ids.length);
            }
            case 'campaign_opened':
            case 'campaign_clicked': {
                const ids = trigger.config.campaign_ids;
                let text: string;
                if (ids === null) text = mt.trigger.summaryAnyCampaign;
                else if (ids.length === 0) return mt.trigger.notConfigured;
                else text = ids.length === 1 ? (label('campaigns', ids[0]) ?? formatText(mt.trigger.summaryCampaigns, 1)) : formatText(mt.trigger.summaryCampaigns, ids.length);
                if (trigger.type === 'campaign_clicked' && trigger.config.link_url) text += ` · ${trigger.config.link_url}`;

                return text;
            }
            case 'attribute_changed': {
                const { attribute, to_value: toValue } = trigger.config;
                if (!attribute) return mt.trigger.notConfigured;
                const name = attributeLabel(attributes, attribute);
                if (toValue === null) return formatText(mt.trigger.summaryAttributeAny, name);
                const definition = attributes.find((a) => a.name === attribute);

                return formatText(
                    mt.trigger.summaryAttributeTo,
                    name,
                    formatAttributeValue(definition, toValue, t.conditionBuilder.booleanValues.true, t.conditionBuilder.booleanValues.false),
                );
            }
            case 'date_anniversary': {
                const { attribute, offset_days: offset, time } = trigger.config;
                if (!attribute) return mt.trigger.notConfigured;
                const signed = offset === 0 ? '' : offset > 0 ? `+${offset}` : String(offset);

                return formatText(mt.trigger.summaryAnniversary, attributeLabel(attributes, attribute), signed, time);
            }
        }
    };
}

export interface TriggerCardProps {
    trigger: AutomationTrigger | null;
    selected?: boolean;
    invalid?: boolean;
    onOpen: () => void;
}

/** Start node of the flow canvas. */
export function TriggerCard({ trigger, selected, invalid, onOpen }: TriggerCardProps) {
    const styles = useStyles();
    const t = useText();
    const summary = useTriggerSummary();
    const Icon = trigger ? TRIGGER_ICONS[trigger.type] : FlashRegular;
    const hasFilter = Boolean(trigger?.config.filter && trigger.config.filter.children.length > 0);

    return (
        <Card
            className={mergeClasses(styles.card, selected && styles.selected, invalid && styles.invalid)}
            onClick={onOpen}
            aria-label={`${t.automations.trigger} : ${trigger ? t.automations.triggers[trigger.type] : mt.trigger.chooseTrigger}`}
        >
            <div className={styles.header}>
                <span className={styles.icon} aria-hidden="true">
                    <Icon />
                </span>
                <div className={styles.titles}>
                    <Text size={200} className={styles.eyebrow}>
                        {t.automations.trigger}
                    </Text>
                    <Text weight="semibold">{trigger ? t.automations.triggers[trigger.type] : mt.trigger.chooseTrigger}</Text>
                </div>
            </div>
            <Text size={300} className={styles.summary}>
                {summary(trigger)}
            </Text>
            {hasFilter && (
                <Text size={200} className={styles.filterNote}>
                    <FilterRegular aria-hidden="true" /> {mt.trigger.filterActive}
                </Text>
            )}
            {invalid && (
                <Badge appearance="tint" color="danger" size="small">
                    {mt.step.incomplete}
                </Badge>
            )}
        </Card>
    );
}

export interface TriggerConfigEditorProps {
    trigger: AutomationTrigger | null;
    onChange: (trigger: AutomationTrigger) => void;
    /** Server errors keyed by full path (`trigger.config.list_ids`, …). */
    errors?: Record<string, string>;
    /** Show "required" hints on empty pickers. */
    showRequired?: boolean;
    disabled?: boolean;
    /** Hide the contact filter (create dialog keeps it short). */
    hideFilter?: boolean;
}

/** Trigger type picker + per-type configuration + optional contact filter. */
export function TriggerConfigEditor({ trigger, onChange, errors = {}, showRequired, disabled, hideFilter }: TriggerConfigEditorProps) {
    const styles = useStyles();
    const t = useText();
    const { attributes, fieldCatalog, optionSources } = useAutomationLookups();
    const error = (key: string) => errors[`trigger.config.${key}`];
    const fieldState = (key: string, empty: boolean) => {
        const message = error(key) ?? (showRequired && empty ? t.common.required : undefined);

        return { validationMessage: message, validationState: message ? ('error' as const) : ('none' as const) };
    };

    const setConfig = <T extends AutomationTrigger>(current: T, patch: Partial<T['config']>) =>
        onChange({ ...current, config: { ...current.config, ...patch } } as AutomationTrigger);

    const dateAttributes = attributes.filter((a) => a.type === 'date');

    const attributeDropdown = (current: string, list: ContactAttribute[], onSelect: (name: string) => void, ariaLabel: string) => (
        <Dropdown
            disabled={disabled}
            aria-label={ariaLabel}
            placeholder={t.conditionBuilder.chooseField}
            selectedOptions={current ? [current] : []}
            value={current ? attributeLabel(attributes, current) : ''}
            onOptionSelect={(_, data) => data.optionValue && onSelect(data.optionValue)}
        >
            {list.map((attribute) => (
                <Option key={attribute.name} value={attribute.name} text={attribute.label}>
                    {attribute.label}
                </Option>
            ))}
        </Dropdown>
    );

    let config = null;
    if (trigger) {
        switch (trigger.type) {
            case 'list_added':
                config = (
                    <Field label={mt.trigger.lists} required {...fieldState('list_ids', trigger.config.list_ids.length === 0)}>
                        <EntityPicker
                            kind="lists"
                            ariaLabel={mt.trigger.lists}
                            disabled={disabled}
                            values={trigger.config.list_ids}
                            onChange={(list_ids) => setConfig(trigger, { list_ids })}
                        />
                    </Field>
                );
                break;
            case 'form_submitted':
                config = (
                    <Field label={mt.trigger.forms} required {...fieldState('form_ids', trigger.config.form_ids.length === 0)}>
                        <EntityPicker
                            kind="forms"
                            ariaLabel={mt.trigger.forms}
                            disabled={disabled}
                            values={trigger.config.form_ids}
                            onChange={(form_ids) => setConfig(trigger, { form_ids })}
                        />
                    </Field>
                );
                break;
            case 'campaign_opened':
            case 'campaign_clicked': {
                const ids = trigger.config.campaign_ids;
                config = (
                    <>
                        <Field label={mt.trigger.campaigns} {...fieldState('campaign_ids', ids !== null && ids.length === 0)}>
                            <RadioGroup
                                disabled={disabled}
                                value={ids === null ? 'any' : 'specific'}
                                onChange={(_, data) => setConfig(trigger, { campaign_ids: data.value === 'any' ? null : [] })}
                            >
                                <Radio value="any" label={mt.trigger.anyCampaign} />
                                <Radio value="specific" label={mt.trigger.specificCampaigns} />
                            </RadioGroup>
                        </Field>
                        {ids !== null && (
                            <EntityPicker
                                kind="campaigns"
                                ariaLabel={mt.trigger.specificCampaigns}
                                disabled={disabled}
                                invalid={Boolean(error('campaign_ids'))}
                                values={ids}
                                onChange={(campaign_ids) => setConfig(trigger, { campaign_ids })}
                            />
                        )}
                        {trigger.type === 'campaign_clicked' && (
                            <Field label={mt.trigger.linkUrl} {...fieldState('link_url', false)}>
                                <Input
                                    disabled={disabled}
                                    type="url"
                                    placeholder="https://…"
                                    value={trigger.config.link_url ?? ''}
                                    onChange={(_, data) => setConfig(trigger, { link_url: data.value.trim() === '' ? null : data.value })}
                                />
                            </Field>
                        )}
                    </>
                );
                break;
            }
            case 'attribute_changed': {
                const definition = attributes.find((a) => a.name === trigger.config.attribute);
                config = (
                    <>
                        <Field label={mt.trigger.attribute} required {...fieldState('attribute', trigger.config.attribute === '')}>
                            {attributeDropdown(
                                trigger.config.attribute,
                                attributes,
                                (attribute) => setConfig(trigger, { attribute, to_value: null }),
                                mt.trigger.attribute,
                            )}
                        </Field>
                        <RadioGroup
                            disabled={disabled || !definition}
                            value={trigger.config.to_value === null ? 'any' : 'specific'}
                            onChange={(_, data) =>
                                setConfig(trigger, {
                                    to_value: data.value === 'any' ? null : definition?.type === 'boolean' ? true : definition?.type === 'multiple_choice' ? [] : '',
                                })
                            }
                        >
                            <Radio value="any" label={mt.trigger.anyValue} />
                            <Radio value="specific" label={mt.trigger.specificValue} />
                        </RadioGroup>
                        {trigger.config.to_value !== null && (
                            <Field label={mt.step.value} {...fieldState('to_value', false)}>
                                <AttributeValueEditor
                                    attribute={definition}
                                    ariaLabel={mt.step.value}
                                    disabled={disabled}
                                    value={trigger.config.to_value}
                                    onChange={(to_value) => setConfig(trigger, { to_value: to_value ?? '' })}
                                />
                            </Field>
                        )}
                    </>
                );
                break;
            }
            case 'date_anniversary':
                config = (
                    <>
                        <Field
                            label={mt.trigger.dateAttribute}
                            required
                            hint={dateAttributes.length === 0 ? mt.trigger.noDateAttribute : undefined}
                            {...fieldState('attribute', trigger.config.attribute === '')}
                        >
                            {attributeDropdown(
                                trigger.config.attribute,
                                dateAttributes,
                                (attribute) => setConfig(trigger, { attribute }),
                                mt.trigger.dateAttribute,
                            )}
                        </Field>
                        <div className={styles.row}>
                            <Field label={t.automations.offsetDays} hint={mt.trigger.offsetHint} {...fieldState('offset_days', false)}>
                                <SpinButton
                                    disabled={disabled}
                                    min={-365}
                                    max={365}
                                    value={trigger.config.offset_days}
                                    onChange={(_, data) => {
                                        const raw = data.value ?? Number.parseInt(data.displayValue ?? '', 10);
                                        if (raw !== undefined && !Number.isNaN(raw)) {
                                            setConfig(trigger, { offset_days: Math.max(-365, Math.min(365, Math.round(raw))) });
                                        }
                                    }}
                                />
                            </Field>
                            <Field label={t.automations.time} {...fieldState('time', false)}>
                                <Dropdown
                                    disabled={disabled}
                                    aria-label={t.automations.time}
                                    selectedOptions={[trigger.config.time]}
                                    value={trigger.config.time}
                                    onOptionSelect={(_, data) => data.optionValue && setConfig(trigger, { time: data.optionValue })}
                                >
                                    {HOURS.map((hour) => (
                                        <Option key={hour} value={hour}>
                                            {hour}
                                        </Option>
                                    ))}
                                </Dropdown>
                            </Field>
                        </div>
                    </>
                );
                break;
        }
    }

    const filter = trigger?.config.filter ?? null;

    return (
        <div className={styles.editor}>
            <Field label={t.automations.trigger} required {...(errors['trigger.type'] ? { validationMessage: errors['trigger.type'], validationState: 'error' as const } : {})}>
                <RadioGroup
                    className={styles.typeGroup}
                    disabled={disabled}
                    value={trigger?.type ?? ''}
                    onChange={(_, data) => {
                        if (data.value !== trigger?.type) {
                            const next = defaultTrigger(data.value as AutomationTriggerType);
                            onChange({ ...next, config: { ...next.config, filter: trigger?.config.filter ?? null } } as AutomationTrigger);
                        }
                    }}
                >
                    {TRIGGER_TYPES.map((type) => {
                        const Icon = TRIGGER_ICONS[type];

                        return (
                            <Radio
                                key={type}
                                value={type}
                                label={
                                    <span className={styles.typeLabel}>
                                        <Icon aria-hidden="true" />
                                        {t.automations.triggers[type]}
                                    </span>
                                }
                            />
                        );
                    })}
                </RadioGroup>
            </Field>
            {config}
            {trigger && !hideFilter && (
                <>
                    <Switch
                        disabled={disabled}
                        checked={filter !== null}
                        label={t.automations.triggerFilter}
                        onChange={(_, data) => setConfig(trigger, { filter: data.checked ? emptyRuleTree() : null })}
                    />
                    {filter !== null && (
                        <div className={styles.filterSurface}>
                            <ConditionBuilder
                                value={filter}
                                onChange={(tree) => setConfig(trigger, { filter: tree })}
                                fieldCatalog={fieldCatalog}
                                optionSources={optionSources}
                                errors={triggerFilterErrors(errors)}
                                disabled={disabled}
                                variant="compact"
                            />
                        </div>
                    )}
                </>
            )}
        </div>
    );
}
