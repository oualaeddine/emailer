import { FormEvent, useState } from 'react';
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
    Dropdown,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    MessageBarTitle,
    Option,
    Spinner,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import {
    ChevronDownRegular,
    ChevronUpRegular,
    CheckmarkCircleRegular,
    DismissCircleRegular,
    InfoRegular,
    PlugConnectedRegular,
    WrenchRegular,
} from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { HelpButton } from '@/Components/Help/HelpButton';
import { testSmtpConfiguration, type CreateSmtpAccountPayload } from '@/Lib/api/smtp';
import { analyzeSmtpError, type QuickFixAction, type SmtpDiagnostic } from '@/Lib/smtp/diagnostics';

const useStyles = makeStyles({
    surface: {
        maxWidth: '620px',
        width: '100%',
    },
    dialogContent: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    diagnosticBox: {
        marginTop: tokens.spacingVerticalM,
        marginBottom: tokens.spacingVerticalS,
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    diagnosticSection: {
        marginTop: tokens.spacingVerticalXXS,
        fontSize: tokens.fontSizeBase300,
        lineHeight: tokens.lineHeightBase300,
    },
    quickFixRow: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
        marginTop: tokens.spacingVerticalS,
    },
    rawCodeToggle: {
        marginTop: tokens.spacingVerticalXS,
    },
    rawCode: {
        backgroundColor: tokens.colorNeutralBackground3,
        border: `1px solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalM}`,
        fontFamily: tokens.fontFamilyMonospace,
        fontSize: tokens.fontSizeBase200,
        whiteSpace: 'pre-wrap',
        wordBreak: 'break-word',
        maxHeight: '140px',
        overflowY: 'auto',
        color: tokens.colorNeutralForeground1,
        marginTop: tokens.spacingVerticalXS,
    },
    tipsList: {
        margin: `${tokens.spacingVerticalXXS} 0 0 0`,
        paddingLeft: tokens.spacingHorizontalL,
        fontSize: tokens.fontSizeBase200,
        color: tokens.colorNeutralForeground2,
    },
});

interface SmtpAccountFormDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSubmit: (values: CreateSmtpAccountPayload) => Promise<void>;
}

interface TestState {
    status: 'idle' | 'success' | 'error';
    rawResponse?: string;
    diagnostic?: SmtpDiagnostic;
    appliedNotice?: string;
}

/**
 * docs/18-smtp-management.md §18.2 — SMTP Account Form Fields.
 */
