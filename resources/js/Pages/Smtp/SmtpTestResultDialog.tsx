import { useState } from 'react';
import {
    Badge,
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
    MessageBar,
    MessageBarBody,
    MessageBarTitle,
    Spinner,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import {
    ArrowClockwiseRegular,
    CheckmarkCircleRegular,
    CheckmarkRegular,
    CopyRegular,
    DismissCircleRegular,
    LightbulbRegular,
    SendRegular,
} from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { testSmtpAccount } from '@/Lib/api/smtp';
import { analyzeSmtpError } from '@/Lib/smtp/diagnostics';
import type { SmtpAccount } from '@/Lib/types/smtp';

const useStyles = makeStyles({
    surface: {
        maxWidth: '640px',
        width: '100%',
    },
    metaRow: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
        alignItems: 'center',
        marginBottom: tokens.spacingVerticalM,
    },
    metaItem: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXXS,
        fontSize: tokens.fontSizeBase200,
        color: tokens.colorNeutralForeground2,
    },
    section: {
        marginTop: tokens.spacingVerticalM,
    },
    solutionCard: {
        backgroundColor: tokens.colorNeutralBackground2,
        border: `1px solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        marginTop: tokens.spacingVerticalM,
    },
    solutionTitle: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
        fontWeight: tokens.fontWeightSemibold,
        fontSize: tokens.fontSizeBase300,
        color: tokens.colorNeutralForeground1,
        marginBottom: tokens.spacingVerticalXS,
    },
    solutionText: {
        fontSize: tokens.fontSizeBase300,
        lineHeight: tokens.lineHeightBase300,
        color: tokens.colorNeutralForeground1,
        marginBottom: tokens.spacingVerticalS,
    },
    causeText: {
        fontSize: tokens.fontSizeBase300,
        lineHeight: tokens.lineHeightBase300,
        color: tokens.colorNeutralForeground2,
        marginBottom: tokens.spacingVerticalS,
    },
    tipsList: {
        margin: `${tokens.spacingVerticalXS} 0 0 0`,
        paddingLeft: tokens.spacingHorizontalL,
        fontSize: tokens.fontSizeBase200,
        color: tokens.colorNeutralForeground2,
    },
    codeHeader: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        marginBottom: tokens.spacingVerticalXS,
    },
    codeTitle: {
        fontWeight: tokens.fontWeightSemibold,
        fontSize: tokens.fontSizeBase200,
        color: tokens.colorNeutralForeground2,
    },
    codeBox: {
        backgroundColor: tokens.colorNeutralBackground3,
        border: `1px solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalM}`,
        fontFamily: tokens.fontFamilyMonospace,
        fontSize: tokens.fontSizeBase200,
        whiteSpace: 'pre-wrap',
        wordBreak: 'break-word',
        maxHeight: '180px',
        overflowY: 'auto',
        color: tokens.colorNeutralForeground1,
        margin: 0,
    },
    testEmailRow: {
        display: 'flex',
        gap: tokens.spacingHorizontalS,
        alignItems: 'flex-end',
        marginTop: tokens.spacingVerticalS,
    },
    testEmailInput: {
        flexGrow: 1,
    },
});

interface SmtpTestResultDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    account: SmtpAccount | null;
    success: boolean;
    rawResponse: string;
    onRetry?: () => Promise<void> | void;
    isRetrying?: boolean;
}

export function SmtpTestResultDialog({
    open,
    onOpenChange,
    account,
    success,
    rawResponse,
    onRetry,
    isRetrying = false,
}: SmtpTestResultDialogProps) {
    const styles = useStyles();
    const t = useText();

    const [copied, setCopied] = useState(false);
    const [testEmail, setTestEmail] = useState('');
    const [sendingEmail, setSendingEmail] = useState(false);
    const [emailSendResult, setEmailSendResult] = useState<{ success: boolean; message: string } | null>(null);

    if (!account) {
        return null;
    }

    const diagnostic = !success
        ? analyzeSmtpError(rawResponse, {
              host: account.host,
              port: account.port,
              encryption: account.encryption,
          })
        : null;

    async function handleCopy() {
        try {
            await navigator.clipboard.writeText(rawResponse);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch {
            // Clipboard write fallback
        }
    }

    async function handleSendTestEmail() {
        if (!account || !testEmail.trim()) {
            return;
        }

        setSendingEmail(true);
        setEmailSendResult(null);
        try {
            const result = await testSmtpAccount(account.id, testEmail.trim());
            setEmailSendResult({ success: result.success, message: result.raw_response });
        } catch (error: any) {
            const errorMsg = error?.response?.data?.message || error?.message || 'Erreur inconnue';
            setEmailSendResult({ success: false, message: errorMsg });
        } finally {
            setSendingEmail(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => onOpenChange(data.open)}>
            <DialogSurface className={styles.surface}>
                <DialogBody>
                    <DialogTitle
                        action={
                            <Badge
                                appearance="tint"
                                color={success ? 'success' : 'danger'}
                                icon={success ? <CheckmarkCircleRegular /> : <DismissCircleRegular />}
                            >
                                {success ? t.smtp.testSuccess : t.smtp.testFailure}
                            </Badge>
                        }
                    >
                        {t.smtp.testResultTitle}
                    </DialogTitle>

                    <DialogContent>
                        {/* Account metadata */}
                        <div className={styles.metaRow}>
                            <Badge appearance="outline">{account.name}</Badge>
                            <span className={styles.metaItem}>
                                <strong>{t.smtp.host}:</strong> {account.host}:{account.port}
                            </span>
                            <span className={styles.metaItem}>
                                <strong>{t.smtp.encryption}:</strong> {account.encryption.toUpperCase()}
                            </span>
                            <span className={styles.metaItem}>
                                <strong>{t.smtp.username}:</strong> {account.username}
                            </span>
                        </div>

                        {/* Status Message */}
                        <MessageBar intent={success ? 'success' : 'error'}>
                            <MessageBarBody>
                                <MessageBarTitle>
                                    {success ? t.smtp.testSuccess : (diagnostic?.title || t.smtp.testFailure)}
                                </MessageBarTitle>
                                {success ? t.smtp.testSuccessMessage : t.smtp.testFailureMessage}
                            </MessageBarBody>
                        </MessageBar>

                        {/* Actionable Solution Section when test failed */}
                        {diagnostic && (
                            <div className={styles.solutionCard}>
                                <div className={styles.solutionTitle}>
                                    <LightbulbRegular style={{ color: tokens.colorPaletteGoldForeground1 }} />
                                    <span>{t.smtp.diagnosticSectionTitle}</span>
                                </div>

                                <div className={styles.causeText}>
                                    <strong>{t.smtp.causeIdentified} :</strong> {diagnostic.cause}
                                </div>

                                <div className={styles.solutionText}>
                                    <strong>{t.smtp.recommendedSolution} :</strong> {diagnostic.solution}
                                </div>

                                {diagnostic.tips && diagnostic.tips.length > 0 && (
                                    <ul className={styles.tipsList}>
                                        {diagnostic.tips.map((tip, i) => (
                                            <li key={i}>{tip}</li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}

                        {/* Raw diagnostic response */}
                        <div className={styles.section}>
                            <div className={styles.codeHeader}>
                                <span className={styles.codeTitle}>{t.smtp.rawResponse}</span>
                                <Button
                                    size="small"
                                    appearance="subtle"
                                    icon={copied ? <CheckmarkRegular /> : <CopyRegular />}
                                    onClick={handleCopy}
                                >
                                    {copied ? t.smtp.copied : t.smtp.copyError}
                                </Button>
                            </div>
                            <pre className={styles.codeBox} tabIndex={0}>
                                {rawResponse || '(Aucune réponse)'}
                            </pre>
                        </div>

                        {/* Send real test email section */}
                        <div className={styles.section}>
                            <span className={styles.codeTitle}>{t.smtp.sendTestEmail}</span>
                            <div className={styles.testEmailRow}>
                                <Field
                                    className={styles.testEmailInput}
                                    label={t.smtp.testEmailAddress}
                                >
                                    <Input
                                        type="email"
                                        placeholder={t.smtp.testEmailPlaceholder}
                                        value={testEmail}
                                        onChange={(_, data) => setTestEmail(data.value)}
                                        disabled={sendingEmail}
                                    />
                                </Field>
                                <Button
                                    appearance="secondary"
                                    icon={sendingEmail ? <Spinner size="tiny" /> : <SendRegular />}
                                    disabled={sendingEmail || !testEmail.trim()}
                                    onClick={handleSendTestEmail}
                                >
                                    {sendingEmail ? t.smtp.testEmailSending : t.smtp.testEmailSendButton}
                                </Button>
                            </div>

                            {emailSendResult && (
                                <div style={{ marginTop: tokens.spacingVerticalS }}>
                                    <MessageBar intent={emailSendResult.success ? 'success' : 'error'}>
                                        <MessageBarBody>
                                            <MessageBarTitle>
                                                {emailSendResult.success
                                                    ? t.smtp.testEmailSuccess
                                                    : t.smtp.testEmailFailure}
                                            </MessageBarTitle>
                                            <div
                                                style={{
                                                    fontFamily: tokens.fontFamilyMonospace,
                                                    fontSize: tokens.fontSizeBase200,
                                                    marginTop: tokens.spacingVerticalXXS,
                                                }}
                                            >
                                                {emailSendResult.message}
                                            </div>
                                        </MessageBarBody>
                                    </MessageBar>
                                </div>
                            )}
                        </div>
                    </DialogContent>

                    <DialogActions>
                        {onRetry && (
                            <Button
                                appearance="secondary"
                                icon={isRetrying ? <Spinner size="tiny" /> : <ArrowClockwiseRegular />}
                                disabled={isRetrying}
                                onClick={onRetry}
                            >
                                {isRetrying ? t.smtp.testing : t.smtp.retryTest}
                            </Button>
                        )}
                        <DialogTrigger disableButtonEnhancement>
                            <Button appearance="primary">{t.common.close}</Button>
                        </DialogTrigger>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
