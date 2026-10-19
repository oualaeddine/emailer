import { Badge, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { SetupCard } from '@/Components/Campaigns/SetupCard';
import { AdvancedSettingsDialog } from '@/Components/Campaigns/AdvancedSettingsDialog';
import type { SetupStepCardProps } from '@/Components/Campaigns/cardTypes';

const useStyles = makeStyles({
    summary: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
    tags: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalXXS,
    },
});

/** Card 5 — **Paramètres supplémentaires** (optional; always complete). */
export function SettingsCard({ campaign, editable, expanded, onExpand, onCollapse, onSaved }: SetupStepCardProps) {
    const styles = useStyles();
    const t = useText();
    const s = t.campaignSetup.settings;
    const onOff = (value: boolean) => (value ? t.common.enabled : t.common.disabled);

    return (
        <>
            <SetupCard
                title={s.title}
                complete
                summary={
                    <div className={styles.summary}>
                        {campaign.tags.length > 0 && (
                            <div className={styles.tags}>
                                {campaign.tags.map((tag) => (
                                    <Badge key={tag} appearance="tint" color="informative" size="small">
                                        {tag}
                                    </Badge>
                                ))}
                            </div>
                        )}
                        <Text size={200}>
                            {s.tracking} : {onOff(campaign.tracking_enabled)} · {s.utm} : {onOff(campaign.utm.enabled)}
                        </Text>
                        <Text size={200}>
                            {s.mirrorLink} : {onOff(campaign.mirror_link_enabled)} · {s.smtpStrategy} :{' '}
                            {t.campaignSetup.smtpStrategies[campaign.smtp_strategy]}
                        </Text>
                        {campaign.attachment && (
                            <Text size={200}>
                                {s.attachment} : {campaign.attachment.filename}
                            </Text>
                        )}
                        {campaign.requires_approval && <Text size={200}>{s.requiresApproval}</Text>}
                    </div>
                }
                actionLabel={s.edit}
                onAction={onExpand}
                disabled={!editable}
            />
            <AdvancedSettingsDialog open={expanded} campaign={campaign} onClose={onCollapse} onSaved={onSaved} />
        </>
    );
}
