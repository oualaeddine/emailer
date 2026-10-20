import { Fragment, useCallback, useEffect, useRef, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
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
import { ChevronDownRegular, ChevronRightRegular, MailInboxRegular, SearchRegular } from '@fluentui/react-icons';
import { PageHelp } from '@/Components/Help/PageHelp';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import { apiErrorMessage, fetchTransactionalLog, fetchTransactionalLogs } from '@/Lib/api/webhooks';
import type {
    BrevoEventLabel,
    TransactionalLog,
    TransactionalLogDetail,
    TransactionalLogQuery,
    TransactionalSource,
} from '@/Lib/types/transactional';

const EVENT_FILTERS: BrevoEventLabel[] = [
    'sent',
    'delivered',
    'opened',
    'clicked',
    'soft_bounce',
    'hard_bounce',
    'invalid',
    'deferred',
    'complaint',
    'unsubscribed',
    'blocked',
    'error',
];

const SOURCES: TransactionalSource[] = ['composer', 'api', 'automation', 'form'];

const EVENT_COLOR: Partial<Record<BrevoEventLabel, 'success' | 'danger' | 'warning' | 'informative'>> = {
    sent: 'informative',
    delivered: 'success',
    opened: 'success',
    clicked: 'success',
    soft_bounce: 'warning',
    deferred: 'warning',
    hard_bounce: 'danger',
    invalid: 'danger',
    complaint: 'danger',
    unsubscribed: 'danger',
    blocked: 'danger',
    error: 'danger',
};

const REFRESH_MS = 15000;

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalL,
    },
    filters: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'flex-end',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalM,
    },
    search: { minWidth: '280px', flexGrow: 1 },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    row: { cursor: 'pointer' },
    detail: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        padding: tokens.spacingVerticalS,
        backgroundColor: tokens.colorNeutralBackground2,
        borderRadius: tokens.borderRadiusMedium,
    },
    timelineRow: {
        display: 'flex',
        alignItems: 'baseline',
        gap: tokens.spacingHorizontalM,
        flexWrap: 'wrap',
        padding: `${tokens.spacingVerticalXXS} 0`,
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorNeutralStroke2,
    },
    mono: { fontFamily: tokens.fontFamilyMonospace, wordBreak: 'break-all' },
    footer: { display: 'flex', justifyContent: 'center', marginTop: tokens.spacingVerticalM },
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleString('fr-FR') : '—';
}

