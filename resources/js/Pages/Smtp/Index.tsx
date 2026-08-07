import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
    Toolbar,
    Title1,
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
import { AddRegular, PlugConnectedRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { createSmtpAccount, fetchSmtpAccounts, testSmtpAccount, type CreateSmtpAccountPayload } from '@/Lib/api/smtp';
import type { SmtpAccount } from '@/Lib/types/smtp';
import { SmtpAccountFormDialog } from '@/Pages/Smtp/SmtpAccountFormDialog';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalL,
    },
    card: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        overflowX: 'auto',
    },
    gridScroll: {
        minWidth: '640px',
    },
});

const HEALTH_COLOR: Record<string, 'success' | 'warning' | 'danger' | 'subtle'> = {
    healthy: 'success',
    degraded: 'warning',
    unhealthy: 'danger',
    disabled: 'subtle',
};

/**
 * docs/18-smtp-management.md §18.6 — SMTP Accounts List View.
 */
export default function SmtpIndex() {
    const styles = useStyles();
    const t = useText();

    const [accounts, setAccounts] = useState<SmtpAccount[]>([]);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [testResults, setTestResults] = useState<Record<string, { success: boolean; message: string }>>({});

    const load = useCallback(async () => {
        setAccounts(await fetchSmtpAccounts());
    }, []);

    useEffect(() => {
        void load();
    }, [load]);

    async function handleCreate(values: CreateSmtpAccountPayload) {
        await createSmtpAccount(values);
        setDialogOpen(false);
        await load();
    }

    async function handleTest(id: string) {
        const result = await testSmtpAccount(id);
        setTestResults((prev) => ({ ...prev, [id]: { success: result.success, message: result.raw_response } }));
    }

    const columns: TableColumnDefinition<SmtpAccount>[] = [
        createTableColumn<SmtpAccount>({
            columnId: 'name',
            renderHeaderCell: () => t.smtp.name,
            renderCell: (a) => a.name,
        }),
        createTableColumn<SmtpAccount>({
            columnId: 'host',
            renderHeaderCell: () => t.smtp.host,
            renderCell: (a) => `${a.host}:${a.port}`,
        }),
        createTableColumn<SmtpAccount>({
            columnId: 'health',
            renderHeaderCell: () => t.smtp.health,
            renderCell: (a) => (
                <Badge appearance="tint" color={HEALTH_COLOR[a.health_status]}>
                    {t.smtp[a.health_status]}
                </Badge>
            ),
        }),
        createTableColumn<SmtpAccount>({
            columnId: 'quota',
            renderHeaderCell: () => t.smtp.dailyQuota,
            renderCell: (a) => (a.daily_quota ? String(a.daily_quota) : '—'),
        }),
        createTableColumn<SmtpAccount>({
            columnId: 'actions',
            renderHeaderCell: () => t.common.actions,
            renderCell: (a) => (
                <Button icon={<PlugConnectedRegular />} appearance="subtle" onClick={() => handleTest(a.id)}>
                    {t.smtp.test}
                    {testResults[a.id] && (
                        <span style={{ marginLeft: 8, color: testResults[a.id].success ? 'green' : 'red' }}>
                            {testResults[a.id].success ? '✓' : '✗'}
                        </span>
                    )}
                </Button>
            ),
        }),
    ];

    return (
        <AppShell>
            <Head title={t.smtp.title} />
            <div className={styles.header}>
                <Title1>{t.smtp.title}</Title1>
                <Toolbar>
                    <Button appearance="primary" icon={<AddRegular />} onClick={() => setDialogOpen(true)}>
                        {t.smtp.newAccount}
                    </Button>
                </Toolbar>
            </div>
            <div className={styles.card}>
                <div className={styles.gridScroll}>
                <DataGrid items={accounts} columns={columns} getRowId={(a) => a.id} resizableColumns>
                    <DataGridHeader>
                        <DataGridRow>
                            {({ renderHeaderCell }) => <DataGridHeaderCell>{renderHeaderCell()}</DataGridHeaderCell>}
                        </DataGridRow>
                    </DataGridHeader>
                    <DataGridBody<SmtpAccount>>
                        {({ item, rowId }) => (
                            <DataGridRow<SmtpAccount> key={rowId}>
                                {({ renderCell }) => <DataGridCell>{renderCell(item)}</DataGridCell>}
                            </DataGridRow>
                        )}
                    </DataGridBody>
                </DataGrid>
                </div>
            </div>
            <SmtpAccountFormDialog open={dialogOpen} onOpenChange={setDialogOpen} onSubmit={handleCreate} />
        </AppShell>
    );
}
