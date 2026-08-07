import { FormEvent } from 'react';
import { Head, useForm } from '@inertiajs/react';
import {
    Button,
    Card,
    CardHeader,
    Checkbox,
    Field,
    Input,
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
    submit: {
        width: '100%',
        marginTop: tokens.spacingVerticalXS,
    },
});

interface LoginForm {
    email: string;
    password: string;
    remember: boolean;
}

export default function Login() {
    const styles = useStyles();
    const t = useText();
    const { data, setData, post, processing, errors } = useForm<LoginForm>({
        email: '',
        password: '',
        remember: false,
    });

    function handleSubmit(event: FormEvent) {
        event.preventDefault();
        post('/login');
    }

    return (
        <div className={styles.page}>
            <Head title={t.auth.loginTitle} />
            <span className={styles.wordmark}>
                <BrandMark size={36} />
                PageJaunes Mailer
            </span>
            <Card className={styles.card}>
                <CardHeader header={<Title2>{t.auth.loginTitle}</Title2>} />
                <form className={styles.form} onSubmit={handleSubmit}>
                    <Field
                        label={t.auth.email}
                        validationState={errors.email ? 'error' : 'none'}
                        validationMessage={errors.email}
                        required
                    >
                        <Input
                            type="email"
                            autoComplete="username"
                            value={data.email}
                            onChange={(_, value) => setData('email', value.value)}
                        />
                    </Field>
                    <Field
                        label={t.auth.password}
                        validationState={errors.password ? 'error' : 'none'}
                        validationMessage={errors.password}
                        required
                    >
                        <Input
                            type="password"
                            autoComplete="current-password"
                            value={data.password}
                            onChange={(_, value) => setData('password', value.value)}
                        />
                    </Field>
                    <Checkbox
                        label={t.auth.rememberMe}
                        checked={data.remember}
                        onChange={(_, value) => setData('remember', value.checked === true)}
                    />
                    <Button appearance="primary" type="submit" disabled={processing} className={styles.submit}>
                        {t.auth.submit}
                    </Button>
                </form>
            </Card>
        </div>
    );
}
