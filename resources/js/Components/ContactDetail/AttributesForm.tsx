import { useEffect, useState } from 'react';
import { Button, Checkbox, Field, Input, MessageBar, Select, Spinner, Switch, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { fetchContactAttributes, saveContactAttributes, type ContactDetailData } from '@/Lib/api/contactDetail';
import type { ContactAttribute, ContactAttributeValue, ContactAttributeValues } from '@/Lib/types/attributes';

const useStyles = makeStyles({
    form: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM, maxWidth: '520px' },
    group: { display: 'flex', flexDirection: 'column' },
});

type Column = 'first_name' | 'last_name' | 'company_name';
const EDITABLE_COLUMNS: string[] = ['first_name', 'last_name', 'company_name'];

/** Hidden from the form: EMAIL is the identity, EXT_ID has no write path in the legacy PATCH. */
function isEditable(attribute: ContactAttribute): boolean {
    if (!attribute.is_system) return true;
    if (attribute.storage === 'column') return attribute.column !== null && EDITABLE_COLUMNS.includes(attribute.column);

    return attribute.name === 'SMS';
}

function currentValue(contact: ContactDetailData, attribute: ContactAttribute): ContactAttributeValue {
    if (attribute.storage === 'column' && attribute.column) {
        return ((contact as unknown as Record<string, ContactAttributeValue>)[attribute.column] ?? null) as ContactAttributeValue;
    }

    return (contact.attributes?.[attribute.name] ?? (contact.custom_fields?.[attribute.name] as ContactAttributeValue | undefined) ?? null) as ContactAttributeValue;
}

interface Props {
    contact: ContactDetailData;
    canManage: boolean;
    onSaved: () => void;
}

export function AttributesForm({ contact, canManage, onSaved }: Props) {
    const t = useText();
    const styles = useStyles();
    const [definitions, setDefinitions] = useState<ContactAttribute[] | null>(null);
    const [values, setValues] = useState<ContactAttributeValues>({});
    const [status, setStatus] = useState<'idle' | 'saving' | 'saved' | 'error'>('idle');
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        let cancelled = false;
        fetchContactAttributes()
            .then((list) => {
                if (!cancelled) setDefinitions(list.filter(isEditable));
            })
            .catch(() => {
                if (!cancelled) setDefinitions([]);
            });

        return () => {
            cancelled = true;
        };
    }, []);

    useEffect(() => {
        if (definitions === null) return;
        const next: ContactAttributeValues = {};
        definitions.forEach((attribute) => {
            next[attribute.name] = currentValue(contact, attribute);
        });
        setValues(next);
    }, [definitions, contact]);

    if (definitions === null) return <Spinner size="small" label={t.common.loading} />;

    const set = (name: string, value: ContactAttributeValue) => setValues((current) => ({ ...current, [name]: value }));

    const submit = async () => {
        setStatus('saving');
        setFieldErrors({});
        const columns: Partial<Record<Column, string | null>> = {};
        const customFields: Record<string, unknown> = { ...(contact.custom_fields ?? {}) };
        const attributes: ContactAttributeValues = {};
        definitions.forEach((attribute) => {
            const value = values[attribute.name] ?? null;
            if (attribute.storage === 'column' && attribute.column) {
                columns[attribute.column as Column] = (value as string | null) || null;
            } else {
                customFields[attribute.name] = value;
                attributes[attribute.name] = value;
            }
        });
        try {
            await saveContactAttributes(contact.id, { ...columns, custom_fields: customFields, attributes });
            setStatus('saved');
            onSaved();
        } catch (e: unknown) {
            const errors = (e as { response?: { data?: { errors?: Record<string, string[]> } } }).response?.data?.errors ?? {};
            const mapped: Record<string, string> = {};
            Object.entries(errors).forEach(([key, messages]) => {
                mapped[key.replace(/^attributes\./, '')] = messages[0];
            });
            setFieldErrors(mapped);
            setStatus('error');
        }
    };

    const control = (attribute: ContactAttribute) => {
        const value = values[attribute.name];
        const disabled = !canManage;
        switch (attribute.type) {
            case 'boolean':
                return <Switch checked={value === true} disabled={disabled} onChange={(_, d) => set(attribute.name, d.checked)} />;
            case 'category':
                return (
                    <Select value={(value as string) ?? ''} disabled={disabled} onChange={(_, d) => set(attribute.name, d.value || null)}>
                        <option value="">{t.common.emptyValue}</option>
                        {attribute.options.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </Select>
                );
            case 'multiple_choice': {
                const selected = Array.isArray(value) ? value : [];

                return (
                    <div className={styles.group}>
                        {attribute.options.map((option) => (
                            <Checkbox
                                key={option.value}
                                label={option.label}
                                disabled={disabled}
                                checked={selected.includes(option.value)}
                                onChange={(_, d) =>
                                    set(attribute.name, d.checked ? [...selected, option.value] : selected.filter((v) => v !== option.value))
                                }
                            />
                        ))}
                    </div>
                );
            }
            case 'number':
                return (
                    <Input
                        type="number"
                        value={value === null || value === undefined ? '' : String(value)}
                        disabled={disabled}
                        onChange={(_, d) => set(attribute.name, d.value === '' ? null : Number(d.value))}
                    />
                );
            case 'date':
                return <Input type="date" value={(value as string) ?? ''} disabled={disabled} onChange={(_, d) => set(attribute.name, d.value || null)} />;
            default:
                return <Input value={(value as string) ?? ''} disabled={disabled} onChange={(_, d) => set(attribute.name, d.value || null)} />;
        }
    };

    return (
        <form
            className={styles.form}
            onSubmit={(event) => {
                event.preventDefault();
                void submit();
            }}
        >
            {definitions.map((attribute) => (
                <Field key={attribute.name} label={attribute.label} validationMessage={fieldErrors[attribute.name]} validationState={fieldErrors[attribute.name] ? 'error' : undefined}>
                    {control(attribute)}
                </Field>
            ))}
            {status === 'saved' && <MessageBar intent="success">{t.contactDetail.attributesSaved}</MessageBar>}
            {status === 'error' && Object.keys(fieldErrors).length === 0 && <MessageBar intent="error">{t.common.genericError}</MessageBar>}
            {canManage && (
                <div>
                    <Button type="submit" appearance="primary" disabled={status === 'saving'}>
                        {status === 'saving' ? t.common.saving : t.common.save}
                    </Button>
                </div>
            )}
        </form>
    );
}
