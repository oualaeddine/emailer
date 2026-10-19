import { useEffect, useState, type FormEvent } from 'react';
import {
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Field,
    Input,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';

const useStyles = makeStyles({
    content: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
});

export interface SaveViewDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** "create" = *Enregistrer comme vue*; "rename" = *Renommer la vue*. */
    mode: 'create' | 'rename';
    initialName?: string;
    initialShared?: boolean;
    /** Resolve to close; reject with a string to show it under the name field. */
    onSubmit: (name: string, isShared: boolean) => Promise<void>;
}

/** docs/43-brevo-parity-plan.md §43.4.6 — name (and sharing) of a saved contacts view. */
export function SaveViewDialog({ open, onOpenChange, mode, initialName = '', initialShared = false, onSubmit }: SaveViewDialogProps) {
    const styles = useStyles();
    const t = useText();
    const [name, setName] = useState(initialName);
    const [shared, setShared] = useState(initialShared);
    const [error, setError] = useState<string | null>(null);
    const [submitting, setSubmitting] = useState(false);

    useEffect(() => {
        if (open) {
            setName(initialName);
            setShared(initialShared);
            setError(null);
        }
    }, [open, initialName, initialShared]);

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        if (name.trim() === '') {
            return;
        }
        setSubmitting(true);
        setError(null);
        try {
            await onSubmit(name.trim(), shared);
            onOpenChange(false);
        } catch (reason) {
            setError(typeof reason === 'string' ? reason : t.common.genericError);
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => onOpenChange(data.open)}>
            <DialogSurface>
                <form onSubmit={handleSubmit}>
                    <DialogBody>
                        <DialogTitle>{mode === 'create' ? t.contacts.views.saveAsView : t.contacts.views.renameView}</DialogTitle>
                        <DialogContent className={styles.content}>
                            <Field
                                label={t.contacts.views.viewName}
                                required
                                validationState={error ? 'error' : 'none'}
                                validationMessage={error ?? undefined}
                            >
                                <Input value={name} maxLength={100} onChange={(_, data) => setName(data.value)} autoFocus />
                            </Field>
                            <Checkbox
                                label={t.contacts.views.shared}
                                checked={shared}
                                onChange={(_, data) => setShared(data.checked === true)}
                            />
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => onOpenChange(false)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" type="submit" disabled={submitting || name.trim() === ''}>
                                {submitting ? t.common.saving : t.common.save}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
