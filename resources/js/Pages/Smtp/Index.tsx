import { useCallback, useEffect, useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
    DataGrid,
    DataGridBody,
    DataGridCell,
    DataGridHeader,
    DataGridHeaderCell,
    DataGridRow,
    MessageBar,
    MessageBarActions,
    MessageBarBody,
    MessageBarTitle,
    Spinner,
    createTableColumn,
    makeStyles,
    tokens,
    type TableColumnDefinition,
} from '@fluentui/react-components';
import {
    AddRegular,
    CheckmarkCircleRegular,
    DismissCircleRegular,
    DismissRegular,
    PlugConnectedRegular,
} from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { PageHelp } from '@/Components/Help/PageHelp';
import { useText } from '@/Hooks/useText';
import { createSmtpAccount, fetchSmtpAccounts, testSmtpAccount, type CreateSmtpAccountPayload } from '@/Lib/api/smtp';
import type { SmtpAccount } from '@/Lib/types/smtp';
import { SmtpAccountFormDialog } from '@/Pages/Smtp/SmtpAccountFormDialog';
import { SmtpTestResultDialog } from '@/Pages/Smtp/SmtpTestResultDialog';

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
    bannerWrapper: {
        marginBottom: tokens.spacingVerticalM,
    },
    actionsCell: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
    },
});

const HEALTH_COLOR: Record<string, 'success' | 'warning' | 'danger' | 'subtle'> = {
    healthy: 'success',
    degraded: 'warning',
    unhealthy: 'danger',
    disabled: 'subtle',
};

interface ActiveResultDialogState {
    account: SmtpAccount;
    success: boolean;
    rawResponse: string;
}

interface BannerMessageState {
    intent: 'error' | 'success';
    title: string;
    body: string;
    account: SmtpAccount;
}

/**
 * docs/18-smtp-management.md §18.6 — SMTP Accounts List View.
 */
export default function SmtpIndex() {
    const styles = useStyles();
    const t = useText();

    const [accounts, setAccounts] = useState<SmtpAccount[]>([]);
    const [dialogOpen, setDialogOpen] = useState(false);
    const [testResults, setTestResults] = useState<Record<string, { success: boolean; message: string }>>({});
    const [testingAccountId, setTestingAccountId] = useState<string | null>(null);
    const [activeResultDialog, setActiveResultDialog] = useState<ActiveResultDialogState | null>(null);
    const [bannerMessage, setBannerMessage] = useState<BannerMessageState | null>(null);

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

    async function handleTest(account: SmtpAccount) {
        setTestingAccountId(account.id);
        try {
            const result = await testSmtpAccount(account.id);
            setTestResults((prev) => ({
                ...prev,
                [account.id]: { success: result.success, message: result.raw_response },
            }));

            setActiveResultDialog({
                account,
                success: result.success,
                rawResponse: result.raw_response,
            });

            if (!result.success) {
                setBannerMessage({
                    intent: 'error',
                    title: `${t.smtp.testFailure} — ${account.name}`,
                    body: result.raw_response,
                    account,
                });
            } else {
                setBannerMessage({
                    intent: 'success',
                    title: `${t.smtp.testSuccess} — ${account.name}`,
                    body: result.raw_response,
                    account,
                });
            }

            // Refresh accounts list to reflect updated last_tested_at
            void load();
        } catch (error: any) {
            const errorMsg =
                error?.response?.data?.message || error?.message || 'Erreur réseau de communication avec le serveur.';
            setTestResults((prev) => ({
                ...prev,
                [account.id]: { success: false, message: errorMsg },
            }));

            setActiveResultDialog({
                account,
                success: false,
                rawResponse: errorMsg,
            });

            setBannerMessage({
                intent: 'error',
                title: `${t.smtp.testFailure} — ${account.name}`,
                body: errorMsg,
                account,
            });
        } finally {
            setTestingAccountId(null);
        }
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
            renderCell: (a) => {
                const isTesting = testingAccountId === a.id;
                const result = testResults[a.id];

                return (
                    <div className={styles.actionsCell}>
                        <Button
                            icon={isTesting ? <Spinner size="tiny" /> : <PlugConnectedRegular />}
                            appearance="subtle"
                            disabled={isTesting}
                            onClick={() => handleTest(a)}
                        >
                            {isTesting ? t.smtp.testing : t.smtp.test}
                        </Button>
                        {result && (
                            <Button
                                size="small"
                                appearance="subtle"
                                icon={
                                    result.success ? (
                                        <CheckmarkCircleRegular style={{ color: tokens.colorPaletteGreenForeground1 }} />
                                    ) : (
                                        <DismissCircleRegular style={{ color: tokens.colorPaletteRedForeground1 }} />
                                    )
                                }
                                style={{
                                    color: result.success
                                        ? tokens.colorPaletteGreenForeground1
                                        : tokens.colorPaletteRedForeground1,
                                    fontWeight: tokens.fontWeightSemibold,
                                }}
                                onClick={() =>
                                    setActiveResultDialog({
                                        account: a,
                                        success: result.success,
                                        rawResponse: result.message,
                                    })
                                }
                            >
                                {result.success ? t.smtp.viewSuccess : t.smtp.viewError}
                            </Button>
                        )}
                    </div>
                );
            },
        }),
    ];

    return (
        <AppShell>
            <Head title={t.smtp.title} />
            <PageHelp
                topic="smtp"
                title={t.smtp.title}
                actions={
                    <Button appearance="primary" icon={<AddRegular />} onClick={() => setDialogOpen(true)}>
                        {t.smtp.newAccount}
                    </Button>
                }
            />

            {bannerMessage && (
                <div className={styles.bannerWrapper}>
                    <MessageBar intent={bannerMessage.intent}>
                        <MessageBarBody>
                            <MessageBarTitle>{bannerMessage.title}</MessageBarTitle>
                            <div
                                style={{
                                    fontFamily: tokens.fontFamilyMonospace,
                                    fontSize: tokens.fontSizeBase200,
                                    whiteSpace: 'pre-wrap',
                                    wordBreak: 'break-word',
                                    maxHeight: '80px',
                                    overflowY: 'auto',
                                    marginTop: tokens.spacingVerticalXXS,
                                }}
                            >
                                {bannerMessage.body}
                            </div>
                        </MessageBarBody>
                        <MessageBarActions
                            containerAction={
                                <Button
                                    appearance="transparent"
                                    icon={<DismissRegular />}
                                    aria-label={t.common.close}
                                    onClick={() => setBannerMessage(null)}
                                />
                            }
                        >
                            <Button
                                appearance="subtle"
                                onClick={() =>
                                    setActiveResultDialog({
                                        account: bannerMessage.account,
                                        success: bannerMessage.intent === 'success',
                                        rawResponse: bannerMessage.body,
                                    })
                                }
                            >
                                {t.smtp.testDetails}
                            </Button>
                        </MessageBarActions>
                    </MessageBar>
                </div>
            )}

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

            <SmtpTestResultDialog
                open={activeResultDialog !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setActiveResultDialog(null);
                    }
                }}
                account={activeResultDialog?.account ?? null}
                success={activeResultDialog?.success ?? false}
                rawResponse={activeResultDialog?.rawResponse ?? ''}
                isRetrying={activeResultDialog ? testingAccountId === activeResultDialog.account.id : false}
                onRetry={
                    activeResultDialog
                        ? () => handleTest(activeResultDialog.account)
                        : undefined
                }
            />
        </AppShell>
    );
}
