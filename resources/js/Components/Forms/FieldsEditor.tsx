import {
    Button,
    Caption1,
    Checkbox,
    Dropdown,
    Field,
    Input,
    Option,
    Switch,
    Text,
    Textarea,
    Tooltip,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { ArrowDownRegular, ArrowUpRegular, DeleteRegular } from '@fluentui/react-icons';
import { EMAIL_ATTRIBUTE, PICKABLE, fieldFromAttribute } from '@/Components/Forms/model';
import { useText } from '@/Hooks/useText';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type { FormField, FormGdpr } from '@/Lib/types/forms';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
    list: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS, margin: 0, padding: 0, listStyleType: 'none' },
    item: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        padding: tokens.spacingVerticalM,
        borderRadius: tokens.borderRadiusMedium,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        backgroundColor: tokens.colorNeutralBackground1,
    },
    itemHeader: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', gap: tokens.spacingHorizontalS },
    itemTitle: { display: 'flex', alignItems: 'baseline', gap: tokens.spacingHorizontalS, minWidth: 0, flexWrap: 'wrap' },
    muted: { color: tokens.colorNeutralForeground3 },
    itemActions: { display: 'flex', gap: tokens.spacingHorizontalXXS, flexShrink: 0 },
    grid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(2, minmax(0, 1fr))',
        gap: tokens.spacingHorizontalM,
        '@media (max-width: 599px)': { gridTemplateColumns: 'minmax(0, 1fr)' },
    },
    full: { gridColumn: '1 / -1' },
    section: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        paddingTop: tokens.spacingVerticalM,
        borderTop: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
    },
});

interface Props {
    fields: FormField[];
    gdpr: FormGdpr;
    attributes: ContactAttribute[];
    onFieldsChange: (fields: FormField[]) => void;
    onGdprChange: (gdpr: FormGdpr) => void;
    /** 422 messages keyed by dot path (`fields.0.label`, `gdpr.text`). */
    errors?: Record<string, string>;
}

