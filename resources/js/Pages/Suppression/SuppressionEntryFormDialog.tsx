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
    Textarea,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { CreateSuppressionEntryPayload } from '@/Lib/api/suppression';
import type { SuppressionReason } from '@/Lib/types/suppression';

interface SuppressionEntryFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (values: CreateSuppressionEntryPayload) => Promise<void>;
    errors?: Partial<Record<string, string>>;
}

const REASONS: { value: SuppressionReason; labelKey: keyof ReturnType<typeof useText>['suppression']['reasons'] }[] = [
    { value: 'manual_block', labelKey: 'manual_block' },
    { value: 'hard_bounce', labelKey: 'hard_bounce' },
    { value: 'spam_complaint', labelKey: 'spam_complaint' },
    { value: 'manual_unsubscribe', labelKey: 'manual_unsubscribe' },
    { value: 'global_unsubscribe', labelKey: 'global_unsubscribe' },
    { value: 'invalid_address', labelKey: 'invalid_address' },
];

/**
 * docs/23-suppression-list.md §23.5 — Administration → Suppression List → Add.
 * §23.7 — `notes` is only encouraged for the `manual_block` reason, so the
 * free-text field is shown solely when that reason is selected (judgment
 * call: doc doesn't forbid notes on other reasons, just doesn't encourage
 * them, so we don't send an unused field for the rest).
 */
export function SuppressionEntryFormDialog({ open, onOpenChange, onSubmit, errors }: SuppressionEntryFormDialogProps) {
    const t = useText();
    const [values, setValues] = useState<CreateSuppressionEntryPayload>({
        email: '',
        reason: 'manual_block',
        notes: '',
    });
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        try {
            await onSubmit({
                ...values,
                notes: values.reason === 'manual_block' && values.notes ? values.notes : undefined,
            });
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => onOpenChange(data.open)}>
            <DialogSurface>
                <form onSubmit={handleSubmit}>
                    <DialogBody>
                        <DialogTitle>{t.suppression.createTitle}</DialogTitle>
                        <DialogContent>
                            <Field
                                label={t.suppression.email}
                                validationState={errors?.email ? 'error' : 'none'}
                                validationMessage={errors?.email}
                                required
                            >
                                <Input
                                    type="email"
                                    value={values.email}
                                    onChange={(_, data) => setValues((v) => ({ ...v, email: data.value }))}
                                />
                            </Field>
                            <Field label={t.suppression.reason} required>
                                <Dropdown
                                    value={t.suppression.reasons[values.reason]}
                                    onOptionSelect={(_, data) =>
                                        setValues((v) => ({
                                            ...v,
                                            reason: (data.optionValue as SuppressionReason) ?? 'manual_block',
                                        }))
                                    }
                                >
                                    {REASONS.map((reason) => (
                                        <Option key={reason.value} value={reason.value}>
                                            {t.suppression.reasons[reason.labelKey]}
                                        </Option>
                                    ))}
                                </Dropdown>
                            </Field>
                            {values.reason === 'manual_block' && (
                                <Field label={t.suppression.notes}>
                                    <Textarea
                                        value={values.notes ?? ''}
                                        placeholder={t.suppression.notesPlaceholder}
                                        onChange={(_, data) => setValues((v) => ({ ...v, notes: data.value }))}
                                    />
                                </Field>
                            )}
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
