import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
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
    MessageBar,
    MessageBarBody,
    Spinner,
    Text,
    Title2,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import { ArrowDownloadRegular, ArrowLeftRegular, ArrowUploadRegular, DeleteRegular, PeopleListRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import { deleteList, fetchList, fetchListContacts, fetchListOperation, startListExport } from '@/Lib/api/lists';
import type { Contact } from '@/Lib/types/contacts';
import type { ContactList } from '@/Lib/types/lists';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalM,
    },
    actions: { display: 'flex', flexWrap: 'wrap', gap: tokens.spacingHorizontalS },
    stats: { display: 'flex', flexWrap: 'wrap', gap: tokens.spacingHorizontalXXL, marginBottom: tokens.spacingVerticalL },
    stat: { display: 'flex', flexDirection: 'column' },
    scroll: { overflowX: 'auto' },
    footer: { display: 'flex', justifyContent: 'center', marginTop: tokens.spacingVerticalM },
});

function formatDate(value: string | null): string {
    return value ? new Date(value).toLocaleDateString('fr-FR') : '—';
}

/**
 * docs/43-brevo-parity-plan.md §43.4.8 — list detail (WP-53): stats + the
 * contacts of the list (GET /recipients?list_id=).
 */
export default function ListShow({ uuid }: { uuid: string }) {
    const styles = useStyles();
    const t = useText();

    const [list, setList] = useState<ContactList | null>(null);
    const [contacts, setContacts] = useState<Contact[]>([]);
    const [nextCursor, setNextCursor] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [exporting, setExporting] = useState(false);
    const [confirmDelete, setConfirmDelete] = useState(false);
    const [deleteContacts, setDeleteContacts] = useState(false);
    const timer = useRef<number | null>(null);

    const fail = useCallback(
        (e: unknown) => {
            const response = (e as { response?: { status?: number; data?: { message?: string } } }).response;
            setError(response?.status === 409 ? t.lists.inUse : (response?.data?.message ?? t.common.genericError));
        },
        [t],
    );

    useEffect(() => {
        let cancelled = false;
        (async () => {
            try {
                const [detail, page] = await Promise.all([fetchList(uuid), fetchListContacts(uuid)]);
                if (cancelled) return;
                setList(detail);
                setContacts(page.data);
                setNextCursor(page.meta.next_cursor);
            } catch (e) {
                if (!cancelled) fail(e);
            } finally {
                if (!cancelled) setLoading(false);
            }
        })();
        return () => {
            cancelled = true;
            if (timer.current) window.clearTimeout(timer.current);
        };
    }, [uuid, fail]);

    async function loadMore() {
        if (!nextCursor) return;
        try {
            const page = await fetchListContacts(uuid, nextCursor);
            setContacts((current) => [...current, ...page.data]);
            setNextCursor(page.meta.next_cursor);
        } catch (e) {
            fail(e);
        }
    }

    async function runExport() {
        setExporting(true);
        setError(null);
        try {
            let op = await startListExport(uuid);
            const poll = async (): Promise<void> => {
                op = await fetchListOperation(op.status_url);
                if (op.status === 'completed' && op.result) {
                    window.location.href = op.result.download_url;
                    setExporting(false);
                } else if (op.status === 'failed') {
                    setError(op.error ?? t.common.genericError);
                    setExporting(false);
                } else {
                    timer.current = window.setTimeout(() => void poll(), 1500);
                }
            };
            await poll();
        } catch (e) {
            fail(e);
            setExporting(false);
        }
    }

    async function runDelete() {
        try {
            await deleteList(uuid, { delete_contacts: deleteContacts ? 1 : 0 });
            router.visit('/contacts/lists');
        } catch (e) {
            fail(e);
            setConfirmDelete(false);
        }
    }

    const columns: TableColumnDefinition<Contact>[] = [
        createTableColumn<Contact>({
            columnId: 'email',
            renderHeaderCell: () => t.contacts.columns.email,
            renderCell: (c) => <Link href={`/contacts/${c.id}`}>{c.email}</Link>,
        }),
        createTableColumn<Contact>({
            columnId: 'first_name',
            renderHeaderCell: () => t.contacts.columns.first_name,
            renderCell: (c) => c.first_name ?? t.common.emptyValue,
        }),
        createTableColumn<Contact>({
            columnId: 'last_name',
            renderHeaderCell: () => t.contacts.columns.last_name,
            renderCell: (c) => c.last_name ?? t.common.emptyValue,
        }),
        createTableColumn<Contact>({
            columnId: 'company_name',
            renderHeaderCell: () => t.contacts.columns.company_name,
            renderCell: (c) => c.company_name ?? t.common.emptyValue,
        }),
        createTableColumn<Contact>({
            columnId: 'status',
            renderHeaderCell: () => t.contacts.columns.status,
            renderCell: (c) => (t.contacts.statusLabels as Record<string, string>)[c.status] ?? c.status,
        }),
    ];

    if (loading) {
        return (
            <AppShell>
                <Spinner label={t.common.loading} />
            </AppShell>
        );
    }

    return (
        <AppShell>
            <Head title={list?.name ?? t.lists.title} />
            <Button appearance="transparent" icon={<ArrowLeftRegular />} onClick={() => router.visit('/contacts/lists')}>
                {t.lists.title}
            </Button>
            {error && (
                <MessageBar intent="error" style={{ marginBottom: tokens.spacingVerticalM }}>
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
            {list && (
                <>
                    <div className={styles.header}>
                        <div>
                            <Title2>{list.name}</Title2>
                            {list.description && <Text block>{list.description}</Text>}
                        </div>
                        <div className={styles.actions}>
                            <Button appearance="primary" icon={<ArrowUploadRegular />} onClick={() => router.visit(`/contacts/imports/new?list_id=${list.id}`)}>
                                {t.lists.importIntoList}
                            </Button>
                            <Button icon={<ArrowDownloadRegular />} disabled={exporting} onClick={() => void runExport()}>
                                {exporting ? t.common.loading : t.lists.exportList}
                            </Button>
                            <Button icon={<DeleteRegular />} onClick={() => setConfirmDelete(true)}>
                                {t.lists.deleteList}
                            </Button>
                        </div>
                    </div>
                    <div className={styles.stats}>
                        <div className={styles.stat}>
                            <Text size={200}>{t.lists.contacts}</Text>
                            <Text size={600} weight="semibold">{list.contact_count.toLocaleString('fr-FR')}</Text>
                        </div>
                        <div className={styles.stat}>
                            <Text size={200}>{t.lists.created}</Text>
                            <Text>{formatDate(list.created_at)}</Text>
                        </div>
                        <div className={styles.stat}>
                            <Text size={200}>{t.lists.lastImport}</Text>
                            <Text>{formatDate(list.last_import_at)}</Text>
                        </div>
                        {list.blocklisted_count > 0 && (
                            <div className={styles.stat}>
                                <Text size={200}>{t.lists.blocklistedCount.replace('%d', String(list.blocklisted_count))}</Text>
                            </div>
                        )}
                    </div>
                    {contacts.length === 0 ? (
                        <EmptyState icon={PeopleListRegular} title={t.contacts.emptyTitle} body={t.contacts.emptyBody} />
                    ) : (
                        <div className={styles.scroll}>
                            <DataGrid items={contacts} columns={columns} getRowId={(c) => c.id} style={{ width: '100%' }}>
                                <DataGridHeader>
                                    <DataGridRow>
                                        {({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}
                                    </DataGridRow>
                                </DataGridHeader>
                                <DataGridBody<Contact>>
                                    {({ item, rowId }) => (
                                        <DataGridRow<Contact> key={rowId}>
                                            {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                                        </DataGridRow>
                                    )}
                                </DataGridBody>
                            </DataGrid>
                        </div>
                    )}
                    {nextCursor && (
                        <div className={styles.footer}>
                            <Button onClick={() => void loadMore()}>{t.common.loadMore}</Button>
                        </div>
                    )}
                </>
            )}

            <Dialog open={confirmDelete} onOpenChange={(_, data) => !data.open && setConfirmDelete(false)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.lists.deleteList}</DialogTitle>
                        <DialogContent>
                            <Text block>{t.lists.deleteConfirm.replace('%s', list?.name ?? '')}</Text>
                            <label>
                                <input type="checkbox" checked={deleteContacts} onChange={(e) => setDeleteContacts(e.target.checked)} />{' '}
                                {t.lists.deleteWithContacts}
                            </label>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setConfirmDelete(false)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" onClick={() => void runDelete()}>
                                {t.common.delete}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
