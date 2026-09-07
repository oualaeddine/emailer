import { FormEvent, useState } from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    CardHeader,
    Field,
    Input,
    Body1,
    Link,
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
        width: '360px',
        maxWidth: '100%',
        boxSizing: 'border-box',
        padding: tokens.spacingVerticalXL,
        boxShadow: tokens.shadow16,
    },
    form: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        marginTop: tokens.spacingVerticalM,
    },
    intro: {
        color: tokens.colorNeutralForeground2,
    },
    submit: {
        width: '100%',
        marginTop: tokens.spacingVerticalXS,
    },
    toggle: {
        textAlign: 'center',
    },
});

interface ChallengeForm {
    code: string;
    recovery_code: string;
}

export default function TwoFactorChallenge() {
    const styles = useStyles();
    const t = useText();
    const [useRecovery, setUseRecovery] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<ChallengeForm>({
        code: '',
        recovery_code: '',
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/two-factor/challenge');
    }

    function toggleMode() {
        reset();
        setUseRecovery((value) => !value);
    }

    return (
        <div className={styles.page}>
            <Head title={t.auth.twoFactor.challengeTitle} />
            <span className={styles.wordmark}>
                <BrandMark size={36} />
                PageJaunes Mailer
            </span>
            <Card className={styles.card}>
                <CardHeader header={<Title2>{t.auth.twoFactor.challengeTitle}</Title2>} />
                <Body1 className={styles.intro}>
                    {useRecovery ? t.auth.twoFactor.recoveryIntro : t.auth.twoFactor.challengeIntro}
                </Body1>
                <form className={styles.form} onSubmit={handleSubmit}>
                    {useRecovery ? (
                        <Field
                            label={t.auth.twoFactor.recoveryCode}
                            validationState={errors.recovery_code ? 'error' : 'none'}
                            validationMessage={errors.recovery_code}
                            required
                        >
                            <Input
                                autoComplete="one-time-code"
                                value={data.recovery_code}
                                onChange={(_, value) => setData('recovery_code', value.value)}
                            />
                        </Field>
                    ) : (
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
                    )}
                    <Button appearance="primary" type="submit" disabled={processing} className={styles.submit}>
                        {t.auth.twoFactor.verify}
                    </Button>
                    <div className={styles.toggle}>
                        <Link as="button" type="button" onClick={toggleMode}>
                            {useRecovery
                                ? t.auth.twoFactor.useAuthenticator
                                : t.auth.twoFactor.useRecovery}
                        </Link>
                    </div>
                </form>
            </Card>
        </div>
    );
}
