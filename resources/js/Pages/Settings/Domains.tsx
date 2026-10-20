import { FormEvent, useCallback, useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
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
    Spinner,
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
import { AddRegular, GlobeRegular } from '@fluentui/react-icons';
import { PageHelp } from '@/Components/Help/PageHelp';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { DomainRecordsPanel } from '@/Components/Senders/DomainRecordsPanel';
import { DomainStatusBadge } from '@/Components/Senders/StatusBadges';
import { useText } from '@/Hooks/useText';
import {
    apiErrorMessage,
    createSendingDomain,
    deleteSendingDomain,
    fetchSendingDomain,
    fetchSendingDomains,
    verifySendingDomain,
} from '@/Lib/api/senders';
import type { SendingDomain } from '@/Lib/types/senders';

const POLL_INTERVAL_MS = 2000;
const POLL_MAX_MS = 30000;

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    messages: { marginBottom: tokens.spacingVerticalM },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    actions: { display: 'flex', flexWrap: 'wrap', gap: tokens.spacingHorizontalXS },
});

/** docs/43-brevo-parity-plan.md §43.4.14 — Settings/Domains. */
export default function Domains() {
    const styles = useStyles();
    const t = useText();
    const [domains, setDomains] = useState<SendingDomain[]>([]);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [addOpen, setAddOpen] = useState(false);
    const [newDomain, setNewDomain] = useState('');
    const [saving, setSaving] = useState(false);
    const [addError, setAddError] = useState<string | null>(null);
    const [recordsId, setRecordsId] = useState<string | null>(null);
    const [deleting, setDeleting] = useState<SendingDomain | null>(null);
    const [polling, setPolling] = useState<Set<string>>(new Set());
    const mounted = useRef(true);

    const load = useCallback(async () => {
        try {
            setDomains(await fetchSendingDomains());
        } catch (e) {
            setError(apiErrorMessage(e, t.common.genericError));
        } finally {
            setLoading(false);
        }
    }, [t]);

    useEffect(() => {
        mounted.current = true;
        void load();

        return () => {
            mounted.current = false;
        };
    }, [load]);

    function replaceDomain(next: SendingDomain) {
        setDomains((rows) => rows.map((d) => (d.id === next.id ? next : d)));
    }

    async function verify(domain: SendingDomain) {
        setError(null);
        setPolling((s) => new Set(s).add(domain.id));
        try {
            let current = await verifySendingDomain(domain.id);
            replaceDomain(current);
            const started = Date.now();
            while (
                mounted.current &&
                (current.status === 'pending' || current.status === 'checking') &&
                Date.now() - started < POLL_MAX_MS
            ) {
                await new Promise((resolve) => window.setTimeout(resolve, POLL_INTERVAL_MS));
                if (!mounted.current) {
                    return;
                }
                current = await fetchSendingDomain(domain.id);
                replaceDomain(current);
            }
        } catch (e) {
            setError(apiErrorMessage(e, t.common.genericError));
        } finally {
            if (mounted.current) {
                setPolling((s) => {
                    const next = new Set(s);
                    next.delete(domain.id);

                    return next;
                });
            }
        }
    }

    async function submitAdd(event: FormEvent) {
        event.preventDefault();
        setSaving(true);
        setAddError(null);
        try {
            const created = await createSendingDomain({ domain: newDomain.trim() });
            setAddOpen(false);
            setNewDomain('');
            await load();
            setRecordsId(created.id);
        } catch (e) {
            setAddError(apiErrorMessage(e, t.common.genericError));
        } finally {
            setSaving(false);
        }
    }

    async function confirmDelete() {
        const target = deleting;
        setDeleting(null);
        if (!target) {
            return;
        }
        try {
            await deleteSendingDomain(target.id);
            await load();
        } catch (e) {
            setError(apiErrorMessage(e, t.domains.inUse));
        }
    }

    const recordsDomain = domains.find((d) => d.id === recordsId) ?? null;

    return (
        <AppShell>
            <Head title={t.domains.title} />
            <PageHelp
                topic="domains"
                title={t.domains.title}
                actions={
                    <>
                        <Button appearance="primary" icon={<AddRegular />} onClick={() => setAddOpen(true)}>
                            {t.domains.addDomain}
                        </Button>
                    </>
                }
            />

            {error && (
                <div className={styles.messages}>
                    <MessageBar intent="error">
                        <MessageBarBody>{error}</MessageBarBody>
                    </MessageBar>
                </div>
            )}

            <div className={styles.card}>
                {loading ? (
                    <Spinner label={t.common.loading} />
                ) : domains.length === 0 ? (
                    <EmptyState
                        icon={GlobeRegular}
                        title={t.domains.emptyTitle}
                        body={t.domains.emptyBody}
                        action={
                            <Button appearance="primary" icon={<AddRegular />} onClick={() => setAddOpen(true)}>
                                {t.domains.addDomain}
                            </Button>
                        }
                    />
                ) : (
                    <Table aria-label={t.domains.title}>
                        <TableHeader>
                            <TableRow>
                                <TableHeaderCell>{t.domains.domain}</TableHeaderCell>
                                <TableHeaderCell>{t.domains.status}</TableHeaderCell>
                                <TableHeaderCell>{t.senders.title}</TableHeaderCell>
                                <TableHeaderCell>{t.common.actions}</TableHeaderCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {domains.map((d) => (
                                <TableRow key={d.id}>
                                    <TableCell>
                                        <div>{d.domain}</div>
                                        {d.last_checked_at && (
                                            <Text size={200}>
                                                {t.domains.lastChecked.replace(
                                                    '%s',
                                                    new Date(d.last_checked_at).toLocaleString('fr-FR'),
                                                )}
                                            </Text>
                                        )}
                                    </TableCell>
                                    <TableCell>
                                        <DomainStatusBadge status={d.status} />
                                    </TableCell>
                                    <TableCell>{t.domains.sendersCount.replace('%d', String(d.senders_count))}</TableCell>
                                    <TableCell>
                                        <div className={styles.actions}>
                                            <Button size="small" onClick={() => setRecordsId(d.id)}>
                                                {t.domains.authenticate}
                                            </Button>
                                            <Button
                                                size="small"
                                                disabled={polling.has(d.id)}
                                                icon={polling.has(d.id) ? <Spinner size="tiny" /> : undefined}
                                                onClick={() => void verify(d)}
                                            >
                                                {polling.has(d.id) ? t.domains.verifying : t.domains.verify}
                                            </Button>
                                            <Button size="small" appearance="subtle" onClick={() => setDeleting(d)}>
                                                {t.common.delete}
                                            </Button>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                )}
            </div>

            <Dialog open={addOpen} onOpenChange={(_, d) => setAddOpen(d.open)}>
                <DialogSurface>
                    <form onSubmit={submitAdd}>
                        <DialogBody>
                            <DialogTitle>{t.domains.addDomain}</DialogTitle>
                            <DialogContent>
                                {addError && (
                                    <MessageBar intent="error">
                                        <MessageBarBody>{addError}</MessageBarBody>
                                    </MessageBar>
                                )}
                                <Field label={t.domains.domain} required>
                                    <Input
                                        value={newDomain}
                                        placeholder="exemple.dz"
                                        onChange={(_, d) => setNewDomain(d.value)}
                                        required
                                    />
                                </Field>
                            </DialogContent>
                            <DialogActions>
                                <Button appearance="secondary" onClick={() => setAddOpen(false)} disabled={saving}>
                                    {t.common.cancel}
                                </Button>
                                <Button appearance="primary" type="submit" disabled={saving}>
                                    {t.common.add}
                                </Button>
                            </DialogActions>
                        </DialogBody>
                    </form>
                </DialogSurface>
            </Dialog>

            <Dialog open={recordsDomain !== null} onOpenChange={(_, d) => !d.open && setRecordsId(null)}>
                <DialogSurface style={{ maxWidth: 760 }}>
                    <DialogBody>
                        <DialogTitle>
                            {t.domains.authenticate} — {recordsDomain?.domain}
                        </DialogTitle>
                        <DialogContent>{recordsDomain && <DomainRecordsPanel domain={recordsDomain} />}</DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setRecordsId(null)}>
                                {t.common.close}
                            </Button>
                            <Button
                                appearance="primary"
                                disabled={recordsDomain ? polling.has(recordsDomain.id) : true}
                                onClick={() => recordsDomain && void verify(recordsDomain)}
                            >
                                {recordsDomain && polling.has(recordsDomain.id) ? t.domains.verifying : t.domains.verify}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={deleting !== null} onOpenChange={(_, d) => !d.open && setDeleting(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.common.delete}</DialogTitle>
                        <DialogContent>{deleting && t.domains.deleteConfirm.replace('%s', deleting.domain)}</DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setDeleting(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" onClick={() => void confirmDelete()}>
                                {t.common.confirm}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
