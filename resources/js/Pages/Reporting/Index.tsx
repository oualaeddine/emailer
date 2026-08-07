import { useCallback, useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    Button,
    Dropdown,
    Input,
    Option,
    Spinner,
    Text,
    Title1,
    Title2,
    Toolbar,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { ArrowDownloadRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { hasPermission } from '@/Lib/permissions';
import { fetchCampaigns } from '@/Lib/api/campaigns';
import { fetchSmtpAccounts } from '@/Lib/api/smtp';
import {
    fetchReportingByCampaign,
    fetchReportingBySmtpAccount,
    fetchReportingSummary,
    reportingExportUrl,
} from '@/Lib/api/reporting';
import type { AuthenticatedUser } from '@/Lib/types/identity';
import type { Campaign } from '@/Lib/types/campaigns';
import type { SmtpAccount } from '@/Lib/types/smtp';
import type {
    ReportingCampaignRow,
    ReportingFilters,
    ReportingSmtpAccountRow,
    ReportingSummary,
} from '@/Lib/types/reporting';

const useStyles = makeStyles({
    header: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        marginBottom: tokens.spacingVerticalM,
    },
    subtitle: {
        color: tokens.colorNeutralForeground3,
        marginBottom: tokens.spacingVerticalL,
    },
    filters: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'flex-end',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalL,
    },
    filterField: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        minWidth: '200px',
    },
    section: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalL,
        marginBottom: tokens.spacingVerticalL,
    },
    cardGrid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(160px, 1fr))',
        gap: tokens.spacingHorizontalM,
    },
    card: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
    },
    row: {
        display: 'flex',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalM,
        padding: tokens.spacingVerticalXS,
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorNeutralStroke2,
    },
    rowHeader: {
        fontWeight: tokens.fontWeightSemibold,
    },
});

function formatPercent(rate: number): string {
    return `${(rate * 100).toFixed(1)} %`;
}

function todayIso(): string {
    return new Date().toISOString().slice(0, 10);
}

function thirtyDaysAgoIso(): string {
    const date = new Date();
    date.setDate(date.getDate() - 30);

    return date.toISOString().slice(0, 10);
}

/**
 * docs/25-reporting.md §25.3 — Report UI Pattern: filter bar → summary stat
 * tiles → detail data table. No charting library is present in this
 * codebase (per this work package's brief), so trend/comparison figures are
 * rendered as plain numeric rows, mirroring
 * `resources/js/Pages/Campaigns/CampaignDetail.tsx`'s Analytics tab.
 */
