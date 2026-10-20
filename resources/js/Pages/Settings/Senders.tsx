import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    MessageBar,
    MessageBarBody,
    Spinner,
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableHeaderCell,
    TableRow,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { AddRegular, MailOpenPersonRegular } from '@fluentui/react-icons';
import { PageHelp } from '@/Components/Help/PageHelp';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { SenderFormDialog } from '@/Components/Senders/SenderFormDialog';
import { DomainStatusBadge, VerifiedBadge } from '@/Components/Senders/StatusBadges';
import { useText } from '@/Hooks/useText';
import {
    apiErrorMessage,
    createSender,
    deleteSender,
    fetchSenders,
    resendSenderVerification,
    setDefaultSender,
    updateSender,
} from '@/Lib/api/senders';
import type { Sender } from '@/Lib/types/senders';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    messages: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS, marginBottom: tokens.spacingVerticalM },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    actions: { display: 'flex', flexWrap: 'wrap', gap: tokens.spacingHorizontalXS },
});

interface Notice {
    intent: 'success' | 'error';
    text: string;
}

/** docs/43-brevo-parity-plan.md §43.4.14 — Settings/Senders. */
export default function Senders() {
    const styles = useStyles();
    const t = useText();
    const [senders, setSenders] = useState<Sender[]>([]);
    const [loading, setLoading] = useState(true);
    const [notice, setNotice] = useState<Notice | null>(null);
    const [formOpen, setFormOpen] = useState(false);
    const [editing, setEditing] = useState<Sender | null>(null);
    const [deleting, setDeleting] = useState<Sender | null>(null);
    const [busyId, setBusyId] = useState<string | null>(null);

    const load = useCallback(async () => {
        try {
            setSenders(await fetchSenders());
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        } finally {
            setLoading(false);
        }
    }, [t]);

    useEffect(() => {
        void load();
        if (new URLSearchParams(window.location.search).has('verified')) {
            setNotice({ intent: 'success', text: t.senders.verifiedNotice });
        }
    }, [load, t]);

    async function run(sender: Sender, action: () => Promise<unknown>, success?: string) {
        setBusyId(sender.id);
        try {
            await action();
            if (success) {
                setNotice({ intent: 'success', text: success });
            }
            await load();
        } catch (e) {
            setNotice({ intent: 'error', text: apiErrorMessage(e, t.common.genericError) });
        } finally {
            setBusyId(null);
        }
    }

    return (
        <AppShell>
            <Head title={t.senders.title} />
            <PageHelp
                topic="senders"
                title={t.senders.title}
                actions={
                    <>
                        <Button
                            appearance="primary"
                            icon={<AddRegular />}
                            onClick={() => {
                                setEditing(null);
                                setFormOpen(true);
                            }}
                        >
                            {t.senders.addSender}
                        </Button>
                    </>
                }
            />

            {notice && (
                <div className={styles.messages}>
                    <MessageBar intent={notice.intent}>
                        <MessageBarBody>{notice.text}</MessageBarBody>
                    </MessageBar>
                </div>
            )}

            <div className={styles.card}>
                {loading ? (
                    <Spinner label={t.common.loading} />
                ) : senders.length === 0 ? (
                    <EmptyState
                        icon={MailOpenPersonRegular}
                        title={t.senders.emptyTitle}
                        body={t.senders.emptyBody}
                        action={
                            <Button appearance="primary" icon={<AddRegular />} onClick={() => setFormOpen(true)}>
                                {t.senders.addSender}
                            </Button>
                        }
                    />
                ) : (
                    <Table aria-label={t.senders.title}>
                        <TableHeader>
                            <TableRow>
                                <TableHeaderCell>{t.senders.name}</TableHeaderCell>
                                <TableHeaderCell>{t.senders.email}</TableHeaderCell>
                                <TableHeaderCell>{t.senders.domainStatus}</TableHeaderCell>
                                <TableHeaderCell>{t.senders.default}</TableHeaderCell>
                                <TableHeaderCell>{t.senders.verified}</TableHeaderCell>
                                <TableHeaderCell>{t.common.actions}</TableHeaderCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {senders.map((s) => (
                                <TableRow key={s.id}>
                                    <TableCell>{s.name}</TableCell>
                                    <TableCell>{s.email}</TableCell>
                                    <TableCell>
                                        <DomainStatusBadge status={s.domain_status} />
                                    </TableCell>
                                    <TableCell>{s.is_default ? t.common.yes : '—'}</TableCell>
                                    <TableCell>
                                        <VerifiedBadge verified={s.is_verified} />
                                    </TableCell>
                                    <TableCell>
                                        <div className={styles.actions}>
                                            <Button
                                                size="small"
                                                onClick={() => {
                                                    setEditing(s);
                                                    setFormOpen(true);
                                                }}
                                            >
                                                {t.common.edit}
                                            </Button>
                                            {!s.is_verified && (
                                                <Button
                                                    size="small"
                                                    disabled={busyId === s.id}
                                                    onClick={() =>
                                                        void run(
                                                            s,
                                                            () => resendSenderVerification(s.id),
                                                            t.senders.verificationSent.replace('%s', s.email),
                                                        )
                                                    }
                                                >
                                                    {t.senders.resendVerification}
                                                </Button>
                                            )}
                                            {!s.is_default && s.is_verified && (
                                                <Button
                                                    size="small"
                                                    disabled={busyId === s.id}
                                                    onClick={() => void run(s, () => setDefaultSender(s.id))}
                                                >
                                                    {t.senders.setDefault}
                                                </Button>
                                            )}
                                            <Button size="small" appearance="subtle" onClick={() => setDeleting(s)}>
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

            <SenderFormDialog
                open={formOpen}
                sender={editing}
                onOpenChange={setFormOpen}
                onCreate={async (payload) => {
                    const created = await createSender(payload);
                    setNotice({
                        intent: 'success',
                        text: created.is_verified
                            ? t.senders.autoVerified
                            : t.senders.verificationSent.replace('%s', created.email),
                    });
                    await load();
                }}
                onUpdate={async (id, payload) => {
                    await updateSender(id, payload);
                    await load();
                }}
            />

            <Dialog open={deleting !== null} onOpenChange={(_, d) => !d.open && setDeleting(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.common.delete}</DialogTitle>
                        <DialogContent>
                            {deleting && t.senders.deleteConfirm.replace('%s', `${deleting.name} <${deleting.email}>`)}
                        </DialogContent>
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
                                        void run(target, () => deleteSender(target.id));
                                    }
                                }}
                            >
                                {t.common.confirm}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
