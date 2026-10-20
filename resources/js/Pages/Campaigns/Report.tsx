import { useCallback, useEffect, useRef, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Button,
    MessageBar,
    MessageBarActions,
    MessageBarBody,
    Spinner,
    Tab,
    TabList,
    Toast,
    ToastTitle,
    Toaster,
    makeStyles,
    tokens,
    useId,
    useToastController,
} from '@fluentui/react-components';
import { DataBarVerticalRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import { fetchCampaignV2 } from '@/Lib/api/campaigns';
import { fetchReportTab } from '@/Lib/api/reports';
import { apiErrorMessage, apiStatus, campaignSetupUrl } from '@/Components/Campaigns/campaignUi';
import { ClicksTab } from '@/Components/Reports/ClicksTab';
import { CompareDrawer } from '@/Components/Reports/CompareDrawer';
import { DeliverabilityTab } from '@/Components/Reports/DeliverabilityTab';
import { OpensTab } from '@/Components/Reports/OpensTab';
import { OverviewTab } from '@/Components/Reports/OverviewTab';
import { EmailPreviewDialog, ReportHeader } from '@/Components/Reports/ReportHeader';
import { UnsubscriptionsTab } from '@/Components/Reports/UnsubscriptionsTab';
import type { ReportCampaignHeader, ReportTab, ReportTabPayloads } from '@/Lib/types/reports';

const TABS: ReportTab[] = ['overview', 'deliverability', 'opens', 'clicks', 'unsubscriptions'];

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalL,
        minWidth: 0,
    },
    tabs: {
        overflowX: 'auto',
        flexWrap: 'nowrap',
        borderBottom: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
    },
    center: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalXXL,
    },
});

interface TabState {
    loading: boolean;
    error: string | null;
    notSent: boolean;
}

function readTab(): ReportTab {
    const value = new URLSearchParams(window.location.search).get('tab') as ReportTab | null;

    return value && TABS.includes(value) ? value : 'overview';
}

function writeTab(tab: ReportTab) {
    const params = new URLSearchParams(window.location.search);
    if (tab === 'overview') {
        params.delete('tab');
    } else {
        params.set('tab', tab);
    }
    const search = params.toString();
    window.history.replaceState(window.history.state, '', `${window.location.pathname}${search ? `?${search}` : ''}`);
}

/**
 * docs/43-brevo-parity-plan.md §43.4.15 — Brevo campaign report: header
 * (Exporter per tab, Comparer, Voir l'e-mail) and the tabs Vue d'ensemble,
 * Délivrabilité, Ouvertures, Clics, Désinscriptions, each loaded lazily from
 * `GET /campaigns/{id}/report/{tab}` (docs/45 §45.6.5).
 */
