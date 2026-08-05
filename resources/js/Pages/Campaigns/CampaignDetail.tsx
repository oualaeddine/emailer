import { useCallback, useEffect, useState } from 'react';
import {
    Badge,
    Spinner,
    Tab,
    TabList,
    Text,
    makeStyles,
    tokens,
    type SelectTabData,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { fetchCampaign, fetchCampaignAnalytics, fetchCampaignRecipients } from '@/Lib/api/campaigns';
import type { CampaignAnalytics, CampaignDetail as CampaignDetailModel, CampaignRecipient, CampaignStatus } from '@/Lib/types/campaigns';

interface CampaignDetailProps {
    campaignId: string;
    /** Called after an action performed from within the detail view (kept optional — none are wired here yet). */
    onChange?: () => Promise<void> | void;
}

type DetailTab = 'overview' | 'recipients' | 'content' | 'analytics';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    row: {
        display: 'flex',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalM,
    },
    preview: {
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke1}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground1,
    },
    recipientRow: {
        display: 'flex',
        justifyContent: 'space-between',
        padding: tokens.spacingVerticalXS,
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorNeutralStroke2,
    },
});

const STATUS_COLOR: Record<CampaignStatus, 'subtle' | 'informative' | 'warning' | 'success' | 'danger'> = {
    draft: 'subtle',
    scheduled: 'informative',
    sending: 'warning',
    sent: 'success',
    paused: 'warning',
    cancelled: 'danger',
};

/**
 * docs/15-campaign-management.md §15.8 — Campaign Detail Tabs
 * (Overview / Recipients / Content / Analytics). Rendered inline inside a
 * Drawer from Campaigns/Index.tsx rather than as a dedicated route — keeps
 * the list and its detail on one page, matching the brief's "inline
 * expandable detail" option.
 */
export function CampaignDetail({ campaignId }: CampaignDetailProps) {
    const styles = useStyles();
    const t = useText();

    const [tab, setTab] = useState<DetailTab>('overview');
    const [detail, setDetail] = useState<CampaignDetailModel | null>(null);
    const [recipients, setRecipients] = useState<CampaignRecipient[] | null>(null);
    const [analytics, setAnalytics] = useState<CampaignAnalytics | null>(null);
    const [loading, setLoading] = useState(true);

    const loadDetail = useCallback(async () => {
        setLoading(true);
        try {
            setDetail(await fetchCampaign(campaignId));
        } finally {
            setLoading(false);
        }
    }, [campaignId]);

    useEffect(() => {
        setDetail(null);
        setRecipients(null);
        setAnalytics(null);
        setTab('overview');
        void loadDetail();
    }, [campaignId, loadDetail]);

    useEffect(() => {
        if (tab === 'recipients' && recipients === null) {
            void fetchCampaignRecipients(campaignId).then((response) => setRecipients(response.data));
        }
        if (tab === 'analytics' && analytics === null) {
            void fetchCampaignAnalytics(campaignId).then(setAnalytics);
        }
    }, [tab, campaignId, recipients, analytics]);

    function statusLabel(status: CampaignStatus): string {
        const labels: Record<CampaignStatus, string> = {
            draft: t.campaigns.statusDraft,
            scheduled: t.campaigns.statusScheduled,
            sending: t.campaigns.statusSending,
            sent: t.campaigns.statusSent,
            paused: t.campaigns.statusPaused,
            cancelled: t.campaigns.statusCancelled,
        };

        return labels[status];
    }

    function formatDate(value: string): string {
        return new Date(value).toLocaleString('fr-FR');
    }

    if (loading || !detail) {
        return <Spinner label={t.common.loading} />;
    }

    return (
        <div className={styles.root}>
            <div className={styles.row}>
                <Text size={500} weight="semibold">
                    {detail.name}
                </Text>
                <Badge color={STATUS_COLOR[detail.status]}>{statusLabel(detail.status)}</Badge>
            </div>

            <TabList selectedValue={tab} onTabSelect={(_, data: SelectTabData) => setTab(data.value as DetailTab)}>
                <Tab value="overview">{t.campaigns.tabOverview}</Tab>
                <Tab value="recipients">{t.campaigns.tabRecipients}</Tab>
                <Tab value="content">{t.campaigns.tabContent}</Tab>
                <Tab value="analytics">{t.campaigns.tabAnalytics}</Tab>
            </TabList>

            {tab === 'overview' && (
                <div className={styles.root}>
                    <div className={styles.row}>
                        <Text weight="semibold">{t.campaigns.template}</Text>
                        <Text>{detail.template?.name ?? '—'}</Text>
                    </div>
                    <div className={styles.row}>
                        <Text weight="semibold">{t.campaigns.target}</Text>
                        <Text>{detail.target_name ?? detail.target_id ?? '—'}</Text>
                    </div>
                    <div className={styles.row}>
                        <Text weight="semibold">{t.campaigns.audienceSize}</Text>
                        <Text>{detail.audience_size ?? '—'}</Text>
                    </div>
                    <div className={styles.row}>
                        <Text weight="semibold">{t.campaigns.scheduledAt}</Text>
                        <Text>{detail.scheduled_at ? formatDate(detail.scheduled_at) : t.campaigns.noScheduledDate}</Text>
                    </div>
                    <div className={styles.row}>
                        <Text weight="semibold">{t.campaigns.sentAt}</Text>
                        <Text>{detail.sent_at ? formatDate(detail.sent_at) : t.campaigns.notSentYet}</Text>
                    </div>
                </div>
            )}

            {tab === 'recipients' && (
                <div className={styles.root}>
                    {recipients === null ? (
                        <Spinner label={t.common.loading} />
                    ) : recipients.length === 0 ? (
                        <Text>—</Text>
                    ) : (
                        recipients.map((recipient) => (
                            <div key={recipient.id} className={styles.recipientRow}>
                                <Text>{recipient.email}</Text>
                                <Text>{recipient.message_status ?? '—'}</Text>
                            </div>
                        ))
                    )}
                </div>
            )}

            {tab === 'content' && (
                <div className={styles.preview}>
                    {detail.html_body ? (
                        <div dangerouslySetInnerHTML={{ __html: detail.html_body }} />
                    ) : (
                        <Text>{t.campaigns.noContentPreview}</Text>
                    )}
                </div>
            )}

            {tab === 'analytics' && (
                <div className={styles.root}>
                    {analytics === null ? (
                        <Spinner label={t.common.loading} />
                    ) : (
                        <>
                            <div className={styles.row}>
                                <Text weight="semibold">{t.campaigns.analyticsSent}</Text>
                                <Text>{analytics.sent}</Text>
                            </div>
                            <div className={styles.row}>
                                <Text weight="semibold">{t.campaigns.analyticsDelivered}</Text>
                                <Text>{analytics.delivered}</Text>
                            </div>
                            <div className={styles.row}>
                                <Text weight="semibold">{t.campaigns.analyticsOpened}</Text>
                                <Text>{analytics.opened}</Text>
                            </div>
                            <div className={styles.row}>
                                <Text weight="semibold">{t.campaigns.analyticsClicked}</Text>
                                <Text>{analytics.clicked}</Text>
                            </div>
                            <div className={styles.row}>
                                <Text weight="semibold">{t.campaigns.analyticsBounced}</Text>
                                <Text>{analytics.bounced}</Text>
                            </div>
                            <div className={styles.row}>
                                <Text weight="semibold">{t.campaigns.analyticsComplained}</Text>
                                <Text>{analytics.complained}</Text>
                            </div>
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