/** *Champs* tab — pick contact attributes, order them, set labels / required, GDPR consent. */
export function FieldsEditor({ fields, gdpr, attributes, onFieldsChange, onGdprChange, errors = {} }: Props) {
    const styles = useStyles();
    const t = useText();
    const used = new Set(fields.map((f) => f.attribute));
    const available = attributes.filter((a) => PICKABLE(a) && !used.has(a.name));

    const patch = (index: number, change: Partial<FormField>) =>
        onFieldsChange(fields.map((f, i) => (i === index ? { ...f, ...change } : f)));

    const move = (index: number, delta: -1 | 1) => {
        const target = index + delta;
        if (target < 0 || target >= fields.length) return;
        const next = [...fields];
        [next[index], next[target]] = [next[target], next[index]];
        onFieldsChange(next);
    };

    const add = (name: string | undefined) => {
        const attribute = attributes.find((a) => a.name === name);
        if (attribute) onFieldsChange([...fields, fieldFromAttribute(attribute)]);
    };

    const errorFor = (key: string) => (errors[key] ? { validationMessage: errors[key], validationState: 'error' as const } : {});

    return (
        <div className={styles.root}>
            <Caption1 className={styles.muted}>{t.forms.editor.fields.emailLocked}</Caption1>
            {errors.fields && <Text style={{ color: tokens.colorPaletteRedForeground1 }}>{errors.fields}</Text>}
            <ol className={styles.list}>
                {fields.map((field, index) => {
                    const isEmail = field.attribute === EMAIL_ATTRIBUTE;
                    const typed = field.type === 'boolean' || field.type === 'multiple_choice';

                    return (
                        <li key={field.attribute} className={styles.item}>
                            <div className={styles.itemHeader}>
                                <div className={styles.itemTitle}>
                                    <Text weight="semibold">{field.label || field.attribute}</Text>
                                    <Caption1 className={styles.muted}>{field.attribute}</Caption1>
                                </div>
                                <div className={styles.itemActions}>
                                    <Tooltip content={t.forms.editor.fields.moveUp} relationship="label">
                                        <Button
                                            size="small"
                                            appearance="subtle"
                                            icon={<ArrowUpRegular />}
                                            disabled={index === 0}
                                            onClick={() => move(index, -1)}
                                        />
                                    </Tooltip>
                                    <Tooltip content={t.forms.editor.fields.moveDown} relationship="label">
                                        <Button
                                            size="small"
                                            appearance="subtle"
                                            icon={<ArrowDownRegular />}
                                            disabled={index === fields.length - 1}
                                            onClick={() => move(index, 1)}
                                        />
                                    </Tooltip>
                                    <Tooltip content={t.forms.editor.fields.removeField} relationship="label">
                                        <Button
                                            size="small"
                                            appearance="subtle"
                                            icon={<DeleteRegular />}
                                            disabled={isEmail}
                                            onClick={() => onFieldsChange(fields.filter((_, i) => i !== index))}
                                        />
                                    </Tooltip>
                                </div>
                            </div>
                            <div className={styles.grid}>
                                <Field label={t.forms.fields.label} required {...errorFor(`fields.${index}.label`)}>
                                    <Input value={field.label} maxLength={255} onChange={(_, d) => patch(index, { label: d.value })} />
                                </Field>
                                {!typed && (
                                    <Field label={t.forms.fields.placeholder} {...errorFor(`fields.${index}.placeholder`)}>
                                        <Input
                                            value={field.placeholder ?? ''}
                                            maxLength={255}
                                            onChange={(_, d) => patch(index, { placeholder: d.value === '' ? null : d.value })}
                                        />
                                    </Field>
                                )}
                                <Field
                                    className={typed ? undefined : styles.full}
                                    label={t.forms.fields.helpText}
                                    {...errorFor(`fields.${index}.help_text`)}
                                >
                                    <Input
                                        value={field.help_text ?? ''}
                                        maxLength={500}
                                        onChange={(_, d) => patch(index, { help_text: d.value === '' ? null : d.value })}
                                    />
                                </Field>
                            </div>
                            <Checkbox
                                label={t.forms.fields.required}
                                checked={isEmail || field.required}
                                disabled={isEmail}
                                onChange={(_, d) => patch(index, { required: d.checked === true })}
                            />
                        </li>
                    );
                })}
            </ol>

            <Field label={t.forms.fields.addField} hint={available.length === 0 ? t.forms.editor.fields.noAttributeLeft : undefined}>
                <Dropdown
                    placeholder={t.forms.fields.attribute}
                    selectedOptions={[]}
                    value=""
                    disabled={available.length === 0}
                    onOptionSelect={(_, d) => add(d.optionValue)}
                >
                    {available.map((attribute) => (
                        <Option key={attribute.id} value={attribute.name} text={attribute.label || attribute.name}>
                            {`${attribute.label || attribute.name} (${attribute.name})`}
                        </Option>
                    ))}
                </Dropdown>
            </Field>

            <div className={styles.section}>
                <Switch
                    label={t.forms.fields.gdpr}
                    checked={gdpr.enabled}
                    onChange={(_, d) => onGdprChange({ ...gdpr, enabled: d.checked })}
                />
                {gdpr.enabled && (
                    <>
                        <Field label={t.forms.fields.gdprText} required {...errorFor('gdpr.text')}>
                            <Textarea
                                value={gdpr.text}
                                resize="vertical"
                                maxLength={1000}
                                onChange={(_, d) => onGdprChange({ ...gdpr, text: d.value })}
                            />
                        </Field>
                        <Checkbox
                            label={t.forms.editor.fields.gdprRequired}
                            checked={gdpr.required}
                            onChange={(_, d) => onGdprChange({ ...gdpr, required: d.checked === true })}
                        />
                    </>
                )}
            </div>
        </div>
    );
}
