import { useCallback, useEffect, useState } from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import {
    Button,
    Input,
    MessageBar,
    MessageBarBody,
    Spinner,
    Text,
    Toast,
    ToastTitle,
    Toaster,
    Tooltip,
    makeStyles,
    tokens,
    useId,
    useToastController,
} from '@fluentui/react-components';
import { ArrowLeftRegular, CalendarLtrRegular, CheckmarkRegular, DismissRegular, EditRegular, EyeRegular } from '@fluentui/react-icons';
import { PageHelp } from '@/Components/Help/PageHelp';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { fetchCampaignV2, updateCampaignV2 } from '@/Lib/api/campaigns';
import { hasPermission } from '@/Lib/permissions';
import type { CampaignChecklist, CampaignSendMode, CampaignV2Detail } from '@/Lib/types/campaigns';
import { AbTestCard, isAbTestReady } from '@/Components/Campaigns/AbTestCard';
import { ApprovalBanner, isApprovalPending } from '@/Components/Campaigns/ApprovalBanner';
import { ChecklistPanel } from '@/Components/Campaigns/ChecklistPanel';
import { ConfirmSendDialog, allowedSendModes } from '@/Components/Campaigns/ConfirmSendDialog';
import { DesignCard, isDesignComplete } from '@/Components/Campaigns/DesignCard';
import { PreviewAndTestPanel } from '@/Components/Campaigns/PreviewAndTestPanel';
import { RecipientsCard, isRecipientsComplete } from '@/Components/Campaigns/RecipientsCard';
import { SenderCard, isSenderComplete } from '@/Components/Campaigns/SenderCard';
import { SettingsCard } from '@/Components/Campaigns/SettingsCard';
import { SubjectCard, isSubjectComplete } from '@/Components/Campaigns/SubjectCard';
import { CampaignStatusChip, apiErrorMessage, apiStatus } from '@/Components/Campaigns/campaignUi';

interface SetupPageProps {
    campaignId: string;
}

type CardKey = 'sender' | 'subject' | 'recipients' | 'settings' | 'abTest';

const useStyles = makeStyles({
    header: {
        position: 'sticky',
        top: 0,
        zIndex: 10,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        padding: `${tokens.spacingVerticalM} ${tokens.spacingHorizontalL}`,
        marginBottom: tokens.spacingVerticalL,
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusLarge,
        boxShadow: tokens.shadow4,
    },
    titleBlock: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        minWidth: 0,
        flexWrap: 'wrap',
    },
    name: {
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
        maxWidth: '420px',
    },
    nameInput: {
        minWidth: '260px',
    },
    headerActions: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        flexWrap: 'wrap',
    },
    layout: {
        display: 'grid',
        gridTemplateColumns: 'minmax(0, 1fr) 340px',
        gap: tokens.spacingHorizontalL,
        alignItems: 'start',
        '@media (max-width: 1100px)': {
            gridTemplateColumns: 'minmax(0, 1fr)',
        },
    },
    cards: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        minWidth: 0,
    },
    aside: {
        position: 'sticky',
        top: '96px',
        '@media (max-width: 1100px)': {
            position: 'static',
        },
    },
    center: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalXXXL,
    },
});

/**
 * docs/43-brevo-parity-plan.md §43.4.2 — Brevo "campaign dashboard"
 * (replaces CampaignWizardDialog): sticky header (inline name, status,
 * Aperçu & test, Programmer), the five cards Expéditeur → Objet →
 * Destinataires → Design → Paramètres supplémentaires, the checklist, and
 * *Confirmer l'envoi*.
 */
