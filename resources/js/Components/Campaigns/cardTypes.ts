import type { CampaignV2Detail } from '@/Lib/types/campaigns';

/** Props shared by the five Campaigns/Setup cards (one card expanded at a time). */
export interface SetupStepCardProps {
    campaign: CampaignV2Detail;
    /** false when `edit` ∉ available_actions (running, completed…). */
    editable: boolean;
    expanded: boolean;
    onExpand: () => void;
    onCollapse: () => void;
    onSaved: (campaign: CampaignV2Detail) => void;
}
