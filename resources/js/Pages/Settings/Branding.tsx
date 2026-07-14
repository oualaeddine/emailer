import { FormEvent, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Button,
    Card,
    Dropdown,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Option,
    Title1,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { fetchSettings, updateSettings } from '@/Lib/api/settings';

const useStyles = makeStyles({
    card: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        padding: tokens.spacingVerticalXL,
        maxWidth: '480px',
    },
});

/**
 * docs/07-ui-design.md §7.11 — Theming & Branding.
 * docs/26-rbac.md §26.3 — gated by `settings.branding_only` (or full
 * `settings.manage`), enforced server-side by SettingsController/UpdateSettingsRequest.
 */
export default function Branding() {
    const styles = useStyles();
    const t = useText();

    const [organizationName, setOrganizationName] = useState('');
    const [brandColor, setBrandColor] = useState('#7740D6');
    const [defaultTheme, setDefaultTheme] = useState('light');
    const [saved, setSaved] = useState(false);

    useEffect(() => {
        void fetchSettings().then((settings) => {
            for (const setting of settings) {
                if (setting.key === 'branding.organization_name' && setting.value) {
                    setOrganizationName(setting.value);
                }
                if (setting.key === 'branding.brand_color' && setting.value) {
                    setBrandColor(setting.value);
                }
                if (setting.key === 'branding.default_theme' && setting.value) {
                    setDefaultTheme(setting.value);
                }
            }
        });
    }, []);

    async function handleSubmit(event: FormEvent) {
        event.preventDefault();
        setSaved(false);
        await updateSettings({
            'branding.organization_name': organizationName,
            'branding.brand_color': brandColor,
            'branding.default_theme': defaultTheme,
        });
        setSaved(true);
    }

    return (
        <AppShell>
            <Head title={t.settings.title} />
            <Title1>{t.settings.title}</Title1>
            <Card className={styles.card}>
                {saved && (
                    <MessageBar intent="success">
                        <MessageBarBody>{t.settings.saved}</MessageBarBody>
                    </MessageBar>
                )}
                <form onSubmit={handleSubmit}>
                    <Field label={t.settings.organizationName}>
                        <Input
                            value={organizationName}
                            onChange={(_, data) => setOrganizationName(data.value)}
                        />
                    </Field>
                    <Field label={t.settings.brandColor}>
                        <Input
                            value={brandColor}
                            onChange={(_, data) => setBrandColor(data.value)}
                            contentAfter={
                                <input
                                    type="color"
                                    value={brandColor}
                                    onChange={(event) => setBrandColor(event.target.value)}
                                    style={{ width: '28px', height: '28px', border: 'none', padding: 0 }}
                                    aria-label={t.settings.brandColor}
                                />
                            }
                        />
                    </Field>
                    <Field label={t.settings.defaultTheme}>
                        <Dropdown
                            value={
                                defaultTheme === 'dark'
                                    ? t.settings.dark
                                    : defaultTheme === 'system'
                                      ? t.settings.system
                                      : t.settings.light
                            }
                            onOptionSelect={(_, data) => setDefaultTheme(data.optionValue ?? 'light')}
                        >
                            <Option value="light">{t.settings.light}</Option>
                            <Option value="dark">{t.settings.dark}</Option>
                            <Option value="system">{t.settings.system}</Option>
                        </Dropdown>
                    </Field>
                    <Button appearance="primary" type="submit">
                        {t.common.save}
                    </Button>
                </form>
            </Card>
        </AppShell>
    );
}
