import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Badge,
    Breadcrumb,
    BreadcrumbButton,
    BreadcrumbDivider,
    BreadcrumbItem,
    Button,
    CounterBadge,
    DataGrid,
    DataGridBody,
    DataGridCell,
    DataGridHeader,
    DataGridHeaderCell,
    DataGridRow,
    Dropdown,
    Link,
    Option,
    SearchBox,
    Spinner,
    Tab,
    TabList,
    Text,
    Toast,
    ToastTitle,
    Toaster,
    Tooltip,
    createTableColumn,
    makeStyles,
    tokens,
    useId,
    useToastController,
    type TableColumnDefinition,
    type TableRowId,
} from '@fluentui/react-components';
import {
    AddRegular,
    ArchiveRegular,
    ArrowUndoRegular,
    DataBarVerticalRegular,
    DeleteRegular,
    DismissRegular,
    FolderArrowRightRegular,
    MailRegular,
} from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { PageHelp } from '@/Components/Help/PageHelp';
import { useText } from '@/Hooks/useText';
import {
    archiveCampaign,
    bulkCampaigns,
    cancelCampaign,
    createCampaignFolder,
    deleteCampaign,
    deleteCampaignFolder,
    duplicateCampaign,
    fetchCampaignFolders,
    fetchCampaignList,
    pauseCampaign,
    renameCampaignFolder,
    resumeCampaign,
    scheduleCampaignV2,
    sendCampaignToNewContacts,
    unarchiveCampaign,
    unscheduleCampaign,
} from '@/Lib/api/campaigns';
import type { Folder } from '@/Lib/types/common';
import type {
    CampaignAction,
    CampaignBulkAction,
    CampaignListQuery,
    CampaignListResponse,
    CampaignSortField,
    CampaignStatusTab,
    CampaignType,
    CampaignV2,
} from '@/Lib/types/campaigns';
import { CampaignRowActions } from '@/Components/Campaigns/CampaignRowActions';
import { ConfirmActionDialog } from '@/Components/Campaigns/ConfirmActionDialog';
import { CreateCampaignDialog } from '@/Components/Campaigns/CreateCampaignDialog';
import { FolderTree, type FolderSelection } from '@/Components/Campaigns/FolderTree';
import { MoveToFolderDialog } from '@/Components/Campaigns/MoveToFolderDialog';
import { TestSendDialog } from '@/Components/Campaigns/TestSendDialog';
import {
    CampaignStatusChip,
    apiErrorMessage,
    campaignSetupUrl,
    formatCount,
    formatDateTime,
    formatRate,
    formatText,
} from '@/Components/Campaigns/campaignUi';

const STATUS_TABS: CampaignStatusTab[] = ['all', 'draft', 'scheduled', 'running', 'sent', 'suspended', 'archived'];

type SortValue = CampaignSortField | `-${CampaignSortField}`;
const SORT_OPTIONS: Array<{ value: SortValue; field: CampaignSortField }> = [
    { value: '-updated_at', field: 'updated_at' },
    { value: 'name', field: 'name' },
    { value: '-sent_at', field: 'sent_at' },
    { value: '-scheduled_at', field: 'scheduled_at' },
    { value: '-created_at', field: 'created_at' },
];

const ALL = '__all__';
const PER_PAGE = 25;

