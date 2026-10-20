import { FormEvent, useEffect, useState } from 'react';
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
    MessageBar,
    MessageBarBody,
    Spinner,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { apiErrorMessage } from '@/Lib/api/senders';
import type { Sender, SenderInput, SenderUpdateInput } from '@/Lib/types/senders';

interface Props {
    open: boolean;
    sender: Sender | null;
    onOpenChange: (open: boolean) => void;
    onCreate: (payload: SenderInput) => Promise<void>;
    onUpdate: (id: string, payload: SenderUpdateInput) => Promise<void>;
}

export function SenderFormDialog({ open, sender, onOpenChange, onCreate, onUpdate }: Props) {
    const t = useText();
    const [name, setName] = useState('');
    const [email, setEmail] = useState('');
    const [replyTo, setReplyTo] = useState('');
    const [isDefault, setIsDefault] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (open) {
            setName(sender?.name ?? '');
            setEmail(sender?.email ?? '');
            setReplyTo(sender?.reply_to ?? '');
            setIsDefault(false);
            setError(null);
        }
    }, [open, sender]);

    async function submit(event: FormEvent) {
        event.preventDefault();
        setSaving(true);
        setError(null);
        try {
            if (sender) {
                await onUpdate(sender.id, { name, reply_to: replyTo.trim() === '' ? null : replyTo.trim() });
            } else {
                await onCreate({
                    name,
                    email,
                    reply_to: replyTo.trim() === '' ? null : replyTo.trim(),
                    is_default: isDefault,
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
                        <DialogTitle>{sender ? t.senders.editTitle : t.senders.createTitle}</DialogTitle>
                        <DialogContent style={{ display: 'flex', flexDirection: 'column', gap: 12 }}>
                            {error && (
                                <MessageBar intent="error">
                                    <MessageBarBody>{error}</MessageBarBody>
                                </MessageBar>
                            )}
                            <Field label={t.senders.name} required>
                                <Input value={name} onChange={(_, d) => setName(d.value)} required />
                            </Field>
                            <Field label={t.senders.email} required>
                                <Input
                                    type="email"
                                    value={email}
                                    onChange={(_, d) => setEmail(d.value)}
                                    disabled={sender !== null}
                                    required
                                />
                            </Field>
                            <Field label={t.senders.replyTo}>
                                <Input type="email" value={replyTo} onChange={(_, d) => setReplyTo(d.value)} />
                            </Field>
                            {!sender && (
                                <Checkbox
                                    checked={isDefault}
                                    onChange={(_, d) => setIsDefault(d.checked === true)}
                                    label={t.senders.default}
                                />
                            )}
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => onOpenChange(false)} disabled={saving}>
                                {t.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                type="submit"
                                disabled={saving}
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
