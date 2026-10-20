import { Badge } from '@fluentui/react-components';
import { fr } from '@/Lib/i18n/fr';
import type { CampaignV2 } from '@/Lib/types/campaigns';

/**
 * gap-approval — approval state next to the status chip of the campaign list.
 * Only for campaigns that can still be sent (draft / scheduled / suspended);
 * a sent campaign no longer needs the flag.
 */
export function CampaignApprovalBadge({ campaign }: { campaign: Pick<CampaignV2, 'approval_status' | 'status' | 'is_archived'> }) {
    if (
        campaign.approval_status === 'not_required' ||
        campaign.is_archived ||
        !['draft', 'scheduled', 'suspended'].includes(campaign.status)
    ) {
        return null;
    }

    return (
        <Badge appearance="outline" size="small" color={campaign.approval_status === 'approved' ? 'success' : 'warning'}>
            {fr.campaigns.approvalStatus[campaign.approval_status]}
        </Badge>
    );
}
