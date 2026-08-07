import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
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
    DialogTrigger,
    Field,
    Input,
    Spinner,
    Text,
    Title1,
    Toolbar,
    Tooltip,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import { ArrowDownloadRegular, EyeRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { exportAuditLogs, fetchAuditLogs } from '@/Lib/api/audit';
import type { AuditLog as AuditLogRow, AuditLogFilters } from '@/Lib/types/audit';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    filters: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalM,
        alignItems: 'flex-end',
    },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    gridScroll: {
        minWidth: '680px',
    },
    truncatedCell: {
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
        display: 'block',
        maxWidth: '220px',
    },
    footer: {
        display: 'flex',
        justifyContent: 'center',
        marginTop: tokens.spacingVerticalM,
    },
    diffGrid: {
        display: 'grid',
        gridTemplateColumns: '1fr 1fr',
        gap: tokens.spacingHorizontalM,
        '@media (max-width: 639px)': {
            gridTemplateColumns: '1fr',
        },
    },
    pre: {
        backgroundColor: tokens.colorNeutralBackground3,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalS,
        overflow: 'auto',
        maxHeight: '320px',
        fontSize: '12px',
        margin: 0,
    },
});

/**
 * docs/27-audit-logs.md §27.7 — Audit Log Viewer UI: filterable grid (user,
 * action, date range, affected entity type) with a detail view rendering
 * the `old_values`/`new_values` diff, plus a CSV export action.
 *
 * Route-gated server-side (`audit.view`/`audit.export`); the nav item
 * itself is hidden client-side for users without `audit.view`
 * (docs/08-navigation.md §8.4).
 *
 * The diff view here is deliberately simple — two side-by-side `<pre>`
 * JSON blocks, not a key-by-key before/after comparison as §27.7
 * describes — since no diffing library is present in this codebase and
 * adding one is out of scope for this work package.
 */
