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
import { fetchTemplates } from '@/Lib/api/templates';
import type { CampaignAnalytics, CampaignDetail as CampaignDetailModel, CampaignStatus } from '@/Lib/types/campaigns';
import type { Message } from '@/Lib/types/messages';

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
    running: 'warning',
    completed: 'success',
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
    const [recipients, setRecipients] = useState<Message[] | null>(null);
    const [analytics, setAnalytics] = useState<CampaignAnalytics | null>(null);
    const [templateNames, setTemplateNames] = useState<Record<string, string>>({});
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
        // `CampaignResource` only exposes `template_id` (no embedded name).
        void fetchTemplates().then((templates) => {
            setTemplateNames(Object.fromEntries(templates.map((template) => [template.id, template.name])));
        });
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
            running: t.campaigns.statusSending,
            completed: t.campaigns.statusSent,
            paused: t.campaigns.statusPaused,
            cancelled: t.campaigns.statusCancelled,
        };

        return labels[status];
    }

    /** `CampaignResource` exposes `recipient_list_id`/`segment_id`, not a resolved `target_name` — no list/segment-name lookup endpoint exists yet, so the raw id is shown (same degradation the wizard's review step already uses). */
    function targetLabel(): string {
        if (detail?.recipient_list_id) {
            return `${t.campaigns.targetTypeList} (${detail.recipient_list_id})`;
        }
        if (detail?.segment_id) {
            return `${t.campaigns.targetTypeSegment} (${detail.segment_id})`;
        }

        return '—';
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
                <Badge appearance="tint" color={STATUS_COLOR[detail.status]}>
                    {statusLabel(detail.status)}
                </Badge>
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
                        <Text>{detail.template_id ? (templateNames[detail.template_id] ?? detail.template_id) : '—'}</Text>
                    </div>
                    <div className={styles.row}>
                        <Text weight="semibold">{t.campaigns.target}</Text>
                        <Text>{targetLabel()}</Text>
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
                                <Text>{recipient.recipient_email}</Text>
                                <Text>{recipient.status}</Text>
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
                            {/*
                                `CampaignController::analytics()` returns
                                `{targeted, by_status, open_count, click_count}`
                                (docs/25-reporting.md's funnel shape), not the
                                originally-assumed `{sent, delivered, opened,
                                clicked, bounced, complained}` — `by_status` is
                                keyed by the raw `MessageStatus` enum value
                                (English, per doc 07 §7.12's DB/API identifier
                                convention), shown as-is like `Lib/types/messages.ts`'s
                                `status: string` already does elsewhere in this app.
                            */}
                            <div className={styles.row}>
                                <Text weight="semibold">{t.campaigns.audienceSize}</Text>
                                <Text>{analytics.targeted}</Text>
                            </div>
                            <div className={styles.row}>
                                <Text weight="semibold">{t.campaigns.analyticsOpened}</Text>
                                <Text>{analytics.open_count}</Text>
                            </div>
                            <div className={styles.row}>
                                <Text weight="semibold">{t.campaigns.analyticsClicked}</Text>
                                <Text>{analytics.click_count}</Text>
                            </div>
                            {Object.entries(analytics.by_status).map(([status, count]) => (
                                <div className={styles.row} key={status}>
                                    <Text weight="semibold">{status}</Text>
                                    <Text>{count}</Text>
                                </div>
                            ))}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
