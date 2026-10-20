import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
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
    Link,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    SearchBox,
    Spinner,
    Tab,
    TabList,
    Text,
    Title2,
    Toast,
    ToastTitle,
    Toaster,
    createTableColumn,
    makeStyles,
    tokens,
    useId,
    useToastController,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import {
    AddRegular,
    CopyRegular,
    DeleteRegular,
    EditRegular,
    FlowchartRegular,
    MoreHorizontalRegular,
    PauseRegular,
    PlayRegular,
} from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { CreateAutomationDialog } from '@/Components/Automations/CreateAutomationDialog';
import { AUTOMATION_STATUS_BADGE } from '@/Components/Automations/graph';
import { automationMissingText as mt } from '@/Components/Automations/text';
import { apiErrorMessage, apiValidationErrors, formatCount, formatDateTime, formatText } from '@/Components/Campaigns/campaignUi';
import { useText } from '@/Hooks/useText';
import {
    activateAutomation,
    deleteAutomation,
    duplicateAutomation,
    fetchAutomations,
    pauseAutomation,
} from '@/Lib/api/automations';
import type { Automation, AutomationListQuery, AutomationStatus } from '@/Lib/types/automations';
import type { PaginatedResponse } from '@/Lib/types/identity';

type StatusTab = 'all' | AutomationStatus;
const STATUS_TABS: StatusTab[] = ['all', 'draft', 'active', 'paused'];
const PER_PAGE = 25;

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalM,
    },
    main: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        minWidth: 0,
    },
    tabList: {
        overflowX: 'auto',
        flexWrap: 'nowrap',
    },
    search: {
        maxWidth: '420px',
        width: '100%',
    },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
        boxSizing: 'border-box',
        width: '100%',
    },
    grid: {
        minWidth: '960px',
    },
    nameCell: {
        display: 'flex',
        flexDirection: 'column',
        minWidth: 0,
        paddingTop: tokens.spacingVerticalXS,
        paddingBottom: tokens.spacingVerticalXS,
    },
    nameLink: {
        fontWeight: tokens.fontWeightSemibold,
        textAlign: 'left',
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
    numeric: {
        fontVariantNumeric: 'tabular-nums',
    },
    center: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalXL,
    },
    pager: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'flex-end',
        gap: tokens.spacingHorizontalS,
        paddingTop: tokens.spacingVerticalS,
    },
});

function editUrl(id: string): string {
    return `/automations/${id}`;
}

/**
 * docs/43-brevo-parity-plan.md §43.4.19 — Automatisations list: status tabs
 * (draft / active / paused), search, table (name, trigger, status, contacts
 * in the flow, started / finished, updated) and row actions activate /
 * pause / duplicate / delete. *Créer une automatisation* asks for a name and
 * a trigger, then opens the editor. Route `automations.index`
 * (`automations.manage`).
 */
