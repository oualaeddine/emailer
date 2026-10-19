import { useCallback, useEffect, useState } from 'react';
import { Head, Link } from '@inertiajs/react';
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
    Field,
    Input,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    MessageBar,
    MessageBarBody,
    Select,
    Spinner,
    Text,
    Title2,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import {
    AddRegular,
    DeleteRegular,
    EditRegular,
    FolderAddRegular,
    FolderRegular,
    MoreHorizontalRegular,
    PeopleListRegular,
} from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import {
    createList,
    createListFolder,
    deleteList,
    deleteListFolder,
    fetchListFolders,
    fetchLists,
    renameListFolder,
    updateList,
} from '@/Lib/api/lists';
import type { Folder, Uuid } from '@/Lib/types/common';
import type { ContactList } from '@/Lib/types/lists';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalL,
    },
    layout: {
        display: 'grid',
        gridTemplateColumns: 'minmax(180px, 240px) 1fr',
        gap: tokens.spacingHorizontalL,
        '@media (max-width: 720px)': { gridTemplateColumns: '1fr' },
    },
    tree: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalXS },
    treeRow: { display: 'flex', alignItems: 'center', justifyContent: 'space-between' },
    treeButton: { flexGrow: 1, justifyContent: 'flex-start' },
    toolbar: { display: 'flex', gap: tokens.spacingHorizontalS, marginBottom: tokens.spacingVerticalM },
    search: { flexGrow: 1, maxWidth: '360px' },
    scroll: { overflowX: 'auto' },
    dialogFields: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
});

type ListDialog = { mode: 'create' | 'edit'; list?: ContactList } | null;
type FolderDialog = { mode: 'create' | 'rename'; folder?: Folder } | null;

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('fr-FR') : '—';
}

/**
 * docs/43-brevo-parity-plan.md §43.4.8 — Contacts › Listes (WP-53).
 */