export default function AuditLog() {
    const styles = useStyles();
    const t = useText();

    const [rows, setRows] = useState<AuditLogRow[]>([]);
    const [nextCursor, setNextCursor] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [exporting, setExporting] = useState(false);
    const [selected, setSelected] = useState<AuditLogRow | null>(null);

    const [filters, setFilters] = useState<AuditLogFilters>({});
    const [appliedFilters, setAppliedFilters] = useState<AuditLogFilters>({});

    const load = useCallback(async (activeFilters: AuditLogFilters, cursor: string | null = null) => {
        setLoading(true);
        try {
            const response = await fetchAuditLogs(activeFilters, cursor);
            setRows((current) => (cursor ? [...current, ...response.data] : response.data));
            setNextCursor(response.meta.next_cursor);
        } finally {
            setLoading(false);
        }
    }, []);

    useEffect(() => {
        void load({});
    }, [load]);

    function applyFilters() {
        setAppliedFilters(filters);
        void load(filters);
    }

    function resetFilters() {
        setFilters({});
        setAppliedFilters({});
        void load({});
    }

    async function loadMore() {
        if (nextCursor) {
            await load(appliedFilters, nextCursor);
        }
    }

    async function handleExport() {
        setExporting(true);
        try {
            await exportAuditLogs(appliedFilters);
        } finally {
            setExporting(false);
        }
    }

    const columns: TableColumnDefinition<AuditLogRow>[] = [
        createTableColumn<AuditLogRow>({
            columnId: 'timestamp',
            renderHeaderCell: () => t.audit.timestamp,
            renderCell: (row) => new Date(row.created_at).toLocaleString('fr-FR'),
        }),
        createTableColumn<AuditLogRow>({
            columnId: 'actor',
            renderHeaderCell: () => t.audit.actor,
            renderCell: (row) => row.user ?? t.audit.system,
        }),
        createTableColumn<AuditLogRow>({
            columnId: 'action',
            renderHeaderCell: () => t.audit.action,
            renderCell: (row) => (
                <Tooltip content={row.action} relationship="label">
                    <Text className={styles.truncatedCell}>{row.action}</Text>
                </Tooltip>
            ),
        }),
        createTableColumn<AuditLogRow>({
            columnId: 'target',
            renderHeaderCell: () => t.audit.target,
            renderCell: (row) => {
                const label = `${row.auditable_type.split('\\').pop()} #${row.auditable_id}`;

                return (
                    <Tooltip content={label} relationship="label">
                        <Text className={styles.truncatedCell}>{label}</Text>
                    </Tooltip>
                );
            },
        }),
        createTableColumn<AuditLogRow>({
            columnId: 'details',
            renderHeaderCell: () => t.common.actions,
            renderCell: (row) => (
                <Button
                    icon={<EyeRegular />}
                    appearance="subtle"
                    aria-label={t.audit.viewDetails}
                    onClick={() => setSelected(row)}
                />
            ),
        }),
    ];

    return (
        <AppShell>
            <Head title={t.audit.title} />
            <div className={styles.header}>
                <Title1>{t.audit.title}</Title1>
                <Toolbar>
                    <Button
                        appearance="primary"
                        icon={<ArrowDownloadRegular />}
                        onClick={handleExport}
                        disabled={exporting}
                    >
                        {t.audit.export}
                    </Button>
                </Toolbar>
            </div>
            <div className={styles.filters}>
                <Field label={t.audit.userIdFilter}>
                    <Input
                        type="number"
                        value={filters.user_id != null ? String(filters.user_id) : ''}
                        onChange={(_, data) =>
                            setFilters((f) => ({
                                ...f,
                                user_id: data.value ? Number(data.value) : undefined,
                            }))
                        }
                    />
                </Field>
                <Field label={t.audit.actionFilter}>
                    <Input
                        value={filters.action ?? ''}
                        onChange={(_, data) => setFilters((f) => ({ ...f, action: data.value || undefined }))}
                    />
                </Field>
                <Field label={t.audit.auditableTypeFilter}>
                    <Input
                        value={filters.auditable_type ?? ''}
                        onChange={(_, data) =>
                            setFilters((f) => ({ ...f, auditable_type: data.value || undefined }))
                        }
                    />
                </Field>
                <Field label={t.audit.dateFromFilter}>
                    <Input
                        type="date"
                        value={filters.date_from ?? ''}
                        onChange={(_, data) => setFilters((f) => ({ ...f, date_from: data.value || undefined }))}
                    />
                </Field>
                <Field label={t.audit.dateToFilter}>
                    <Input
                        type="date"
                        value={filters.date_to ?? ''}
                        onChange={(_, data) => setFilters((f) => ({ ...f, date_to: data.value || undefined }))}
                    />
                </Field>
                <Button appearance="primary" onClick={applyFilters}>
                    {t.common.confirm}
                </Button>
                <Button appearance="secondary" onClick={resetFilters}>
                    {t.audit.resetFilters}
                </Button>
            </div>
            <div className={styles.card}>
                {loading && rows.length === 0 ? (
                    <Spinner label={t.common.loading} />
                ) : rows.length === 0 ? (
                    <Text>{t.audit.noResults}</Text>
                ) : (
                    <div className={styles.gridScroll}>
                    <DataGrid items={rows} columns={columns} getRowId={(row) => row.id} resizableColumns>
                        <DataGridHeader>
                            <DataGridRow>
                                {({ renderHeaderCell }) => (
                                    <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>
                                )}
                            </DataGridRow>
                        </DataGridHeader>
                        <DataGridBody<AuditLogRow>>
                            {({ item, rowId }) => (
                                <DataGridRow<AuditLogRow> key={rowId}>
                                    {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                                </DataGridRow>
                            )}
                        </DataGridBody>
                    </DataGrid>
                    </div>
                )}
                {nextCursor && (
                    <div className={styles.footer}>
                        <Button appearance="secondary" onClick={loadMore} disabled={loading}>
                            {loading ? t.common.loading : t.audit.loadMore}
                        </Button>
                    </div>
                )}
            </div>
            <Dialog open={selected != null} onOpenChange={(_, data) => !data.open && setSelected(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.audit.detailsTitle}</DialogTitle>
                        <DialogContent>
                            {selected && (
                                <>
                                    <Text block weight="semibold">
                                        {selected.action} — {selected.auditable_type.split('\\').pop()} #
                                        {selected.auditable_id}
                                    </Text>
                                    <Text block size={200}>
                                        {selected.user ?? t.audit.system} · {selected.ip_address} ·{' '}
                                        {new Date(selected.created_at).toLocaleString('fr-FR')}
                                    </Text>
                                    <div className={styles.diffGrid}>
                                        <div>
                                            <Text block weight="semibold">
                                                {t.audit.oldValues}
                                            </Text>
                                            <pre className={styles.pre}>
                                                {selected.old_values
                                                    ? JSON.stringify(selected.old_values, null, 2)
                                                    : t.audit.noValues}
                                            </pre>
                                        </div>
                                        <div>
                                            <Text block weight="semibold">
                                                {t.audit.newValues}
                                            </Text>
                                            <pre className={styles.pre}>
                                                {selected.new_values
                                                    ? JSON.stringify(selected.new_values, null, 2)
                                                    : t.audit.noValues}
                                            </pre>
                                        </div>
                                    </div>
                                </>
                            )}
                        </DialogContent>
                        <DialogActions>
                            <DialogTrigger disableButtonEnhancement>
                                <Button appearance="secondary">{t.audit.close}</Button>
                            </DialogTrigger>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
