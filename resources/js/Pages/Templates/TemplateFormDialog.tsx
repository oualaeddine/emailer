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
    Field,
    Input,
} from '@fluentui/react-components';
import { RichTextEditor } from '@/Components/Composer/RichTextEditor';
import { useText } from '@/Hooks/useText';

interface TemplateFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (values: { name: string; category?: string; html_content: string }) => Promise<void>;
}

/**
 * docs/12-template-system.md §12.5 — Editing Workflow, reuses the same
 * editor component as the Composer.
 */
export function TemplateFormDialog({ open, onOpenChange, onSubmit }: TemplateFormDialogProps) {
    const t = useText();
    const [name, setName] = useState('');
    const [category, setCategory] = useState('');
    const [htmlContent, setHtmlContent] = useState('');
    const [submitting, setSubmitting] = useState(false);

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setSubmitting(true);
        try {
            await onSubmit({ name, category: category || undefined, html_content: htmlContent });
            setName('');
            setCategory('');
            setHtmlContent('');
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => onOpenChange(data.open)}>
            <DialogSurface>
                <form onSubmit={handleSubmit}>
                    <DialogBody>
                        <DialogTitle>{t.templates.createTitle}</DialogTitle>
                        <DialogContent>
                            <Field label={t.templates.name} required>
                                <Input value={name} onChange={(_, data) => setName(data.value)} />
                            </Field>
                            <Field label={t.templates.category}>
                                <Input value={category} onChange={(_, data) => setCategory(data.value)} />
                            </Field>
                            <RichTextEditor value={htmlContent} onChange={setHtmlContent} />
                        </DialogContent>
                        <DialogActions>
                            <DialogTrigger disableButtonEnhancement>
                                <Button appearance="secondary">{t.common.cancel}</Button>
                            </DialogTrigger>
                            <Button appearance="primary" type="submit" disabled={submitting || !name}>
                                {t.common.save}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
