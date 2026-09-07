import { FormEvent } from 'react';
import { Head, router, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    CardHeader,
    Field,
    Input,
    Body1,
    Caption1,
    Title2,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { BrandMark } from '@/Components/Shell/BrandMark';

const useStyles = makeStyles({
    page: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        gap: tokens.spacingVerticalXL,
        minHeight: '100vh',
        padding: tokens.spacingHorizontalM,
        boxSizing: 'border-box',
        backgroundImage: `radial-gradient(circle at 50% 0%, ${tokens.colorBrandBackground2} 0%, ${tokens.colorNeutralBackground3} 55%)`,
    },
    wordmark: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        fontWeight: tokens.fontWeightSemibold,
        fontSize: tokens.fontSizeBase500,
        color: tokens.colorNeutralForeground1,
    },
    card: {
        width: '420px',
        maxWidth: '100%',
        boxSizing: 'border-box',
        padding: tokens.spacingVerticalXL,
        boxShadow: tokens.shadow16,
    },
    body: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        marginTop: tokens.spacingVerticalM,
    },
    intro: {
        color: tokens.colorNeutralForeground2,
    },
    qr: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
    },
    secret: {
        fontFamily: tokens.fontFamilyMonospace,
        wordBreak: 'break-all',
        textAlign: 'center',
        color: tokens.colorNeutralForeground1,
    },
    codes: {
        display: 'grid',
        gridTemplateColumns: '1fr 1fr',
        gap: tokens.spacingVerticalXS,
        padding: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground3,
        borderRadius: tokens.borderRadiusMedium,
        fontFamily: tokens.fontFamilyMonospace,
    },
    submit: {
        width: '100%',
    },
});

interface SetupProps {
    confirmed: boolean;
    secret?: string;
    qrSvg?: string;
    recoveryCodes?: string[];
}

interface ConfirmForm {
    code: string;
}

export default function TwoFactorSetup({ confirmed, secret, qrSvg, recoveryCodes }: SetupProps) {
    const styles = useStyles();
    const t = useText();
    const { data, setData, post, processing, errors } = useForm<ConfirmForm>({ code: '' });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/two-factor/setup');
    }

    const showRecoveryCodes = confirmed && recoveryCodes && recoveryCodes.length > 0;

    return (
        <div className={styles.page}>
            <Head title={t.auth.twoFactor.setupTitle} />
            <span className={styles.wordmark}>
                <BrandMark size={36} />
                PageJaunes Mailer
            </span>
            <Card className={styles.card}>
                {showRecoveryCodes ? (
                    <>
                        <CardHeader header={<Title2>{t.auth.twoFactor.recoveryCodesTitle}</Title2>} />
                        <div className={styles.body}>
                            <Body1 className={styles.intro}>{t.auth.twoFactor.recoveryCodesIntro}</Body1>
                            <div className={styles.codes}>
                                {recoveryCodes!.map((code) => (
                                    <span key={code}>{code}</span>
                                ))}
                            </div>
                            <Button
                                appearance="primary"
                                className={styles.submit}
                                onClick={() => router.visit('/dashboard')}
                            >
                                {t.auth.twoFactor.recoveryCodesContinue}
                            </Button>
                        </div>
                    </>
                ) : (
                    <>
                        <CardHeader header={<Title2>{t.auth.twoFactor.setupTitle}</Title2>} />
                        <div className={styles.body}>
                            <Body1 className={styles.intro}>{t.auth.twoFactor.setupIntro}</Body1>
                            {qrSvg && (
                                <div
                                    className={styles.qr}
                                    // The SVG is generated server-side by bacon/bacon-qr-code
                                    // from the otpauth URI — no user-supplied content.
                                    dangerouslySetInnerHTML={{ __html: qrSvg }}
                                />
                            )}
                            <Caption1 className={styles.intro}>{t.auth.twoFactor.manualKey}</Caption1>
                            <div className={styles.secret}>{secret}</div>
                            <form className={styles.body} onSubmit={handleSubmit}>
                                <Field
                                    label={t.auth.twoFactor.code}
                                    validationState={errors.code ? 'error' : 'none'}
                                    validationMessage={errors.code}
                                    required
                                >
                                    <Input
                                        autoComplete="one-time-code"
                                        inputMode="numeric"
                                        value={data.code}
                                        onChange={(_, value) => setData('code', value.value)}
                                    />
                                </Field>
                                <Button
                                    appearance="primary"
                                    type="submit"
                                    disabled={processing}
                                    className={styles.submit}
                                >
                                    {t.auth.twoFactor.confirm}
                                </Button>
                            </form>
                        </div>
                    </>
                )}
            </Card>
        </div>
    );
}
