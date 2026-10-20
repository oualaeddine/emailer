import { Fragment, useCallback, useEffect, useState } from 'react';
import {
    Badge,
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    DrawerBody,
    DrawerHeader,
    DrawerHeaderTitle,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    OverlayDrawer,
    Select,
    Spinner,
    Switch,
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
import { AddRegular, DeleteRegular, DismissRegular, LinkRegular } from '@fluentui/react-icons';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import {
    apiErrorMessage,
    createWebhook,
    deleteWebhook,
    fetchWebhookDeliveries,
    fetchWebhooks,
    retryWebhookDelivery,
    rotateWebhookSecret,
    testWebhook,
    updateWebhook,
} from '@/Lib/api/webhooks';
import type {
    MarketingWebhookEvent,
    TransactionalWebhookEvent,
    Webhook,
    WebhookDelivery,
    WebhookDeliveryStatus,
    WebhookInput,
    WebhookSubscriptionEvent,
    WebhookType,
} from '@/Lib/types/webhooks';
import { CheckboxGroup } from '@/Components/Common/CheckboxGroup';

/** Subscription identifiers offered per type (docs/45 §45.7.7); `sent` is a synonym of `request`. */
const TRANSACTIONAL_EVENTS: TransactionalWebhookEvent[] = [
    'request',
    'delivered',
    'opened',
    'uniqueOpened',
    'click',
    'softBounce',
    'hardBounce',
    'invalid',
    'deferred',
    'spam',
    'unsubscribed',
    'blocked',
    'error',
    'proxyOpen',
];
const MARKETING_EVENTS: MarketingWebhookEvent[] = [
    'delivered',
    'opened',
    'click',
    'softBounce',
    'hardBounce',
    'spam',
    'unsubscribed',
    'listAddition',
    'contactUpdated',
    'contactDeleted',
];
const DELIVERY_STATUSES: WebhookDeliveryStatus[] = ['pending', 'succeeded', 'failed', 'retrying'];

const STATUS_COLOR: Record<WebhookDeliveryStatus, 'success' | 'danger' | 'warning' | 'informative'> = {
    pending: 'informative',
    succeeded: 'success',
    failed: 'danger',
    retrying: 'warning',
};

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalM,
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
    },
    stack: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
    actions: { display: 'flex', flexWrap: 'wrap', gap: tokens.spacingHorizontalXS },
    events: { display: 'grid', gridTemplateColumns: 'repeat(auto-fill, minmax(220px, 1fr))' },
    headerRow: { display: 'flex', gap: tokens.spacingHorizontalS, alignItems: 'flex-end' },
    secret: {
        fontFamily: tokens.fontFamilyMonospace,
        wordBreak: 'break-all',
        padding: tokens.spacingVerticalS,
        backgroundColor: tokens.colorNeutralBackground3,
        borderRadius: tokens.borderRadiusMedium,
    },
    url: { wordBreak: 'break-all' },
    chips: { display: 'flex', flexWrap: 'wrap', gap: tokens.spacingHorizontalXXS },
    delivery: {
        padding: tokens.spacingVerticalS,
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorNeutralStroke2,
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
    deliveryHead: { display: 'flex', alignItems: 'center', gap: tokens.spacingHorizontalS, flexWrap: 'wrap' },
    payload: {
        fontFamily: tokens.fontFamilyMonospace,
        fontSize: tokens.fontSizeBase200,
        whiteSpace: 'pre-wrap',
        wordBreak: 'break-all',
        backgroundColor: tokens.colorNeutralBackground3,
        padding: tokens.spacingVerticalS,
        borderRadius: tokens.borderRadiusMedium,
        margin: 0,
    },
    footer: { display: 'flex', justifyContent: 'center', padding: tokens.spacingVerticalM },
});

interface Notice {
    intent: 'success' | 'error';
    text: string;
}

interface FormState {
    url: string;
    description: string;
    type: WebhookType;
    events: WebhookSubscriptionEvent[];
    is_active: boolean;
    headers: { name: string; value: string }[];
}

const EMPTY_FORM: FormState = {
    url: '',
    description: '',
    type: 'transactional',
    events: ['delivered'],
    is_active: true,
    headers: [],
};

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('fr-FR') : '—';
}

function eventsFor(type: WebhookType): WebhookSubscriptionEvent[] {
    return type === 'marketing' ? MARKETING_EVENTS : TRANSACTIONAL_EVENTS;
}