export default function ReportingIndex() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth: { user: AuthenticatedUser; permissions: string[] } }>();
    const permissions = props.auth.permissions;
    const canExport = hasPermission(permissions, 'reporting.export');

    const [dateFrom, setDateFrom] = useState(thirtyDaysAgoIso());
    const [dateTo, setDateTo] = useState(todayIso());
    const [campaignId, setCampaignId] = useState('');
    const [smtpAccountId, setSmtpAccountId] = useState('');

    const [campaigns, setCampaigns] = useState<Campaign[]>([]);
    const [smtpAccounts, setSmtpAccounts] = useState<SmtpAccount[]>([]);

    const [summary, setSummary] = useState<ReportingSummary | null>(null);
    const [byCampaign, setByCampaign] = useState<ReportingCampaignRow[] | null>(null);
    const [bySmtpAccount, setBySmtpAccount] = useState<ReportingSmtpAccountRow[] | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        void fetchCampaigns().then((page) => setCampaigns(page.data));
        void fetchSmtpAccounts().then(setSmtpAccounts);
    }, []);

    const filters: ReportingFilters = {
        date_from: dateFrom,
        date_to: dateTo,
        campaign_id: campaignId || undefined,
        smtp_account_id: smtpAccountId || undefined,
    };

    const load = useCallback(async (nextFilters: ReportingFilters) => {
        setLoading(true);
        try {
            const [summaryResult, campaignResult, smtpResult] = await Promise.all([
                fetchReportingSummary(nextFilters),
                fetchReportingByCampaign(nextFilters),
                fetchReportingBySmtpAccount(nextFilters),
            ]);
            setSummary(summaryResult);
            setByCampaign(campaignResult);
            setBySmtpAccount(smtpResult);
        } finally {
            setLoading(false);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    useEffect(() => {
        void load(filters);
        // Only re-run on mount — "Appliquer" triggers subsequent loads
        // explicitly so date-range typing doesn't refetch on every keystroke.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [load]);

    return (
        <AppShell>
            <Head title={t.reporting.title} />
            <div className={styles.header}>
                <Title1>{t.reporting.title}</Title1>
                <Toolbar>
                    {canExport && (
                        <Button
                            as="a"
                            href={reportingExportUrl(filters)}
                            icon={<ArrowDownloadRegular />}
                            appearance="primary"
                        >
                            {t.reporting.export}
                        </Button>
                    )}
                </Toolbar>
            </div>
            <Text className={styles.subtitle}>{t.reporting.subtitle}</Text>

            <div className={styles.filters}>
                <div className={styles.filterField}>
                    <Text weight="semibold">{t.reporting.dateFrom}</Text>
                    <Input type="date" value={dateFrom} onChange={(_, data) => setDateFrom(data.value)} />
                </div>
                <div className={styles.filterField}>
                    <Text weight="semibold">{t.reporting.dateTo}</Text>
                    <Input type="date" value={dateTo} onChange={(_, data) => setDateTo(data.value)} />
                </div>
                <div className={styles.filterField}>
                    <Text weight="semibold">{t.reporting.campaignFilter}</Text>
                    <Dropdown
                        value={campaigns.find((c) => c.id === campaignId)?.name ?? t.reporting.allCampaigns}
                        onOptionSelect={(_, data) => setCampaignId((data.optionValue as string) ?? '')}
                    >
                        <Option value="">{t.reporting.allCampaigns}</Option>
                        {campaigns.map((campaign) => (
                            <Option key={campaign.id} value={campaign.id}>
                                {campaign.name}
                            </Option>
                        ))}
                    </Dropdown>
                </div>
                <div className={styles.filterField}>
                    <Text weight="semibold">{t.reporting.smtpAccountFilter}</Text>
                    <Dropdown
                        value={smtpAccounts.find((a) => a.id === smtpAccountId)?.name ?? t.reporting.allSmtpAccounts}
                        onOptionSelect={(_, data) => setSmtpAccountId((data.optionValue as string) ?? '')}
                    >
                        <Option value="">{t.reporting.allSmtpAccounts}</Option>
                        {smtpAccounts.map((account) => (
                            <Option key={account.id} value={account.id}>
                                {account.name}
                            </Option>
                        ))}
                    </Dropdown>
                </div>
                <Button appearance="primary" onClick={() => void load(filters)}>
                    {t.reporting.apply}
                </Button>
            </div>

            {loading || !summary ? (
                <Spinner label={t.common.loading} />
            ) : (
                <>
                    <div className={styles.section}>
                        <Title2>{t.reporting.delivery}</Title2>
                        <div className={styles.cardGrid}>
                            <div className={styles.card}>
                                <Text>{t.reporting.sent}</Text>
                                <Text size={600} weight="bold">
                                    {summary.sent}
                                </Text>
                            </div>
                            <div className={styles.card}>
                                <Text>{t.reporting.delivered}</Text>
                                <Text size={600} weight="bold">
                                    {summary.delivered}
                                </Text>
                            </div>
                            <div className={styles.card}>
                                <Text>{t.reporting.opened}</Text>
                                <Text size={600} weight="bold">
                                    {summary.unique_opens}
                                </Text>
                            </div>
                            <div className={styles.card}>
                                <Text>{t.reporting.clicked}</Text>
                                <Text size={600} weight="bold">
                                    {summary.unique_clicks}
                                </Text>
                            </div>
                            <div className={styles.card}>
                                <Text>{t.reporting.bounced}</Text>
                                <Text size={600} weight="bold">
                                    {summary.bounced}
                                </Text>
                            </div>
                            <div className={styles.card}>
                                <Text>{t.reporting.failed}</Text>
                                <Text size={600} weight="bold">
                                    {summary.failed}
                                </Text>
                            </div>
                        </div>
                    </div>

                    <div className={styles.section}>
                        <div className={styles.cardGrid}>
                            <div className={styles.card}>
                                <Text>{t.reporting.deliveryRate}</Text>
                                <Text size={600} weight="bold">
                                    {formatPercent(summary.delivery_rate)}
                                </Text>
                            </div>
                            <div className={styles.card}>
                                <Text>{t.reporting.openRate}</Text>
                                <Text size={600} weight="bold">
                                    {formatPercent(summary.open_rate)}
                                </Text>
                            </div>
                            <div className={styles.card}>
                                <Text>{t.reporting.clickRate}</Text>
                                <Text size={600} weight="bold">
                                    {formatPercent(summary.click_rate)}
                                </Text>
                            </div>
                            <div className={styles.card}>
                                <Text>{t.reporting.bounceRate}</Text>
                                <Text size={600} weight="bold">
                                    {formatPercent(summary.bounce_rate)}
                                </Text>
                            </div>
                            <div className={styles.card}>
                                <Text>{t.reporting.totalOpens}</Text>
                                <Text size={600} weight="bold">
                                    {summary.open_count}
                                </Text>
                            </div>
                            <div className={styles.card}>
                                <Text>{t.reporting.totalClicks}</Text>
                                <Text size={600} weight="bold">
                                    {summary.click_count}
                                </Text>
                            </div>
                        </div>
                    </div>

                    <div className={styles.section}>
                        <Title2>{t.reporting.byStatus}</Title2>
                        {Object.entries(summary.by_status).length === 0 ? (
                            <Text>{t.reporting.noData}</Text>
                        ) : (
                            Object.entries(summary.by_status).map(([status, count]) => (
                                <div className={styles.row} key={status}>
                                    <Text>{status}</Text>
                                    <Text>{count}</Text>
                                </div>
                            ))
                        )}
                    </div>

                    <div className={styles.section}>
                        <Title2>{t.reporting.byCampaign}</Title2>
                        <div className={`${styles.row} ${styles.rowHeader}`}>
                            <Text weight="semibold">{t.reporting.byCampaignName}</Text>
                            <Text weight="semibold">
                                {t.reporting.sent} / {t.reporting.delivered} / {t.reporting.bounced}
                            </Text>
                        </div>
                        {!byCampaign || byCampaign.length === 0 ? (
                            <Text>{t.reporting.noData}</Text>
                        ) : (
                            byCampaign.map((row) => (
                                <div className={styles.row} key={row.campaign_id ?? row.campaign_name}>
                                    <Text>{row.campaign_name}</Text>
                                    <Text>
                                        {row.sent} / {row.delivered} / {row.bounced}
                                    </Text>
                                </div>
                            ))
                        )}
                    </div>

                    <div className={styles.section}>
                        <Title2>{t.reporting.bySmtpAccount}</Title2>
                        <div className={`${styles.row} ${styles.rowHeader}`}>
                            <Text weight="semibold">{t.reporting.bySmtpAccountName}</Text>
                            <Text weight="semibold">
                                {t.reporting.sent} / {t.reporting.delivered} / {t.reporting.bounced} / {t.reporting.healthStatus}
                            </Text>
                        </div>
                        {!bySmtpAccount || bySmtpAccount.length === 0 ? (
                            <Text>{t.reporting.noData}</Text>
                        ) : (
                            bySmtpAccount.map((row) => (
                                <div className={styles.row} key={row.smtp_account_id ?? row.smtp_account_name}>
                                    <Text>{row.smtp_account_name}</Text>
                                    <Text>
                                        {row.sent} / {row.delivered} / {row.bounced} / {row.health_status ?? '—'}
                                    </Text>
                                </div>
                            ))
                        )}
                    </div>
                </>
            )}
        </AppShell>
    );
}
