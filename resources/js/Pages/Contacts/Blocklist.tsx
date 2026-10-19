import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
    DataGrid,
    DataGridBody,
    DataGridCell,
    DataGridHeader,
    DataGridHeaderCell,
    DataGridRow,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    DialogTrigger,
    Dropdown,
    Input,
    Option,
    Tab,
    TabList,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import { AddRegular, ArrowUndoRegular, SearchRegular, ShieldProhibitedRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { PageHelp } from '@/Components/Help/PageHelp';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import { listBlocklistEntries, listSoftBounces, reactivateEntry, type SoftBounce } from '@/Lib/api/blocklist';
import { createSuppressionEntry, type CreateSuppressionEntryPayload } from '@/Lib/api/suppression';
import {
    BLOCKLIST_TAB_REASONS,
    type BlocklistTab,
    type SuppressionEntry,
    type SuppressionReason,
} from '@/Lib/types/suppression';
import { SuppressionEntryFormDialog } from '@/Pages/Suppression/SuppressionEntryFormDialog';

const REASON_COLOR: Record<SuppressionReason, 'danger' | 'severe' | 'warning' | 'informative' | 'brand' | 'subtle'> = {
    hard_bounce: 'danger',
    spam_complaint: 'severe',
    invalid_address: 'warning',
    manual_unsubscribe: 'informative',
    global_unsubscribe: 'brand',
    manual_block: 'subtle',
};

const STRONG_CONFIRM_REASONS: SuppressionReason[] = ['hard_bounce', 'spam_complaint'];

const useStyles = makeStyles({
    filters: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalM,
    },
    search: { maxWidth: '360px' },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
        width: '100%',
        boxSizing: 'border-box',
    },
    grid: { width: '100%', minWidth: '640px' },
    tabs: { marginBottom: tokens.spacingVerticalM },
});

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('fr-FR', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

interface HttpErrorResponse {
    response?: { status?: number; data?: { errors?: Record<string, string[]> } };
}

/**
 * docs/43 §43.4.13, docs/45 §45.5.8 — Contacts/Blocklist: Brevo's two tabs
 * over the existing suppression API. Réactiver = delete the entry.
 */
export default function Blocklist() {
    const styles = useStyles();
    const t = useText();

    const [tab, setTab] = useState<BlocklistTab>('blocklisted');
    const [entries, setEntries] = useState<SuppressionEntry[]>([]);
    const [search, setSearch] = useState('');
    const [reasonFilter, setReasonFilter] = useState<SuppressionReason | ''>('');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [formErrors, setFormErrors] = useState<Partial<Record<string, string>>>({});
    const [softBounces, setSoftBounces] = useState<SoftBounce[]>([]);
    const [page, setPage] = useState(1);
    const [lastPage, setLastPage] = useState(1);
    const [target, setTarget] = useState<SuppressionEntry | null>(null);
    const [busy, setBusy] = useState(false);
    const [notice, setNotice] = useState<string | null>(null);

    const tabReasons = BLOCKLIST_TAB_REASONS[tab];

    const load = useCallback(
        async (nextTab: BlocklistTab, q: string, reason: SuppressionReason | '', nextPage = 1) => {
            const reasons = reason ? [reason] : BLOCKLIST_TAB_REASONS[nextTab];
            const result = await listBlocklistEntries(reasons, q, nextPage);
            setEntries(result.data);
            setPage(result.meta.current_page);
            setLastPage(result.meta.last_page);
            setSoftBounces(nextTab === 'bounces' && !reason ? await listSoftBounces(q) : []);
        },
        [],
    );

    useEffect(() => {
        void load(tab, search, reasonFilter);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [tab, load]);

    async function handleCreate(values: CreateSuppressionEntryPayload) {
        setFormErrors({});
        try {
            await createSuppressionEntry(values);
            setDialogOpen(false);
            await load(tab, search, reasonFilter);
        } catch (error) {
            const status = (error as HttpErrorResponse).response?.status;
            if (status === 409) {
                setFormErrors({ email: t.suppression.alreadyExists });
            } else if (status === 422) {
                const errors = (error as HttpErrorResponse).response?.data?.errors ?? {};
                setFormErrors(Object.fromEntries(Object.entries(errors).map(([k, m]) => [k, m[0]])));
            } else {
                throw error;
            }
        }
    }

    async function handleReactivate() {
        if (!target) {
            return;
        }
        setBusy(true);
        try {
            await reactivateEntry(target.id);
            setTarget(null);
            setNotice(t.blocklist.reactivated);
            await load(tab, search, reasonFilter);
        } finally {
            setBusy(false);
        }
    }

    const columns: TableColumnDefinition<SuppressionEntry>[] = [
        createTableColumn<SuppressionEntry>({
            columnId: 'email',
            renderHeaderCell: () => t.blocklist.email,
            renderCell: (e) => e.email,
        }),
        createTableColumn<SuppressionEntry>({
            columnId: 'reason',
            renderHeaderCell: () => (tab === 'bounces' ? t.blocklist.bounceType : t.blocklist.reason),
            renderCell: (e) => (
                <Badge appearance="tint" color={REASON_COLOR[e.reason]}>
                    {t.suppression.reasons[e.reason]}
                </Badge>
            ),
        }),
        createTableColumn<SuppressionEntry>({
            columnId: 'date',
            renderHeaderCell: () => t.blocklist.date,
            renderCell: (e) => formatDate(e.created_at),
        }),
        createTableColumn<SuppressionEntry>({
            columnId: 'campaign',
            renderHeaderCell: () => t.blocklist.sourceCampaign,
            renderCell: (e) => e.source_campaign?.name ?? '—',
        }),
        createTableColumn<SuppressionEntry>({
            columnId: 'actions',
            renderHeaderCell: () => t.common.actions,
            renderCell: (e) => (
                <Button icon={<ArrowUndoRegular />} appearance="subtle" onClick={() => setTarget(e)}>
                    {t.blocklist.reactivate}
                </Button>
            ),
        }),
    ];

    return (
        <AppShell>
            <Head title={t.blocklist.title} />
            <PageHelp
                topic="suppression"
                title={t.blocklist.title}
                actions={
                    <Button appearance="primary" icon={<AddRegular />} onClick={() => setDialogOpen(true)}>
                        {t.blocklist.addToBlocklist}
                    </Button>
                }
            />
            <TabList
                className={styles.tabs}
                selectedValue={tab}
                onTabSelect={(_, data) => {
                    setReasonFilter('');
                    setTab(data.value as BlocklistTab);
                }}
            >
                <Tab value="blocklisted">{t.blocklist.tabs.blocklisted}</Tab>
                <Tab value="bounces">{t.blocklist.tabs.bounces}</Tab>
            </TabList>
            {notice && <div role="status">{notice}</div>}
            <div className={styles.filters}>
                <Input
                    className={styles.search}
                    contentBefore={<SearchRegular />}
                    placeholder={t.blocklist.searchPlaceholder}
                    value={search}
                    onChange={(_, data) => {
                        setSearch(data.value);
                        void load(tab, data.value, reasonFilter);
                    }}
                />
                {tabReasons.length > 1 && (
                    <Dropdown
                        value={reasonFilter ? t.suppression.reasons[reasonFilter] : t.suppression.allReasons}
                        onOptionSelect={(_, data) => {
                            const next = (data.optionValue as SuppressionReason | '') ?? '';
                            setReasonFilter(next);
                            void load(tab, search, next);
                        }}
                    >
                        <Option value="">{t.suppression.allReasons}</Option>
                        {tabReasons.map((reason) => (
                            <Option key={reason} value={reason}>
                                {t.suppression.reasons[reason]}
                            </Option>
                        ))}
                    </Dropdown>
                )}
            </div>
            <div className={styles.card}>
                {entries.length === 0 && softBounces.length === 0 ? (
                    <EmptyState icon={ShieldProhibitedRegular} title={t.blocklist.empty} />
                ) : (
                    <DataGrid items={entries} columns={columns} getRowId={(e) => e.id} className={styles.grid}>
                        <DataGridHeader>
                            <DataGridRow>
                                {({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}
                            </DataGridRow>
                        </DataGridHeader>
                        <DataGridBody<SuppressionEntry>>
                            {({ item, rowId }) => (
                                <DataGridRow<SuppressionEntry> key={rowId}>
                                    {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                                </DataGridRow>
                            )}
                        </DataGridBody>
                    </DataGrid>
                )}
                {softBounces.length > 0 && (
                    <ul>
                        {softBounces.map((m) => (
                            <li key={m.id}>
                                {m.recipient_email} <Badge appearance="tint">Rebond temporaire</Badge>{' '}
                                {m.bounced_at ? formatDate(m.bounced_at) : ''}
                            </li>
                        ))}
                    </ul>
                )}
                {lastPage > 1 && (
                    <div>
                        <Button disabled={page <= 1} onClick={() => void load(tab, search, reasonFilter, page - 1)}>
                            ‹
                        </Button>{' '}
                        {page} / {lastPage}{' '}
                        <Button disabled={page >= lastPage} onClick={() => void load(tab, search, reasonFilter, page + 1)}>
                            ›
                        </Button>
                    </div>
                )}
            </div>
            <SuppressionEntryFormDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                onSubmit={handleCreate}
                errors={formErrors}
            />
            <Dialog open={target !== null} onOpenChange={(_, data) => !data.open && setTarget(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.blocklist.reactivate}</DialogTitle>
                        <DialogContent>
                            {target && STRONG_CONFIRM_REASONS.includes(target.reason)
                                ? t.suppression.confirmRemoveStrong
                                : t.blocklist.reactivateConfirm}
                        </DialogContent>
                        <DialogActions>
                            <DialogTrigger disableButtonEnhancement>
                                <Button appearance="secondary">{t.common.cancel}</Button>
                            </DialogTrigger>
                            <Button appearance="primary" disabled={busy} onClick={() => void handleReactivate()}>
                                {t.common.confirm}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
