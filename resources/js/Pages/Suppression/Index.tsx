import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
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
    Title1,
    Toolbar,
    DataGrid,
    DataGridBody,
    DataGridCell,
    DataGridHeader,
    DataGridHeaderCell,
    DataGridRow,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import { AddRegular, DeleteRegular, SearchRegular, ShieldProhibitedRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import {
    createSuppressionEntry,
    deleteSuppressionEntry,
    listSuppressionEntries,
    type CreateSuppressionEntryPayload,
} from '@/Lib/api/suppression';
import type { SuppressionEntry, SuppressionReason } from '@/Lib/types/suppression';
import { SuppressionEntryFormDialog } from '@/Pages/Suppression/SuppressionEntryFormDialog';

const REASON_COLOR: Record<SuppressionReason, 'danger' | 'severe' | 'warning' | 'informative' | 'brand' | 'subtle'> = {
    hard_bounce: 'danger',
    spam_complaint: 'severe',
    invalid_address: 'warning',
    manual_unsubscribe: 'informative',
    global_unsubscribe: 'brand',
    manual_block: 'subtle',
};

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    filters: {
        display: 'flex',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalM,
    },
    search: {
        maxWidth: '360px',
    },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    gridScroll: {
        minWidth: '560px',
    },
});

// docs/23-suppression-list.md §23.7 — complaint/bounce-based entries get a
// stronger confirmation copy on removal; reversible reasons keep the
// default confirmation text. Removal itself is permission-gated the same
// way for every reason (not restricted here).
const STRONG_CONFIRM_REASONS: SuppressionReason[] = ['hard_bounce', 'spam_complaint'];

function formatDate(value: string): string {
    return new Date(value).toLocaleDateString('fr-FR', { year: 'numeric', month: '2-digit', day: '2-digit' });
}

/**
 * docs/23-suppression-list.md §23.5 — Suppression List Management UI.
 * Bulk import / CSV export (also mentioned in §23.5) are out of scope for
 * WP-14 (frontend-only list + add + remove-with-confirm per the work
 * package brief); left for a future work package.
 */
