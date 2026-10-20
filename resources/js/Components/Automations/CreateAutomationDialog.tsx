import { useEffect, useState, type FormEvent } from 'react';
import { router } from '@inertiajs/react';
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
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { apiErrorMessage, apiValidationErrors } from '@/Components/Campaigns/campaignUi';
import { useText } from '@/Hooks/useText';
import { createAutomation } from '@/Lib/api/automations';
import type { AutomationTrigger } from '@/Lib/types/automations';
import { isTriggerConfigured } from './graph';
import { AutomationLookupsProvider } from './lookups';
import { TriggerConfigEditor } from './TriggerCard';

const useStyles = makeStyles({
    content: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
});

export interface CreateAutomationDialogProps {
    open: boolean;
    onClose: () => void;
}

/** Index "Créer une automatisation": name + trigger → POST /automations → editor. */
export function CreateAutomationDialog({ open, onClose }: CreateAutomationDialogProps) {
    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && onClose()}>
            <DialogSurface>
                {open && (
                    <AutomationLookupsProvider>
                        <CreateForm onClose={onClose} />
                    </AutomationLookupsProvider>
                )}
            </DialogSurface>
        </Dialog>
    );
}

function CreateForm({ onClose }: { onClose: () => void }) {
    const styles = useStyles();
    const t = useText();
    const [name, setName] = useState('');
    const [trigger, setTrigger] = useState<AutomationTrigger | null>(null);
    const [attempted, setAttempted] = useState(false);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [failure, setFailure] = useState<string | null>(null);

    useEffect(() => {
        setErrors({});
    }, [trigger?.type]);

    const nameMissing = name.trim() === '';
    const triggerReady = isTriggerConfigured(trigger);

    async function submit(event: FormEvent) {
        event.preventDefault();
        setAttempted(true);
        if (nameMissing || !trigger || !triggerReady || saving) return;
        setSaving(true);
        setFailure(null);
        setErrors({});
        try {
            const created = await createAutomation({ name: name.trim(), trigger, allow_reentry: false, reentry_after_days: null });
            router.visit(`/automations/${created.id}`);
        } catch (error) {
            const validation = apiValidationErrors(error);
            setErrors(validation);
            setFailure(Object.keys(validation).length > 0 ? null : apiErrorMessage(error));
            setSaving(false);
        }
    }

    const nameError = errors.name ?? (attempted && nameMissing ? t.automations.editor.editor.nameRequired : undefined);

    return (
        <form onSubmit={submit}>
            <DialogBody>
                <DialogTitle>{t.automations.createTitle}</DialogTitle>
                <DialogContent className={styles.content}>
                    {failure && (
                        <MessageBar intent="error">
                            <MessageBarBody>{failure}</MessageBarBody>
                        </MessageBar>
                    )}
                    <Field label={t.automations.name} required validationMessage={nameError} validationState={nameError ? 'error' : 'none'}>
                        <Input value={name} maxLength={255} autoFocus onChange={(_, data) => setName(data.value)} />
                    </Field>
                    <Text className={styles.muted}>{t.automations.editor.create.chooseTrigger}</Text>
                    <TriggerConfigEditor trigger={trigger} onChange={setTrigger} errors={errors} showRequired={attempted} hideFilter disabled={saving} />
                </DialogContent>
                <DialogActions>
                    <Button appearance="secondary" onClick={onClose} disabled={saving}>
                        {t.common.cancel}
                    </Button>
                    <Button appearance="primary" type="submit" disabled={saving} icon={saving ? <Spinner size="tiny" /> : undefined}>
                        {t.automations.editor.create.submit}
                    </Button>
                </DialogActions>
            </DialogBody>
        </form>
    );
}
