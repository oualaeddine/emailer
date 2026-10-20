import { useCallback, useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    Button,
    Checkbox,
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
    Tab,
    TabList,
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableHeaderCell,
    TableRow,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import type { SelectTabData, SelectTabEvent } from '@fluentui/react-components';
import { AddRegular, CopyRegular, KeyRegular } from '@fluentui/react-icons';
import { PageHelp } from '@/Components/Help/PageHelp';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import WebhooksPanel from '@/Components/Transactional/WebhooksPanel';
import { useText } from '@/Hooks/useText';
import { hasPermission } from '@/Lib/permissions';
import {
    apiErrorMessage,
    createApiKey,
    fetchApiKeys,
    fetchTransactionalSettings,
    revokeApiKey,
} from '@/Lib/api/transactional';
import type {
    ApiKey,
    ApiKeyScope,
    TransactionalSettings,
    TransactionalSettingsTab,
} from '@/Lib/types/transactional';

const SCOPES: ApiKeyScope[] = [
    'transactional:send',
    'transactional:read',
    'contacts:read',
    'contacts:write',
    'campaigns:read',
    'statistics:read',
];

const useStyles = makeStyles({
    header: { marginBottom: tokens.spacingVerticalM },
    messages: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS, marginBottom: tokens.spacingVerticalM },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        marginTop: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    toolbar: { display: 'flex', justifyContent: 'flex-end', marginBottom: tokens.spacingVerticalM },
    stack: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
    scopes: { display: 'flex', flexDirection: 'column' },
    token: {
        fontFamily: tokens.fontFamilyMonospace,
        wordBreak: 'break-all',
        padding: tokens.spacingVerticalS,
        backgroundColor: tokens.colorNeutralBackground3,
        borderRadius: tokens.borderRadiusMedium,
    },
    tokenRow: { display: 'flex', alignItems: 'flex-start', gap: tokens.spacingHorizontalS },
});

interface Notice {
    intent: 'success' | 'error';
    text: string;
}

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('fr-FR') : '';
}