export default function SuppressionIndex() {
    const styles = useStyles();
    const t = useText();

    const [entries, setEntries] = useState<SuppressionEntry[]>([]);
    const [search, setSearch] = useState('');
    const [reasonFilter, setReasonFilter] = useState<SuppressionReason | ''>('');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [formErrors, setFormErrors] = useState<Partial<Record<string, string>>>({});
    const [removeTarget, setRemoveTarget] = useState<SuppressionEntry | null>(null);
    const [removing, setRemoving] = useState(false);

    const load = useCallback(async (nextSearch: string, nextReason: SuppressionReason | '') => {
        setEntries(await listSuppressionEntries({ search: nextSearch, reason: nextReason }));
    }, []);

    useEffect(() => {
        void load('', '');
    }, [load]);

    async function handleCreate(values: CreateSuppressionEntryPayload) {
        setFormErrors({});
        try {
            await createSuppressionEntry(values);
            setDialogOpen(false);
            await load(search, reasonFilter);
        } catch (error) {
            if (isConflict(error)) {
                setFormErrors({ email: t.suppression.alreadyExists });
            } else if (isValidationError(error)) {
                setFormErrors(flattenValidationErrors(error));
            } else {
                throw error;
            }
        }
    }

    async function handleConfirmRemove() {
        if (!removeTarget) {
            return;
        }
        setRemoving(true);
        try {
            await deleteSuppressionEntry(removeTarget.id);
            setRemoveTarget(null);
            await load(search, reasonFilter);
        } finally {
            setRemoving(false);
        }
    }

    const columns: TableColumnDefinition<SuppressionEntry>[] = [
        createTableColumn<SuppressionEntry>({
            columnId: 'email',
            renderHeaderCell: () => t.suppression.email,
            renderCell: (entry) => entry.email,
        }),
        createTableColumn<SuppressionEntry>({
            columnId: 'reason',
            renderHeaderCell: () => t.suppression.reason,
            renderCell: (entry) => (
                <Badge appearance="tint" color={REASON_COLOR[entry.reason]}>
                    {t.suppression.reasons[entry.reason]}
                </Badge>
            ),
        }),
        createTableColumn<SuppressionEntry>({
            columnId: 'dateAdded',
            renderHeaderCell: () => t.suppression.dateAdded,
            renderCell: (entry) => formatDate(entry.created_at),
        }),
        createTableColumn<SuppressionEntry>({
            columnId: 'actions',
            renderHeaderCell: () => t.common.actions,
            renderCell: (entry) => (
                <Button icon={<DeleteRegular />} appearance="subtle" onClick={() => setRemoveTarget(entry)}>
                    {t.suppression.remove}
                </Button>
            ),
        }),
    ];

    return (
        <AppShell>
            <Head title={t.suppression.title} />
            <div className={styles.header}>
                <Title1>{t.suppression.title}</Title1>
                <Toolbar>
                    <Button appearance="primary" icon={<AddRegular />} onClick={() => setDialogOpen(true)}>
                        {t.suppression.newEntry}
                    </Button>
                </Toolbar>
            </div>
            <div className={styles.filters}>
                <Input
                    className={styles.search}
                    contentBefore={<SearchRegular />}
                    placeholder={t.suppression.searchPlaceholder}
                    value={search}
                    onChange={(_, data) => {
                        setSearch(data.value);
                        void load(data.value, reasonFilter);
                    }}
                />
                <Dropdown
                    value={reasonFilter ? t.suppression.reasons[reasonFilter] : t.suppression.allReasons}
                    onOptionSelect={(_, data) => {
                        const next = (data.optionValue as SuppressionReason | '') ?? '';
                        setReasonFilter(next);
                        void load(search, next);
                    }}
                >
                    <Option value="">{t.suppression.allReasons}</Option>
                    <Option value="manual_block">{t.suppression.reasons.manual_block}</Option>
                    <Option value="hard_bounce">{t.suppression.reasons.hard_bounce}</Option>
                    <Option value="spam_complaint">{t.suppression.reasons.spam_complaint}</Option>
                    <Option value="manual_unsubscribe">{t.suppression.reasons.manual_unsubscribe}</Option>
                    <Option value="global_unsubscribe">{t.suppression.reasons.global_unsubscribe}</Option>
                    <Option value="invalid_address">{t.suppression.reasons.invalid_address}</Option>
                </Dropdown>
            </div>
            <div className={styles.card}>
                {entries.length === 0 ? (
                    <EmptyState icon={ShieldProhibitedRegular} title={t.suppression.noResults} />
                ) : (
                    <div className={styles.gridScroll}>
                    <DataGrid items={entries} columns={columns} getRowId={(entry) => entry.id} resizableColumns>
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
                    </div>
                )}
            </div>
            <SuppressionEntryFormDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                onSubmit={handleCreate}
                errors={formErrors}
            />
            <Dialog open={removeTarget !== null} onOpenChange={(_, data) => !data.open && setRemoveTarget(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{t.suppression.remove}</DialogTitle>
                        <DialogContent>
                            {removeTarget && STRONG_CONFIRM_REASONS.includes(removeTarget.reason)
                                ? t.suppression.confirmRemoveStrong
                                : t.suppression.confirmRemove}
                        </DialogContent>
                        <DialogActions>
                            <DialogTrigger disableButtonEnhancement>
                                <Button appearance="secondary">{t.common.cancel}</Button>
                            </DialogTrigger>
                            <Button appearance="primary" disabled={removing} onClick={() => void handleConfirmRemove()}>
                                {t.common.confirm}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}

interface HttpErrorResponse {
    response?: { status?: number; data?: { errors?: Record<string, string[]> } };
}

function isConflict(error: unknown): error is HttpErrorResponse {
    return typeof error === 'object' && error !== null && (error as HttpErrorResponse).response?.status === 409;
}

function isValidationError(error: unknown): error is HttpErrorResponse {
    return typeof error === 'object' && error !== null && (error as HttpErrorResponse).response?.status === 422;
}

function flattenValidationErrors(error: HttpErrorResponse): Record<string, string> {
    const errors = error.response?.data?.errors ?? {};

    return Object.fromEntries(Object.entries(errors).map(([field, messages]) => [field, messages[0]]));
}
