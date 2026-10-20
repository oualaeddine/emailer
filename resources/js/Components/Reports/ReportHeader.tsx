import { router } from '@inertiajs/react';
import {
    Badge,
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Spinner,
    Text,
    Title2,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { ArrowLeftRegular, DataHistogramRegular, EyeRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { formatDateTime, formatText } from '@/Components/Campaigns/campaignUi';
import { EmailFrame } from '@/Components/Campaigns/EmailFrame';
import { ExportButton } from '@/Components/Reports/ExportButton';
import { headerSentAt, reportMissingText } from '@/Components/Reports/reportUi';
import type { ReportCampaignHeader, ReportTab } from '@/Lib/types/reports';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    top: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'flex-start',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalM,
    },
    titleBlock: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        minWidth: 0,
    },
    titleRow: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    meta: {
        display: 'flex',
        flexWrap: 'wrap',
        columnGap: tokens.spacingHorizontalL,
        rowGap: tokens.spacingVerticalXXS,
        color: tokens.colorNeutralForeground2,
    },
    metaLabel: {
        color: tokens.colorNeutralForeground3,
    },
    actions: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
    },
    back: {
        alignSelf: 'flex-start',
    },
    dialogSurface: {
        maxWidth: '760px',
        width: '100%',
    },
    center: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalXL,
    },
});

export interface ReportHeaderProps {
    campaignId: string;
    campaign: ReportCampaignHeader | null;
    tab: ReportTab;
    onCompare: () => void;
    onViewEmail: () => void;
    onError: (message: string) => void;
}

/**
 * Report header: campaign name, sent date, sender, subject, then **Exporter**
 * (current tab), **Comparer** and **Voir l'e-mail** (docs/43 §43.4.15).
 */
export function ReportHeader({ campaignId, campaign, tab, onCompare, onViewEmail, onError }: ReportHeaderProps) {
    const styles = useStyles();
    const t = useText();
    const sentAt = campaign ? headerSentAt(campaign) : null;

    return (
        <header className={styles.root}>
            <Button
                className={styles.back}
                appearance="subtle"
                size="small"
                icon={<ArrowLeftRegular />}
                onClick={() => router.visit('/campaigns')}
            >
                {t.campaignSetup.backToCampaigns}
            </Button>
            <div className={styles.top}>
                <div className={styles.titleBlock}>
                    <div className={styles.titleRow}>
                        <Title2 as="h1">{campaign?.name ?? t.campaignReport.title}</Title2>
                        {campaign && (
                            <Badge appearance="tint" color={campaign.brevo_status === 'sent' ? 'success' : 'informative'}>
                                {t.campaigns.brevoStatusLabels[campaign.brevo_status] ?? campaign.brevo_status}
                            </Badge>
                        )}
                        {campaign?.type === 'ab_test' && (
                            <Badge appearance="outline" color="brand">
                                {t.campaigns.abBadge}
                            </Badge>
                        )}
                    </div>
                    {campaign && (
                        <div className={styles.meta}>
                            {sentAt && <Text>{formatText(t.campaignReport.sentOn, formatDateTime(sentAt))}</Text>}
                            {campaign.sender && (
                                <Text>
                                    <span className={styles.metaLabel}>{t.campaignReport.from} : </span>
                                    {campaign.sender.name ?? ''} &lt;{campaign.sender.email ?? ''}&gt;
                                </Text>
                            )}
                            <Text>
                                <span className={styles.metaLabel}>{t.campaignReport.subject} : </span>
                                {campaign.subject}
                            </Text>
                        </div>
                    )}
                </div>
                <div className={styles.actions}>
                    <ExportButton campaignId={campaignId} campaignName={campaign?.name ?? campaignId} tab={tab} onError={onError} />
                    <Button icon={<DataHistogramRegular />} onClick={onCompare}>
                        {t.campaignReport.compare}
                    </Button>
                    <Button icon={<EyeRegular />} onClick={onViewEmail}>
                        {t.campaignReport.viewEmail}
                    </Button>
                </div>
            </div>
        </header>
    );
}

export interface EmailPreviewDialogProps {
    open: boolean;
    html: string | null;
    loading: boolean;
    onClose: () => void;
}

/** **Voir l'e-mail** — the sent HTML in the sandboxed EmailFrame. */
export function EmailPreviewDialog({ open, html, loading, onClose }: EmailPreviewDialogProps) {
    const styles = useStyles();
    const t = useText();

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && onClose()}>
            <DialogSurface className={styles.dialogSurface}>
                <DialogBody>
                    <DialogTitle>{t.campaignReport.viewEmail}</DialogTitle>
                    <DialogContent>
                        {loading ? (
                            <div className={styles.center}>
                                <Spinner label={t.common.loading} />
                            </div>
                        ) : html ? (
                            <EmailFrame html={html} title={t.campaignReport.viewEmail} height={640} />
                        ) : (
                            <Text>{reportMissingText.emailUnavailable}</Text>
                        )}
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="primary" onClick={onClose}>
                            {t.common.close}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
