import { useCallback, useEffect, useState } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    Badge,
    Button,
    Card,
    Input,
    Spinner,
    Switch,
    Text,
    Title1,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import {
    ArchiveRegular,
    ClockRegular,
    DrawerRegular,
    MailInboxRegular,
    SearchRegular,
    SendRegular,
} from '@fluentui/react-icons';
import type { ComponentType } from 'react';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import type { TranslationDictionary } from '@/Lib/i18n/fr';
import { hasPermission } from '@/Lib/permissions';
import {
    cancelMessage,
    fetchMessagesPage,
    getMessage,
    listMessages,
    retryMessage,
} from '@/Lib/api/messages';
import type { Message, MessageDetail, MessageFolder } from '@/Lib/types/messages';
import type { Draft } from '@/Lib/types/composer';
import type { AuthenticatedUser } from '@/Lib/types/identity';

type FolderKey = 'inbox' | 'drafts' | MessageFolder;

const FOLDERS: FolderKey[] = ['inbox', 'drafts', 'outbox', 'scheduled', 'sent'];

const STATUS_COLOR: Record<string, 'success' | 'danger' | 'warning'> = {
    delivered: 'success',
    opened: 'success',
    clicked: 'success',
    accepted: 'success',
    queued: 'warning',
    sending: 'warning',
    soft_bounced: 'danger',
    hard_bounced: 'danger',
    failed: 'danger',
    rejected: 'danger',
    spam_complaint: 'danger',
    unsubscribed: 'danger',
};

function folderLabel(t: TranslationDictionary, folder: FolderKey): string {
    return t.mailbox[folder];
}

