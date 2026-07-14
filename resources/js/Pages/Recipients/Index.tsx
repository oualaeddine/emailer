import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
    Input,
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
import { AddRegular, SearchRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { createRecipient, fetchRecipients, type CreateRecipientPayload } from '@/Lib/api/recipients';
import type { Recipient } from '@/Lib/types/recipients';
import { RecipientFormDialog } from '@/Pages/Recipients/RecipientFormDialog';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    searchBar: {
        maxWidth: '360px',
        marginBottom: tokens.spacingVerticalM,
    },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
    },
});

const STATUS_COLOR: Record<string, 'success' | 'danger' | 'warning'> = {
    active: 'success',
    suppressed: 'danger',
    invalid: 'warning',
};

/**
 * docs/13-recipient-management.md, docs/29-api-specification.md §29.4.
 */
export default function Index() {
    const styles = useStyles();
    const t = useText();

    const [recipients, setRecipients] = useState<Recipient[]>([]);
    const [query, setQuery] = useState('');
    const [dialogOpen, setDialogOpen] = useState(false);
    const [formErrors, setFormErrors] = useState<Partial<Record<string, string>>>({});

    const load = useCallback(async (q = '') => {
        const response = await fetchRecipients(q ? { q } : {});
        setRecipients(response.data);
    }, []);

    useEffect(() => {
        void load();
    }, [load]);

    async function handleCreate(values: CreateRecipientPayload) {
        setFormErrors({});
        try {
            await createRecipient(values);
            setDialogOpen(false);
            await load(query);
        } catch (error) {
            if (isConflict(error)) {
                setFormErrors({ email: t.recipients.alreadyExists });
            } else if (isValidationError(error)) {
                setFormErrors(flattenValidationErrors(error));
            } else {
                throw error;
            }
        }
    }

    const columns: TableColumnDefinition<Recipient>[] = [
        createTableColumn<Recipient>({
            columnId: 'name',
            renderHeaderCell: () => `${t.recipients.firstName} / ${t.recipients.lastName}`,
            renderCell: (r) => [r.first_name, r.last_name].filter(Boolean).join(' ') || '—',
        }),
        createTableColumn<Recipient>({
            columnId: 'email',
            renderHeaderCell: () => t.recipients.email,
            renderCell: (r) => r.email,
        }),
        createTableColumn<Recipient>({
            columnId: 'company',
            renderHeaderCell: () => t.recipients.companyName,
            renderCell: (r) => r.company_name ?? '—',
        }),
        createTableColumn<Recipient>({
            columnId: 'status',
            renderHeaderCell: () => t.recipients.status,
            renderCell: (r) => <Badge color={STATUS_COLOR[r.status] ?? 'informative'}>{r.status}</Badge>,
        }),
        createTableColumn<Recipient>({
            columnId: 'tags',
            renderHeaderCell: () => t.recipients.tags,
            renderCell: (r) => r.tags.map((tag) => tag.name).join(', ') || '—',
        }),
    ];

    return (
        <AppShell>
            <Head title={t.recipients.title} />
            <div className={styles.header}>
                <Title1>{t.recipients.title}</Title1>
                <Toolbar>
                    <Button appearance="primary" icon={<AddRegular />} onClick={() => setDialogOpen(true)}>
                        {t.recipients.newRecipient}
                    </Button>
                </Toolbar>
            </div>
            <Input
                className={styles.searchBar}
                contentBefore={<SearchRegular />}
                placeholder={t.recipients.searchPlaceholder}
                value={query}
                onChange={(_, data) => {
                    setQuery(data.value);
                    void load(data.value);
                }}
            />
            <div className={styles.card}>
                <DataGrid items={recipients} columns={columns} getRowId={(r) => r.id} resizableColumns>
                    <DataGridHeader>
                        <DataGridRow>
                            {({ renderHeaderCell }) => (
                                <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>
                            )}
                        </DataGridRow>
                    </DataGridHeader>
                    <DataGridBody<Recipient>>
                        {({ item, rowId }) => (
                            <DataGridRow<Recipient> key={rowId}>
                                {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                            </DataGridRow>
                        )}
                    </DataGridBody>
                </DataGrid>
            </div>
            <RecipientFormDialog
                open={dialogOpen}
                onOpenChange={setDialogOpen}
                onSubmit={handleCreate}
                errors={formErrors}
            />
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
