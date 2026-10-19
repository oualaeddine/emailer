import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Button,
    CounterBadge,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Dropdown,
    Input,
    MessageBar,
    MessageBarActions,
    MessageBarBody,
    Option,
    Spinner,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import {
    AddRegular,
    ArrowDownloadRegular,
    ArrowUploadRegular,
    ChevronLeftRegular,
    ChevronRightRegular,
    ContactCardRegular,
    DismissRegular,
    FilterRegular,
    SaveRegular,
    SearchRegular,
    TableSettingsRegular,
} from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { PageHelp } from '@/Components/Help/PageHelp';
import { BulkActionDialog } from '@/Components/Contacts/BulkActionDialog';
import { ColumnChooserDialog } from '@/Components/Contacts/ColumnChooserDialog';
import { ContactBulkBar, type ContactBulkBarAction } from '@/Components/Contacts/ContactBulkBar';
import { ContactFilterDrawer, type SegmentSaveErrors } from '@/Components/Contacts/ContactFilterDrawer';
import { ContactsGrid } from '@/Components/Contacts/ContactsGrid';
import { ContactViewTabs, contactViewLabel } from '@/Components/Contacts/ContactViewTabs';
import { ExportContactsDialog } from '@/Components/Contacts/ExportContactsDialog';
import { LoadListOrSegmentPopover, type ContactScopeTarget } from '@/Components/Contacts/LoadListOrSegmentPopover';
import { SaveViewDialog } from '@/Components/Contacts/SaveViewDialog';
import {
    DEFAULT_CONTACT_COLUMNS,
    DEFAULT_CONTACT_SORT,
    buildColumnCatalog,
    buildContactFieldCatalog,
    countConditions,
    formatText,
    indexColumnCatalog,
    normaliseColumns,
} from '@/Components/Contacts/contactColumns';
import { useText } from '@/Hooks/useText';
import {
    bulkContacts,
    countContacts,
    createContactView,
    createSegment,
    deleteContactView,
    fetchContactAttributes,
    fetchContactLists,
    fetchContactViews,
    fetchContacts,
    fetchSegments,
    pollOperation,
    saveColumnPreferences,
    updateContactView,
} from '@/Lib/api/contacts';
import { createRecipient, fetchTags, type CreateRecipientPayload } from '@/Lib/api/recipients';
import { hasPermission } from '@/Lib/permissions';
import type { ContactAttribute } from '@/Lib/types/attributes';
import type {
    Contact,
    ContactBulkAction,
    ContactBulkParams,
    ContactColumn,
    ContactColumnKey,
    ContactScope,
    ContactSelection,
    ContactSelectionAllMatching,
    ContactView,
    ContactViewInput,
} from '@/Lib/types/contacts';
import type { Tag } from '@/Lib/types/recipients';
import type { ConditionOptionSources, RuleTree } from '@/Lib/types/segments';
import { RecipientFormDialog } from '@/Pages/Recipients/RecipientFormDialog';

const useStyles = makeStyles({
    toolbar: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        marginBottom: tokens.spacingVerticalS,
    },
    search: {
        flexGrow: 1,
        minWidth: '200px',
        maxWidth: '360px',
    },
    spacer: {
        flexGrow: 1,
    },
    chips: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        marginBottom: tokens.spacingVerticalS,
    },
    summary: {
        color: tokens.colorNeutralForeground3,
    },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
        width: '100%',
        boxSizing: 'border-box',
    },
    footer: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalM,
        marginTop: tokens.spacingVerticalM,
    },
    pager: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    perPage: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    perPageDropdown: {
        minWidth: '88px',
    },
    feedback: {
        marginBottom: tokens.spacingVerticalS,
    },
    loading: {
        padding: tokens.spacingVerticalXL,
    },
});

const PAGE_SIZES = [25, 50, 100];

/** Fallback when GET /contact-views is unavailable (system views always exist server-side). */
const SYSTEM_VIEWS: ContactView[] = (['all', 'mine'] as const).map((id) => ({
    id,
    name: id,
    is_system: true,
    is_shared: true,
    filters: null,
    list_id: null,
    segment_id: null,
    columns: DEFAULT_CONTACT_COLUMNS,
    sort: DEFAULT_CONTACT_SORT,
    owner: null,
    created_at: null,
    updated_at: null,
}));