/** docs/45 §45.7.7 — outgoing webhooks: list, create/edit, secret (shown once), test, delivery log. */
export default function WebhooksPanel() {
    const styles = useStyles();
    const t = useText();
    const [webhooks, setWebhooks] = useState<Webhook[]>([]);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState<Notice | null>(null);
    const [busyId, setBusyId] = useState<string | null>(null);

    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Webhook | null>(null);
    const [form, setForm] = useState<FormState>(EMPTY_FORM);
    const [formError, setFormError] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    const [secret, setSecret] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);
    const [deleting, setDeleting] = useState<Webhook | null>(null);

    const [drawerFor, setDrawerFor] = useState<Webhook | null>(null);
    const [deliveries, setDeliveries] = useState<WebhookDelivery[]>([]);
    const [deliveriesCursor, setDeliveriesCursor] = useState<string | null>(null);
    const [deliveriesLoading, setDeliveriesLoading] = useState(false);
    const [statusFilter, setStatusFilter] = useState<WebhookDeliveryStatus | ''>('');
    const [expandedDelivery, setExpandedDelivery] = useState<string | null>(null);

    const load = useCallback(async () => {
        try {
            setWebhooks(await fetchWebhooks());
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        } finally {
            setLoading(false);
        }
    }, [t]);

    useEffect(() => {
        void load();
    }, [load]);

    function openForm(webhook: Webhook | null) {
        setEditing(webhook);
        setFormError(null);
        setForm(
            webhook
                ? {
                      url: webhook.url,
                      description: webhook.description ?? '',
                      type: webhook.type,
                      events: webhook.events,
                      is_active: webhook.is_active,
                      headers: Object.entries(webhook.headers ?? {}).map(([name, value]) => ({ name, value })),
                  }
                : EMPTY_FORM,
        );
        setFormOpen(true);
    }

    function toggleEvent(event: WebhookSubscriptionEvent, checked: boolean) {
        setForm((current) => ({
            ...current,
            events: checked ? [...current.events, event] : current.events.filter((e) => e !== event),
        }));
    }

    async function save() {
        setSaving(true);
        setFormError(null);
        const headers = Object.fromEntries(form.headers.filter((h) => h.name.trim() !== '').map((h) => [h.name.trim(), h.value]));
        const payload: WebhookInput = {
            url: form.url.trim(),
            description: form.description.trim() === '' ? null : form.description.trim(),
            type: form.type,
            events: form.events,
            is_active: form.is_active,
            headers: Object.keys(headers).length > 0 ? headers : null,
        };
        try {
            if (editing) {
                await updateWebhook(editing.id, payload);
            } else {
                const created = await createWebhook(payload);
                setSecret(created.secret);
                setCopied(false);
            }
            setFormOpen(false);
            await load();
        } catch (e) {
            setFormError(apiErrorMessage(e, t.common.genericError));
        } finally {
            setSaving(false);
        }
    }

    async function run(webhook: Webhook, action: () => Promise<void>) {
        setBusyId(webhook.id);
        try {
            await action();
            await load();
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        } finally {
            setBusyId(null);
        }
    }

    async function sendTest(webhook: Webhook) {
        const event = webhook.events[0];
        if (!event) {
            return;
        }
        await run(webhook, async () => {
            const delivery = await testWebhook(webhook.id, event);
            setNotice(
                delivery.status === 'succeeded'
                    ? { intent: 'success', text: t.webhooks.testSent }
                    : {
                          intent: 'error',
                          text: `${t.webhooks.deliveryStatus[delivery.status]} — ${t.webhooks.responseStatus} : ${delivery.response_status ?? '—'}`,
                      },
            );
        });
    }

    const loadDeliveries = useCallback(
        async (webhook: Webhook, status: WebhookDeliveryStatus | '', cursor?: string) => {
            setDeliveriesLoading(true);
            try {
                const page = await fetchWebhookDeliveries(webhook.id, {
                    ...(status ? { status } : {}),
                    ...(cursor ? { cursor } : {}),
                });
                setDeliveries((current) => (cursor ? [...current, ...page.data] : page.data));
                setDeliveriesCursor(page.meta.next_cursor);
            } catch (e) {
                setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
            } finally {
                setDeliveriesLoading(false);
            }
        },
        [t],
    );

    function openDeliveries(webhook: Webhook) {
        setDrawerFor(webhook);
        setDeliveries([]);
        setStatusFilter('');
        setExpandedDelivery(null);
        void loadDeliveries(webhook, '');
    }

    async function redeliver(delivery: WebhookDelivery) {
        try {
            await retryWebhookDelivery(delivery.id);
            if (drawerFor) {
                await loadDeliveries(drawerFor, statusFilter);
            }
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        }
    }

    async function copySecret() {
        try {
            await navigator.clipboard.writeText(secret ?? '');
            setCopied(true);
        } catch {
            setCopied(false);
        }
    }

    const available = eventsFor(form.type);

    return (
        <div className={styles.stack}>
            <div className={styles.header}>
                <Text as="h2" size={500} weight="semibold">
                    {t.webhooks.title}
                </Text>
                <Button appearance="primary" icon={<AddRegular />} onClick={() => openForm(null)}>
                    {t.webhooks.addWebhook}
                </Button>
            </div>
            <Text size={200}>{t.webhooks.signatureHelp}</Text>

            {notice && (
                <MessageBar intent={notice.intent}>
                    <MessageBarBody>{notice.text}</MessageBarBody>
                </MessageBar>
            )}

            {loading ? (
                <Spinner label={t.common.loading} />
            ) : webhooks.length === 0 ? (
                <EmptyState
                    icon={LinkRegular}
                    title={t.webhooks.emptyTitle}
                    action={
                        <Button appearance="primary" icon={<AddRegular />} onClick={() => openForm(null)}>
                            {t.webhooks.addWebhook}
                        </Button>
                    }
                />
            ) : (
                <Table aria-label={t.webhooks.title}>
                    <TableHeader>
                        <TableRow>
                            <TableHeaderCell>{t.webhooks.url}</TableHeaderCell>
                            <TableHeaderCell>{t.webhooks.type}</TableHeaderCell>
                            <TableHeaderCell>{t.webhooks.eventsLabel}</TableHeaderCell>
                            <TableHeaderCell>{t.common.status}</TableHeaderCell>
                            <TableHeaderCell>{t.webhooks.deliveries}</TableHeaderCell>
                            <TableHeaderCell>{t.common.actions}</TableHeaderCell>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {webhooks.map((w) => (
                            <TableRow key={w.id}>
                                <TableCell>
                                    <Text className={styles.url} block>
                                        {w.url}
                                    </Text>
                                    {w.description && <Text size={200}>{w.description}</Text>}
                                </TableCell>
                                <TableCell>{t.webhooks.types[w.type]}</TableCell>
                                <TableCell>
                                    <div className={styles.chips}>
                                        {w.events.map((e) => (
                                            <Badge key={e} appearance="outline" size="small">
                                                {t.webhooks.events[e]}
                                            </Badge>
                                        ))}
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <Badge appearance="tint" color={w.is_active ? 'success' : 'danger'}>
                                        {w.is_active ? t.common.active : t.common.inactive}
                                    </Badge>
                                    {!w.is_active && w.failure_count > 0 && (
                                        <Text size={200} block>
                                            {t.webhooks.autoDisabled}
                                        </Text>
                                    )}
                                    {w.failure_count > 0 && w.is_active && (
                                        <Text size={200} block>
                                            {t.webhooks.failureCount.replace('%d', String(w.failure_count))}
                                        </Text>
                                    )}
                                </TableCell>
                                <TableCell>
                                    {w.last_delivery_status ? (
                                        <Badge appearance="tint" color={STATUS_COLOR[w.last_delivery_status]}>
                                            {t.webhooks.deliveryStatus[w.last_delivery_status]}
                                        </Badge>
                                    ) : (
                                        '—'
                                    )}
                                    <Text size={200} block>
                                        {formatDate(w.last_delivery_at)}
                                    </Text>
                                </TableCell>
                                <TableCell>
                                    <div className={styles.actions}>
                                        <Button size="small" onClick={() => openForm(w)}>
                                            {t.common.edit}
                                        </Button>
                                        <Button size="small" disabled={busyId === w.id} onClick={() => void sendTest(w)}>
                                            {t.webhooks.test}
                                        </Button>
                                        <Button size="small" onClick={() => openDeliveries(w)}>
                                            {t.webhooks.deliveries}
                                        </Button>
                                        <Button
                                            size="small"
                                            disabled={busyId === w.id}
                                            onClick={() =>
                                                void run(w, async () => {
                                                    const rotated = await rotateWebhookSecret(w.id);
                                                    setSecret(rotated.secret);
                                                    setCopied(false);
                                                })
                                            }
                                        >
                                            {t.webhooks.rotateSecret}
                                        </Button>
                                        <Button
                                            size="small"
                                            appearance="subtle"
                                            icon={<DeleteRegular />}
                                            aria-label={t.common.delete}
                                            onClick={() => setDeleting(w)}
                                        />
                                    </div>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            )}

            <Dialog open={formOpen} onOpenChange={(_, data) => setFormOpen(data.open)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{editing ? t.webhooks.editTitle : t.webhooks.createTitle}</DialogTitle>
                        <DialogContent>
                            <div className={styles.stack}>
                                {formError && (
                                    <MessageBar intent="error">
                                        <MessageBarBody>{formError}</MessageBarBody>
                                    </MessageBar>
                                )}
                                <Field label={t.webhooks.url} required>
                                    <Input
                                        type="url"
                                        placeholder="https://"
                                        value={form.url}
                                        onChange={(_, data) => setForm({ ...form, url: data.value })}
                                    />
                                </Field>
                                <Field label={t.webhooks.description}>
                                    <Input value={form.description} onChange={(_, data) => setForm({ ...form, description: data.value })} />
                                </Field>
                                <Field label={t.webhooks.type}>
                                    <Select
                                        value={form.type}
                                        onChange={(_, data) => {
                                            const type = data.value as WebhookType;
                                            const allowed = eventsFor(type);
                                            setForm({
                                                ...form,
                                                type,
                                                events: form.events.filter((e) => allowed.includes(e)),
                                            });
                                        }}
                                    >
                                        <option value="transactional">{t.webhooks.types.transactional}</option>
                                        <option value="marketing">{t.webhooks.types.marketing}</option>
                                    </Select>
                                </Field>
                                <Field label={t.webhooks.eventsLabel} required>
                                    <CheckboxGroup className={styles.events}>
                                        {available.map((e) => (
                                            <Checkbox
                                                key={e}
                                                label={t.webhooks.events[e]}
                                                checked={form.events.includes(e)}
                                                onChange={(_, data) => toggleEvent(e, data.checked === true)}
                                            />
                                        ))}
                                    </CheckboxGroup>
                                </Field>
                                <Field label={t.webhooks.headers}>
                                    <div className={styles.stack}>
                                        {form.headers.map((h, index) => (
                                            <div key={index} className={styles.headerRow}>
                                                <Input
                                                    placeholder="X-Api-Token"
                                                    value={h.name}
                                                    onChange={(_, data) =>
                                                        setForm({
                                                            ...form,
                                                            headers: form.headers.map((x, i) => (i === index ? { ...x, name: data.value } : x)),
                                                        })
                                                    }
                                                />
                                                <Input
                                                    value={h.value}
                                                    onChange={(_, data) =>
                                                        setForm({
                                                            ...form,
                                                            headers: form.headers.map((x, i) => (i === index ? { ...x, value: data.value } : x)),
                                                        })
                                                    }
                                                />
                                                <Button
                                                    appearance="subtle"
                                                    icon={<DismissRegular />}
                                                    aria-label={t.common.remove}
                                                    onClick={() =>
                                                        setForm({ ...form, headers: form.headers.filter((_x, i) => i !== index) })
                                                    }
                                                />
                                            </div>
                                        ))}
                                        <div>
                                            <Button
                                                size="small"
                                                icon={<AddRegular />}
                                                onClick={() => setForm({ ...form, headers: [...form.headers, { name: '', value: '' }] })}
                                            >
                                                {t.webhooks.addHeader}
                                            </Button>
                                        </div>
                                    </div>
                                </Field>
                                <Switch
                                    checked={form.is_active}
                                    onChange={(_, data) => setForm({ ...form, is_active: data.checked })}
                                    label={t.webhooks.active}
                                />
                            </div>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setFormOpen(false)}>
                                {t.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                disabled={saving || form.url.trim() === '' || form.events.length === 0}
                                onClick={() => void save()}
                            >
                                {saving ? t.common.saving : t.common.save}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={secret !== null} onOpenChange={(_, data) => !data.open && setSecret(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.webhooks.secret}</DialogTitle>
                        <DialogContent>
                            <div className={styles.stack}>
                                <MessageBar intent="warning">
                                    <MessageBarBody>{t.webhooks.secretWarning}</MessageBarBody>
                                </MessageBar>
                                <Text className={styles.secret}>{secret}</Text>
                            </div>
                        </DialogContent>
                        <DialogActions>
                            <Button onClick={() => void copySecret()}>{copied ? t.common.copied : t.common.copy}</Button>
                            <Button appearance="primary" onClick={() => setSecret(null)}>
                                {t.common.close}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={deleting !== null} onOpenChange={(_, data) => !data.open && setDeleting(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.common.delete}</DialogTitle>
                        <DialogContent>{t.webhooks.deleteConfirm}</DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setDeleting(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                onClick={() => {
                                    const target = deleting;
                                    setDeleting(null);
                                    if (target) {
                                        void run(target, () => deleteWebhook(target.id));
                                    }
                                }}
                            >
                                {t.common.confirm}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <OverlayDrawer
                open={drawerFor !== null}
                onOpenChange={(_, data) => !data.open && setDrawerFor(null)}
                position="end"
                size="large"
            >
                <DrawerHeader>
                    <DrawerHeaderTitle
                        action={
                            <Button
                                appearance="subtle"
                                aria-label={t.common.close}
                                icon={<DismissRegular />}
                                onClick={() => setDrawerFor(null)}
                            />
                        }
                    >
                        {t.webhooks.deliveries}
                    </DrawerHeaderTitle>
                </DrawerHeader>
                <DrawerBody>
                    {drawerFor && (
                        <Fragment>
                            <Field label={t.common.status}>
                                <Select
                                    value={statusFilter}
                                    onChange={(_, data) => {
                                        const next = data.value as WebhookDeliveryStatus | '';
                                        setStatusFilter(next);
                                        void loadDeliveries(drawerFor, next);
                                    }}
                                >
                                    <option value="">{t.common.all}</option>
                                    {DELIVERY_STATUSES.map((s) => (
                                        <option key={s} value={s}>
                                            {t.webhooks.deliveryStatus[s]}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                            {deliveries.map((d) => (
                                <div key={d.id} className={styles.delivery}>
                                    <div className={styles.deliveryHead}>
                                        <Badge appearance="tint" color={STATUS_COLOR[d.status]}>
                                            {t.webhooks.deliveryStatus[d.status]}
                                        </Badge>
                                        <Text weight="semibold">{d.event}</Text>
                                        <Text size={200}>{formatDate(d.created_at)}</Text>
                                    </div>
                                    <Text size={200}>
                                        {t.webhooks.attempts} : {d.attempts} · {t.webhooks.responseStatus} : {d.response_status ?? '—'} ·{' '}
                                        {t.webhooks.duration} : {d.duration_ms !== null ? `${d.duration_ms} ms` : '—'}
                                    </Text>
                                    {d.next_retry_at && (
                                        <Text size={200}>
                                            {t.webhooks.nextRetry} : {formatDate(d.next_retry_at)}
                                        </Text>
                                    )}
                                    {d.response_body_excerpt && (
                                        <Text size={200} className={styles.url}>
                                            {d.response_body_excerpt}
                                        </Text>
                                    )}
                                    <div className={styles.actions}>
                                        <Button
                                            size="small"
                                            appearance="subtle"
                                            onClick={() => setExpandedDelivery(expandedDelivery === d.id ? null : d.id)}
                                        >
                                            {t.webhooks.payload}
                                        </Button>
                                        <Button size="small" onClick={() => void redeliver(d)}>
                                            {t.webhooks.retry}
                                        </Button>
                                    </div>
                                    {expandedDelivery === d.id && <pre className={styles.payload}>{JSON.stringify(d.payload, null, 2)}</pre>}
                                </div>
                            ))}
                            {deliveriesLoading && <Spinner label={t.common.loading} />}
                            {!deliveriesLoading && deliveries.length === 0 && <Text>{t.common.noResults}</Text>}
                            {deliveriesCursor && !deliveriesLoading && (
                                <div className={styles.footer}>
                                    <Button onClick={() => void loadDeliveries(drawerFor, statusFilter, deliveriesCursor)}>
                                        {t.common.loadMore}
                                    </Button>
                                </div>
                            )}
                        </Fragment>
                    )}
                </DrawerBody>
            </OverlayDrawer>
        </div>
    );
}