export default function AutomationsIndex() {
    const styles = useStyles();
    const t = useText();
    const toasterId = useId('automations-toaster');
    const { dispatchToast } = useToastController(toasterId);

    const [tab, setTab] = useState<StatusTab>('all');
    const [searchInput, setSearchInput] = useState('');
    const [q, setQ] = useState('');
    const [page, setPage] = useState(1);
    const [response, setResponse] = useState<PaginatedResponse<Automation> | null>(null);
    const [loading, setLoading] = useState(true);
    const [createOpen, setCreateOpen] = useState(false);
    const [toDelete, setToDelete] = useState<Automation | null>(null);
    const [busy, setBusy] = useState(false);
    const requestRef = useRef(0);

    const notify = useCallback(
        (text: string, intent: 'success' | 'error' | 'info' = 'success') => {
            dispatchToast(
                <Toast>
                    <ToastTitle>{text}</ToastTitle>
                </Toast>,
                { intent },
            );
        },
        [dispatchToast],
    );

    const load = useCallback(async () => {
        const requestId = ++requestRef.current;
        setLoading(true);
        const query: AutomationListQuery = {
            ...(tab !== 'all' ? { status: tab } : {}),
            ...(q ? { q } : {}),
            page,
            per_page: PER_PAGE,
        };
        try {
            const result = await fetchAutomations(query);
            if (requestId === requestRef.current) setResponse(result);
        } catch (error) {
            if (requestId === requestRef.current) notify(apiErrorMessage(error), 'error');
        } finally {
            if (requestId === requestRef.current) setLoading(false);
        }
    }, [tab, q, page, notify]);

    useEffect(() => {
        void load();
    }, [load]);

    useEffect(() => {
        const timer = window.setTimeout(() => {
            setQ(searchInput.trim());
            setPage(1);
        }, 300);

        return () => window.clearTimeout(timer);
    }, [searchInput]);

    async function run(action: () => Promise<void>) {
        setBusy(true);
        try {
            await action();
            await load();
        } catch (error) {
            const validation = apiValidationErrors(error);
            const first = Object.values(validation)[0];
            notify(first ?? apiErrorMessage(error), 'error');
        } finally {
            setBusy(false);
        }
    }

    const activate = (automation: Automation) =>
        run(async () => {
            await activateAutomation(automation.id);
            notify(t.automations.activated);
        });
    const pause = (automation: Automation) =>
        run(async () => {
            await pauseAutomation(automation.id);
            notify(t.automations.paused);
        });
    const duplicate = (automation: Automation) =>
        run(async () => {
            await duplicateAutomation(automation.id);
            notify(mt.list.duplicated);
        });

    const automations = response?.data ?? [];

    const columns: TableColumnDefinition<Automation>[] = [
        createTableColumn<Automation>({
            columnId: 'name',
            renderHeaderCell: () => t.automations.name,
            renderCell: (automation) => (
                <div className={styles.nameCell}>
                    <Link className={styles.nameLink} onClick={() => router.visit(editUrl(automation.id))}>
                        {automation.name}
                    </Link>
                    {automation.description && (
                        <Text size={200} className={styles.muted} truncate wrap={false}>
                            {automation.description}
                        </Text>
                    )}
                </div>
            ),
        }),
        createTableColumn<Automation>({
            columnId: 'trigger',
            renderHeaderCell: () => t.automations.trigger,
            renderCell: (automation) => t.automations.triggers[automation.trigger.type] ?? automation.trigger.type,
        }),
        createTableColumn<Automation>({
            columnId: 'status',
            renderHeaderCell: () => t.automations.status,
            renderCell: (automation) => (
                <Badge appearance="tint" color={AUTOMATION_STATUS_BADGE[automation.status]}>
                    {t.automations.statusLabels[automation.status]}
                </Badge>
            ),
        }),
        createTableColumn<Automation>({
            columnId: 'in_progress',
            renderHeaderCell: () => mt.columns.contactsInFlow,
            renderCell: (automation) => <span className={styles.numeric}>{formatCount(automation.stats.in_progress)}</span>,
        }),
        createTableColumn<Automation>({
            columnId: 'started_finished',
            renderHeaderCell: () => mt.columns.startedFinished,
            renderCell: (automation) => (
                <span className={styles.numeric}>
                    {formatCount(automation.stats.started)} / {formatCount(automation.stats.completed)}
                </span>
            ),
        }),
        createTableColumn<Automation>({
            columnId: 'updated_at',
            renderHeaderCell: () => t.common.updatedAt,
            renderCell: (automation) => formatDateTime(automation.updated_at),
        }),
        createTableColumn<Automation>({
            columnId: 'actions',
            renderHeaderCell: () => <span className={styles.muted}>{t.common.actions}</span>,
            renderCell: (automation) => (
                <Menu>
                    <MenuTrigger disableButtonEnhancement>
                        <Button
                            appearance="subtle"
                            icon={<MoreHorizontalRegular />}
                            aria-label={`${t.common.moreActions} — ${automation.name}`}
                            disabled={busy}
                        />
                    </MenuTrigger>
                    <MenuPopover>
                        <MenuList>
                            <MenuItem icon={<EditRegular />} onClick={() => router.visit(editUrl(automation.id))}>
                                {t.common.edit}
                            </MenuItem>
                            {automation.status === 'active' ? (
                                <MenuItem icon={<PauseRegular />} onClick={() => void pause(automation)}>
                                    {t.automations.pause}
                                </MenuItem>
                            ) : (
                                <MenuItem icon={<PlayRegular />} onClick={() => void activate(automation)}>
                                    {t.automations.activate}
                                </MenuItem>
                            )}
                            <MenuItem icon={<CopyRegular />} onClick={() => void duplicate(automation)}>
                                {t.common.duplicate}
                            </MenuItem>
                            <MenuItem
                                icon={<DeleteRegular />}
                                disabled={automation.status === 'active'}
                                title={automation.status === 'active' ? mt.list.deleteActive : undefined}
                                onClick={() => setToDelete(automation)}
                            >
                                {t.common.delete}
                            </MenuItem>
                        </MenuList>
                    </MenuPopover>
                </Menu>
            ),
        }),
    ];

    const hasFilters = tab !== 'all' || q !== '';
    const tabLabel = (value: StatusTab) => (value === 'all' ? mt.statusTabs.all : t.automations.statusLabels[value]);

    return (
        <AppShell>
            <Head title={t.automations.title} />
            <Toaster toasterId={toasterId} position="bottom-end" />
            <div className={styles.header}>
                <Title2 as="h1">{t.automations.title}</Title2>
                <Button appearance="primary" icon={<AddRegular />} onClick={() => setCreateOpen(true)}>
                    {t.automations.createAutomation}
                </Button>
            </div>

            <div className={styles.main}>
                <TabList
                    className={styles.tabList}
                    selectedValue={tab}
                    onTabSelect={(_, data) => {
                        setTab(data.value as StatusTab);
                        setPage(1);
                    }}
                >
                    {STATUS_TABS.map((value) => (
                        <Tab key={value} value={value}>
                            {tabLabel(value)}
                        </Tab>
                    ))}
                </TabList>

                <SearchBox
                    className={styles.search}
                    placeholder={t.common.searchPlaceholder}
                    aria-label={t.common.search}
                    value={searchInput}
                    onChange={(_, data) => setSearchInput(data.value)}
                />

                <div className={styles.card}>
                    {loading && !response ? (
                        <div className={styles.center}>
                            <Spinner label={t.common.loading} />
                        </div>
                    ) : automations.length === 0 ? (
                        hasFilters ? (
                            <EmptyState icon={FlowchartRegular} title={t.common.noResults} body={mt.list.emptyTab} />
                        ) : (
                            <EmptyState
                                icon={FlowchartRegular}
                                title={t.automations.emptyTitle}
                                body={t.automations.emptyBody}
                                action={
                                    <Button appearance="primary" icon={<AddRegular />} onClick={() => setCreateOpen(true)}>
                                        {t.automations.createAutomation}
                                    </Button>
                                }
                            />
                        )
                    ) : (
                        <DataGrid
                            items={automations}
                            columns={columns}
                            getRowId={(automation: Automation) => automation.id}
                            className={styles.grid}
                            columnSizingOptions={{
                                name: { minWidth: 220, idealWidth: 280 },
                                actions: { minWidth: 64, idealWidth: 64 },
                            }}
                            resizableColumns
                            style={{ opacity: loading ? 0.6 : 1 }}
                        >
                            <DataGridHeader>
                                <DataGridRow>{({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}</DataGridRow>
                            </DataGridHeader>
                            <DataGridBody<Automation>>
                                {({ item, rowId }) => (
                                    <DataGridRow<Automation> key={rowId}>
                                        {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                                    </DataGridRow>
                                )}
                            </DataGridBody>
                        </DataGrid>
                    )}
                    {response && response.meta.last_page > 1 && (
                        <div className={styles.pager}>
                            <Button appearance="subtle" disabled={page <= 1} onClick={() => setPage((p) => p - 1)}>
                                {t.common.previous}
                            </Button>
                            <Text>{formatText(t.common.page, response.meta.current_page, response.meta.last_page)}</Text>
                            <Button appearance="subtle" disabled={page >= response.meta.last_page} onClick={() => setPage((p) => p + 1)}>
                                {t.common.next}
                            </Button>
                        </div>
                    )}
                </div>
            </div>

            <CreateAutomationDialog open={createOpen} onClose={() => setCreateOpen(false)} />

            <Dialog open={toDelete !== null} onOpenChange={(_, data) => !data.open && setToDelete(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.common.deleteConfirmTitle}</DialogTitle>
                        <DialogContent>{toDelete ? formatText(t.automations.deleteConfirm, toDelete.name) : ''}</DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" disabled={busy} onClick={() => setToDelete(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                disabled={busy}
                                onClick={() => {
                                    const target = toDelete;
                                    setToDelete(null);
                                    if (target) {
                                        void run(async () => {
                                            await deleteAutomation(target.id);
                                            notify(mt.list.deleted);
                                        });
                                    }
                                }}
                            >
                                {t.common.delete}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
