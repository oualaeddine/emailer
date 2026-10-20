import { useState, type FormEvent } from 'react';
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
    Spinner,
} from '@fluentui/react-components';
import { newFormInput } from '@/Components/Forms/model';
import { useText } from '@/Hooks/useText';
import { apiErrorMessage, createForm } from '@/Lib/api/forms';
import type { SubscriptionForm } from '@/Lib/types/forms';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: (form: SubscriptionForm) => void;
}

/** *Créer un formulaire d'inscription* → name → POST /forms with the default definition. */
export function CreateFormDialog({ open, onOpenChange, onCreated }: Props) {
    const t = useText();
    const [name, setName] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit(event: FormEvent) {
        event.preventDefault();
        const trimmed = name.trim();
        if (trimmed === '' || saving) return;
        setSaving(true);
        setError(null);
        try {
            onCreated(await createForm(newFormInput(t, trimmed)));
            setName('');
        } catch (e) {
            setError(apiErrorMessage(e, t.common.genericError));
        } finally {
            setSaving(false);
        }
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(_, d) => {
                if (!d.open) setError(null);
                onOpenChange(d.open);
            }}
        >
            <DialogSurface>
                <form onSubmit={submit}>
                    <DialogBody>
                        <DialogTitle>{t.forms.createTitle}</DialogTitle>
                        <DialogContent>
                            {error && (
                                <MessageBar intent="error" style={{ marginBottom: 12 }}>
                                    <MessageBarBody>{error}</MessageBarBody>
                                </MessageBar>
                            )}
                            <Field label={t.forms.name} required>
                                <Input value={name} maxLength={150} autoFocus onChange={(_, d) => setName(d.value)} />
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" type="button" onClick={() => onOpenChange(false)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" type="submit" disabled={saving || name.trim() === ''}>
                                {saving ? <Spinner size="tiny" /> : t.common.create}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