/** What a view stores (§45.5.4 ContactViewInput minus name / sharing). */
interface TableState {
    filters: RuleTree | null;
    target: ContactScopeTarget | null;
    columns: ContactColumn[];
    sort: string;
}

type SelectionState = { mode: 'ids'; ids: Set<string> } | { mode: 'all'; excluded: Set<string> };

type Feedback = { intent: 'success' | 'info' | 'warning' | 'error'; text: string };

interface HttpErrorResponse {
    response?: { status?: number; data?: { message?: string; errors?: Record<string, string[]> } };
}

function stateFromView(view: ContactView, names: Map<string, string>): TableState {
    const target: ContactScopeTarget | null = view.list_id
        ? { type: 'list', id: view.list_id, name: names.get(view.list_id) ?? '' }
        : view.segment_id
          ? { type: 'segment', id: view.segment_id, name: names.get(view.segment_id) ?? '' }
          : null;

    return {
        filters: view.filters,
        target,
        columns: normaliseColumns(view.columns),
        sort: view.sort || DEFAULT_CONTACT_SORT,
    };
}

function stateSignature(state: TableState): string {
    return JSON.stringify([
        state.filters,
        state.target?.type ?? null,
        state.target?.id ?? null,
        state.columns.map((column) => column.key),
        state.sort,
    ]);
}

/** Initial list / segment scope from `?list_id=` / `?segment_id=` (links from the Lists and Segments pages). */
function initialTargetFromUrl(): ContactScopeTarget | null {
    if (typeof window === 'undefined') {
        return null;
    }
    const params = new URLSearchParams(window.location.search);
    const listId = params.get('list_id');
    const segmentId = params.get('segment_id');

    return listId ? { type: 'list', id: listId, name: '' } : segmentId ? { type: 'segment', id: segmentId, name: '' } : null;
}

function httpStatus(error: unknown): number | undefined {
    return (error as HttpErrorResponse | null)?.response?.status;
}

function validationErrors(error: unknown): Record<string, string> {
    const errors = (error as HttpErrorResponse | null)?.response?.data?.errors ?? {};

    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]));
}

/**
 * docs/43-brevo-parity-plan.md §43.4.6 — Contacts (v2): views as tabs,
 * search, *Charger une liste ou un segment*, *Ajouter un filtre* (shared
 * ConditionBuilder, *Enregistrer comme segment*), *Créer un contact*,
 * *Importer des contacts*, export, bulk-action bar, *Personnaliser les
 * colonnes*, server-side cursor pagination; row click opens the contact.
 * API: docs/45-brevo-api-contracts.md §45.5.4.
 */