function formatDate(value: string | null | undefined): string {
    return value ? new Date(value).toLocaleString('fr-FR') : '—';
}

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    layout: {
        display: 'flex',
        gap: tokens.spacingHorizontalM,
        alignItems: 'stretch',
        minHeight: '60vh',
    },
    folderPane: {
        width: '200px',
        flex: '0 0 auto',
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        padding: tokens.spacingVerticalM,
    },
    listPane: {
        width: '380px',
        flex: '0 0 auto',
        display: 'flex',
        flexDirection: 'column',
        padding: tokens.spacingVerticalM,
        gap: tokens.spacingVerticalS,
        overflowY: 'auto',
    },
    listToolbar: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    listRow: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
        padding: tokens.spacingVerticalS,
        borderRadius: tokens.borderRadiusMedium,
        cursor: 'pointer',
        // `borderColor`/`borderWidth`/`borderStyle` longhand properties hit a
        // csstype/@griffel type-inference bug (any value assigned to them
        // fails tsc with "Type 'string' is not assignable to type
        // 'undefined'") — use the `border` shorthand instead, which is
        // unaffected.
        border: `${tokens.strokeWidthThin} solid transparent`,
    },
    listRowSelected: {
        backgroundColor: tokens.colorBrandBackground2,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorBrandStroke1}`,
    },
    listRowTop: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    readingPane: {
        flex: '1 1 auto',
        padding: tokens.spacingVerticalL,
        overflowY: 'auto',
    },
    timelineRow: {
        display: 'flex',
        justifyContent: 'space-between',
        padding: `${tokens.spacingVerticalXS} 0`,
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorNeutralStroke2,
    },
    emptyState: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        textAlign: 'center',
        gap: tokens.spacingVerticalS,
        padding: tokens.spacingVerticalXXL,
        height: '100%',
    },
});

/**
 * docs/10-mailbox.md §10.2 — Three-pane mailbox: folder nav, message list,
 * reading pane. Built against docs/29-api-specification.md §29.7's
 * documented `/api/v1/messages` contract ahead of WP-12 (Tracking module)
 * landing (docs/42-parallel-execution-plan.md §42.6) — Sent/Outbox/Scheduled
 * query it with a `folder` filter; Drafts queries the already-committed
 * Composer module's `/api/v1/drafts` and opens the existing `/compose` route
 * rather than reimplementing draft editing (§10.3/§10.4); Inbox has no real
 * data source in v1 (system notices only, doc 01 §1.4 out-of-scope) so it
 * always renders its empty state.
 */
export default function MailboxIndex() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth: { user: AuthenticatedUser; permissions: string[] } }>();
    const permissions = props.auth.permissions;
    const canViewAll = hasPermission(permissions, 'mailbox.view_all');

    const [folder, setFolder] = useState<FolderKey>('inbox');
    const [search, setSearch] = useState('');
    const [viewAll, setViewAll] = useState(false);
    const [loadingList, setLoadingList] = useState(false);
    const [messages, setMessages] = useState<Message[]>([]);
    const [drafts, setDrafts] = useState<Draft[]>([]);
    const [nextPageUrl, setNextPageUrl] = useState<string | null>(null);
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [detail, setDetail] = useState<MessageDetail | null>(null);
    const [loadingDetail, setLoadingDetail] = useState(false);
    const [actionPending, setActionPending] = useState(false);

    const loadFolder = useCallback(async (targetFolder: FolderKey, q: string, all: boolean) => {
        setSelectedId(null);
        setDetail(null);
        setNextPageUrl(null);

        if (targetFolder === 'inbox') {
            setMessages([]);
            setDrafts([]);
            return;
        }

        setLoadingList(true);
        try {
            if (targetFolder === 'drafts') {
                const response = await axios.get<{ data: Draft[] }>('/api/v1/drafts');
                setDrafts(response.data.data);
                setMessages([]);
            } else {
                const response = await listMessages(targetFolder, {
                    ...(q ? { recipient_q: q } : {}),
                    ...(all ? { scope: 'all' } : {}),
                });
                setMessages(response.data);
                setDrafts([]);
                setNextPageUrl(response.links.next);
            }
        } finally {
            setLoadingList(false);
        }
    }, []);

    useEffect(() => {
        void loadFolder(folder, search, viewAll);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [folder]);

    async function handleSearchChange(value: string) {
        setSearch(value);
        if (folder !== 'inbox' && folder !== 'drafts') {
            await loadFolder(folder, value, viewAll);
        }
    }

    async function handleViewAllChange(checked: boolean) {
        setViewAll(checked);
        if (folder !== 'inbox' && folder !== 'drafts') {
            await loadFolder(folder, search, checked);
        }
    }

    async function handleLoadMore() {
        if (!nextPageUrl) {
            return;
        }
        setLoadingList(true);
        try {
            const response = await fetchMessagesPage(nextPageUrl);
            setMessages((current) => [...current, ...response.data]);
            setNextPageUrl(response.links.next);
        } finally {
            setLoadingList(false);
        }
    }

    async function selectMessage(id: string) {
        setSelectedId(id);
        setLoadingDetail(true);
        try {
            const message = await getMessage(id);
            setDetail(message);
        } finally {
            setLoadingDetail(false);
        }
    }

    async function handleCancel(id: string) {
        setActionPending(true);
        try {
            await cancelMessage(id);
            await selectMessage(id);
            await loadFolder(folder, search, viewAll);
        } finally {
            setActionPending(false);
        }
    }

    async function handleRetry(id: string) {
        setActionPending(true);
        try {
            await retryMessage(id);
            await selectMessage(id);
            await loadFolder(folder, search, viewAll);
        } finally {
            setActionPending(false);
        }
    }

    const emptyCopy: Record<FolderKey, { title: string; body: string; icon: ComponentType }> = {
        inbox: { title: t.mailbox.emptyInboxTitle, body: t.mailbox.emptyInboxBody, icon: MailInboxRegular },
        drafts: { title: t.mailbox.emptyDraftsTitle, body: t.mailbox.emptyDraftsBody, icon: DrawerRegular },
        outbox: { title: t.mailbox.emptyOutboxTitle, body: t.mailbox.emptyOutboxBody, icon: SendRegular },
        scheduled: { title: t.mailbox.emptyScheduledTitle, body: t.mailbox.emptyScheduledBody, icon: ClockRegular },
        sent: { title: t.mailbox.emptySentTitle, body: t.mailbox.emptySentBody, icon: ArchiveRegular },
    };

    const isMessageFolder = folder === 'outbox' || folder === 'scheduled' || folder === 'sent';
    const showEmptyState = folder === 'inbox' || (isMessageFolder && !loadingList && messages.length === 0) ||
        (folder === 'drafts' && !loadingList && drafts.length === 0);

    return (
        <AppShell>
            <Head title={t.mailbox.title} />
            <div className={styles.header}>
                <Title1>{t.mailbox.title}</Title1>
                <Button appearance="primary" onClick={() => router.visit('/compose')}>
                    {t.mailbox.startNewEmail}
                </Button>
            </div>

            <Card className={styles.layout}>
                <nav className={styles.folderPane}>
                    {FOLDERS.map((key) => (
                        <Button
                            key={key}
                            style={{ justifyContent: 'flex-start', width: '100%' }}
                            appearance={folder === key ? 'primary' : 'subtle'}
                            onClick={() => setFolder(key)}
                        >
                            {folderLabel(t, key)}
                        </Button>
                    ))}
                </nav>

                <div className={styles.listPane}>
                    {isMessageFolder && (
                        <div className={styles.listToolbar}>
                            <Input
                                contentBefore={<SearchRegular />}
                                placeholder={t.mailbox.searchPlaceholder}
                                value={search}
                                onChange={(_, data) => void handleSearchChange(data.value)}
                            />
                            {canViewAll && (
                                <Switch
                                    checked={viewAll}
                                    label={t.mailbox.viewAllToggle}
                                    onChange={(_, data) => void handleViewAllChange(data.checked)}
                                />
                            )}
                        </div>
                    )}

                    {loadingList && <Spinner label={t.common.loading} />}

                    {!loadingList && isMessageFolder &&
                        messages.map((message) => (
                            <div
                                key={message.id}
                                className={`${styles.listRow} ${selectedId === message.id ? styles.listRowSelected : ''}`}
                                onClick={() => void selectMessage(message.id)}
                            >
                                <div className={styles.listRowTop}>
                                    <Text weight="semibold">{message.recipient_email}</Text>
                                    <Badge appearance="tint" color={STATUS_COLOR[message.status] ?? 'informative'}>
                                        {message.status}
                                    </Badge>
                                </div>
                                <Text size={200}>{message.subject}</Text>
                                <Text size={200}>{formatDate(message.sent_at ?? message.queued_at)}</Text>
                            </div>
                        ))}

                    {!loadingList && isMessageFolder && nextPageUrl && (
                        <Button appearance="subtle" onClick={() => void handleLoadMore()}>
                            {t.mailbox.loadMore}
                        </Button>
                    )}

                    {!loadingList && folder === 'drafts' &&
                        drafts.map((draft) => (
                            <Link key={draft.id} href={`/compose?draft=${draft.id}`} className={styles.listRow}>
                                <Text weight="semibold">{draft.subject || t.mailbox.startNewEmail}</Text>
                                <Text size={200}>{formatDate(draft.updated_at)}</Text>
                            </Link>
                        ))}
                </div>

                <div className={styles.readingPane}>
                    {showEmptyState && (
                        <EmptyState
                            icon={emptyCopy[folder].icon}
                            title={emptyCopy[folder].title}
                            body={emptyCopy[folder].body}
                        />
                    )}

                    {!showEmptyState && folder === 'drafts' && (
                        <div className={styles.emptyState}>
                            <Text>{t.mailbox.noSelection}</Text>
                        </div>
                    )}

                    {!showEmptyState && isMessageFolder && !selectedId && (
                        <div className={styles.emptyState}>
                            <Text>{t.mailbox.noSelection}</Text>
                        </div>
                    )}

                    {!showEmptyState && isMessageFolder && selectedId && loadingDetail && (
                        <Spinner label={t.common.loading} />
                    )}

                    {!showEmptyState && isMessageFolder && selectedId && !loadingDetail && detail && (
                        <>
                            <Text weight="semibold" size={500} block>
                                {detail.subject}
                            </Text>
                            <Text block>{detail.recipient_email}</Text>
                            <div style={{ margin: `${tokens.spacingVerticalM} 0` }}>
                                <Badge appearance="tint" color={STATUS_COLOR[detail.status] ?? 'informative'}>
                                    {detail.status}
                                </Badge>
                            </div>

                            <div className={styles.timelineRow}>
                                <Text>{t.mailbox.smtpAccount}</Text>
                                <Text>{detail.smtp_account ?? '—'}</Text>
                            </div>
                            <div className={styles.timelineRow}>
                                <Text>{t.mailbox.timelineQueued}</Text>
                                <Text>{formatDate(detail.queued_at)}</Text>
                            </div>
                            <div className={styles.timelineRow}>
                                <Text>{t.mailbox.timelineSent}</Text>
                                <Text>{formatDate(detail.sent_at)}</Text>
                            </div>
                            <div className={styles.timelineRow}>
                                <Text>{t.mailbox.timelineDelivered}</Text>
                                <Text>{formatDate(detail.delivered_at)}</Text>
                            </div>
                            <div className={styles.timelineRow}>
                                <Text>
                                    {t.mailbox.timelineOpened} ({t.mailbox.opens}: {detail.open_count})
                                </Text>
                                <Text>{formatDate(detail.opened_at)}</Text>
                            </div>
                            <div className={styles.timelineRow}>
                                <Text>
                                    {t.mailbox.timelineClicked} ({t.mailbox.clicks}: {detail.click_count})
                                </Text>
                                <Text>{formatDate(detail.clicked_at)}</Text>
                            </div>
                            <div className={styles.timelineRow}>
                                <Text>{t.mailbox.timelineBounced}</Text>
                                <Text>{formatDate(detail.bounced_at)}</Text>
                            </div>
                            <div className={styles.timelineRow}>
                                <Text>{t.mailbox.timelineFailed}</Text>
                                <Text>{formatDate(detail.failed_at)}</Text>
                            </div>

                            {detail.last_provider_response && (
                                <div style={{ marginTop: tokens.spacingVerticalM }}>
                                    <Text weight="semibold" block>
                                        {t.mailbox.lastResponse}
                                    </Text>
                                    <Text block>{detail.last_provider_response}</Text>
                                </div>
                            )}

                            {folder === 'outbox' && (
                                <div style={{ display: 'flex', gap: tokens.spacingHorizontalS, marginTop: tokens.spacingVerticalL }}>
                                    <Button
                                        disabled={actionPending}
                                        onClick={() => void handleRetry(detail.id)}
                                    >
                                        {t.mailbox.retryNow}
                                    </Button>
                                    <Button
                                        disabled={actionPending}
                                        appearance="secondary"
                                        onClick={() => void handleCancel(detail.id)}
                                    >
                                        {t.common.cancel}
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </div>
            </Card>
        </AppShell>
    );
}