/** docs/43-brevo-parity-plan.md §43.4.17 — Transactional/Logs (data: GET /transactional/logs, docs/45 §45.7.6). */
export default function Logs() {
    const styles = useStyles();
    const t = useText();
    const [rows, setRows] = useState<TransactionalLog[]>([]);
    const [nextCursor, setNextCursor] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [loadingMore, setLoadingMore] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [event, setEvent] = useState<BrevoEventLabel | ''>('');
    const [source, setSource] = useState<TransactionalSource | ''>('');
    const [search, setSearch] = useState('');
    const [debounced, setDebounced] = useState('');
    const [realtime, setRealtime] = useState(false);
    const [expandedId, setExpandedId] = useState<string | null>(null);
    const [details, setDetails] = useState<Record<string, TransactionalLogDetail>>({});
    const [detailLoading, setDetailLoading] = useState<string | null>(null);
    const requestId = useRef(0);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebounced(search.trim()), 300);

        return () => window.clearTimeout(timer);
    }, [search]);

    const query = useCallback(
        (cursor?: string): TransactionalLogQuery => ({
            ...(event ? { event } : {}),
            ...(source ? { source } : {}),
            ...(debounced ? { q: debounced } : {}),
            ...(cursor ? { cursor } : {}),
            per_page: 50,
        }),
        [event, source, debounced],
    );

    const load = useCallback(
        async (silent = false) => {
            const current = ++requestId.current;
            if (!silent) {
                setLoading(true);
            }
            try {
                const page = await fetchTransactionalLogs(query());
                if (current !== requestId.current) {
                    return;
                }
                setRows(page.data);
                setNextCursor(page.meta.next_cursor);
                setError(null);
            } catch (e) {
                if (current === requestId.current) {
                    setError(apiErrorMessage(e, t.common.genericError));
                }
            } finally {
                if (current === requestId.current) {
                    setLoading(false);
                }
            }
        },
        [query, t],
    );

    useEffect(() => {
        void load();
    }, [load]);

    useEffect(() => {
        if (!realtime) {
            return undefined;
        }
        const timer = window.setInterval(() => void load(true), REFRESH_MS);

        return () => window.clearInterval(timer);
    }, [realtime, load]);

    async function loadMore() {
        if (!nextCursor) {
            return;
        }
        setLoadingMore(true);
        try {
            const page = await fetchTransactionalLogs(query(nextCursor));
            setRows((current) => [...current, ...page.data]);
            setNextCursor(page.meta.next_cursor);
        } catch (e) {
            setError(apiErrorMessage(e, t.common.genericError));
        } finally {
            setLoadingMore(false);
        }
    }

    async function toggle(log: TransactionalLog) {
        if (expandedId === log.id) {
            setExpandedId(null);

            return;
        }
        setExpandedId(log.id);
        if (details[log.id]) {
            return;
        }
        setDetailLoading(log.id);
        try {
            const detail = await fetchTransactionalLog(log.id);
            setDetails((current) => ({ ...current, [log.id]: detail }));
        } catch (e) {
            setError(apiErrorMessage(e, t.common.genericError));
        } finally {
            setDetailLoading(null);
        }
    }

    function eventBadge(label: BrevoEventLabel | null) {
        if (!label) {
            return <Text>—</Text>;
        }

        return (
            <Badge appearance="tint" color={EVENT_COLOR[label] ?? 'informative'}>
                {t.transactional.events[label]}
            </Badge>
        );
    }

    return (
        <AppShell>
            <Head title={t.transactional.logsTitle} />
            <PageHelp
                topic="transactional-logs"
                title={t.transactional.logsTitle}
                actions={
                    <>
                        <Switch
                            checked={realtime}
                            onChange={(_, data) => setRealtime(data.checked)}
                            label={t.transactional.realtime}
                        />
                    </>
                }
            />

            <div className={styles.filters}>
                <Field className={styles.search} label={t.common.search}>
                    <Input
                        contentBefore={<SearchRegular />}
                        placeholder={t.transactional.searchPlaceholder}
                        value={search}
                        onChange={(_, data) => setSearch(data.value)}
                    />
                </Field>
                <Field label={t.transactional.eventFilter}>
                    <Select value={event} onChange={(_, data) => setEvent(data.value as BrevoEventLabel | '')}>
                        <option value="">{t.transactional.allEvents}</option>
                        {EVENT_FILTERS.map((e) => (
                            <option key={e} value={e}>
                                {t.transactional.events[e]}
                            </option>
                        ))}
                    </Select>
                </Field>
                <Field label={t.transactional.source}>
                    <Select value={source} onChange={(_, data) => setSource(data.value as TransactionalSource | '')}>
                        <option value="">{t.common.all}</option>
                        {SOURCES.map((s) => (
                            <option key={s} value={s}>
                                {t.transactional.sources[s]}
                            </option>
                        ))}
                    </Select>
                </Field>
            </div>

            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}

            <div className={styles.card}>
                {loading ? (
                    <Spinner label={t.common.loading} />
                ) : rows.length === 0 ? (
                    <EmptyState icon={MailInboxRegular} title={t.transactional.emptyLogs} />
                ) : (
                    <Table aria-label={t.transactional.logsTitle}>
                        <TableHeader>
                            <TableRow>
                                <TableHeaderCell style={{ width: 32 }} />
                                <TableHeaderCell>{t.transactional.recipient}</TableHeaderCell>
                                <TableHeaderCell>{t.transactional.subject}</TableHeaderCell>
                                <TableHeaderCell>{t.transactional.lastEvent}</TableHeaderCell>
                                <TableHeaderCell>{t.transactional.source}</TableHeaderCell>
                                <TableHeaderCell>{t.transactional.date}</TableHeaderCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {rows.map((log) => {
                                const open = expandedId === log.id;
                                const detail = details[log.id];

                                return (
                                    <Fragment key={log.id}>
                                        <TableRow
                                            className={styles.row}
                                            aria-expanded={open}
                                            onClick={() => void toggle(log)}
                                        >
                                            <TableCell>{open ? <ChevronDownRegular /> : <ChevronRightRegular />}</TableCell>
                                            <TableCell>{log.recipient_email}</TableCell>
                                            <TableCell>{log.subject}</TableCell>
                                            <TableCell>{eventBadge(log.last_event)}</TableCell>
                                            <TableCell>{t.transactional.sources[log.source]}</TableCell>
                                            <TableCell>{formatDate(log.last_event_at ?? log.created_at)}</TableCell>
                                        </TableRow>
                                        {open && (
                                            <TableRow>
                                                <TableCell colSpan={6}>
                                                    <div className={styles.detail}>
                                                        <Text size={200}>
                                                            {t.transactional.messageId} :{' '}
                                                            <span className={styles.mono}>{log.message_id}</span>
                                                        </Text>
                                                        {log.tags.length > 0 && (
                                                            <Text size={200}>
                                                                {t.transactional.tag} : {log.tags.join(', ')}
                                                            </Text>
                                                        )}
                                                        <Text weight="semibold">{t.transactional.eventTimeline}</Text>
                                                        {detailLoading === log.id && <Spinner size="tiny" />}
                                                        {detail?.events.map((e, index) => (
                                                            <div key={`${e.event}-${e.occurred_at}-${index}`} className={styles.timelineRow}>
                                                                {eventBadge(e.event)}
                                                                <Text size={200}>{formatDate(e.occurred_at)}</Text>
                                                                {e.link && <Text size={200} className={styles.mono}>{e.link}</Text>}
                                                                {e.reason && <Text size={200}>{e.reason}</Text>}
                                                                {e.device_type && <Text size={200}>{e.device_type}</Text>}
                                                                {e.ip && <Text size={200} className={styles.mono}>{e.ip}</Text>}
                                                            </div>
                                                        ))}
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </Fragment>
                                );
                            })}
                        </TableBody>
                    </Table>
                )}
                {nextCursor && !loading && (
                    <div className={styles.footer}>
                        <Button onClick={() => void loadMore()} disabled={loadingMore}>
                            {loadingMore ? t.common.loading : t.common.loadMore}
                        </Button>
                    </div>
                )}
            </div>
        </AppShell>
    );
}
