import { useEffect, useRef, useState } from 'react';
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
import { fetchCampaignTestStatus, sendCampaignTest } from '@/Lib/api/campaigns';
import type { AbVariantLabel, TestSendMessageStatus, TestSendResult } from '@/Lib/types/campaigns';
import { apiErrorMessage, apiValidationErrors, formatDateTime, formatText, isEmailLike } from '@/Components/Campaigns/campaignUi';

const MAX_TEST_EMAILS = 5;
const TEST_STATUS_POLL_MS = 3000;
/** The delivery engine retries for ~15 min; stop watching after 3 min and say it is still pending. */
const TEST_STATUS_TIMEOUT_MS = 3 * 60 * 1000;

const DELIVERED_STATUSES = ['accepted', 'delivered', 'opened', 'clicked'];
const PENDING_STATUSES = ['queued', 'sending'];

type FeedbackIntent = 'success' | 'error' | 'info' | 'warning';

interface TestDeliveryTexts {
    testSent: string;
    testDeliveredTo: string;
    testFailed: string;
    testRetrying: string;
}

export interface TestDeliveryOutcome {
    /** Every message reached a final state. */
    done: boolean;
    intent: FeedbackIntent;
    text: string;
    /** Set while a message waits for a retry after a server refusal. */
    retrying?: string;
}

/** Turns the test messages' statuses into the message shown under "Envoyer un test". */
export function summarizeTestDelivery(rows: TestSendMessageStatus[], texts: TestDeliveryTexts): TestDeliveryOutcome {
    const label = (row: TestSendMessageStatus) => row.email ?? '?';
    const failed = rows.filter((row) => !DELIVERED_STATUSES.includes(row.status) && !PENDING_STATUSES.includes(row.status));
    const pending = rows.filter((row) => PENDING_STATUSES.includes(row.status));
    const retrying = pending.filter((row) => row.error);
    const retryText = retrying.length > 0
        ? formatText(texts.testRetrying, retrying.map(label).join(', '), retrying[0].error ?? '')
        : undefined;

    if (failed.length > 0) {
        return {
            done: pending.length === 0,
            intent: 'error',
            text: formatText(texts.testFailed, failed.map(label).join(', '), failed[0].error ?? failed[0].status),
            retrying: retryText,
        };
    }
    if (pending.length > 0 || rows.length === 0) {
        return { done: false, intent: 'info', text: '', retrying: retryText };
    }

    return { done: true, intent: 'success', text: formatText(texts.testDeliveredTo, rows.map(label).join(', ')) };
}

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
    const [feedback, setFeedback] = useState<{ intent: FeedbackIntent; text: string } | null>(null);
    const [sentAt, setSentAt] = useState<string | null>(lastTestSentAt ?? null);

    useEffect(() => setSentAt(lastTestSentAt ?? null), [lastTestSentAt]);

    const emails = parseTestEmails(raw);
    const tooMany = emails.length > MAX_TEST_EMAILS;
    const invalid = emails.find((email) => !isEmailLike(email));
    const validation = tooMany ? t.campaignSetup.previewPanel.tooManyEmails : invalid;

    // The API only queues the test: poll its real outcome so "envoyé" is shown
    // once the SMTP server accepted it, and the server's error otherwise.
    const pollTimer = useRef<number | null>(null);
    const pollRun = useRef(0);

    function stopPolling() {
        pollRun.current += 1;
        if (pollTimer.current !== null) {
            window.clearTimeout(pollTimer.current);
            pollTimer.current = null;
        }
    }

    useEffect(() => stopPolling, []);

    function trackDelivery(messageIds: string[]) {
        stopPolling();
        if (messageIds.length === 0) {
            setFeedback({ intent: 'success', text: t.campaignSetup.previewPanel.testSent });

            return;
        }
        const run = pollRun.current;
        const panel = t.campaignSetup.previewPanel;
        const startedAt = Date.now();
        setFeedback({ intent: 'info', text: panel.testQueued });

        const poll = async () => {
            let rows: TestSendMessageStatus[];
            try {
                rows = await fetchCampaignTestStatus(campaignId, messageIds);
            } catch {
                if (run === pollRun.current) {
                    setFeedback({ intent: 'warning', text: panel.testStatusUnknown });
                }

                return;
            }
            if (run !== pollRun.current) {
                return;
            }
            const outcome = summarizeTestDelivery(rows, panel);
            if (outcome.done) {
                setFeedback({ intent: outcome.intent, text: outcome.text });

                return;
            }
            const interim: { intent: FeedbackIntent; text: string } = outcome.intent === 'error'
                ? { intent: 'error', text: outcome.text }
                : outcome.retrying
                    ? { intent: 'warning', text: outcome.retrying }
                    : { intent: 'info', text: panel.testQueued };
            if (Date.now() - startedAt >= TEST_STATUS_TIMEOUT_MS) {
                setFeedback(interim.intent === 'info' ? { intent: 'warning', text: panel.testStillPending } : interim);

                return;
            }
            setFeedback(interim);
            pollTimer.current = window.setTimeout(() => void poll(), TEST_STATUS_POLL_MS);
        };

        pollTimer.current = window.setTimeout(() => void poll(), TEST_STATUS_POLL_MS);
    }

    async function handleSend() {
        if (emails.length === 0 || validation) {
            return;
        }
        stopPolling();
        setBusy(true);
        setFeedback(null);
        try {
            const result = await sendCampaignTest(campaignId, variant ? { emails, variant } : { emails });
            setSentAt(result.test_sent_at);
            trackDelivery(result.message_ids ?? []);
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
