import { useEffect, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import {
    Badge,
    Card,
    ProgressBar,
    Spinner,
    Text,
    Title1,
    Title3,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { fetchDashboardWidgets } from '@/Lib/api/dashboard';
import type { AuthenticatedUser } from '@/Lib/types/identity';
import type { CampaignStatus } from '@/Lib/types/campaigns';
import type { DashboardWidgets } from '@/Lib/types/dashboard';

const useStyles = makeStyles({
    welcomeCard: {
        padding: tokens.spacingVerticalXL,
        maxWidth: '640px',
        marginBottom: tokens.spacingVerticalL,
    },
    grid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fill, minmax(320px, 1fr))',
        gap: tokens.spacingHorizontalM,
    },
    widgetCard: {
        padding: tokens.spacingVerticalL,
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    row: {
        display: 'flex',
        justifyContent: 'space-between',
        alignItems: 'center',
        gap: tokens.spacingHorizontalM,
    },
    quotaRow: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
    },
    emptyState: {
        color: tokens.colorNeutralForeground3,
    },
});

const STATUS_COLOR: Record<CampaignStatus, 'subtle' | 'informative' | 'warning' | 'success' | 'danger'> = {
    draft: 'subtle',
    scheduled: 'informative',
    running: 'warning',
    completed: 'success',
    paused: 'warning',
    cancelled: 'danger',
};

/**
 * docs/09-dashboard.md — Dashboard landing page (WP-33), replacing the
 * placeholder. AppShell/Head/welcome-card structure is unchanged (still
 * the Identity module's login-flow landing point); the real widgets are
 * added below it, loaded from `GET /api/v1/dashboard/widgets`
 * (`DashboardController::widgets()`).
 *
 * `quota_usage`/`recent_campaigns` are omitted server-side for a caller
 * lacking `smtp.view`/`campaigns.view` respectively (§9.1's role-aware
 * widget visibility) — each card is only rendered when its key is present.
 */
export default function Dashboard() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth: { user: AuthenticatedUser } }>();

    const [widgets, setWidgets] = useState<DashboardWidgets | null>(null);
    const [loading, setLoading] = useState(true);

    useEffect(() => {
        let cancelled = false;
        setLoading(true);
        void fetchDashboardWidgets().then((data) => {
            if (!cancelled) {
                setWidgets(data);
            }
        }).finally(() => {
            if (!cancelled) {
                setLoading(false);
            }
        });

        return () => {
            cancelled = true;
        };
    }, []);

    function statusLabel(status: CampaignStatus): string {
        const labels: Record<CampaignStatus, string> = {
            draft: t.campaigns.statusDraft,
            scheduled: t.campaigns.statusScheduled,
            running: t.campaigns.statusSending,
            completed: t.campaigns.statusSent,
            paused: t.campaigns.statusPaused,
            cancelled: t.campaigns.statusCancelled,
        };

        return labels[status];
    }

    function formatDate(value: string | null): string {
        return value ? new Date(value).toLocaleDateString('fr-FR') : '—';
    }

    const totalSendVolume = widgets?.send_volume.series.reduce((sum, point) => sum + point.count, 0) ?? 0;
    const deliverabilityPercent = widgets ? Math.round(widgets.deliverability.rate * 100) : 0;

    return (
        <AppShell>
            <Head title={t.nav.dashboard} />
            <Card className={styles.welcomeCard}>
                <Title1>
                    {t.dashboard.welcome}, {props.auth.user.name}
                </Title1>
                <Text block>{t.dashboard.placeholder}</Text>
            </Card>

            {loading || !widgets ? (
                <Spinner label={t.common.loading} />
            ) : (
                <div className={styles.grid}>
                    <Card className={styles.widgetCard}>
                        <Title3>{t.dashboard.sendVolumeTitle}</Title3>
                        <Text size={600} weight="semibold">
                            {totalSendVolume}
                        </Text>
                        <Text className={styles.emptyState}>
                            {widgets.send_volume.days} {t.dashboard.sendVolumeLastDays}
                        </Text>
                        {widgets.send_volume.series.map((point) => (
                            <div className={styles.row} key={point.date}>
                                <Text>{formatDate(point.date)}</Text>
                                <Text weight="semibold">{point.count}</Text>
                            </div>
                        ))}
                    </Card>

                    <Card className={styles.widgetCard}>
                        <Title3>{t.dashboard.deliverabilityTitle}</Title3>
                        <Text size={600} weight="semibold">
                            {deliverabilityPercent}%
                        </Text>
                        <div className={styles.row}>
                            <Text weight="semibold">{t.dashboard.deliverabilitySent}</Text>
                            <Text>{widgets.deliverability.sent}</Text>
                        </div>
                        <div className={styles.row}>
                            <Text weight="semibold">{t.dashboard.deliverabilityDelivered}</Text>
                            <Text>{widgets.deliverability.delivered}</Text>
                        </div>
                    </Card>

                    {widgets.quota_usage && (
                        <Card className={styles.widgetCard}>
                            <Title3>{t.dashboard.quotaUsageTitle}</Title3>
                            {widgets.quota_usage.length === 0 ? (
                                <Text className={styles.emptyState}>{t.dashboard.quotaUsageEmpty}</Text>
                            ) : (
                                widgets.quota_usage.map((account) => (
                                    <div className={styles.quotaRow} key={account.smtp_account_id}>
                                        <div className={styles.row}>
                                            <Text weight="semibold">{account.name}</Text>
                                            <Text>
                                                {account.daily_quota === null
                                                    ? t.dashboard.quotaUsageNoLimit
                                                    : `${account.used_today} / ${account.daily_quota}`}
                                            </Text>
                                        </div>
                                        {account.percent_used !== null && (
                                            <ProgressBar value={account.percent_used / 100} />
                                        )}
                                    </div>
                                ))
                            )}
                        </Card>
                    )}

                    {widgets.recent_campaigns && (
                        <Card className={styles.widgetCard}>
                            <Title3>{t.dashboard.recentCampaignsTitle}</Title3>
                            {widgets.recent_campaigns.length === 0 ? (
                                <Text className={styles.emptyState}>{t.dashboard.recentCampaignsEmpty}</Text>
                            ) : (
                                widgets.recent_campaigns.map((campaign) => (
                                    <div className={styles.row} key={campaign.id}>
                                        <Text>{campaign.name}</Text>
                                        <Badge color={STATUS_COLOR[campaign.status]}>{statusLabel(campaign.status)}</Badge>
                                        <Text className={styles.emptyState}>
                                            {formatDate(campaign.sent_at ?? campaign.scheduled_at ?? campaign.created_at)}
                                        </Text>
                                    </div>
                                ))
                            )}
                        </Card>
                    )}
                </div>
            )}
        </AppShell>
    );
}