/** docs/43-brevo-parity-plan.md §43.4.17 — Transactional/Settings (SMTP · Clés API · Webhooks). */
export default function Settings() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth?: { permissions?: string[] } }>();
    const permissions = props.auth?.permissions ?? [];
    const canView = hasPermission(permissions, 'transactional.view');
    const canManageKeys = hasPermission(permissions, 'api_keys.manage');
    const canManageWebhooks = hasPermission(permissions, 'webhooks.manage');
    const visibleTabs = (
        [
            ['smtp', canView],
            ['api_keys', canManageKeys],
            ['webhooks', canManageWebhooks],
        ] as [TransactionalSettingsTab, boolean][]
    )
        .filter(([, allowed]) => allowed)
        .map(([value]) => value);
    const [tab, setTab] = useState<TransactionalSettingsTab>(visibleTabs[0] ?? 'smtp');
    const [notice, setNotice] = useState<Notice | null>(null);
    const [settings, setSettings] = useState<TransactionalSettings | null>(null);
    const [keys, setKeys] = useState<ApiKey[]>([]);
    const [loading, setLoading] = useState(true);
    const [formOpen, setFormOpen] = useState(false);
    const [name, setName] = useState('');
    const [scopes, setScopes] = useState<ApiKeyScope[]>(['transactional:send']);
    const [saving, setSaving] = useState(false);
    const [plainToken, setPlainToken] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);
    const [revoking, setRevoking] = useState<ApiKey | null>(null);

    const load = useCallback(async () => {
        try {
            const [s, k] = await Promise.allSettled([
                canView ? fetchTransactionalSettings() : Promise.reject(new Error('forbidden')),
                canManageKeys ? fetchApiKeys() : Promise.resolve([] as ApiKey[]),
            ]);
            if (s.status === 'fulfilled') {
                setSettings(s.value);
            }
            if (k.status === 'fulfilled') {
                setKeys(k.value);
            }
        } finally {
            setLoading(false);
        }
    }, [canView, canManageKeys]);

    useEffect(() => {
        void load();
    }, [load]);

    function openForm() {
        setName('');
        setScopes(['transactional:send']);
        setFormOpen(true);
    }

    async function submit() {
        setSaving(true);
        try {
            const created = await createApiKey({ name: name.trim(), scopes });
            setFormOpen(false);
            setPlainToken(created.plain_text_token);
            setCopied(false);
            await load();
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        } finally {
            setSaving(false);
        }
    }

    async function revoke() {
        if (!revoking) {
            return;
        }
        try {
            await revokeApiKey(revoking.id);
            setNotice({ intent: 'success', text: t.apiKeys.revoked });
            await load();
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        } finally {
            setRevoking(null);
        }
    }

    async function copyToken() {
        if (!plainToken) {
            return;
        }
        try {
            await navigator.clipboard.writeText(plainToken);
            setCopied(true);
        } catch {
            setCopied(false);
        }
    }

    function toggleScope(scope: ApiKeyScope, checked: boolean) {
        setScopes((current) => (checked ? [...current, scope] : current.filter((s) => s !== scope)));
    }

    return (
        <AppShell>
            <Head title={t.transactional.settingsTitle} />
            <PageHelp topic="transactional-settings" title={t.transactional.settingsTitle} />

            {notice && (
                <div className={styles.messages}>
                    <MessageBar intent={notice.intent}>
                        <MessageBarBody>{notice.text}</MessageBarBody>
                    </MessageBar>
                </div>
            )}

            {visibleTabs.length === 0 ? (
                <MessageBar intent="warning">
                    <MessageBarBody>{t.common.forbidden}</MessageBarBody>
                </MessageBar>
            ) : (
                <TabList
                    selectedValue={tab}
                    onTabSelect={(_: SelectTabEvent, data: SelectTabData) => setTab(data.value as TransactionalSettingsTab)}
                >
                    {visibleTabs.map((value) => (
                        <Tab key={value} value={value}>
                            {t.transactional.settingsTabs[value]}
                        </Tab>
                    ))}
                </TabList>
            )}

            <div className={styles.card} hidden={visibleTabs.length === 0}>
                {tab === 'smtp' && canView && (
                    <div className={styles.stack}>
                        <Text as="h2" size={500} weight="semibold">
                            {t.transactional.smtpRelay}
                        </Text>
                        <Text>{t.transactional.smtpRelayUnavailable}</Text>
                        {loading ? (
                            <Spinner label={t.common.loading} />
                        ) : (
                            settings && (
                                <>
                                    <Text>
                                        {t.transactional.apiBaseUrl} : <code>{settings.api_base_url}</code>
                                    </Text>
                                    <Text>{t.transactional.rateLimit.replace('%d', String(settings.rate_limit_per_minute))}</Text>
                                    <Text>{t.transactional.sendEndpointHelp}</Text>
                                </>
                            )
                        )}
                    </div>
                )}

                {tab === 'api_keys' && canManageKeys && (
                    <>
                        <div className={styles.toolbar}>
                            <Button appearance="primary" icon={<AddRegular />} onClick={openForm}>
                                {t.apiKeys.createKey}
                            </Button>
                        </div>
                        {loading ? (
                            <Spinner label={t.common.loading} />
                        ) : keys.length === 0 ? (
                            <EmptyState
                                icon={KeyRegular}
                                title={t.apiKeys.emptyTitle}
                                body={t.apiKeys.title}
                                action={
                                    <Button appearance="primary" icon={<AddRegular />} onClick={openForm}>
                                        {t.apiKeys.createKey}
                                    </Button>
                                }
                            />
                        ) : (
                            <Table aria-label={t.apiKeys.title}>
                                <TableHeader>
                                    <TableRow>
                                        <TableHeaderCell>{t.apiKeys.name}</TableHeaderCell>
                                        <TableHeaderCell>{t.apiKeys.prefix}</TableHeaderCell>
                                        <TableHeaderCell>{t.apiKeys.scopes}</TableHeaderCell>
                                        <TableHeaderCell>{t.apiKeys.lastUsed}</TableHeaderCell>
                                        <TableHeaderCell>{t.apiKeys.expiresAt}</TableHeaderCell>
                                        <TableHeaderCell>{t.apiKeys.createdBy}</TableHeaderCell>
                                        <TableHeaderCell>{t.common.actions}</TableHeaderCell>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {keys.map((key) => (
                                        <TableRow key={key.id}>
                                            <TableCell>{key.name}</TableCell>
                                            <TableCell>
                                                <code>{key.token_prefix}…</code>
                                            </TableCell>
                                            <TableCell>{key.scopes.map((s) => t.apiKeys.scopeLabels[s]).join(', ')}</TableCell>
                                            <TableCell>
                                                {key.last_used_at
                                                    ? `${formatDate(key.last_used_at)}${key.last_used_ip ? ` (${key.last_used_ip})` : ''}`
                                                    : t.apiKeys.neverUsed}
                                            </TableCell>
                                            <TableCell>{key.expires_at ? formatDate(key.expires_at) : t.apiKeys.noExpiry}</TableCell>
                                            <TableCell>{key.created_by?.name ?? ''}</TableCell>
                                            <TableCell>
                                                <Button size="small" onClick={() => setRevoking(key)}>
                                                    {t.apiKeys.revoke}
                                                </Button>
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </>
                )}

                {tab === 'webhooks' && canManageWebhooks && <WebhooksPanel />}
            </div>

            <Dialog open={formOpen} onOpenChange={(_, data) => setFormOpen(data.open)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.apiKeys.createTitle}</DialogTitle>
                        <DialogContent className={styles.stack}>
                            <Field label={t.apiKeys.name} required>
                                <Input value={name} maxLength={100} onChange={(_, data) => setName(data.value)} />
                            </Field>
                            <Field label={t.apiKeys.scopes} required>
                                <div className={styles.scopes}>
                                    {SCOPES.map((scope) => (
                                        <Checkbox
                                            key={scope}
                                            label={t.apiKeys.scopeLabels[scope]}
                                            checked={scopes.includes(scope)}
                                            onChange={(_, data) => toggleScope(scope, data.checked === true)}
                                        />
                                    ))}
                                </div>
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setFormOpen(false)}>
                                {t.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                disabled={saving || name.trim() === '' || scopes.length === 0}
                                onClick={() => void submit()}
                            >
                                {t.common.create}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={plainToken !== null} onOpenChange={(_, data) => !data.open && setPlainToken(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.apiKeys.tokenTitle}</DialogTitle>
                        <DialogContent className={styles.stack}>
                            <MessageBar intent="warning">
                                <MessageBarBody>{t.apiKeys.tokenWarning}</MessageBarBody>
                            </MessageBar>
                            <div className={styles.tokenRow}>
                                <div className={styles.token}>{plainToken}</div>
                                <Button icon={<CopyRegular />} onClick={() => void copyToken()}>
                                    {copied ? t.common.copied : t.common.copy}
                                </Button>
                            </div>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="primary" onClick={() => setPlainToken(null)}>
                                {t.common.close}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={revoking !== null} onOpenChange={(_, data) => !data.open && setRevoking(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.apiKeys.revoke}</DialogTitle>
                        <DialogContent>{revoking && t.apiKeys.revokeConfirm.replace('%s', revoking.name)}</DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setRevoking(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" onClick={() => void revoke()}>
                                {t.apiKeys.revoke}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
