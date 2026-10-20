import { useEffect, useState } from 'react';
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
import { SendRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { sendCampaignTest } from '@/Lib/api/campaigns';
import type { AbVariantLabel, TestSendResult } from '@/Lib/types/campaigns';
import { apiErrorMessage, apiValidationErrors, formatDateTime, formatText, isEmailLike } from '@/Components/Campaigns/campaignUi';

const MAX_TEST_EMAILS = 5;

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    row: {
        display: 'flex',
        gap: tokens.spacingHorizontalS,
        alignItems: 'flex-end',
        flexWrap: 'wrap',
    },
    input: {
        flexGrow: 1,
        minWidth: '220px',
    },
});

export function parseTestEmails(raw: string): string[] {
    return raw
        .split(/[,;\s]+/)
        .map((email) => email.trim())
        .filter((email) => email.length > 0);
}

export interface TestSendFormProps {
    campaignId: string;
    /** A/B version driving the test send (gap-abvariant). */
    variant?: AbVariantLabel | null;
    lastTestSentAt?: string | null;
    disabled?: boolean;
    onSent?: (result: TestSendResult) => void;
}

/** "Envoyer un test" — up to 5 addresses (`POST /campaigns/{id}/test-send`, docs/45 §45.4.2). */
export function TestSendForm({ campaignId, variant, lastTestSentAt, disabled, onSent }: TestSendFormProps) {
    const styles = useStyles();
    const t = useText();
    const [raw, setRaw] = useState('');
    const [busy, setBusy] = useState(false);
    const [feedback, setFeedback] = useState<{ intent: 'success' | 'error'; text: string } | null>(null);
    const [sentAt, setSentAt] = useState<string | null>(lastTestSentAt ?? null);

    useEffect(() => setSentAt(lastTestSentAt ?? null), [lastTestSentAt]);

    const emails = parseTestEmails(raw);
    const tooMany = emails.length > MAX_TEST_EMAILS;
    const invalid = emails.find((email) => !isEmailLike(email));
    const validation = tooMany ? t.campaignSetup.previewPanel.tooManyEmails : invalid;

    async function handleSend() {
        if (emails.length === 0 || validation) {
            return;
        }
        setBusy(true);
        setFeedback(null);
        try {
            const result = await sendCampaignTest(campaignId, variant ? { emails, variant } : { emails });
            setSentAt(result.test_sent_at);
            setFeedback({ intent: 'success', text: t.campaignSetup.previewPanel.testSent });
            onSent?.(result);
        } catch (err) {
            const errors = apiValidationErrors(err);
            setFeedback({ intent: 'error', text: errors.emails ?? Object.values(errors)[0] ?? apiErrorMessage(err) });
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className={styles.root}>
            <div className={styles.row}>
                <Field
                    className={styles.input}
                    label={t.campaignSetup.previewPanel.testEmails}
                    hint={t.campaignSetup.previewPanel.testEmailsHelp}
                    validationMessage={validation}
                >
                    <Input
                        type="text"
                        value={raw}
                        disabled={disabled || busy}
                        placeholder="a@exemple.dz, b@exemple.dz"
                        onChange={(_, data) => setRaw(data.value)}
                        onKeyDown={(event) => {
                            if (event.key === 'Enter') {
                                event.preventDefault();
                                void handleSend();
                            }
                        }}
                    />
                </Field>
                <Button
                    appearance="primary"
                    icon={busy ? <Spinner size="tiny" /> : <SendRegular />}
                    disabled={disabled || busy || emails.length === 0 || validation !== undefined}
                    onClick={() => void handleSend()}
                >
                    {t.campaignSetup.previewPanel.sendTest}
                </Button>
            </div>
            {feedback && (
                <MessageBar intent={feedback.intent}>
                    <MessageBarBody>{feedback.text}</MessageBarBody>
                </MessageBar>
            )}
            {sentAt && (
                <Text size={200}>{formatText(t.campaignSetup.previewPanel.testSentAt, formatDateTime(sentAt))}</Text>
            )}
        </div>
    );
}

export interface TestSendDialogProps {
    open: boolean;
    campaignId: string | null;
    onClose: () => void;
}

/** Row action "Envoyer un test" of Campaigns/Index. */
export function TestSendDialog({ open, campaignId, onClose }: TestSendDialogProps) {
    const t = useText();

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && onClose()}>
            <DialogSurface style={{ maxWidth: '560px' }}>
                <DialogBody>
                    <DialogTitle>{t.campaigns.actions.test_send}</DialogTitle>
                    <DialogContent>{campaignId && <TestSendForm campaignId={campaignId} />}</DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" onClick={onClose}>
                            {t.common.close}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