export default function ContactsLists() {
    const styles = useStyles();
    const t = useText();

    const [folders, setFolders] = useState<Folder[]>([]);
    const [folderFilter, setFolderFilter] = useState<Uuid | 'none' | null>(null);
    const [lists, setLists] = useState<ContactList[]>([]);
    const [q, setQ] = useState('');
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [listDialog, setListDialog] = useState<ListDialog>(null);
    const [folderDialog, setFolderDialog] = useState<FolderDialog>(null);
    const [deleting, setDeleting] = useState<ContactList | null>(null);
    const [deleteContacts, setDeleteContacts] = useState(false);
    const [name, setName] = useState('');
    const [description, setDescription] = useState('');
    const [folderId, setFolderId] = useState<string>('');
    const [folderName, setFolderName] = useState('');

    const message = useCallback(
        (e: unknown): string => {
            const response = (e as { response?: { status?: number; data?: { message?: string } } }).response;
            if (response?.status === 409) return t.lists.inUse;
            return response?.data?.message ?? t.common.genericError;
        },
        [t],
    );

    const loadFolders = useCallback(async () => {
        setFolders(await fetchListFolders());
    }, []);

    const loadLists = useCallback(async () => {
        setLoading(true);
        try {
            const page = await fetchLists({
                q: q || undefined,
                folder_id: folderFilter ?? undefined,
                per_page: 100,
            });
            setLists(page.data);
        } catch (e) {
            setError(message(e));
        } finally {
            setLoading(false);
        }
    }, [q, folderFilter, message]);

    useEffect(() => {
        void loadFolders();
    }, [loadFolders]);

    useEffect(() => {
        const handle = window.setTimeout(() => void loadLists(), 250);
        return () => window.clearTimeout(handle);
    }, [loadLists]);

    function openListDialog(next: ListDialog) {
        setName(next?.list?.name ?? '');
        setDescription(next?.list?.description ?? '');
        setFolderId(next?.list?.folder_id ?? (typeof folderFilter === 'string' && folderFilter !== 'none' ? folderFilter : ''));
        setError(null);
        setListDialog(next);
    }

    async function submitList() {
        try {
            const payload = { name, description: description || null, folder_id: folderId || null };
            if (listDialog?.mode === 'edit' && listDialog.list) {
                await updateList(listDialog.list.id, payload);
            } else {
                await createList(payload);
            }
            setListDialog(null);
            await Promise.all([loadLists(), loadFolders()]);
        } catch (e) {
            setError(message(e));
        }
    }

    async function submitFolder() {
        try {
            if (folderDialog?.mode === 'rename' && folderDialog.folder) {
                await renameListFolder(folderDialog.folder.id, { name: folderName });
            } else {
                await createListFolder({ name: folderName });
            }
            setFolderDialog(null);
            await loadFolders();
        } catch (e) {
            setError(message(e));
        }
    }

    async function removeFolder(folder: Folder) {
        if (!window.confirm(`${t.common.deleteConfirmTitle} — ${folder.name}\n${t.common.deleteConfirmBody}`)) return;
        try {
            await deleteListFolder(folder.id);
            if (folderFilter === folder.id) setFolderFilter(null);
            await Promise.all([loadFolders(), loadLists()]);
        } catch (e) {
            setError(message(e));
        }
    }

    async function confirmDelete() {
        if (!deleting) return;
        try {
            await deleteList(deleting.id, { delete_contacts: deleteContacts ? 1 : 0 });
            setDeleting(null);
            setDeleteContacts(false);
            await Promise.all([loadLists(), loadFolders()]);
        } catch (e) {
            setError(message(e));
            setDeleting(null);
        }
    }

    const columns: TableColumnDefinition<ContactList>[] = [
        createTableColumn<ContactList>({
            columnId: 'name',
            renderHeaderCell: () => t.common.name,
            renderCell: (list) => (
                <Link href={`/contacts/lists/${list.id}`}>
                    <Text weight="semibold">{list.name}</Text>
                </Link>
            ),
        }),
        createTableColumn<ContactList>({
            columnId: 'contact_count',
            renderHeaderCell: () => t.lists.contacts,
            renderCell: (list) => list.contact_count.toLocaleString('fr-FR'),
        }),
        createTableColumn<ContactList>({
            columnId: 'created_at',
            renderHeaderCell: () => t.lists.created,
            renderCell: (list) => formatDate(list.created_at),
        }),
        createTableColumn<ContactList>({
            columnId: 'last_import_at',
            renderHeaderCell: () => t.lists.lastImport,
            renderCell: (list) => formatDate(list.last_import_at),
        }),
        createTableColumn<ContactList>({
            columnId: 'actions',
            renderHeaderCell: () => t.common.actions,
            renderCell: (list) => (
                <Menu>
                    <MenuTrigger disableButtonEnhancement>
                        <Button appearance="transparent" icon={<MoreHorizontalRegular />} aria-label={t.common.moreActions} />
                    </MenuTrigger>
                    <MenuPopover>
                        <MenuList>
                            <MenuItem icon={<EditRegular />} onClick={() => openListDialog({ mode: 'edit', list })}>
                                {t.common.rename} / {t.common.move}
                            </MenuItem>
                            <MenuItem icon={<DeleteRegular />} onClick={() => setDeleting(list)}>
                                {t.common.delete}
                            </MenuItem>
                        </MenuList>
                    </MenuPopover>
                </Menu>
            ),
        }),
    ];

    return (
        <AppShell>
            <Head title={t.lists.title} />
            <div className={styles.header}>
                <Title2>{t.lists.title}</Title2>
                <Button appearance="primary" icon={<AddRegular />} onClick={() => openListDialog({ mode: 'create' })}>
                    {t.lists.createList}
                </Button>
            </div>
            {error && (
                <MessageBar intent="error" style={{ marginBottom: tokens.spacingVerticalM }}>
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
            <div className={styles.layout}>
                <nav className={styles.tree} aria-label={t.common.folders}>
                    <Button
                        appearance={folderFilter === null ? 'primary' : 'subtle'}
                        className={styles.treeButton}
                        onClick={() => setFolderFilter(null)}
                    >
                        {t.lists.allLists}
                    </Button>
                    <Button
                        appearance={folderFilter === 'none' ? 'primary' : 'subtle'}
                        className={styles.treeButton}
                        onClick={() => setFolderFilter('none')}
                    >
                        {t.common.noFolder}
                    </Button>
                    {folders.map((folder) => (
                        <div key={folder.id} className={styles.treeRow}>
                            <Button
                                appearance={folderFilter === folder.id ? 'primary' : 'subtle'}
                                className={styles.treeButton}
                                icon={<FolderRegular />}
                                onClick={() => setFolderFilter(folder.id)}
                            >
                                {folder.name} ({folder.items_count})
                            </Button>
                            <Menu>
                                <MenuTrigger disableButtonEnhancement>
                                    <Button appearance="transparent" size="small" icon={<MoreHorizontalRegular />} aria-label={t.common.moreActions} />
                                </MenuTrigger>
                                <MenuPopover>
                                    <MenuList>
                                        <MenuItem
                                            icon={<EditRegular />}
                                            onClick={() => {
                                                setFolderName(folder.name);
                                                setFolderDialog({ mode: 'rename', folder });
                                            }}
                                        >
                                            {t.common.renameFolder}
                                        </MenuItem>
                                        <MenuItem icon={<DeleteRegular />} onClick={() => void removeFolder(folder)}>
                                            {t.common.deleteFolder}
                                        </MenuItem>
                                    </MenuList>
                                </MenuPopover>
                            </Menu>
                        </div>
                    ))}
                    <Button
                        appearance="transparent"
                        icon={<FolderAddRegular />}
                        onClick={() => {
                            setFolderName('');
                            setFolderDialog({ mode: 'create' });
                        }}
                    >
                        {t.common.newFolder}
                    </Button>
                </nav>
                <div>
                    <div className={styles.toolbar}>
                        <Input
                            className={styles.search}
                            value={q}
                            placeholder={t.lists.searchPlaceholder}
                            onChange={(_, data) => setQ(data.value)}
                            aria-label={t.common.search}
                        />
                    </div>
                    {loading ? (
                        <Spinner label={t.common.loading} />
                    ) : lists.length === 0 ? (
                        <EmptyState
                            icon={PeopleListRegular}
                            title={t.lists.emptyTitle}
                            body={t.lists.emptyBody}
                            action={
                                <Button appearance="primary" icon={<AddRegular />} onClick={() => openListDialog({ mode: 'create' })}>
                                    {t.lists.createList}
                                </Button>
                            }
                        />
                    ) : (
                        <div className={styles.scroll}>
                            <DataGrid items={lists} columns={columns} getRowId={(list) => list.id} style={{ width: '100%' }}>
                                <DataGridHeader>
                                    <DataGridRow>
                                        {({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}
                                    </DataGridRow>
                                </DataGridHeader>
                                <DataGridBody<ContactList>>
                                    {({ item, rowId }) => (
                                        <DataGridRow<ContactList> key={rowId}>
                                            {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                                        </DataGridRow>
                                    )}
                                </DataGridBody>
                            </DataGrid>
                        </div>
                    )}
                </div>
            </div>

            <Dialog open={listDialog !== null} onOpenChange={(_, data) => !data.open && setListDialog(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{listDialog?.mode === 'edit' ? t.lists.editTitle : t.lists.createTitle}</DialogTitle>
                        <DialogContent className={styles.dialogFields}>
                            <Field label={t.lists.name} required>
                                <Input value={name} onChange={(_, data) => setName(data.value)} maxLength={150} />
                            </Field>
                            <Field label={t.lists.description}>
                                <Input value={description} onChange={(_, data) => setDescription(data.value)} maxLength={500} />
                            </Field>
                            <Field label={t.lists.folder}>
                                <Select value={folderId} onChange={(_, data) => setFolderId(data.value)}>
                                    <option value="">{t.common.noFolder}</option>
                                    {folders.map((folder) => (
                                        <option key={folder.id} value={folder.id}>
                                            {folder.name}
                                        </option>
                                    ))}
                                </Select>
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setListDialog(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" disabled={name.trim() === ''} onClick={() => void submitList()}>
                                {listDialog?.mode === 'edit' ? t.common.save : t.common.create}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={folderDialog !== null} onOpenChange={(_, data) => !data.open && setFolderDialog(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{folderDialog?.mode === 'rename' ? t.common.renameFolder : t.common.newFolder}</DialogTitle>
                        <DialogContent>
                            <Field label={t.common.folderName} required>
                                <Input value={folderName} onChange={(_, data) => setFolderName(data.value)} maxLength={100} />
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setFolderDialog(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" disabled={folderName.trim() === ''} onClick={() => void submitFolder()}>
                                {folderDialog?.mode === 'rename' ? t.common.save : t.common.create}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <Dialog open={deleting !== null} onOpenChange={(_, data) => !data.open && setDeleting(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.lists.deleteList}</DialogTitle>
                        <DialogContent className={styles.dialogFields}>
                            <Text>{t.lists.deleteConfirm.replace('%s', deleting?.name ?? '')}</Text>
                            <label>
                                <input type="checkbox" checked={deleteContacts} onChange={(e) => setDeleteContacts(e.target.checked)} />{' '}
                                {t.lists.deleteWithContacts}
                            </label>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setDeleting(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" onClick={() => void confirmDelete()}>
                                {t.common.delete}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
