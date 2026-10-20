import { useState } from 'react';
import { Button, MessageBar, MessageBarActions, MessageBarBody, MessageBarTitle, Spinner } from '@fluentui/react-components';
import { CheckmarkCircleRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { approveCampaign } from '@/Lib/api/campaigns';
import type { CampaignV2, CampaignV2Detail } from '@/Lib/types/campaigns';
import { apiErrorMessage, formatDateTime, formatText } from '@/Components/Campaigns/campaignUi';

/** Statuses in which an approval is still meaningful (and can be given: draft / suspended). */
const OPEN_STATUSES: CampaignV2['status'][] = ['draft', 'scheduled', 'suspended'];

/** Campaigns that wait for an approval the backend will accept (`CampaignService::approve`). */
export function canBeApproved(campaign: Pick<CampaignV2, 'approval_status' | 'status' | 'is_archived'>): boolean {
    return campaign.approval_status === 'pending' && !campaign.is_archived && (campaign.status === 'draft' || campaign.status === 'suspended');
}

/** True while « Approbation requise avant l'envoi » is on and nobody approved yet. */
export function isApprovalPending(campaign: Pick<CampaignV2, 'approval_status'>): boolean {
    return campaign.approval_status === 'pending';
}

export interface ApprovalBannerProps {
    campaign: CampaignV2Detail;
    /** The viewer holds `campaigns.approve`. */
    canApprove: boolean;
    onApproved: (campaign: CampaignV2Detail) => void;
}

/**
 * gap-approval — state banner of Campaigns/Setup: « En attente d'approbation »
 * (with **Approuver** for users holding `campaigns.approve`) or the approver
 * once approved. Nothing when approval is not required.
 */
export function ApprovalBanner({ campaign, canApprove, onApproved }: ApprovalBannerProps) {
    const t = useText();
    const a = t.campaignSetup.approval;
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);

    if (campaign.approval_status === 'not_required' || campaign.is_archived || !OPEN_STATUSES.includes(campaign.status)) {
        return null;
    }

    async function approve() {
        setBusy(true);
        setError(null);
        try {
            onApproved(await approveCampaign(campaign.id));
        } catch (err) {
            setError(apiErrorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    if (campaign.approval_status === 'approved') {
        return (
            <MessageBar intent="success" style={{ marginBottom: 16 }}>
                <MessageBarBody>
                    <MessageBarTitle>{a.approvedTitle}</MessageBarTitle>
                    {formatText(a.approvedBy, campaign.approver?.name ?? t.common.emptyValue, formatDateTime(campaign.approved_at))} {a.approvedNote}
                </MessageBarBody>
            </MessageBar>
        );
    }

    return (
        <MessageBar intent="warning" layout="multiline" style={{ marginBottom: 16 }} role="status">
            <MessageBarBody>
                <MessageBarTitle>{a.pendingTitle}</MessageBarTitle>
                {canApprove ? a.pendingCanApprove : a.pendingCannotApprove}
                {error && <div role="alert">{error}</div>}
            </MessageBarBody>
            {canApprove && canBeApproved(campaign) && (
                <MessageBarActions>
                    <Button
                        appearance="primary"
                        icon={busy ? <Spinner size="tiny" /> : <CheckmarkCircleRegular />}
                        disabled={busy}
                        onClick={() => void approve()}
                    >
                        {busy ? a.approving : a.approve}
                    </Button>
                </MessageBarActions>
            )}
        </MessageBar>
    );
}
