import { useEffect, useState, type FormEvent } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Dropdown,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Option,
    Spinner,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import {
    apiErrorMessage,
    apiValidationErrors,
    fetchCaptchaSettings,
    updateCaptchaSettings,
    type CaptchaProvider,
    type CaptchaSettings,
} from '@/Lib/api/forms';

const PROVIDERS: CaptchaProvider[] = ['none', 'hcaptcha', 'turnstile'];

const useStyles = makeStyles({
    body: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
    muted: { color: tokens.colorNeutralForeground3 },
});

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onSaved: (message: string) => void;
}

/**
 * *Captcha des formulaires* — provider, site key and write-only secret of the public forms'
 * captcha (`GET|PUT /api/v1/forms/captcha-settings`, `settings.manage`). The saved secret is
 * never shown: only « Clé secrète enregistrée » and a field to replace it.
 */
export function CaptchaSettingsDialog({ open, onOpenChange, onSaved }: Props) {
    const styles = useStyles();
    const t = useText();
    const c = t.forms.captchaSettings;
    const [loading, setLoading] = useState(false);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [fieldErrors, setFieldErrors] = useState<Record<string, string>>({});
    const [current, setCurrent] = useState<CaptchaSettings | null>(null);
    const [provider, setProvider] = useState<CaptchaProvider>('none');
    const [siteKey, setSiteKey] = useState('');
    const [secret, setSecret] = useState('');
    const [replacing, setReplacing] = useState(false);

    useEffect(() => {
        if (!open) return;
        let cancelled = false;
        setLoading(true);
        setError(null);
        setFieldErrors({});
        setSecret('');
        setReplacing(false);
        fetchCaptchaSettings()
            .then((settings) => {
                if (cancelled) return;
                setCurrent(settings);
                setProvider(settings.provider);
                setSiteKey(settings.site_key);
            })
            .catch((e) => {
                if (!cancelled) setError(apiErrorMessage(e, t.common.genericError));
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [open, t]);

    const hasSecret = current?.has_secret === true;
    const showSecretField = provider !== 'none' && (!hasSecret || replacing);

    async function submit(event: FormEvent) {
        event.preventDefault();
        if (saving || loading || current === null) return;
        setSaving(true);
        setError(null);
        setFieldErrors({});
        try {
            const saved = await updateCaptchaSettings({ provider, site_key: siteKey.trim(), secret: secret.trim() });
            setCurrent(saved);
            onSaved(c.saved);
            onOpenChange(false);
        } catch (e) {
            const validation = apiValidationErrors(e);
            if (validation) {
                setFieldErrors(validation);
            } else {
                setError(apiErrorMessage(e, t.common.genericError));
            }
        } finally {
            setSaving(false);
        }
    }

    const errorFor = (key: string) =>
        fieldErrors[key] ? { validationState: 'error' as const, validationMessage: fieldErrors[key] } : {};

    return (
        <Dialog open={open} onOpenChange={(_, d) => onOpenChange(d.open)}>
            <DialogSurface>
                <form onSubmit={submit}>
                    <DialogBody>
                        <DialogTitle>{c.title}</DialogTitle>
                        <DialogContent className={styles.body}>
                            <Text size={200} className={styles.muted}>
                                {c.intro}
                            </Text>
                            {error && (
                                <MessageBar intent="error">
                                    <MessageBarBody>{error}</MessageBarBody>
                                </MessageBar>
                            )}
                            {loading ? (
                                <Spinner label={t.common.loading} />
                            ) : (
                                <>
                                    <Field label={c.provider} {...errorFor('provider')}>
                                        <Dropdown
                                            value={c.providers[provider]}
                                            selectedOptions={[provider]}
                                            onOptionSelect={(_, d) => d.optionValue && setProvider(d.optionValue as CaptchaProvider)}
                                        >
                                            {PROVIDERS.map((p) => (
                                                <Option key={p} value={p}>
                                                    {c.providers[p]}
                                                </Option>
                                            ))}
                                        </Dropdown>
                                    </Field>
                                    {provider !== 'none' && (
                                        <Field label={c.siteKey} required {...errorFor('site_key')}>
                                            <Input value={siteKey} maxLength={255} onChange={(_, d) => setSiteKey(d.value)} />
                                        </Field>
                                    )}
                                    {provider !== 'none' && hasSecret && !replacing && (
                                        <Field label={c.secret} hint={c.secretSavedHint}>
                                            <div style={{ display: 'flex', alignItems: 'center', gap: tokens.spacingHorizontalS }}>
                                                <Text weight="semibold">{c.secretSaved}</Text>
                                                <Button type="button" size="small" onClick={() => setReplacing(true)}>
                                                    {c.replaceSecret}
                                                </Button>
                                            </div>
                                        </Field>
                                    )}
                                    {showSecretField && (
                                        <Field label={c.secret} required {...errorFor('secret')}>
                                            <Input
                                                type="password"
                                                autoComplete="new-password"
                                                value={secret}
                                                maxLength={255}
                                                onChange={(_, d) => setSecret(d.value)}
                                            />
                                        </Field>
                                    )}
                                </>
                            )}
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" type="button" onClick={() => onOpenChange(false)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" type="submit" disabled={saving || loading || current === null}>
                                {saving ? <Spinner size="tiny" /> : t.common.save}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}
