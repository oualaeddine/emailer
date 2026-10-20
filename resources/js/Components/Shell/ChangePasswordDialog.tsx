import { FormEvent, useState } from 'react';
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
    Toast,
    ToastTitle,
    Toaster,
    useId,
    useToastController,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { changePassword } from '@/Lib/api/identity';

interface ChangePasswordDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

interface ValidationErrorResponse {
    response?: { status?: number; data?: { errors?: Record<string, string[]> } };
}

const emptyValues = { current_password: '', password: '', password_confirmation: '' };

/**
 * PUT /api/v1/me/password — opened from the TopBar account menu.
 */
export function ChangePasswordDialog({ open, onOpenChange }: ChangePasswordDialogProps) {
    const t = useText();
    const toasterId = useId('change-password-toaster');
    const { dispatchToast } = useToastController(toasterId);
    const [values, setValues] = useState(emptyValues);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [submitting, setSubmitting] = useState(false);

    function close(next: boolean) {
        if (!next) {
            setValues(emptyValues);
            setErrors({});
        }
        onOpenChange(next);
    }

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        setErrors({});
        try {
            await changePassword(values);
            close(false);
            dispatchToast(
                <Toast>
                    <ToastTitle>{t.users.changePassword.success}</ToastTitle>
                </Toast>,
                { intent: 'success' },
            );
        } catch (error) {
            const response = (error as ValidationErrorResponse).response;
            if (response?.status === 422) {
                setErrors(
                    Object.fromEntries(
                        Object.entries(response.data?.errors ?? {}).map(([field, messages]) => [field, messages[0]]),
                    ),
                );
            } else {
                throw error;
            }
        } finally {
            setSubmitting(false);
        }
    }

    const field = (name: keyof typeof emptyValues) => ({
        validationState: errors[name] ? ('error' as const) : ('none' as const),
        validationMessage: errors[name],
    });

    return (
        <>
            <Toaster toasterId={toasterId} position="bottom-end" />
            <Dialog open={open} onOpenChange={(_, data) => close(data.open)}>
                <DialogSurface>
                    <form onSubmit={handleSubmit}>
                        <DialogBody>
                            <DialogTitle>{t.users.changePassword.title}</DialogTitle>
                            <DialogContent>
                                <Field label={t.users.changePassword.current} required {...field('current_password')}>
                                    <Input
                                        type="password"
                                        autoComplete="current-password"
                                        value={values.current_password}
                                        onChange={(_, d) => setValues((v) => ({ ...v, current_password: d.value }))}
                                    />
                                </Field>
                                <Field
                                    label={t.users.changePassword.new}
                                    hint={t.users.changePassword.hint}
                                    required
                                    {...field('password')}
                                >
                                    <Input
                                        type="password"
                                        autoComplete="new-password"
                                        value={values.password}
                                        onChange={(_, d) => setValues((v) => ({ ...v, password: d.value }))}
                                    />
                                </Field>
                                <Field label={t.users.changePassword.confirm} required {...field('password_confirmation')}>
                                    <Input
                                        type="password"
                                        autoComplete="new-password"
                                        value={values.password_confirmation}
                                        onChange={(_, d) => setValues((v) => ({ ...v, password_confirmation: d.value }))}
                                    />
                                </Field>
                            </DialogContent>
                            <DialogActions>
                                <Button appearance="secondary" onClick={() => close(false)}>
                                    {t.common.cancel}
                                </Button>
                                <Button appearance="primary" type="submit" disabled={submitting}>
                                    {t.common.save}
                                </Button>
                            </DialogActions>
                        </DialogBody>
                    </form>
                </DialogSurface>
            </Dialog>
        </>
    );
}
