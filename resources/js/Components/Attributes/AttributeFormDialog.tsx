import { FormEvent, useEffect, useState } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Dropdown,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Option,
    Spinner,
} from '@fluentui/react-components';
import { AddRegular, DeleteRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { apiErrorMessage } from '@/Lib/api/attributes';
import type {
    ContactAttribute,
    ContactAttributeInput,
    ContactAttributeOption,
    ContactAttributeType,
    ContactAttributeUpdateInput,
} from '@/Lib/types/attributes';

const TYPES: ContactAttributeType[] = ['text', 'number', 'date', 'boolean', 'category', 'multiple_choice', 'user'];
const NAME_PATTERN = /^[A-Z][A-Z0-9_]{0,49}$/;

/** Live normalisation: uppercase, spaces to `_`, strip anything outside A–Z / 0–9 / `_`, max 50. */
export function normalizeAttributeName(raw: string): string {
    return raw
        .toUpperCase()
        .replace(/\s+/g, '_')
        .replace(/[^A-Z0-9_]/g, '')
        .slice(0, 50);
}

interface Props {
    open: boolean;
    attribute: ContactAttribute | null;
    onOpenChange: (open: boolean) => void;
    onCreate: (payload: ContactAttributeInput) => Promise<void>;
    onUpdate: (id: string, payload: ContactAttributeUpdateInput) => Promise<void>;
}

export function AttributeFormDialog({ open, attribute, onOpenChange, onCreate, onUpdate }: Props) {
    const t = useText();
    const [name, setName] = useState('');
    const [label, setLabel] = useState('');
    const [type, setType] = useState<ContactAttributeType>('text');
    const [options, setOptions] = useState<ContactAttributeOption[]>([]);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setName(attribute?.name ?? '');
            setLabel(attribute?.label ?? '');
            setType(attribute?.type ?? 'text');
            setOptions(attribute?.options ?? []);
            setError(null);
        }
    }, [open, attribute]);

    const hasOptions = type === 'category' || type === 'multiple_choice';
    const nameValid = attribute !== null || NAME_PATTERN.test(name);
    const optionsValid = !hasOptions || (options.length > 0 && options.every((o) => o.value.trim() !== ''));

    function setOption(index: number, patch: Partial<ContactAttributeOption>) {
        setOptions((prev) => prev.map((o, i) => (i === index ? { ...o, ...patch } : o)));
    }

    async function submit(event: FormEvent) {
        event.preventDefault();
        if (!nameValid || !optionsValid) {
            return;
        }
        setSaving(true);
        setError(null);
        const cleanOptions = options.map((o) => ({
            value: o.value.trim(),
            label: o.label.trim() === '' ? o.value.trim() : o.label.trim(),
        }));
        try {
            if (attribute) {
                await onUpdate(attribute.id, {
                    label: label.trim() === '' ? undefined : label.trim(),
                    ...(hasOptions ? { options: cleanOptions } : {}),
                });
            } else {
                await onCreate({
                    name,
                    ...(label.trim() === '' ? {} : { label: label.trim() }),
                    type,
                    ...(hasOptions ? { options: cleanOptions } : {}),
                });
            }
            onOpenChange(false);
        } catch (e) {
            setError(apiErrorMessage(e, t.common.genericError));
        } finally {
            setSaving(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, d) => onOpenChange(d.open)}>
            <DialogSurface>
                <form onSubmit={submit}>
                    <DialogBody>
                        <DialogTitle>{attribute ? t.attributes.editTitle : t.attributes.createTitle}</DialogTitle>
                        <DialogContent style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {error && (
                                <MessageBar intent="error">
                                    <MessageBarBody>{error}</MessageBarBody>
                                </MessageBar>
                            )}
                            <Field
                                label={t.attributes.name}
                                required
                                hint={t.attributes.nameHelp}
                                validationState={name !== '' && !nameValid ? 'error' : 'none'}
                            >
                                <Input
                                    value={name}
                                    maxLength={50}
                                    disabled={attribute !== null}
                                    onChange={(_, d) => setName(normalizeAttributeName(d.value))}
                                />
                            </Field>
                            <Field label={t.attributes.label}>
                                <Input value={label} onChange={(_, d) => setLabel(d.value)} />
                            </Field>
                            <Field label={t.attributes.type} required hint={t.attributes.typeHelp[type]}>
                                <Dropdown
                                    value={t.attributes.types[type]}
                                    selectedOptions={[type]}
                                    disabled={attribute !== null}
                                    onOptionSelect={(_, d) => {
                                        if (d.optionValue) {
                                            setType(d.optionValue as ContactAttributeType);
                                        }
                                    }}
                                >
                                    {TYPES.map((ty) => (
                                        <Option key={ty} value={ty} text={t.attributes.types[ty]}>
                                            {t.attributes.types[ty]}
                                        </Option>
                                    ))}
                                </Dropdown>
                            </Field>
                            {hasOptions && (
                                <Field label={t.attributes.options} required>
                                    <div style={{ display: 'flex', flexDirection: 'column', gap: 8 }}>
                                        {options.map((o, i) => (
                                            <div key={i} style={{ display: 'flex', gap: 8 }}>
                                                <Input
                                                    style={{ flex: 1 }}
                                                    placeholder={t.attributes.optionValue}
                                                    aria-label={t.attributes.optionValue}
                                                    value={o.value}
                                                    onChange={(_, d) => setOption(i, { value: d.value })}
                                                />
                                                <Input
                                                    style={{ flex: 1 }}
                                                    placeholder={t.attributes.optionLabel}
                                                    aria-label={t.attributes.optionLabel}
                                                    value={o.label}
                                                    onChange={(_, d) => setOption(i, { label: d.value })}
                                                />
                                                <Button
                                                    appearance="subtle"
                                                    icon={<DeleteRegular />}
                                                    aria-label={t.common.remove}
                                                    onClick={() => setOptions((prev) => prev.filter((_x, j) => j !== i))}
                                                />
                                            </div>
                                        ))}
                                        <div>
                                            <Button
                                                size="small"
                                                icon={<AddRegular />}
                                                onClick={() => setOptions((prev) => [...prev, { value: '', label: '' }])}
                                            >
                                                {t.attributes.addOption}
                                            </Button>
                                        </div>
                                    </div>
                                </Field>
                            )}
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => onOpenChange(false)} disabled={saving}>
                                {t.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                type="submit"
                                disabled={saving || !nameValid || name === '' && attribute === null || !optionsValid}
                                icon={saving ? <Spinner size="tiny" /> : undefined}
                            >
                                {t.common.save}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