export default function CampaignReport({ campaignId }: { campaignId: string }) {
    const styles = useStyles();
    const t = useText();
    const toasterId = useId('report-toaster');
    const { dispatchToast } = useToastController(toasterId);

    const [tab, setTab] = useState<ReportTab>(readTab);
    const [payloads, setPayloads] = useState<Partial<ReportTabPayloads>>({});
    const [tabState, setTabState] = useState<Partial<Record<ReportTab, TabState>>>({});
    const [header, setHeader] = useState<ReportCampaignHeader | null>(null);
    const [compareOpen, setCompareOpen] = useState(false);
    const [emailOpen, setEmailOpen] = useState(false);
    const [html, setHtml] = useState<string | null>(null);
    const [htmlState, setHtmlState] = useState<'idle' | 'loading' | 'done'>('idle');
    const requested = useRef(new Set<ReportTab>());

    const notifyError = useCallback(
        (message: string) => {
            dispatchToast(
                <Toast>
                    <ToastTitle>{message}</ToastTitle>
                </Toast>,
                { intent: 'error' },
            );
        },
        [dispatchToast],
    );

    const loadTab = useCallback(
        async (target: ReportTab) => {
            requested.current.add(target);
            setTabState((previous) => ({ ...previous, [target]: { loading: true, error: null, notSent: false } }));
            try {
                const data = await fetchReportTab(campaignId, target);
                setPayloads((previous) => ({ ...previous, [target]: data }));
                setHeader((previous) => previous ?? data.campaign);
                setTabState((previous) => ({ ...previous, [target]: { loading: false, error: null, notSent: false } }));
            } catch (error) {
                const notSent = apiStatus(error) === 409;
                setTabState((previous) => ({
                    ...previous,
                    [target]: { loading: false, error: notSent ? null : apiErrorMessage(error), notSent },
                }));
            }
        },
        [campaignId],
    );

    useEffect(() => {
        writeTab(tab);
        if (!requested.current.has(tab)) {
            void loadTab(tab);
        }
    }, [tab, loadTab]);

    // html_body (GET /campaigns/{id}) is needed by the click map and "Voir l'e-mail" only.
    const needHtml = tab === 'clicks' || emailOpen;
    useEffect(() => {
        if (!needHtml || htmlState !== 'idle') {
            return;
        }
        setHtmlState('loading');
        fetchCampaignV2(campaignId)
            .then((campaign) => setHtml(campaign.html_body))
            .catch((error) => notifyError(apiErrorMessage(error)))
            .finally(() => setHtmlState('done'));
    }, [needHtml, htmlState, campaignId, notifyError]);

    const current = tabState[tab];
    const campaignName = header?.name ?? '';
    const notSent = Object.values(tabState).some((state) => state?.notSent);

    function renderTab() {
        if (!current || current.loading) {
            return (
                <div className={styles.center}>
                    <Spinner label={t.common.loading} />
                </div>
            );
        }
        if (current.error) {
            return (
                <MessageBar intent="error">
                    <MessageBarBody>{current.error}</MessageBarBody>
                    <MessageBarActions>
                        <Button size="small" onClick={() => void loadTab(tab)}>
                            {t.common.retry}
                        </Button>
                    </MessageBarActions>
                </MessageBar>
            );
        }
        const common = { campaignId, campaignName, onError: notifyError };
        switch (tab) {
            case 'overview':
                return payloads.overview ? <OverviewTab data={payloads.overview} /> : null;
            case 'deliverability':
                return payloads.deliverability ? <DeliverabilityTab data={payloads.deliverability} {...common} /> : null;
            case 'opens':
                return payloads.opens ? <OpensTab data={payloads.opens} {...common} /> : null;
            case 'clicks':
                return payloads.clicks ? (
                    <ClicksTab data={payloads.clicks} html={html} htmlLoading={htmlState !== 'done'} {...common} />
                ) : null;
            case 'unsubscriptions':
                return payloads.unsubscriptions ? <UnsubscriptionsTab data={payloads.unsubscriptions} {...common} /> : null;
            default:
                return null;
        }
    }

    return (
        <AppShell>
            <Head title={campaignName ? `${t.campaignReport.title} — ${campaignName}` : t.campaignReport.title} />
            <Toaster toasterId={toasterId} position="bottom-end" />
            <div className={styles.root}>
                <ReportHeader
                    campaignId={campaignId}
                    campaign={header}
                    tab={tab}
                    onCompare={() => setCompareOpen(true)}
                    onViewEmail={() => setEmailOpen(true)}
                    onError={notifyError}
                />
                {notSent ? (
                    <EmptyState
                        icon={DataBarVerticalRegular}
                        title={t.campaignReport.notSent}
                        action={
                            <Button appearance="primary" onClick={() => router.visit(campaignSetupUrl(campaignId))}>
                                {t.campaigns.actions.edit}
                            </Button>
                        }
                    />
                ) : (
                    <>
                        <TabList
                            className={styles.tabs}
                            selectedValue={tab}
                            onTabSelect={(_, data) => setTab(data.value as ReportTab)}
                            aria-label={t.campaignReport.title}
                        >
                            {TABS.map((value) => (
                                <Tab key={value} value={value}>
                                    {t.campaignReport.tabs[value]}
                                </Tab>
                            ))}
                        </TabList>
                        <div role="tabpanel" aria-label={t.campaignReport.tabs[tab]}>
                            {renderTab()}
                        </div>
                    </>
                )}
            </div>
            <CompareDrawer open={compareOpen} campaignId={campaignId} onClose={() => setCompareOpen(false)} />
            <EmailPreviewDialog open={emailOpen} html={html} loading={htmlState !== 'done'} onClose={() => setEmailOpen(false)} />
        </AppShell>
    );
}
