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
import type { CreateRecipientPayload } from '@/Lib/api/recipients';

interface RecipientFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (values: CreateRecipientPayload) => Promise<void>;
    errors?: Partial<Record<string, string>>;
}

const SOURCES = [
    { value: 'manual', labelKey: 'sourceManual' as const },
    { value: 'internal_contact', labelKey: 'sourceInternalContact' as const },
];

/**
 * docs/29-api-specification.md §29.4 — POST /api/v1/recipients.
 * Only manual/internal_contact are offered here — csv_import/excel_import/
 * pagejaunes are set internally by their respective import flows, never
 * chosen by hand in this form (docs/13-recipient-management.md §13.1).
 */
export function RecipientFormDialog({ open, onOpenChange, onSubmit, errors }: RecipientFormDialogProps) {
    const t = useText();
    const [values, setValues] = useState<CreateRecipientPayload>({
        email: '',
        first_name: '',
        last_name: '',
        company_name: '',
        source: 'manual',
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
                        <DialogTitle>{t.recipients.createTitle}</DialogTitle>
                        <DialogContent>
                            <Field
                                label={t.recipients.email}
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
                            <Field label={t.recipients.firstName}>
                                <Input
                                    value={values.first_name}
                                    onChange={(_, data) => setValues((v) => ({ ...v, first_name: data.value }))}
                                />
                            </Field>
                            <Field label={t.recipients.lastName}>
                                <Input
                                    value={values.last_name}
                                    onChange={(_, data) => setValues((v) => ({ ...v, last_name: data.value }))}
                                />
                            </Field>
                            <Field label={t.recipients.companyName}>
                                <Input
                                    value={values.company_name}
                                    onChange={(_, data) => setValues((v) => ({ ...v, company_name: data.value }))}
                                />
                            </Field>
                            <Field label={t.recipients.source}>
                                <Dropdown
                                    value={t.recipients[SOURCES.find((s) => s.value === values.source)?.labelKey ?? 'sourceManual']}
                                    onOptionSelect={(_, data) =>
                                        setValues((v) => ({ ...v, source: data.optionValue ?? 'manual' }))
                                    }
                                >
                                    {SOURCES.map((source) => (
                                        <Option key={source.value} value={source.value}>
                                            {t.recipients[source.labelKey]}
                                        </Option>
                                    ))}
                                </Dropdown>
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
