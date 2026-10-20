import { useCallback, useEffect, useMemo, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
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
    Dropdown,
    Field,
    Input,
    Link,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    MessageBar,
    MessageBarBody,
    Option,
    Spinner,
    Text,
    Tooltip,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import {
    AddRegular,
    CopyRegular,
    DeleteRegular,
    EditRegular,
    FilterRegular,
    MailRegular,
    MoreHorizontalRegular,
    SearchRegular,
} from '@fluentui/react-icons';
import { PageHelp } from '@/Components/Help/PageHelp';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { format } from '@/Components/Contacts/ConditionBuilder/editors/shared';
import { errorMessage, errorStatus } from '@/Components/Contacts/ConditionBuilder/errors';
import { useText } from '@/Hooks/useText';
import { deleteSegment, duplicateSegment, fetchSegmentFolders, fetchSegments } from '@/Lib/api/segments';
import { hasPermission } from '@/Lib/permissions';
import type { Folder } from '@/Lib/types/common';
import type { PaginatedResponse } from '@/Lib/types/identity';
import type { Segment, SegmentListQuery } from '@/Lib/types/segments';

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
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalM,
        alignItems: 'flex-end',
    },
    search: {
        minWidth: '260px',
    },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
        width: '100%',
        boxSizing: 'border-box',
    },
    gridScroll: {
        width: '100%',
        minWidth: '760px',
    },
    truncated: {
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
        display: 'block',
        maxWidth: '260px',
    },
    muted: {
        color: tokens.colorNeutralForeground3,
    },
    pager: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'flex-end',
        gap: tokens.spacingHorizontalM,
        marginTop: tokens.spacingVerticalM,
    },
    message: {
        marginBottom: tokens.spacingVerticalM,
    },
});

const ALL_FOLDERS = '__all';
const NO_FOLDER = 'none';
const USE_IN_CAMPAIGN_URL = '/campaigns?create=1';

const editUrl = (segment: Segment) => `/contacts/segments/${segment.id}`;
const formatDate = (iso: string | null) => (iso ? new Date(iso).toLocaleString('fr-FR', { dateStyle: 'short', timeStyle: 'short' }) : '—');

/**
 * docs/43-brevo-parity-plan.md §43.4.9 — Contacts › Segments: searchable,
 * folder-filtered table (name, description, folder, members, updated) with
 * create / duplicate / delete and *Utiliser dans une campagne*.
 * Route `contacts.segments` (`recipients.view`); writes need `segments.manage`.
 */
