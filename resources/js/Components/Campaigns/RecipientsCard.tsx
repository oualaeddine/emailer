import { Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { updateCampaignV2 } from '@/Lib/api/campaigns';
import type { CampaignAudienceInput } from '@/Lib/types/campaigns';
import { SetupCard } from '@/Components/Campaigns/SetupCard';
import { RecipientsPickerDialog } from '@/Components/Campaigns/RecipientsPickerDialog';
import type { SetupStepCardProps } from '@/Components/Campaigns/cardTypes';
import { formatPlural, formatText } from '@/Components/Campaigns/campaignUi';

const useStyles = makeStyles({
    summary: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
});

export function isRecipientsComplete(campaign: SetupStepCardProps['campaign']): boolean {
    return campaign.audience.lists.length + campaign.audience.segments.length > 0;
}

/** Card 3 — **Destinataires** (summary + RecipientsPickerDialog). */
export function RecipientsCard({ campaign, editable, expanded, onExpand, onCollapse, onSaved }: SetupStepCardProps) {
    const styles = useStyles();
    const t = useText();
    const audience = campaign.audience;
    const complete = isRecipientsComplete(campaign);
    const included = [...audience.lists, ...audience.segments].map((target) => target.name);
    const exclusions = audience.exclusion_lists.length + audience.excluded_segments.length;

    async function handleSubmit(input: CampaignAudienceInput, toField: string | null) {
        const updated = await updateCampaignV2(campaign.id, { audience: input, to_field: toField });
        onSaved(updated);
        onCollapse();
    }

    return (
        <>
            <SetupCard
                title={t.campaignSetup.recipients.title}
                complete={complete}
                summary={
                    complete ? (
                        <div className={styles.summary}>
                            <Text>
                                {[
                                    audience.lists.length > 0 ? formatText(t.campaignSetup.recipients.summaryLists, audience.lists.length) : null,
                                    audience.segments.length > 0
                                        ? formatText(t.campaignSetup.recipients.summarySegments, audience.segments.length)
                                        : null,
                                    exclusions > 0 ? formatText(t.campaignSetup.recipients.summaryExcluded, exclusions) : null,
                                ]
                                    .filter(Boolean)
                                    .join(' · ')}
                            </Text>
                            <Text size={200} truncate wrap={false}>
                                {included.join(', ')}
                            </Text>
                            {audience.estimated_count !== null && (
                                <Text weight="semibold">
                                    {formatPlural(audience.estimated_count, t.campaignSetup.recipients.estimatedCountOne, t.campaignSetup.recipients.estimatedCount)}
                                </Text>
                            )}
                            {campaign.to_field && (
                                <Text size={200}>
                                    {t.campaignSetup.recipients.toField} : {campaign.to_field}
                                </Text>
                            )}
                        </div>
                    ) : undefined
                }
                actionLabel={complete ? t.campaignSetup.recipients.edit : t.campaignSetup.recipients.add}
                onAction={onExpand}
                disabled={!editable}
            />
            <RecipientsPickerDialog open={expanded} campaign={campaign} onClose={onCollapse} onSubmit={handleSubmit} />
        </>
    );
}