export default function ContactsIndex() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth?: { permissions?: string[] } }>();
    const permissions = props.auth?.permissions ?? [];
    const canManage = hasPermission(permissions, 'recipients.manage');
    const canBlocklist = hasPermission(permissions, 'suppression.manage');
    const canExport = hasPermission(permissions, 'contacts.export');
    const canImport = hasPermission(permissions, 'recipients.import');
    const canSaveSegment = hasPermission(permissions, 'segments.manage');

    // --- Reference data ---------------------------------------------------
    const [attributes, setAttributes] = useState<ContactAttribute[]>([]);
    const [names, setNames] = useState<Map<string, string>>(new Map());
    const [views, setViews] = useState<ContactView[]>(SYSTEM_VIEWS);
    const [activeViewId, setActiveViewId] = useState<string>('all');
    const tagsCache = useRef<Promise<Tag[]> | null>(null);

    const catalog = useMemo(() => buildColumnCatalog(attributes), [attributes]);
    const catalogIndex = useMemo(() => indexColumnCatalog(catalog), [catalog]);
    const fieldCatalog = useMemo(() => buildContactFieldCatalog(attributes), [attributes]);

    // --- Table state ------------------------------------------------------
    const initialTarget = useMemo(initialTargetFromUrl, []);
    const [table, setTable] = useState<TableState>({
        filters: null,
        target: initialTarget,
        columns: normaliseColumns(DEFAULT_CONTACT_COLUMNS),
        sort: DEFAULT_CONTACT_SORT,
    });
    const [baseline, setBaseline] = useState<string>(() =>
        stateSignature({ filters: null, target: null, columns: normaliseColumns(DEFAULT_CONTACT_COLUMNS), sort: DEFAULT_CONTACT_SORT }),
    );
    const widthsRef = useRef<Map<ContactColumnKey, number>>(new Map());
    const [widthsDirty, setWidthsDirty] = useState(false);
    const [search, setSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [perPage, setPerPage] = useState(50);
    const [paging, setPaging] = useState<{ key: string; stack: Array<string | null> }>({ key: '', stack: [null] });

    const [rows, setRows] = useState<Contact[]>([]);
    const [nextCursor, setNextCursor] = useState<string | null>(null);
    const [total, setTotal] = useState<number | null>(null);
    const [loading, setLoading] = useState(true);
    const [reloadKey, setReloadKey] = useState(0);
    const [selection, setSelection] = useState<SelectionState>({ mode: 'ids', ids: new Set() });
    const [feedback, setFeedback] = useState<Feedback | null>(null);

    // --- Dialogs ----------------------------------------------------------
    const [createOpen, setCreateOpen] = useState(false);
    const [createErrors, setCreateErrors] = useState<Partial<Record<string, string>>>({});
    const [columnsOpen, setColumnsOpen] = useState(false);
    const [filterOpen, setFilterOpen] = useState(false);
    const [saveViewMode, setSaveViewMode] = useState<'create' | 'rename' | null>(null);
    const [viewToDelete, setViewToDelete] = useState<ContactView | null>(null);
    const [bulkAction, setBulkAction] = useState<ContactBulkAction | null>(null);
    const [exportTarget, setExportTarget] = useState<{ selection: ContactSelection; count: number | null } | null>(null);

    const activeView = views.find((view) => view.id === activeViewId) ?? views[0];
    const scope: ContactScope = activeViewId === 'mine' ? 'mine' : 'all';
    const columnKeys = useMemo(() => table.columns.map((column) => column.key), [table.columns]);
    const columnKeysSignature = columnKeys.join(',');
    const modified = stateSignature(table) !== baseline || widthsDirty;

    // --- Reference data loading -------------------------------------------
    useEffect(() => {
        fetchContactAttributes()
            .then(setAttributes)
            .catch(() => setAttributes([]));

        Promise.allSettled([fetchContactLists({ sort: 'name', per_page: 100 }), fetchSegments({ sort: 'name', per_page: 100 })]).then(
            ([lists, segments]) => {
                setNames((current) => {
                    const next = new Map(current);
                    if (lists.status === 'fulfilled') {
                        lists.value.data.forEach((list) => next.set(list.id, list.name));
                    }
                    if (segments.status === 'fulfilled') {
                        segments.value.data.forEach((segment) => next.set(segment.id, segment.name));
                    }

                    return next;
                });
            },
        );

        fetchContactViews()
            .then((loaded) => {
                if (loaded.length === 0) {
                    return;
                }
                const hasSystem = loaded.some((view) => view.id === 'all');
                const merged = hasSystem ? loaded : [...SYSTEM_VIEWS, ...loaded];
                setViews(merged);
                // Apply the system view's (possibly user-customised) columns and sort unless a list/segment came from the URL.
                const all = merged.find((view) => view.id === 'all');
                if (all && !initialTarget) {
                    const state = stateFromView(all, new Map());
                    setTable(state);
                    setBaseline(stateSignature(state));
                }
            })
            .catch(() => setViews(SYSTEM_VIEWS));
    }, [initialTarget]);

    useEffect(() => {
        const timer = window.setTimeout(() => setDebouncedSearch(search.trim()), 300);

        return () => window.clearTimeout(timer);
    }, [search]);

    // --- Query ------------------------------------------------------------
    const allMatching = useMemo<ContactSelectionAllMatching>(
        () => ({
            q: debouncedSearch || undefined,
            scope,
            list_id: table.target?.type === 'list' ? table.target.id : null,
            segment_id: table.target?.type === 'segment' ? table.target.id : null,
            filters: table.filters,
        }),
        [debouncedSearch, scope, table.target, table.filters],
    );

    // Any change to what matches restarts paging (the cursor stack is keyed by the query) and clears the selection.
    const matchSignature = JSON.stringify([allMatching, table.sort, columnKeysSignature, perPage]);
    const cursors = paging.key === matchSignature ? paging.stack : [null];
    const currentCursor = cursors[cursors.length - 1];
    useEffect(() => {
        setSelection({ mode: 'ids', ids: new Set() });
    }, [matchSignature]);

    useEffect(() => {
        const controller = new AbortController();
        setLoading(true);
        fetchContacts(
            {
                pagination: 'cursor',
                cursor: currentCursor,
                per_page: perPage,
                ...allMatching,
                columns: columnKeysSignature.split(',') as ContactColumnKey[],
                sort: table.sort,
            },
            controller.signal,
        )
            .then((response) => {
                setRows(response.data);
                setNextCursor(response.meta.next_cursor);
                setTotal(response.meta.total);
            })
            .catch((error) => {
                if (controller.signal.aborted) {
                    return;
                }
                setRows([]);
                setNextCursor(null);
                setFeedback({ intent: 'error', text: httpStatus(error) === 403 ? t.common.forbidden : t.common.genericError });
            })
            .finally(() => {
                if (!controller.signal.aborted) {
                    setLoading(false);
                }
            });

        return () => controller.abort();
    }, [allMatching, columnKeysSignature, table.sort, perPage, currentCursor, reloadKey, t]);

    const reload = useCallback(() => setReloadKey((key) => key + 1), []);

    // --- Views ------------------------------------------------------------
    function applyView(view: ContactView) {
        const state = stateFromView(view, names);
        setActiveViewId(view.id);
        setTable(state);
        setBaseline(stateSignature(state));
        widthsRef.current = new Map();
        setWidthsDirty(false);
        setFeedback(null);
    }

    function currentColumnsWithWidths(): ContactColumn[] {
        return table.columns.map((column) => ({ ...column, width: widthsRef.current.get(column.key) ?? column.width }));
    }

    function viewPayload(): Omit<ContactViewInput, 'name'> {
        return {
            filters: table.filters,
            list_id: table.target?.type === 'list' ? table.target.id : null,
            segment_id: table.target?.type === 'segment' ? table.target.id : null,
            columns: currentColumnsWithWidths(),
            sort: table.sort,
        };
    }

    async function submitViewDialog(name: string, isShared: boolean): Promise<void> {
        try {
            if (saveViewMode === 'rename' && activeView && !activeView.is_system) {
                const updated = await updateContactView(activeView.id, { name, is_shared: isShared });
                setViews((current) => current.map((view) => (view.id === updated.id ? updated : view)));
            } else {
                const created = await createContactView({ name, is_shared: isShared, ...viewPayload() });
                setViews((current) => [...current, created]);
                setActiveViewId(created.id);
                const state = { ...table, columns: currentColumnsWithWidths() };
                setTable(state);
                setBaseline(stateSignature(state));
                setWidthsDirty(false);
            }
            setFeedback({ intent: 'success', text: t.contacts.views.viewSaved });
        } catch (error) {
            const status = httpStatus(error);
            throw status === 422
                ? (validationErrors(error).name ?? Object.values(validationErrors(error))[0] ?? t.common.genericError)
                : status === 403
                  ? t.common.forbidden
                  : status === 409
                    ? t.contacts.views.systemViewReadOnly
                    : t.common.genericError;
        }
    }

    async function saveActiveView() {
        if (!activeView || activeView.is_system) {
            setSaveViewMode('create');
            return;
        }
        try {
            const updated = await updateContactView(activeView.id, viewPayload());
            setViews((current) => current.map((view) => (view.id === updated.id ? updated : view)));
            setTable((current) => ({ ...current, columns: currentColumnsWithWidths() }));
            setBaseline(stateSignature(table));
            setWidthsDirty(false);
            setFeedback({ intent: 'success', text: t.contacts.views.viewSaved });
        } catch (error) {
            const status = httpStatus(error);
            setFeedback({
                intent: 'error',
                text: status === 409 ? t.contacts.views.systemViewReadOnly : status === 403 ? t.common.forbidden : t.common.genericError,
            });
        }
    }

    async function confirmDeleteView() {
        if (!viewToDelete) {
            return;
        }
        try {
            await deleteContactView(viewToDelete.id);
            const remaining = views.filter((view) => view.id !== viewToDelete.id);
            setViews(remaining);
            if (activeViewId === viewToDelete.id) {
                applyView(remaining.find((view) => view.id === 'all') ?? SYSTEM_VIEWS[0]);
            }
        } catch (error) {
            const status = httpStatus(error);
            setFeedback({
                intent: 'error',
                text: status === 409 ? t.contacts.views.systemViewReadOnly : status === 403 ? t.common.forbidden : t.common.genericError,
            });
        } finally {
            setViewToDelete(null);
        }
    }

    // System views cannot be overwritten: their columns are kept as the user's column preferences.
    const preferencesTimer = useRef<number | undefined>(undefined);
    function persistSystemColumns(columns: ContactColumn[]) {
        if (!activeView?.is_system) {
            return;
        }
        window.clearTimeout(preferencesTimer.current);
        preferencesTimer.current = window.setTimeout(() => {
            saveColumnPreferences({ page: 'contacts', columns }).catch(() => undefined);
        }, 800);
    }

    function applyColumns(columns: ContactColumn[]) {
        const merged = columns.map((column) => ({ ...column, width: widthsRef.current.get(column.key) ?? column.width }));
        setTable((current) => ({ ...current, columns: merged }));
        persistSystemColumns(merged);
    }

    function onColumnResize(key: ContactColumnKey, width: number) {
        widthsRef.current.set(key, width);
        setWidthsDirty(true);
        persistSystemColumns(currentColumnsWithWidths());
    }

    // --- Selection ----------------------------------------------------------
    const isSelected = useCallback(
        (id: string) => (selection.mode === 'ids' ? selection.ids.has(id) : !selection.excluded.has(id)),
        [selection],
    );

    function toggleRow(id: string, selected: boolean) {
        setSelection((current) => {
            if (current.mode === 'ids') {
                const ids = new Set(current.ids);
                if (selected) {
                    ids.add(id);
                } else {
                    ids.delete(id);
                }

                return { mode: 'ids', ids };
            }
            const excluded = new Set(current.excluded);
            if (selected) {
                excluded.delete(id);
            } else {
                excluded.add(id);
            }

            return { mode: 'all', excluded };
        });
    }

    function togglePage(selected: boolean) {
        setSelection((current) => {
            if (current.mode === 'ids') {
                const ids = new Set(current.ids);
                rows.forEach((row) => (selected ? ids.add(row.id) : ids.delete(row.id)));

                return { mode: 'ids', ids };
            }
            const excluded = new Set(current.excluded);
            rows.forEach((row) => (selected ? excluded.delete(row.id) : excluded.add(row.id)));

            return { mode: 'all', excluded };
        });
    }

    const clearSelection = () => setSelection({ mode: 'ids', ids: new Set() });

    const selectedCount =
        selection.mode === 'ids' ? selection.ids.size : Math.max(0, (total ?? rows.length) - selection.excluded.size);
    const pageFullySelected = rows.length > 0 && rows.every((row) => isSelected(row.id));

    function currentSelection(): ContactSelection {
        if (selection.mode === 'ids') {
            return { ids: [...selection.ids] };
        }

        return selection.excluded.size > 0
            ? { all_matching: allMatching, exclude_ids: [...selection.excluded] }
            : { all_matching: allMatching };
    }

    const allowedBulkActions: ContactBulkBarAction[] = [
        ...(canManage ? (['add_to_list', 'remove_from_list', 'update_attribute', 'delete'] as const) : []),
        ...(canBlocklist ? (['blocklist', 'unblocklist'] as const) : []),
        ...(canExport ? (['export'] as const) : []),
    ];

    function onBulkAction(action: ContactBulkBarAction) {
        if (action === 'export') {
            setExportTarget({ selection: currentSelection(), count: selectedCount });
            return;
        }
        setBulkAction(action);
    }

    async function runBulk(params: ContactBulkParams): Promise<void> {
        if (!bulkAction) {
            return;
        }
        try {
            const response = await bulkContacts({ action: bulkAction, selection: currentSelection(), params });
            setBulkAction(null);
            clearSelection();
            if (response.kind === 'done') {
                setFeedback({ intent: 'success', text: formatText(t.contacts.bulkDone, response.result.processed) });
                reload();
            } else {
                setFeedback({ intent: 'info', text: t.contacts.bulkQueued });
                pollOperation(response.operation)
                    .then((finished) => {
                        setFeedback(
                            finished.status === 'completed'
                                ? { intent: 'success', text: formatText(t.contacts.bulkDone, finished.processed) }
                                : { intent: 'error', text: finished.error ?? t.common.genericError },
                        );
                        reload();
                    })
                    .catch(() => undefined);
            }
        } catch (error) {
            const status = httpStatus(error);
            throw status === 403
                ? t.common.forbidden
                : status === 409
                  ? t.common.conflict
                  : status === 422
                    ? (Object.values(validationErrors(error))[0] ?? t.common.genericError)
                    : t.common.genericError;
        }
    }

    // --- Filters / segments -------------------------------------------------
    const onCount = useCallback(
        (tree: RuleTree, signal: AbortSignal) => countContacts({ ...allMatching, filters: tree }, signal),
        [allMatching],
    );

    const optionSources = useMemo<ConditionOptionSources>(
        () => ({
            lists: (q: string) =>
                fetchContactLists({ q: q || undefined, sort: 'name', per_page: 20 }).then((response) =>
                    response.data.map((list) => ({ value: list.id, label: list.name })),
                ),
            tags: (q: string) => {
                tagsCache.current ??= fetchTags().catch(() => {
                    tagsCache.current = null;

                    return [] as Tag[];
                });
                const needle = q.trim().toLocaleLowerCase('fr');

                return tagsCache.current.then((tags) =>
                    tags
                        .filter((tag) => needle === '' || tag.name.toLocaleLowerCase('fr').includes(needle))
                        .map((tag) => ({ value: tag.id, label: tag.name })),
                );
            },
        }),
        [],
    );

    async function saveSegment(name: string, tree: RuleTree): Promise<void> {
        try {
            const segment = await createSegment({ name, rules: tree });
            setNames((current) => new Map(current).set(segment.id, segment.name));
        } catch (error) {
            const status = httpStatus(error);
            if (status === 422) {
                const errors = validationErrors(error);
                const rules = Object.fromEntries(
                    Object.entries(errors)
                        .filter(([key]) => key.startsWith('rules.'))
                        .map(([key, message]) => [key.slice('rules.'.length), message]),
                );
                const failure: SegmentSaveErrors = {
                    name: errors.name,
                    rules: Object.keys(rules).length > 0 ? rules : undefined,
                    message: !errors.name && Object.keys(rules).length === 0 ? (Object.values(errors)[0] ?? t.common.genericError) : undefined,
                };
                throw failure;
            }
            const failure: SegmentSaveErrors = { message: status === 403 ? t.common.forbidden : t.common.genericError };
            throw failure;
        }
    }

    // --- Create contact -----------------------------------------------------
    async function handleCreate(values: CreateRecipientPayload) {
        setCreateErrors({});
        try {
            await createRecipient(values);
            setCreateOpen(false);
            reload();
        } catch (error) {
            const status = httpStatus(error);
            if (status === 409) {
                setCreateErrors({ email: t.contacts.alreadyExists });
            } else if (status === 422) {
                setCreateErrors(validationErrors(error));
            } else {
                setCreateErrors({ email: status === 403 ? t.common.forbidden : t.common.genericError });
            }
        }
    }

    // --- Render ---------------------------------------------------------------
    const targetName = table.target ? table.target.name || names.get(table.target.id) || table.target.id : '';
    const conditionCount = countConditions(table.filters);
    const narrowed = debouncedSearch !== '' || table.target !== null || table.filters !== null || scope === 'mine';
    const totalLabel =
        total !== null
            ? formatText(t.contacts.totalContacts, total)
            : nextCursor
              ? formatText(t.contacts.totalUnknown, rows.length + perPage * (cursors.length - 1))
              : formatText(t.contacts.totalContacts, rows.length + perPage * (cursors.length - 1));

    const headerActions = (
        <>
            {canImport && (
                <Button icon={<ArrowUploadRegular />} onClick={() => router.visit('/contacts/imports/new')}>
                    {t.contacts.importContacts}
                </Button>
            )}
            {canManage && (
                <Button
                    appearance="primary"
                    icon={<AddRegular />}
                    onClick={() => {
                        setCreateErrors({});
                        setCreateOpen(true);
                    }}
                >
                    {t.contacts.createContact}
                </Button>
            )}
        </>
    );

    return (
        <AppShell>
            <Head title={t.contacts.title} />
            <PageHelp topic="recipients" title={t.contacts.title} actions={headerActions} />

            <ContactViewTabs
                views={views}
                activeId={activeViewId}
                onSelect={applyView}
                onRename={() => setSaveViewMode('rename')}
                onDelete={(view) => setViewToDelete(view)}
            />

            <div className={styles.toolbar}>
                <Input
                    className={styles.search}
                    contentBefore={<SearchRegular />}
                    placeholder={t.contacts.searchPlaceholder}
                    aria-label={t.common.search}
                    value={search}
                    onChange={(_, data) => setSearch(data.value)}
                />
                <LoadListOrSegmentPopover
                    value={table.target}
                    onSelect={(target) => {
                        setNames((current) => new Map(current).set(target.id, target.name));
                        setTable((current) => ({ ...current, target }));
                    }}
                />
                <Button
                    icon={<FilterRegular />}
                    appearance={conditionCount > 0 ? 'primary' : 'secondary'}
                    onClick={() => setFilterOpen(true)}
                >
                    {t.contacts.addFilter}
                    {conditionCount > 0 && <CounterBadge count={conditionCount} size="small" appearance="ghost" />}
                </Button>
                <span className={styles.spacer} />
                <Button icon={<TableSettingsRegular />} appearance="subtle" onClick={() => setColumnsOpen(true)}>
                    {t.contacts.customizeColumns}
                </Button>
                {canExport && (
                    <Button
                        icon={<ArrowDownloadRegular />}
                        appearance="subtle"
                        onClick={() => setExportTarget({ selection: { all_matching: allMatching }, count: total })}
                    >
                        {t.contacts.export}
                    </Button>
                )}
                {modified && !activeView.is_system && (
                    <Button icon={<SaveRegular />} onClick={() => void saveActiveView()}>
                        {t.contacts.views.saveView}
                    </Button>
                )}
                <Button
                    icon={<SaveRegular />}
                    appearance={modified ? 'primary' : 'secondary'}
                    onClick={() => setSaveViewMode('create')}
                >
                    {t.contacts.views.saveAsView}
                </Button>
            </div>

            {(table.target || conditionCount > 0) && (
                <div className={styles.chips}>
                    {table.target && (
                        <Button
                            size="small"
                            shape="circular"
                            icon={<DismissRegular />}
                            iconPosition="after"
                            aria-label={`${t.contacts.clearScope} — ${targetName}`}
                            onClick={() => setTable((current) => ({ ...current, target: null }))}
                        >
                            {formatText(table.target.type === 'list' ? t.contacts.loadedList : t.contacts.loadedSegment, targetName)}
                        </Button>
                    )}
                    {conditionCount > 0 && (
                        <Button
                            size="small"
                            shape="circular"
                            icon={<DismissRegular />}
                            iconPosition="after"
                            aria-label={t.common.resetFilters}
                            onClick={() => setTable((current) => ({ ...current, filters: null }))}
                        >
                            {`${t.common.filters} (${conditionCount})`}
                        </Button>
                    )}
                </div>
            )}

            {modified && activeView.is_system && (
                <Text size={200} block className={styles.summary}>
                    {t.contacts.views.systemViewReadOnly}
                </Text>
            )}

            {feedback && (
                <MessageBar intent={feedback.intent} className={styles.feedback}>
                    <MessageBarBody>{feedback.text}</MessageBarBody>
                    <MessageBarActions
                        containerAction={
                            <Button appearance="transparent" icon={<DismissRegular />} aria-label={t.common.close} onClick={() => setFeedback(null)} />
                        }
                    />
                </MessageBar>
            )}

            {selectedCount > 0 && (
                <ContactBulkBar
                    selectedCount={selectedCount}
                    allMatching={selection.mode === 'all'}
                    matchingTotal={total}
                    canSelectAllMatching={pageFullySelected && total !== null && total > selectedCount}
                    onSelectAllMatching={() => setSelection({ mode: 'all', excluded: new Set() })}
                    onClear={clearSelection}
                    onAction={onBulkAction}
                    allowedActions={allowedBulkActions}
                />
            )}

            <div className={styles.card}>
                {loading && rows.length === 0 ? (
                    <Spinner className={styles.loading} label={t.common.loading} />
                ) : rows.length === 0 ? (
                    narrowed ? (
                        <EmptyState icon={ContactCardRegular} title={t.contacts.noMatch} />
                    ) : (
                        <EmptyState
                            icon={ContactCardRegular}
                            title={t.contacts.emptyTitle}
                            body={t.contacts.emptyBody}
                            action={headerActions}
                        />
                    )
                ) : (
                    <ContactsGrid
                        items={rows}
                        columns={table.columns}
                        catalog={catalogIndex}
                        listNames={names}
                        sort={table.sort}
                        onSortChange={(sort) => setTable((current) => ({ ...current, sort }))}
                        isSelected={isSelected}
                        onToggleRow={toggleRow}
                        onTogglePage={togglePage}
                        onColumnResize={onColumnResize}
                        onOpenContact={(contact) => router.visit(`/contacts/${contact.id}`)}
                        selectionDisabled={allowedBulkActions.length === 0}
                    />
                )}
                {rows.length > 0 && (
                    <div className={styles.footer}>
                        <Text className={styles.summary}>{totalLabel}</Text>
                        <div className={styles.pager}>
                            {loading && <Spinner size="tiny" />}
                            <div className={styles.perPage}>
                                <Text>{t.common.rowsPerPage}</Text>
                                <Dropdown
                                    className={styles.perPageDropdown}
                                    aria-label={t.common.rowsPerPage}
                                    value={String(perPage)}
                                    selectedOptions={[String(perPage)]}
                                    onOptionSelect={(_, data) => setPerPage(Number(data.optionValue ?? perPage))}
                                >
                                    {PAGE_SIZES.map((size) => (
                                        <Option key={size} value={String(size)}>
                                            {String(size)}
                                        </Option>
                                    ))}
                                </Dropdown>
                            </div>
                            <Button
                                icon={<ChevronLeftRegular />}
                                disabled={cursors.length <= 1 || loading}
                                onClick={() => setPaging({ key: matchSignature, stack: cursors.slice(0, -1) })}
                            >
                                {t.common.previous}
                            </Button>
                            <Button
                                icon={<ChevronRightRegular />}
                                iconPosition="after"
                                disabled={!nextCursor || loading}
                                onClick={() => nextCursor && setPaging({ key: matchSignature, stack: [...cursors, nextCursor] })}
                            >
                                {t.common.next}
                            </Button>
                        </div>
                    </div>
                )}
            </div>

            <ContactFilterDrawer
                open={filterOpen}
                onOpenChange={setFilterOpen}
                value={table.filters}
                onApply={(filters) => setTable((current) => ({ ...current, filters }))}
                fieldCatalog={fieldCatalog}
                onCount={onCount}
                optionSources={optionSources}
                canSaveSegment={canSaveSegment}
                onSaveSegment={saveSegment}
            />

            <ColumnChooserDialog
                open={columnsOpen}
                onOpenChange={setColumnsOpen}
                columns={table.columns}
                catalog={catalog}
                onApply={applyColumns}
            />

            <SaveViewDialog
                open={saveViewMode !== null}
                onOpenChange={(open) => !open && setSaveViewMode(null)}
                mode={saveViewMode ?? 'create'}
                initialName={saveViewMode === 'rename' && activeView ? contactViewLabel(activeView, t) : ''}
                initialShared={saveViewMode === 'rename' && activeView ? activeView.is_shared : false}
                onSubmit={submitViewDialog}
            />

            <Dialog open={viewToDelete !== null} onOpenChange={(_, data) => !data.open && setViewToDelete(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.contacts.views.deleteView}</DialogTitle>
                        <DialogContent>
                            <Text block weight="semibold">
                                {viewToDelete ? contactViewLabel(viewToDelete, t) : ''}
                            </Text>
                            <Text block>{t.common.deleteConfirmBody}</Text>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setViewToDelete(null)}>
                                {t.common.cancel}
                            </Button>
                            <Button appearance="primary" onClick={() => void confirmDeleteView()}>
                                {t.common.delete}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>

            <BulkActionDialog
                action={bulkAction}
                count={selectedCount}
                attributes={attributes}
                onCancel={() => setBulkAction(null)}
                onConfirm={runBulk}
            />

            <ExportContactsDialog
                open={exportTarget !== null}
                onOpenChange={(open) => !open && setExportTarget(null)}
                selection={exportTarget?.selection ?? null}
                count={exportTarget?.count ?? null}
                defaultColumns={columnKeys}
                catalog={catalog}
            />

            <RecipientFormDialog open={createOpen} onOpenChange={setCreateOpen} onSubmit={handleCreate} errors={createErrors} />
        </AppShell>
    );
}