const useStyles = makeStyles({
    layout: {
        display: 'grid',
        gridTemplateColumns: '240px minmax(0, 1fr)',
        gap: tokens.spacingHorizontalL,
        alignItems: 'start',
        '@media (max-width: 900px)': {
            gridTemplateColumns: 'minmax(0, 1fr)',
        },
    },
    sidebar: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
    },
    main: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        minWidth: 0,
    },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
        width: '100%',
        boxSizing: 'border-box',
    },
    tabList: {
        overflowX: 'auto',
        flexWrap: 'nowrap',
    },
    toolbar: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    search: {
        flexGrow: 1,
        minWidth: '220px',
        maxWidth: '420px',
    },
    filter: {
        minWidth: '180px',
    },
    bulkBar: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalM}`,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorBrandBackground2,
    },
    gridScroll: {
        width: '100%',
        minWidth: '1040px',
    },
    nameCell: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
        paddingTop: tokens.spacingVerticalXS,
        paddingBottom: tokens.spacingVerticalXS,
        minWidth: 0,
    },
    nameRow: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
        flexWrap: 'wrap',
    },
    nameLink: {
        fontWeight: tokens.fontWeightSemibold,
        textAlign: 'left',
    },
    tags: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalXXS,
    },
    subtle: {
        color: tokens.colorNeutralForeground3,
    },
    pager: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'flex-end',
        gap: tokens.spacingHorizontalS,
        paddingTop: tokens.spacingVerticalS,
    },
    center: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalXXL,
    },
});

interface ListState {
    tab: CampaignStatusTab;
    folder: FolderSelection;
    q: string;
    sort: SortValue;
    tag: string;
    type: CampaignType | '';
    page: number;
}

function readInitialState(): { state: ListState; create: boolean } {
    const params = new URLSearchParams(window.location.search);
    const tab = params.get('status_tab') as CampaignStatusTab | null;
    const sort = params.get('sort') as SortValue | null;
    const type = params.get('type');

    return {
        create: params.get('create') === '1',
        state: {
            tab: tab && STATUS_TABS.includes(tab) ? tab : 'all',
            folder: params.get('folder_id') ?? 'all',
            q: params.get('q') ?? '',
            sort: sort && SORT_OPTIONS.some((o) => o.value === sort) ? sort : '-updated_at',
            tag: params.get('tag') ?? '',
            type: type === 'classic' || type === 'ab_test' ? type : '',
            page: Math.max(1, Number(params.get('page') ?? '1') || 1),
        },
    };
}

function writeUrl(state: ListState) {
    const params = new URLSearchParams();
    if (state.tab !== 'all') params.set('status_tab', state.tab);
    if (state.folder !== 'all') params.set('folder_id', state.folder);
    if (state.q) params.set('q', state.q);
    if (state.sort !== '-updated_at') params.set('sort', state.sort);
    if (state.tag) params.set('tag', state.tag);
    if (state.type) params.set('type', state.type);
    if (state.page > 1) params.set('page', String(state.page));
    const search = params.toString();
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${search ? `?${search}` : ''}`);
}

interface ConfirmState {
    title: string;
    body: string;
    confirmLabel: string;
    danger?: boolean;
    run: () => Promise<void>;
}

interface MoveState {
    ids: string[];
    folderId: string | null;
}

/**
 * docs/43-brevo-parity-plan.md §43.4.1 — Brevo campaign list: status tabs with
 * counts (`status_tab`), folders sidebar + breadcrumb, search / sort / tag and
 * type filters, Brevo columns, **Plus d'actions** gated by
 * `available_actions`, bulk archive / move / delete, and **Créer une
 * campagne** (also opened by `?create=1`, docs/45 §45.4.3).
 */