export default function CampaignSetup({ campaignId }: SetupPageProps) {
    const styles = useStyles();
    const t = useText();
    const toasterId = useId('campaign-setup-toaster');
    const { dispatchToast } = useToastController(toasterId);

    const { props } = usePage<{ auth?: { permissions?: string[] } }>();
    const canApprove = hasPermission(props.auth?.permissions ?? [], 'campaigns.approve');

    const [campaign, setCampaign] = useState<CampaignV2Detail | null>(null);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [expanded, setExpanded] = useState<CardKey | null>(null);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [checklist, setChecklist] = useState<CampaignChecklist | null>(null);
    const [checklistKey, setChecklistKey] = useState(0);
    const [editingName, setEditingName] = useState(false);
    const [nameDraft, setNameDraft] = useState('');
    const [savingName, setSavingName] = useState(false);

    const notify = useCallback(
        (text: string, intent: 'success' | 'error' | 'warning' = 'success') => {
            dispatchToast(
                <Toast>
                    <ToastTitle>{text}</ToastTitle>
                </Toast>,
                { intent },
            );
        },
        [dispatchToast],
    );

    useEffect(() => {
        let cancelled = false;
        fetchCampaignV2(campaignId)
            .then((detail) => !cancelled && setCampaign(detail))
            .catch((err) => {
                if (!cancelled) {
                    setLoadError(apiStatus(err) === 404 ? t.common.notFound : apiErrorMessage(err));
                }
            });

        return () => {
            cancelled = true;
        };
    }, [campaignId, t]);

    function handleSaved(updated: CampaignV2Detail) {
        // An edit of approved content withdraws the approval (and unschedules a scheduled campaign).
        const revoked = campaign?.approval_status === 'approved' && updated.approval_status === 'pending';
        setCampaign(updated);
        setChecklistKey((key) => key + 1);
        if (revoked) {
            notify(
                campaign?.status === 'scheduled' && updated.status === 'draft'
                    ? t.campaignSetup.approval.revokedUnscheduled
                    : t.campaignSetup.approval.revoked,
                'warning',
            );
        } else {
            notify(t.common.saved);
        }
    }

    function handleApproved(updated: CampaignV2Detail) {
        setCampaign(updated);
        setChecklistKey((key) => key + 1);
        notify(t.campaignSetup.approval.approved);
    }

    async function saveName() {
        if (!campaign) {
            return;
        }
        const trimmed = nameDraft.trim();
        if (trimmed === '' || trimmed === campaign.name) {
            setEditingName(false);

            return;
        }
        setSavingName(true);
        try {
            setCampaign(await updateCampaignV2(campaign.id, { name: trimmed }));
            setEditingName(false);
        } catch (err) {
            notify(apiErrorMessage(err), 'error');
        } finally {
            setSavingName(false);
        }
    }

    function handleScheduled(updated: CampaignV2Detail, mode: CampaignSendMode) {
        setCampaign(updated);
        setConfirmOpen(false);
        notify(mode === 'now' ? t.campaignSetup.confirm.sent : t.campaignSetup.confirm.scheduled);
        // Land on the tab that lists the campaign: best-time and batch sends start
        // as `running` with per-contact times, not as `scheduled`.
        router.visit(`/campaigns?status_tab=${updated.status === 'scheduled' ? 'scheduled' : 'running'}`);
    }

    if (!campaign) {
        return (
            <AppShell>
                <Head title={t.campaignSetup.title} />
                {loadError ? (
                    <MessageBar intent="error">
                        <MessageBarBody>{loadError}</MessageBarBody>
                    </MessageBar>
                ) : (
                    <div className={styles.center}>
                        <Spinner label={t.common.loading} />
                    </div>
                )}
            </AppShell>
        );
    }

    const editable = campaign.available_actions.includes('edit');
    const requiredComplete =
        isSenderComplete(campaign) && isSubjectComplete(campaign) && isRecipientsComplete(campaign) && isDesignComplete(campaign) &&
        isAbTestReady(campaign);
    const approvalPending = isApprovalPending(campaign);
    const canProgram =
        editable &&
        requiredComplete &&
        !approvalPending &&
        allowedSendModes(campaign).length > 0 &&
        (checklist ? checklist.can_schedule : true);

    const cardProps = (key: CardKey) => ({
        campaign,
        editable,
        expanded: expanded === key,
        onExpand: () => setExpanded(key),
        onCollapse: () => setExpanded(null),
        onSaved: handleSaved,
    });

    const programButton = (
        <Button appearance="primary" icon={<CalendarLtrRegular />} disabledFocusable={!canProgram} onClick={() => setConfirmOpen(true)}>
            {t.campaignSetup.schedule}
        </Button>
    );

    return (
        <AppShell>
            <Head title={campaign.name || t.campaignSetup.title} />
            <Toaster toasterId={toasterId} position="bottom-end" />

            <header className={styles.header}>
                <div className={styles.titleBlock}>
                    <Tooltip content={t.campaignSetup.backToCampaigns} relationship="label">
                        <Button appearance="subtle" icon={<ArrowLeftRegular />} onClick={() => router.visit('/campaigns')} />
                    </Tooltip>
                    {editingName ? (
                        <>
                            <Input
                                className={styles.nameInput}
                                value={nameDraft}
                                autoFocus
                                maxLength={255}
                                disabled={savingName}
                                aria-label={t.campaigns.create.internalName}
                                onChange={(_, data) => setNameDraft(data.value)}
                                onKeyDown={(event) => {
                                    if (event.key === 'Enter') {
                                        void saveName();
                                    } else if (event.key === 'Escape') {
                                        setEditingName(false);
                                    }
                                }}
                            />
                            <Button
                                appearance="subtle"
                                icon={savingName ? <Spinner size="tiny" /> : <CheckmarkRegular />}
                                aria-label={t.common.save}
                                disabled={savingName}
                                onClick={() => void saveName()}
                            />
                            <Button
                                appearance="subtle"
                                icon={<DismissRegular />}
                                aria-label={t.common.cancel}
                                disabled={savingName}
                                onClick={() => setEditingName(false)}
                            />
                        </>
                    ) : (
                        <>
                            <Text as="h1" size={600} weight="semibold" className={styles.name} title={campaign.name}>
                                {campaign.name}
                            </Text>
                            {editable && (
                                <Tooltip content={t.campaignSetup.editName} relationship="label">
                                    <Button
                                        appearance="subtle"
                                        icon={<EditRegular />}
                                        onClick={() => {
                                            setNameDraft(campaign.name);
                                            setEditingName(true);
                                        }}
                                    />
                                </Tooltip>
                            )}
                        </>
                    )}
                    <CampaignStatusChip campaign={campaign} />
                </div>
                <div className={styles.headerActions}>
                    <Button appearance="subtle" onClick={() => router.visit('/campaigns')}>
                        {t.campaignSetup.saveAndQuit}
                    </Button>
                    <Button icon={<EyeRegular />} onClick={() => setPreviewOpen(true)}>
                        {t.campaignSetup.previewAndTest}
                    </Button>
                    {canProgram ? (
                        programButton
                    ) : (
                        <Tooltip
                            content={approvalPending ? t.campaignSetup.approval.scheduleBlocked : t.campaignSetup.scheduleDisabled}
                            relationship="description"
                        >
                            {programButton}
                        </Tooltip>
                    )}
                </div>
            </header>
            <PageHelp topic="campaign-setup" hideHeaderRow />

            <ApprovalBanner campaign={campaign} canApprove={canApprove} onApproved={handleApproved} />

            {campaign.status === 'suspended' && campaign.suspended_reason && (
                <MessageBar intent="warning" style={{ marginBottom: tokens.spacingVerticalM }}>
                    <MessageBarBody>{t.campaigns.suspendedReasons[campaign.suspended_reason]}</MessageBarBody>
                </MessageBar>
            )}

            <div className={styles.layout}>
                <div className={styles.cards}>
                    <SenderCard {...cardProps('sender')} />
                    <SubjectCard {...cardProps('subject')} />
                    <RecipientsCard {...cardProps('recipients')} />
                    <DesignCard campaign={campaign} editable={editable} onSaved={handleSaved} onPreview={() => setPreviewOpen(true)} />
                    <AbTestCard {...cardProps('abTest')} />
                    <SettingsCard {...cardProps('settings')} />
                </div>
                <aside className={styles.aside}>
                    <ChecklistPanel
                        campaignId={campaign.id}
                        refreshKey={checklistKey}
                        canCheckLinks={editable && isDesignComplete(campaign)}
                        onLoaded={setChecklist}
                    />
                </aside>
            </div>

            <PreviewAndTestPanel
                open={previewOpen}
                campaign={campaign}
                onClose={() => setPreviewOpen(false)}
                onTestSent={(result) => {
                    setCampaign((current) => (current ? { ...current, test_sent_at: result.test_sent_at } : current));
                    setChecklistKey((key) => key + 1);
                }}
            />
            <ConfirmSendDialog
                open={confirmOpen}
                campaign={campaign}
                onClose={() => setConfirmOpen(false)}
                onDone={handleScheduled}
            />
        </AppShell>
    );
}
