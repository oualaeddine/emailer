import { FormEvent, useEffect, useState } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Select,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { createContactAttribute, importErrorMessage } from '@/Lib/api/imports';
import type { ContactAttribute, ContactAttributeType } from '@/Lib/types/attributes';

const TYPES: ContactAttributeType[] = ['text', 'number', 'date', 'boolean'];

interface Props {
    open: boolean;
    initialName: string;
    onOpenChange: (open: boolean) => void;
    onCreated: (attribute: ContactAttribute) => void;
}

/** Inline "Créer un attribut" (POST /contact-attributes, WP-50 contract). */
export function CreateAttributeDialog({ open, initialName, onOpenChange, onCreated }: Props) {
    const t = useText();
    const [name, setName] = useState('');
    const [type, setType] = useState<ContactAttributeType>('text');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setName(initialName.toUpperCase().replace(/[^A-Z0-9_]+/g, '_').replace(/^[^A-Z]+/, '').slice(0, 50));
            setType('text');
            setError(null);
        }
    }, [open, initialName]);

    async function submit(event: FormEvent) {
        event.preventDefault();
        setSaving(true);
        setError(null);
        try {
            onCreated(await createContactAttribute({ name, label: name, type }));
            onOpenChange(false);
        } catch (e) {
            setError(importErrorMessage(e, t.common.genericError));
        } finally {
            setSaving(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, d) => onOpenChange(d.open)}>
            <DialogSurface>
                <form onSubmit={submit}>
                    <DialogBody>
                        <DialogTitle>{t.attributes.createTitle}</DialogTitle>
                        <DialogContent style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {error && (
                                <MessageBar intent="error">
                                    <MessageBarBody>{error}</MessageBarBody>
                                </MessageBar>
                            )}
                            <Field label={t.attributes.name} hint={t.attributes.nameHelp} required>
                                <Input value={name} onChange={(_, d) => setName(d.value.toUpperCase())} />
                            </Field>
                            <Field label={t.attributes.type}>
                                <Select value={type} onChange={(_, d) => setType(d.value as ContactAttributeType)}>
                                    {TYPES.map((value) => (
                                        <option key={value} value={value}>
                                            {t.attributes.types[value]}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => onOpenChange(false)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" type="submit" disabled={saving || name === ''}>
                                {t.common.create}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