export default function CampaignsIndex() {
    const styles = useStyles();
    const t = useText();
    const toasterId = useId('campaigns-toaster');
    const { dispatchToast } = useToastController(toasterId);

    const initial = useMemo(readInitialState, []);
    const [state, setState] = useState<ListState>(initial.state);
    const [searchInput, setSearchInput] = useState(initial.state.q);
    const [response, setResponse] = useState<CampaignListResponse | null>(null);
    const [loading, setLoading] = useState(true);
    const [folders, setFolders] = useState<Folder[]>([]);
    const [knownTags, setKnownTags] = useState<string[]>([]);
    const [selected, setSelected] = useState<Set<TableRowId>>(new Set());
    const [createOpen, setCreateOpen] = useState(initial.create);
    const [confirm, setConfirm] = useState<ConfirmState | null>(null);
    const [move, setMove] = useState<MoveState | null>(null);
    const [testSendId, setTestSendId] = useState<string | null>(null);
    const setReportId = (id: string) => router.visit(`/campaigns/${id}/report`);
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
        const query: CampaignListQuery = {
            status_tab: state.tab,
            folder_id: state.folder === 'all' ? undefined : state.folder,
            q: state.q || undefined,
            sort: state.sort,
            tag: state.tag || undefined,
            type: state.type || undefined,
            page: state.page,
            per_page: PER_PAGE,
        };
        try {
            const result = await fetchCampaignList(query);
            if (requestId !== requestRef.current) {
                return;
            }
            setResponse(result);
            setKnownTags((previous) => {
                const merged = new Set(previous);
                result.data.forEach((campaign) => campaign.tags.forEach((tag) => merged.add(tag)));

                return [...merged].sort((a, b) => a.localeCompare(b, 'fr'));
            });
        } catch (err) {
            if (requestId === requestRef.current) {
                notify(apiErrorMessage(err), 'error');
            }
        } finally {
            if (requestId === requestRef.current) {
                setLoading(false);
            }
        }
    }, [state, notify]);

    const loadFolders = useCallback(async () => {
        try {
            setFolders(await fetchCampaignFolders());
        } catch {
            setFolders([]);
        }
    }, []);

    useEffect(() => {
        writeUrl(state);
        void load();
    }, [state, load]);

    useEffect(() => {
        void loadFolders();
    }, [loadFolders]);

    // The top-bar "Créer une campagne" links to /campaigns?create=1 (docs/45 §45.4.3);
    // Inertia keeps this component mounted when already on the list, so watch navigations too.
    const stateRef = useRef(state);
    stateRef.current = state;
    useEffect(
        () =>
            router.on('navigate', () => {
                if (new URLSearchParams(window.location.search).get('create') === '1') {
                    setCreateOpen(true);
                    writeUrl(stateRef.current);
                }
            }),
        [],
    );

    // Debounced search.
    useEffect(() => {
        const handle = window.setTimeout(() => {
            setState((previous) => (previous.q === searchInput ? previous : { ...previous, q: searchInput, page: 1 }));
        }, 300);

        return () => window.clearTimeout(handle);
    }, [searchInput]);

    useEffect(() => setSelected(new Set()), [state]);

    function update(patch: Partial<ListState>) {
        setState((previous) => ({ ...previous, page: 1, ...patch }));
    }

    async function refresh() {
        await Promise.all([load(), loadFolders()]);
    }

    function ask(next: ConfirmState) {
        setConfirm(next);
    }

    async function guarded(run: () => Promise<void>) {
        try {
            await run();
        } catch (err) {
            notify(apiErrorMessage(err), 'error');
        }
    }

    function handleAction(action: CampaignAction, campaign: CampaignV2) {
        switch (action) {
            case 'edit':
            case 'schedule':
                router.visit(campaignSetupUrl(campaign.id));
                break;
            case 'report':
                setReportId(campaign.id);
                break;
            case 'duplicate':
                void guarded(async () => {
                    await duplicateCampaign(campaign.id);
                    notify(t.campaigns.duplicated);
                    await refresh();
                });
                break;
            case 'test_send':
                setTestSendId(campaign.id);
                break;
            case 'unschedule':
                void guarded(async () => {
                    await unscheduleCampaign(campaign.id);
                    await load();
                });
                break;
            case 'send_now':
                ask({
                    title: t.campaigns.actions.send_now,
                    body: t.campaigns.confirmSendNow,
                    confirmLabel: t.campaigns.actions.send_now,
                    run: async () => {
                        await scheduleCampaignV2(campaign.id, { mode: 'now' });
                        notify(t.campaignSetup.confirm.sent);
                        await load();
                    },
                });
                break;
            case 'pause':
                void guarded(async () => {
                    await pauseCampaign(campaign.id);
                    await load();
                });
                break;
            case 'resume':
                void guarded(async () => {
                    await resumeCampaign(campaign.id);
                    await load();
                });
                break;
            case 'cancel':
                ask({
                    title: t.campaigns.actions.cancel,
                    body: t.campaigns.confirmCancel,
                    confirmLabel: t.campaigns.actions.cancel,
                    danger: true,
                    run: async () => {
                        await cancelCampaign(campaign.id);
                        await load();
                    },
                });
                break;
            case 'send_to_new_contacts':
                ask({
                    title: t.campaigns.actions.send_to_new_contacts,
                    body: t.campaigns.confirmSendToNewContacts,
                    confirmLabel: t.common.continue,
                    run: async () => {
                        const child = await sendCampaignToNewContacts(campaign.id);
                        router.visit(campaignSetupUrl(child.id));
                    },
                });
                break;
            case 'archive':
                ask({
                    title: t.campaigns.actions.archive,
                    body: t.campaigns.confirmArchive,
                    confirmLabel: t.common.archive,
                    run: async () => {
                        await archiveCampaign(campaign.id);
                        notify(t.campaigns.archived);
                        await refresh();
                    },
                });
                break;
            case 'unarchive':
                void guarded(async () => {
                    await unarchiveCampaign(campaign.id);
                    notify(t.campaigns.unarchived);
                    await refresh();
                });
                break;
            case 'move':
                setMove({ ids: [campaign.id], folderId: campaign.folder_id });
                break;
            case 'delete':
                ask({
                    title: t.common.deleteConfirmTitle,
                    body: t.campaigns.confirmDelete,
                    confirmLabel: t.common.delete,
                    danger: true,
                    run: async () => {
                        await deleteCampaign(campaign.id);
                        notify(t.campaigns.deleted);
                        await refresh();
                    },
                });
                break;
            default:
                break;
        }
    }

    const selectedIds = [...selected].map(String);

    async function runBulk(action: CampaignBulkAction, folderId?: string | null) {
        const result = await bulkCampaigns({ action, ids: selectedIds, ...(action === 'move' ? { folder_id: folderId ?? null } : {}) });
        notify(formatText(t.campaigns.bulk.result, result.processed, result.skipped.length), result.skipped.length > 0 ? 'info' : 'success');
        setSelected(new Set());
        await refresh();
    }

    const campaigns = response?.data ?? [];
    const counts = response?.meta.status_counts;
    const currentFolder = folders.find((folder) => folder.id === state.folder) ?? null;
    const hasFilters = state.q !== '' || state.tag !== '' || state.type !== '' || state.folder !== 'all';

    function openRowTarget(campaign: CampaignV2) {
        if (campaign.available_actions.includes('edit')) {
            router.visit(campaignSetupUrl(campaign.id));
        } else if (campaign.available_actions.includes('report')) {
            setReportId(campaign.id);
        }
    }

    function dateCell(campaign: CampaignV2) {
        if (campaign.sent_at) {
            return { caption: t.campaigns.columns.sentAt, value: formatDateTime(campaign.sent_at) };
        }
        if (campaign.status === 'scheduled' && campaign.scheduled_at) {
            return { caption: t.campaigns.columns.scheduledAt, value: formatDateTime(campaign.scheduled_at) };
        }

        return { caption: t.campaigns.columns.lastModified, value: formatDateTime(campaign.updated_at) };
    }

    const columns: TableColumnDefinition<CampaignV2>[] = [
        createTableColumn<CampaignV2>({
            columnId: 'name',
            renderHeaderCell: () => t.campaigns.columns.name,
            renderCell: (campaign) => (
                <div className={styles.nameCell}>
                    <div className={styles.nameRow}>
                        <Link as="button" className={styles.nameLink} onClick={() => openRowTarget(campaign)}>
                            {campaign.name}
                        </Link>
                        {(campaign.type === 'ab_test' || campaign.ab_test_enabled) && (
                            <Badge appearance="outline" color="brand" size="small">
                                {t.campaigns.abBadge}
                            </Badge>
                        )}
                        {campaign.status === 'completed' && campaign.available_actions.includes('report') && (
                            <Tooltip content={t.campaigns.reportIcon} relationship="label">
                                <Button
                                    appearance="transparent"
                                    size="small"
                                    icon={<DataBarVerticalRegular />}
                                    onClick={() => setReportId(campaign.id)}
                                />
                            </Tooltip>
                        )}
                    </div>
                    {campaign.subject && (
                        <Text size={200} className={styles.subtle} truncate wrap={false}>
                            {campaign.subject}
                        </Text>
                    )}
                    {campaign.tags.length > 0 && (
                        <div className={styles.tags}>
                            {campaign.tags.map((tag) => (
                                <Badge key={tag} appearance="tint" color="informative" size="small">
                                    {tag}
                                </Badge>
                            ))}
                        </div>
                    )}
                </div>
            ),
        }),
        createTableColumn<CampaignV2>({
            columnId: 'status',
            renderHeaderCell: () => t.campaigns.columns.status,
            renderCell: (campaign) => <CampaignStatusChip campaign={campaign} />,
        }),
        createTableColumn<CampaignV2>({
            columnId: 'recipients',
            renderHeaderCell: () => t.campaigns.columns.recipients,
            renderCell: (campaign) => formatCount(campaign.stats_summary?.recipients),
        }),
        createTableColumn<CampaignV2>({
            columnId: 'open_rate',
            renderHeaderCell: () => t.campaigns.columns.openRate,
            renderCell: (campaign) => formatRate(campaign.stats_summary?.open_rate),
        }),
        createTableColumn<CampaignV2>({
            columnId: 'click_rate',
            renderHeaderCell: () => t.campaigns.columns.clickRate,
            renderCell: (campaign) => formatRate(campaign.stats_summary?.click_rate),
        }),
        createTableColumn<CampaignV2>({
            columnId: 'date',
            renderHeaderCell: () => t.campaigns.columns.lastModified,
            renderCell: (campaign) => {
                const cell = dateCell(campaign);

                return (
                    <div className={styles.nameCell}>
                        <Text size={200} className={styles.subtle}>
                            {cell.caption}
                        </Text>
                        <Text>{cell.value}</Text>
                    </div>
                );
            },
        }),
        createTableColumn<CampaignV2>({
            columnId: 'owner',
            renderHeaderCell: () => t.campaigns.columns.owner,
            renderCell: (campaign) => campaign.owner?.name ?? t.common.emptyValue,
        }),
        createTableColumn<CampaignV2>({
            columnId: 'actions',
            renderHeaderCell: () => <span className={styles.subtle}>{t.common.actions}</span>,
            renderCell: (campaign) => <CampaignRowActions campaign={campaign} onAction={handleAction} />,
        }),
    ];

    const sortLabel = (value: SortValue) => {
        const option = SORT_OPTIONS.find((o) => o.value === value);

        return option ? t.campaigns.sort[option.field] : '';
    };

    return (
        <AppShell>
            <Head title={t.campaigns.pageTitle} />
            <Toaster toasterId={toasterId} position="bottom-end" />
            <PageHelp
                topic="campaigns"
                title={t.campaigns.pageTitle}
                actions={
                    <Button appearance="primary" icon={<AddRegular />} onClick={() => setCreateOpen(true)}>
                        {t.campaigns.createCampaign}
                    </Button>
                }
            />

            <div className={styles.layout}>
                <aside className={styles.sidebar}>
                    <FolderTree
                        folders={folders}
                        selected={state.folder}
                        onSelect={(folder) => update({ folder })}
                        onCreate={async (name) => {
                            await createCampaignFolder({ name });
                            await loadFolders();
                        }}
                        onRename={async (folder, name) => {
                            await renameCampaignFolder(folder.id, { name });
                            await loadFolders();
                        }}
                        onDelete={async (folder) => {
                            await guarded(async () => {
                                await deleteCampaignFolder(folder.id);
                                if (state.folder === folder.id) {
                                    update({ folder: 'all' });
                                }
                                await refresh();
                            });
                        }}
                    />
                </aside>

                <div className={styles.main}>
                    <Breadcrumb aria-label={t.common.folders}>
                        <BreadcrumbItem>
                            <BreadcrumbButton onClick={() => update({ folder: 'all' })} current={state.folder === 'all'}>
                                {t.campaigns.pageTitle}
                            </BreadcrumbButton>
                        </BreadcrumbItem>
                        {state.folder !== 'all' && (
                            <>
                                <BreadcrumbDivider />
                                <BreadcrumbItem>
                                    <BreadcrumbButton current>
                                        {state.folder === 'none' ? t.common.noFolder : (currentFolder?.name ?? t.common.folder)}
                                    </BreadcrumbButton>
                                </BreadcrumbItem>
                            </>
                        )}
                    </Breadcrumb>

                    <TabList
                        className={styles.tabList}
                        selectedValue={state.tab}
                        onTabSelect={(_, data) => update({ tab: data.value as CampaignStatusTab })}
                    >
                        {STATUS_TABS.map((tab) => (
                            <Tab key={tab} value={tab}>
                                {t.campaigns.tabs[tab]}{' '}
                                {counts && (
                                    <CounterBadge
                                        count={counts[tab] ?? 0}
                                        showZero
                                        overflowCount={9999}
                                        appearance="ghost"
                                        color={state.tab === tab ? 'brand' : 'informative'}
                                        size="small"
                                    />
                                )}
                            </Tab>
                        ))}
                    </TabList>

                    <div className={styles.toolbar}>
                        <SearchBox
                            className={styles.search}
                            placeholder={t.campaigns.searchPlaceholder}
                            value={searchInput}
                            aria-label={t.common.search}
                            onChange={(_, data) => setSearchInput(data.value)}
                        />
                        <Dropdown
                            className={styles.filter}
                            aria-label={t.campaigns.sort.label}
                            value={`${t.campaigns.sort.label} : ${sortLabel(state.sort)}`}
                            selectedOptions={[state.sort]}
                            onOptionSelect={(_, data) => update({ sort: (data.optionValue as SortValue) ?? '-updated_at' })}
                        >
                            {SORT_OPTIONS.map((option) => (
                                <Option key={option.value} value={option.value}>
                                    {t.campaigns.sort[option.field]}
                                </Option>
                            ))}
                        </Dropdown>
                        <Dropdown
                            className={styles.filter}
                            aria-label={t.campaigns.filters.tag}
                            value={state.tag || t.campaigns.filters.allTags}
                            selectedOptions={[state.tag || ALL]}
                            onOptionSelect={(_, data) => update({ tag: data.optionValue === ALL ? '' : (data.optionValue ?? '') })}
                        >
                            <Option value={ALL}>{t.campaigns.filters.allTags}</Option>
                            {knownTags.map((tag) => (
                                <Option key={tag} value={tag}>
                                    {tag}
                                </Option>
                            ))}
                        </Dropdown>
                        <Dropdown
                            className={styles.filter}
                            aria-label={t.campaigns.filters.type}
                            value={state.type ? t.campaigns.types[state.type] : `${t.campaigns.filters.type} : ${t.common.all}`}
                            selectedOptions={[state.type || ALL]}
                            onOptionSelect={(_, data) =>
                                update({ type: data.optionValue === ALL ? '' : ((data.optionValue as CampaignType) ?? '') })
                            }
                        >
                            <Option value={ALL}>{t.common.all}</Option>
                            <Option value="classic">{t.campaigns.types.classic}</Option>
                            <Option value="ab_test">{t.campaigns.types.ab_test}</Option>
                        </Dropdown>
                        {hasFilters && (
                            <Button
                                appearance="subtle"
                                icon={<DismissRegular />}
                                onClick={() => {
                                    setSearchInput('');
                                    update({ q: '', tag: '', type: '', folder: 'all' });
                                }}
                            >
                                {t.common.resetFilters}
                            </Button>
                        )}
                    </div>

                    {selectedIds.length > 0 && (
                        <div className={styles.bulkBar} role="toolbar" aria-label={formatText(t.common.selected, selectedIds.length)}>
                            <Text weight="semibold">{formatText(t.common.selected, selectedIds.length)}</Text>
                            {state.tab === 'archived' ? (
                                <Button
                                    icon={<ArrowUndoRegular />}
                                    onClick={() => void guarded(() => runBulk('unarchive'))}
                                >
                                    {t.common.unarchive}
                                </Button>
                            ) : (
                                <Button
                                    icon={<ArchiveRegular />}
                                    onClick={() =>
                                        ask({
                                            title: t.campaigns.bulk.archive,
                                            body: formatText(t.campaigns.bulk.confirmArchive, selectedIds.length),
                                            confirmLabel: t.campaigns.bulk.archive,
                                            run: () => runBulk('archive'),
                                        })
                                    }
                                >
                                    {t.campaigns.bulk.archive}
                                </Button>
                            )}
                            <Button icon={<FolderArrowRightRegular />} onClick={() => setMove({ ids: selectedIds, folderId: null })}>
                                {t.campaigns.bulk.move}
                            </Button>
                            <Button
                                icon={<DeleteRegular />}
                                onClick={() =>
                                    ask({
                                        title: t.common.deleteConfirmTitle,
                                        body: formatText(t.campaigns.bulk.confirmDelete, selectedIds.length),
                                        confirmLabel: t.campaigns.bulk.delete,
                                        danger: true,
                                        run: () => runBulk('delete'),
                                    })
                                }
                            >
                                {t.campaigns.bulk.delete}
                            </Button>
                            <Button appearance="subtle" icon={<DismissRegular />} onClick={() => setSelected(new Set())}>
                                {t.common.clearSelection}
                            </Button>
                        </div>
                    )}

                    <div className={styles.card}>
                        {loading && !response ? (
                            <div className={styles.center}>
                                <Spinner label={t.common.loading} />
                            </div>
                        ) : campaigns.length === 0 ? (
                            state.tab === 'all' && !hasFilters ? (
                                <EmptyState
                                    icon={MailRegular}
                                    title={t.campaigns.emptyTitle}
                                    body={t.campaigns.emptyBody}
                                    action={
                                        <Button appearance="primary" icon={<AddRegular />} onClick={() => setCreateOpen(true)}>
                                            {t.campaigns.createCampaign}
                                        </Button>
                                    }
                                />
                            ) : (
                                <EmptyState icon={MailRegular} title={t.common.noResults} body={t.campaigns.emptyTabBody} />
                            )
                        ) : (
                            <div className={styles.gridScroll}>
                                <DataGrid
                                    items={campaigns}
                                    columns={columns}
                                    getRowId={(campaign) => campaign.id}
                                    selectionMode="multiselect"
                                    selectedItems={selected}
                                    onSelectionChange={(_, data) => setSelected(data.selectedItems)}
                                    columnSizingOptions={{
                                        name: { minWidth: 260, idealWidth: 320 },
                                        actions: { minWidth: 56, idealWidth: 56 },
                                    }}
                                    resizableColumns
                                    style={{ width: '100%', opacity: loading ? 0.6 : 1 }}
                                >
                                    <DataGridHeader>
                                        <DataGridRow selectionCell={{ checkboxIndicator: { 'aria-label': t.common.selectAll } }}>
                                            {({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}
                                        </DataGridRow>
                                    </DataGridHeader>
                                    <DataGridBody<CampaignV2>>
                                        {({ item, rowId }) => (
                                            <DataGridRow<CampaignV2>
                                                key={rowId}
                                                selectionCell={{ checkboxIndicator: { 'aria-label': item.name } }}
                                            >
                                                {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                                            </DataGridRow>
                                        )}
                                    </DataGridBody>
                                </DataGrid>
                            </div>
                        )}
                        {response && response.meta.last_page > 1 && (
                            <div className={styles.pager}>
                                <Button
                                    appearance="subtle"
                                    disabled={state.page <= 1}
                                    onClick={() => setState((previous) => ({ ...previous, page: previous.page - 1 }))}
                                >
                                    {t.common.previous}
                                </Button>
                                <Text>{formatText(t.common.page, response.meta.current_page, response.meta.last_page)}</Text>
                                <Button
                                    appearance="subtle"
                                    disabled={state.page >= response.meta.last_page}
                                    onClick={() => setState((previous) => ({ ...previous, page: previous.page + 1 }))}
                                >
                                    {t.common.next}
                                </Button>
                            </div>
                        )}
                    </div>
                </div>
            </div>

            <CreateCampaignDialog
                open={createOpen}
                folderId={state.folder !== 'all' && state.folder !== 'none' ? state.folder : null}
                onClose={() => setCreateOpen(false)}
            />
            <ConfirmActionDialog
                open={confirm !== null}
                title={confirm?.title ?? ''}
                body={confirm?.body ?? ''}
                confirmLabel={confirm?.confirmLabel ?? t.common.confirm}
                danger={confirm?.danger}
                onClose={() => setConfirm(null)}
                onConfirm={async () => {
                    if (confirm) {
                        await guarded(confirm.run);
                    }
                    setConfirm(null);
                }}
            />
            <MoveToFolderDialog
                open={move !== null}
                folders={folders}
                initialFolderId={move?.folderId ?? null}
                onClose={() => setMove(null)}
                onSubmit={async (folderId) => {
                    if (!move) {
                        return;
                    }
                    const result = await bulkCampaigns({ action: 'move', ids: move.ids, folder_id: folderId });
                    notify(
                        move.ids.length === 1 && result.skipped.length === 0
                            ? t.campaigns.moved
                            : formatText(t.campaigns.bulk.result, result.processed, result.skipped.length),
                    );
                    setMove(null);
                    setSelected(new Set());
                    await refresh();
                }}
            />
            <TestSendDialog open={testSendId !== null} campaignId={testSendId} onClose={() => setTestSendId(null)} />
        </AppShell>
    );
}