export function SmtpAccountFormDialog({ open, onOpenChange, onSubmit }: SmtpAccountFormDialogProps) {
    const styles = useStyles();
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
    const [testing, setTesting] = useState(false);
    const [testState, setTestState] = useState<TestState>({ status: 'idle' });
    const [showRawDetails, setShowRawDetails] = useState(false);

    function handleFieldChange<K extends keyof CreateSmtpAccountPayload>(key: K, value: CreateSmtpAccountPayload[K]) {
        setValues((v) => ({ ...v, [key]: value }));
        // If connection fields change, clear previous test result so user knows to re-test
        if (['host', 'port', 'encryption', 'username', 'password'].includes(key)) {
            if (testState.status !== 'idle') {
                setTestState({ status: 'idle' });
            }
        }
    }

    async function handleTestBeforeSave() {
        if (!values.host.trim() || !values.username.trim() || !values.password.trim() || !values.from_email.trim()) {
            setTestState({
                status: 'error',
                rawResponse: t.smtp.testBeforeSaveFillRequired,
                diagnostic: {
                    category: 'generic',
                    title: 'Champs requis manquants',
                    cause: 'Les paramètres de connexion sont incomplets.',
                    solution: t.smtp.testBeforeSaveFillRequired,
                    tips: ['L’Hôte, le Port, les Identifiants et l’E-mail d’expédition sont nécessaires pour tester la connexion.'],
                },
            });
            return;
        }

        setTesting(true);
        setTestState({ status: 'idle' });
        try {
            const result = await testSmtpConfiguration({
                name: values.name,
                provider: values.provider,
                host: values.host.trim(),
                port: Number(values.port),
                encryption: values.encryption,
                username: values.username.trim(),
                password: values.password,
                from_email: values.from_email.trim(),
                from_name: values.from_name,
            });

            if (result.success) {
                setTestState({
                    status: 'success',
                    rawResponse: result.raw_response,
                });
            } else {
                const diagnostic = analyzeSmtpError(result.raw_response, {
                    host: values.host,
                    port: Number(values.port),
                    encryption: values.encryption,
                });
                setTestState({
                    status: 'error',
                    rawResponse: result.raw_response,
                    diagnostic,
                });
            }
        } catch (error: any) {
            const errorMsg =
                error?.response?.data?.message || error?.message || 'Erreur réseau de communication avec le serveur.';
            const diagnostic = analyzeSmtpError(errorMsg, {
                host: values.host,
                port: Number(values.port),
                encryption: values.encryption,
            });
            setTestState({
                status: 'error',
                rawResponse: errorMsg,
                diagnostic,
            });
        } finally {
            setTesting(false);
        }
    }

    function applyQuickFix(fix: QuickFixAction) {
        setValues((v) => ({
            ...v,
            ...(fix.patch.port ? { port: fix.patch.port } : {}),
            ...(fix.patch.encryption ? { encryption: fix.patch.encryption } : {}),
            ...(fix.patch.host ? { host: fix.patch.host } : {}),
        }));

        setTestState((prev) => ({
            ...prev,
            appliedNotice: `${t.smtp.quickFixApplied} (${fix.label})`,
        }));
    }

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
        <Dialog
            open={open}
            onOpenChange={(_, data) => {
                if (!data.open) {
                    setTestState({ status: 'idle' });
                    setShowRawDetails(false);
                }
                onOpenChange(data.open);
            }}
        >
            <DialogSurface className={styles.surface}>
                <form onSubmit={handleSubmit}>
                    <DialogBody>
                        <DialogTitle action={<HelpButton topic="dialog-smtp-account-form" />}>
                            {t.smtp.createTitle}
                        </DialogTitle>
                        <DialogContent className={styles.dialogContent}>
                            <Field label={t.smtp.name} required>
                                <Input value={values.name} onChange={(_, d) => handleFieldChange('name', d.value)} />
                            </Field>
                            <Field label={t.smtp.provider}>
                                <Input
                                    value={values.provider}
                                    onChange={(_, d) => handleFieldChange('provider', d.value)}
                                />
                            </Field>
                            <Field label={t.smtp.host} required>
                                <Input value={values.host} onChange={(_, d) => handleFieldChange('host', d.value)} />
                            </Field>
                            <Field label={t.smtp.port} required>
                                <Input
                                    type="number"
                                    value={String(values.port)}
                                    onChange={(_, d) => handleFieldChange('port', Number(d.value))}
                                />
                            </Field>
                            <Field label={t.smtp.encryption}>
                                <Dropdown
                                    value={values.encryption}
                                    onOptionSelect={(_, d) =>
                                        handleFieldChange('encryption', (d.optionValue as 'tls' | 'ssl' | 'none') ?? 'tls')
                                    }
                                >
                                    <Option value="none">none</Option>
                                    <Option value="ssl">ssl</Option>
                                    <Option value="tls">tls</Option>
                                </Dropdown>
                            </Field>
                            <Field label={t.smtp.username} required>
                                <Input
                                    value={values.username}
                                    onChange={(_, d) => handleFieldChange('username', d.value)}
                                />
                            </Field>
                            <Field label={t.smtp.password} required>
                                <Input
                                    type="password"
                                    value={values.password}
                                    onChange={(_, d) => handleFieldChange('password', d.value)}
                                />
                            </Field>
                            <Field label={t.smtp.fromEmail} required>
                                <Input
                                    type="email"
                                    value={values.from_email}
                                    onChange={(_, d) => handleFieldChange('from_email', d.value)}
                                />
                            </Field>
                            <Field label={t.smtp.dailyQuota}>
                                <Input
                                    type="number"
                                    value={values.daily_quota ? String(values.daily_quota) : ''}
                                    onChange={(_, d) =>
                                        handleFieldChange('daily_quota', d.value ? Number(d.value) : undefined)
                                    }
                                />
                            </Field>

                            {/* Test Before Save Feedback Section */}
                            {testState.status === 'success' && (
                                <div className={styles.diagnosticBox}>
                                    <MessageBar intent="success" icon={<CheckmarkCircleRegular />}>
                                        <MessageBarBody>
                                            <MessageBarTitle>{t.smtp.testSuccess}</MessageBarTitle>
                                            {t.smtp.testBeforeSaveSuccess}
                                        </MessageBarBody>
                                    </MessageBar>
                                </div>
                            )}

                            {testState.status === 'error' && (
                                <div className={styles.diagnosticBox}>
                                    <MessageBar intent="error" icon={<DismissCircleRegular />}>
                                        <MessageBarBody>
                                            <MessageBarTitle>
                                                {testState.diagnostic?.title || t.smtp.testFailure}
                                            </MessageBarTitle>

                                            {testState.diagnostic && (
                                                <div className={styles.diagnosticSection}>
                                                    <div>
                                                        <strong>{t.smtp.causeIdentified} :</strong>{' '}
                                                        {testState.diagnostic.cause}
                                                    </div>
                                                    <div style={{ marginTop: tokens.spacingVerticalXS }}>
                                                        <strong>{t.smtp.recommendedSolution} :</strong>{' '}
                                                        {testState.diagnostic.solution}
                                                    </div>

                                                    {/* Quick fixes */}
                                                    {testState.diagnostic.quickFixes &&
                                                        testState.diagnostic.quickFixes.length > 0 && (
                                                            <div className={styles.quickFixRow}>
                                                                {testState.diagnostic.quickFixes.map((fix, idx) => (
                                                                    <Button
                                                                        key={idx}
                                                                        size="small"
                                                                        appearance="primary"
                                                                        icon={<WrenchRegular />}
                                                                        onClick={() => applyQuickFix(fix)}
                                                                    >
                                                                        {fix.label}
                                                                    </Button>
                                                                ))}
                                                            </div>
                                                        )}

                                                    {/* Quick fix applied notice */}
                                                    {testState.appliedNotice && (
                                                        <div style={{ marginTop: tokens.spacingVerticalXS }}>
                                                            <Badge appearance="tint" color="success">
                                                                {testState.appliedNotice}
                                                            </Badge>
                                                        </div>
                                                    )}

                                                    {/* Tips list */}
                                                    {testState.diagnostic.tips &&
                                                        testState.diagnostic.tips.length > 0 && (
                                                            <div style={{ marginTop: tokens.spacingVerticalS }}>
                                                                <ul className={styles.tipsList}>
                                                                    {testState.diagnostic.tips.map((tip, i) => (
                                                                        <li key={i}>{tip}</li>
                                                                    ))}
                                                                </ul>
                                                            </div>
                                                        )}
                                                </div>
                                            )}

                                            {/* Collapsible raw response */}
                                            <div className={styles.rawCodeToggle}>
                                                <Button
                                                    size="small"
                                                    appearance="transparent"
                                                    icon={
                                                        showRawDetails ? (
                                                            <ChevronUpRegular />
                                                        ) : (
                                                            <ChevronDownRegular />
                                                        )
                                                    }
                                                    onClick={() => setShowRawDetails((prev) => !prev)}
                                                >
                                                    {showRawDetails
                                                        ? 'Masquer les détails bruts'
                                                        : 'Afficher les détails bruts du serveur'}
                                                </Button>
                                                {showRawDetails && testState.rawResponse && (
                                                    <pre className={styles.rawCode} tabIndex={0}>
                                                        {testState.rawResponse}
                                                    </pre>
                                                )}
                                            </div>
                                        </MessageBarBody>
                                    </MessageBar>
                                </div>
                            )}
                        </DialogContent>
                        <DialogActions>
                            <DialogTrigger disableButtonEnhancement>
                                <Button appearance="secondary" disabled={submitting || testing}>
                                    {t.common.cancel}
                                </Button>
                            </DialogTrigger>
                            <Button
                                appearance="secondary"
                                icon={testing ? <Spinner size="tiny" /> : <PlugConnectedRegular />}
                                disabled={testing || submitting}
                                onClick={handleTestBeforeSave}
                            >
                                {testing ? t.smtp.testing : t.smtp.testBeforeSave}
                            </Button>
                            <Button appearance="primary" type="submit" disabled={submitting || testing}>
                                {t.common.save}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