export default function Segments() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth?: { permissions?: string[] } }>();
    const canManage = hasPermission(props.auth?.permissions ?? [], 'segments.manage');
    const canUseInCampaign = hasPermission(props.auth?.permissions ?? [], 'campaigns.create');

    const [response, setResponse] = useState<PaginatedResponse<Segment> | null>(null);
    const [loading, setLoading] = useState(true);
    const [failed, setFailed] = useState(false);
    const [folders, setFolders] = useState<Folder[]>([]);
    const [search, setSearch] = useState('');
    const [query, setQuery] = useState<SegmentListQuery>({ sort: '-updated_at', page: 1 });
    const [notice, setNotice] = useState<{ intent: 'success' | 'error'; text: string } | null>(null);
    const [toDelete, setToDelete] = useState<Segment | null>(null);
    const [deleting, setDeleting] = useState(false);

    const load = useCallback(async (q: SegmentListQuery) => {
        setLoading(true);
        setFailed(false);
        try {
            setResponse(await fetchSegments(q));
        } catch {
            setFailed(true);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void load(query);
    }, [load, query]);

    useEffect(() => {
        fetchSegmentFolders()
            .then(setFolders)
            .catch(() => setFolders([]));
    }, []);

    // Debounced search.
    useEffect(() => {
        const timer = window.setTimeout(() => {
            setQuery((current) => {
                const q = search.trim() || undefined;

                return current.q === q ? current : { ...current, q, page: 1 };
            });
        }, 300);

        return () => window.clearTimeout(timer);
    }, [search]);

    const folderNames = useMemo(() => new Map(folders.map((f) => [f.id, f.name])), [folders]);
    const rows = response?.data ?? [];
    const meta = response?.meta;
    const filtered = Boolean(query.q) || query.folder_id !== undefined;

    async function handleDuplicate(segment: Segment) {
        setNotice(null);
        try {
            await duplicateSegment(segment.id);
            setNotice({ intent: 'success', text: t.segments.saved });
            await load(query);
        } catch (error) {
            setNotice({ intent: 'error', text: errorMessage(error) ?? t.common.genericError });
        }
    }

    async function confirmDelete() {
        if (!toDelete) return;
        setDeleting(true);
        try {
            await deleteSegment(toDelete.id);
            setToDelete(null);
            const lastOnPage = rows.length === 1 && (query.page ?? 1) > 1;
            if (lastOnPage) setQuery((q) => ({ ...q, page: (q.page ?? 2) - 1 }));
            else await load(query);
        } catch (error) {
            const status = errorStatus(error);
            setToDelete(null);
            setNotice({
                intent: 'error',
                text: status === 409 ? (errorMessage(error) ?? t.segments.inUse) : (errorMessage(error) ?? t.common.genericError),
            });
        } finally {
            setDeleting(false);
        }
    }

    const columns: TableColumnDefinition<Segment>[] = [
        createTableColumn<Segment>({
            columnId: 'name',
            renderHeaderCell: () => t.common.name,
            renderCell: (segment) =>
                canManage ? (
                    <Link
                        href={editUrl(segment)}
                        onClick={(event) => {
                            event.preventDefault();
                            router.visit(editUrl(segment));
                        }}
                    >
                        {segment.name}
                    </Link>
                ) : (
                    <Text weight="semibold">{segment.name}</Text>
                ),
        }),
        createTableColumn<Segment>({
            columnId: 'description',
            renderHeaderCell: () => t.segments.description,
            renderCell: (segment) =>
                segment.description ? (
                    <Tooltip content={segment.description} relationship="description">
                        <Text className={styles.truncated}>{segment.description}</Text>
                    </Tooltip>
                ) : (
                    <Text className={styles.muted}>{t.common.emptyValue}</Text>
                ),
        }),
        createTableColumn<Segment>({
            columnId: 'folder',
            renderHeaderCell: () => t.segments.folder,
            renderCell: (segment) =>
                segment.folder_id ? (
                    (folderNames.get(segment.folder_id) ?? t.common.emptyValue)
                ) : (
                    <Text className={styles.muted}>{t.common.noFolder}</Text>
                ),
        }),
        createTableColumn<Segment>({
            columnId: 'contacts',
            renderHeaderCell: () => t.segments.contacts,
            renderCell: (segment) =>
                segment.contact_count === null ? (
                    <Text className={styles.muted}>{t.common.emptyValue}</Text>
                ) : (
                    <Tooltip
                        content={
                            segment.count_computed_at
                                ? format(t.segments.countComputedAt, formatDate(segment.count_computed_at))
                                : t.segments.contacts
                        }
                        relationship="description"
                    >
                        <Text>{segment.contact_count.toLocaleString('fr-FR')}</Text>
                    </Tooltip>
                ),
        }),
        createTableColumn<Segment>({
            columnId: 'updated',
            renderHeaderCell: () => t.segments.updated,
            renderCell: (segment) => formatDate(segment.updated_at),
        }),
        createTableColumn<Segment>({
            columnId: 'actions',
            renderHeaderCell: () => t.common.actions,
            renderCell: (segment) =>
                !canManage && !canUseInCampaign ? null : (
                    <Menu>
                        <MenuTrigger disableButtonEnhancement>
                            <Button
                                appearance="subtle"
                                icon={<MoreHorizontalRegular />}
                                aria-label={`${t.common.moreActions} — ${segment.name}`}
                            />
                        </MenuTrigger>
                        <MenuPopover>
                            <MenuList>
                                {canManage && (
                                    <MenuItem icon={<EditRegular />} onClick={() => router.visit(editUrl(segment))}>
                                        {t.common.edit}
                                    </MenuItem>
                                )}
                                {canUseInCampaign && (
                                    <MenuItem icon={<MailRegular />} onClick={() => router.visit(USE_IN_CAMPAIGN_URL)}>
                                        {t.segments.useInCampaign}
                                    </MenuItem>
                                )}
                                {canManage && (
                                    <MenuItem icon={<CopyRegular />} onClick={() => void handleDuplicate(segment)}>
                                        {t.common.duplicate}
                                    </MenuItem>
                                )}
                                {canManage && (
                                    <MenuItem icon={<DeleteRegular />} onClick={() => setToDelete(segment)}>
                                        {t.common.delete}
                                    </MenuItem>
                                )}
                            </MenuList>
                        </MenuPopover>
                    </Menu>
                ),
        }),
    ];

    const folderValue = query.folder_id ?? ALL_FOLDERS;
    const folderLabel =
        folderValue === ALL_FOLDERS
            ? t.common.all
            : folderValue === NO_FOLDER
              ? t.common.noFolder
              : (folderNames.get(folderValue) ?? t.common.all);

    const createButton = canManage ? (
        <Button appearance="primary" icon={<AddRegular />} onClick={() => router.visit('/contacts/segments/new')}>
            {t.segments.createSegment}
        </Button>
    ) : null;

    return (
        <AppShell>
            <Head title={t.segments.title} />
            <PageHelp
                topic="segments"
                title={t.segments.title}
                actions={
                    <>
                        {createButton}
                    </>
                }
            />

            {notice && (
                <MessageBar intent={notice.intent} className={styles.message}>
                    <MessageBarBody>{notice.text}</MessageBarBody>
                </MessageBar>
            )}

            <div className={styles.filters}>
                <Field label={t.common.search}>
                    <Input
                        className={styles.search}
                        contentBefore={<SearchRegular />}
                        placeholder={t.segments.searchPlaceholder}
                        value={search}
                        onChange={(_, data) => setSearch(data.value)}
                    />
                </Field>
                <Field label={t.segments.folder}>
                    <Dropdown
                        selectedOptions={[folderValue]}
                        value={folderLabel}
                        onOptionSelect={(_, data) =>
                            setQuery((q) => ({
                                ...q,
                                page: 1,
                                folder_id: !data.optionValue || data.optionValue === ALL_FOLDERS ? undefined : data.optionValue,
                            }))
                        }
                    >
                        <Option value={ALL_FOLDERS}>{t.common.all}</Option>
                        <Option value={NO_FOLDER}>{t.common.noFolder}</Option>
                        {folders.map((folder) => (
                            <Option key={folder.id} value={folder.id}>
                                {folder.name}
                            </Option>
                        ))}
                    </Dropdown>
                </Field>
                {filtered && (
                    <Button
                        appearance="subtle"
                        icon={<FilterRegular />}
                        onClick={() => {
                            setSearch('');
                            setQuery({ sort: '-updated_at', page: 1 });
                        }}
                    >
                        {t.common.resetFilters}
                    </Button>
                )}
            </div>

            <div className={styles.card}>
                {loading && rows.length === 0 ? (
                    <Spinner label={t.common.loading} />
                ) : failed ? (
                    <MessageBar intent="error">
                        <MessageBarBody>{t.common.genericError}</MessageBarBody>
                    </MessageBar>
                ) : rows.length === 0 ? (
                    filtered ? (
                        <Text>{t.common.noResults}</Text>
                    ) : (
                        <EmptyState icon={FilterRegular} title={t.segments.emptyTitle} body={t.segments.emptyBody} action={createButton} />
                    )
                ) : (
                    <div className={styles.gridScroll}>
                        <DataGrid items={rows} columns={columns} getRowId={(row: Segment) => row.id} style={{ width: '100%' }}>
                            <DataGridHeader>
                                <DataGridRow>
                                    {({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}
                                </DataGridRow>
                            </DataGridHeader>
                            <DataGridBody<Segment>>
                                {({ item, rowId }) => (
                                    <DataGridRow<Segment> key={rowId}>
                                        {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                                    </DataGridRow>
                                )}
                            </DataGridBody>
                        </DataGrid>
                    </div>
                )}
                {meta && meta.last_page > 1 && (
                    <div className={styles.pager}>
                        <Button
                            appearance="secondary"
                            disabled={loading || meta.current_page <= 1}
                            onClick={() => setQuery((q) => ({ ...q, page: meta.current_page - 1 }))}
                        >
                            {t.common.previous}
                        </Button>
                        <Text>{format(t.common.page, meta.current_page, meta.last_page)}</Text>
                        <Button
                            appearance="secondary"
                            disabled={loading || meta.current_page >= meta.last_page}
                            onClick={() => setQuery((q) => ({ ...q, page: meta.current_page + 1 }))}
                        >
                            {t.common.next}
                        </Button>
                    </div>
                )}
            </div>

            <Dialog open={toDelete !== null} onOpenChange={(_, data) => !data.open && !deleting && setToDelete(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.common.deleteConfirmTitle}</DialogTitle>
                        <DialogContent>
                            <Text block>{toDelete ? format(t.segments.deleteConfirm, toDelete.name) : ''}</Text>
                            {toDelete && (toDelete.used_in.campaigns > 0 || toDelete.used_in.automations > 0) && (
                                <Text block className={styles.muted}>
                                    {format(t.segments.usedIn, toDelete.used_in.campaigns, toDelete.used_in.automations)}
                                </Text>
                            )}
                            <Text block className={styles.muted}>
                                {t.common.deleteConfirmBody}
                            </Text>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" disabled={deleting} onClick={() => setToDelete(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" disabled={deleting} onClick={() => void confirmDelete()}>
                                {deleting ? t.common.loading : t.common.delete}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
