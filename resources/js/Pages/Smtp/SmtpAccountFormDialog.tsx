import { FormEvent, useState } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    DialogTrigger,
    Dropdown,
    Field,
    Input,
    Option,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { CreateSmtpAccountPayload } from '@/Lib/api/smtp';

interface SmtpAccountFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (values: CreateSmtpAccountPayload) => Promise<void>;
}

/**
 * docs/18-smtp-management.md §18.2 — SMTP Account Form Fields.
 */
export function SmtpAccountFormDialog({ open, onOpenChange, onSubmit }: SmtpAccountFormDialogProps) {
    const t = useText();
    const [values, setValues] = useState<CreateSmtpAccountPayload>({
        name: '',
        provider: 'custom',
        host: '',
        port: 587,
        encryption: 'tls',
        username: '',
        password: '',
        from_email: '',
    });
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        try {
            await onSubmit(values);
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => onOpenChange(data.open)}>
            <DialogSurface>
                <form onSubmit={handleSubmit}>
                    <DialogBody>
                        <DialogTitle>{t.smtp.createTitle}</DialogTitle>
                        <DialogContent>
                            <Field label={t.smtp.name} required>
                                <Input value={values.name} onChange={(_, d) => setValues((v) => ({ ...v, name: d.value }))} />
                            </Field>
                            <Field label={t.smtp.provider}>
                                <Input
                                    value={values.provider}
                                    onChange={(_, d) => setValues((v) => ({ ...v, provider: d.value }))}
                                />
                            </Field>
                            <Field label={t.smtp.host} required>
                                <Input value={values.host} onChange={(_, d) => setValues((v) => ({ ...v, host: d.value }))} />
                            </Field>
                            <Field label={t.smtp.port} required>
                                <Input
                                    type="number"
                                    value={String(values.port)}
                                    onChange={(_, d) => setValues((v) => ({ ...v, port: Number(d.value) }))}
                                />
                            </Field>
                            <Field label={t.smtp.encryption}>
                                <Dropdown
                                    value={values.encryption}
                                    onOptionSelect={(_, d) => setValues((v) => ({ ...v, encryption: d.optionValue ?? 'tls' }))}
                                >
                                    <Option value="none">none</Option>
                                    <Option value="ssl">ssl</Option>
                                    <Option value="tls">tls</Option>
                                </Dropdown>
                            </Field>
                            <Field label={t.smtp.username} required>
                                <Input
                                    value={values.username}
                                    onChange={(_, d) => setValues((v) => ({ ...v, username: d.value }))}
                                />
                            </Field>
                            <Field label={t.smtp.password} required>
                                <Input
                                    type="password"
                                    value={values.password}
                                    onChange={(_, d) => setValues((v) => ({ ...v, password: d.value }))}
                                />
                            </Field>
                            <Field label={t.smtp.fromEmail} required>
                                <Input
                                    type="email"
                                    value={values.from_email}
                                    onChange={(_, d) => setValues((v) => ({ ...v, from_email: d.value }))}
                                />
                            </Field>
                            <Field label={t.smtp.dailyQuota}>
                                <Input
                                    type="number"
                                    value={values.daily_quota ? String(values.daily_quota) : ''}
                                    onChange={(_, d) =>
                                        setValues((v) => ({ ...v, daily_quota: d.value ? Number(d.value) : undefined }))
                                    }
                                />
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <DialogTrigger disableButtonEnhancement>
                                <Button appearance="secondary">{t.common.cancel}</Button>
                            </DialogTrigger>
                            <Button appearance="primary" type="submit" disabled={submitting}>
                                {t.common.save}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
