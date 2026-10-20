/**
 * WP-60 — *Aperçu & test*: desktop / mobile preview of the current design
 * (browser compile, sandboxed iframe) and a test send of the saved design.
 */
import { useState } from 'react';
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
    Tab,
    TabList,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { DesktopRegular, PhoneRegular, SendRegular } from '@fluentui/react-icons';
import { text } from './strings';

const useStyles = makeStyles({
    surface: { maxWidth: '920px', width: 'calc(100vw - 32px)' },
    content: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
    stage: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground3,
        borderRadius: tokens.borderRadiusMedium,
    },
    frame: {
        height: '60vh',
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground1,
    },
    test: { display: 'flex', gap: tokens.spacingHorizontalS, alignItems: 'flex-end', flexWrap: 'wrap' },
    testField: { flex: '1 1 280px' },
});

export interface PreviewDialogProps {
    open: boolean;
    html: string;
    onClose: () => void;
    /** Saves, then sends the test; rejects with a message to show. */
    onSendTest?: (emails: string[]) => Promise<void>;
}

const EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function PreviewDialog({ open, html, onClose, onSendTest }: PreviewDialogProps) {
    const styles = useStyles();
    const [device, setDevice] = useState<'desktop' | 'mobile'>('desktop');
    const [emails, setEmails] = useState('');
    const [sending, setSending] = useState(false);
    const [feedback, setFeedback] = useState<{ intent: 'success' | 'error'; message: string } | null>(null);

    const list = emails
        .split(/[,;\s]+/)
        .map((email) => email.trim())
        .filter(Boolean);
    const tooMany = list.length > 5;
    const invalid = list.some((email) => !EMAIL.test(email));

    const send = async () => {
        if (!onSendTest || list.length === 0 || tooMany || invalid) {
            return;
        }
        setSending(true);
        setFeedback(null);
        try {
            await onSendTest(list);
            setFeedback({ intent: 'success', message: text.preview.testSent });
        } catch (error) {
            setFeedback({ intent: 'error', message: error instanceof Error && error.message ? error.message : text.common.genericError });
        } finally {
            setSending(false);
        }
    };

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && onClose()}>
            <DialogSurface className={styles.surface}>
                <DialogBody>
                    <DialogTitle>{text.preview.title}</DialogTitle>
                    <DialogContent className={styles.content}>
                        <TabList size="small" selectedValue={device} onTabSelect={(_, data) => setDevice(data.value as 'desktop' | 'mobile')}>
                            <Tab value="desktop" icon={<DesktopRegular />}>
                                {text.preview.desktop}
                            </Tab>
                            <Tab value="mobile" icon={<PhoneRegular />}>
                                {text.preview.mobile}
                            </Tab>
                        </TabList>
                        <div className={styles.stage}>
                            <iframe
                                title={text.preview.title}
                                className={styles.frame}
                                style={{ width: device === 'mobile' ? '375px' : '100%' }}
                                sandbox=""
                                srcDoc={html}
                            />
                        </div>
                        {onSendTest && (
                            <div className={styles.test}>
                                <Field
                                    className={styles.testField}
                                    label={text.preview.testEmails}
                                    hint={`${text.preview.testEmailsHelp} ${text.sendTestSaveFirst}`}
                                    validationState={tooMany || invalid ? 'error' : 'none'}
                                    validationMessage={tooMany ? text.preview.tooManyEmails : undefined}
                                >
                                    <Input type="text" value={emails} onChange={(_, data) => setEmails(data.value)} />
                                </Field>
                                <Button
                                    appearance="primary"
                                    icon={<SendRegular />}
                                    disabled={sending || list.length === 0 || tooMany || invalid}
                                    onClick={() => void send()}
                                >
                                    {sending ? text.saving : text.preview.sendTest}
                                </Button>
                            </div>
                        )}
                        {feedback && (
                            <MessageBar intent={feedback.intent}>
                                <MessageBarBody>{feedback.message}</MessageBarBody>
                            </MessageBar>
                        )}
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" onClick={onClose}>
                            {text.common.close}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
