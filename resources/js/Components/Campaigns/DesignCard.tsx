import { useState } from 'react';
import { router } from '@inertiajs/react';
import { Button, Text, makeStyles, tokens } from '@fluentui/react-components';
import { EditRegular, EyeRegular, PaintBrushRegular, ArrowSwapRegular } from '@fluentui/react-icons';
import TemplatePicker from '@/Components/Editor/TemplatePicker';
import { useText } from '@/Hooks/useText';
import type { CampaignV2Detail } from '@/Lib/types/campaigns';
import { SetupCard } from '@/Components/Campaigns/SetupCard';
import { EmailThumbnail } from '@/Components/Campaigns/EmailFrame';
import { campaignDesignUrl } from '@/Components/Campaigns/campaignUi';

const useStyles = makeStyles({
    body: {
        display: 'flex',
        gap: tokens.spacingHorizontalL,
        alignItems: 'flex-start',
        flexWrap: 'wrap',
        paddingTop: tokens.spacingVerticalS,
    },
    thumbnail: {
        width: '180px',
        height: '240px',
        objectFit: 'cover',
        objectPosition: 'top',
        borderRadius: tokens.borderRadiusMedium,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
    },
    actions: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'flex-start',
        gap: tokens.spacingVerticalS,
    },
});

export function isDesignComplete(campaign: CampaignV2Detail): boolean {
    return (campaign.html_body ?? '').trim().length > 0;
}

export interface DesignCardProps {
    campaign: CampaignV2Detail;
    editable: boolean;
    onSaved: (campaign: CampaignV2Detail) => void;
    onPreview: () => void;
}

/**
 * Card 4 — **Design**: thumbnail or *Commencer la conception* → TemplatePicker
 * (WP-44, docs/43 §43.4.3); Modifier le design (Wave 3 editor page),
 * Changer de modèle, Aperçu.
 */
export function DesignCard({ campaign, editable, onSaved, onPreview }: DesignCardProps) {
    const styles = useStyles();
    const t = useText();
    const [pickerOpen, setPickerOpen] = useState(false);
    const complete = isDesignComplete(campaign);

    return (
        <>
            <SetupCard
                title={t.campaignSetup.design.title}
                complete={complete}
                summary={
                    complete ? (
                        <div className={styles.body}>
                            {campaign.thumbnail_url ? (
                                <img className={styles.thumbnail} src={campaign.thumbnail_url} alt={t.campaignSetup.design.title} />
                            ) : (
                                <EmailThumbnail html={campaign.html_body ?? ''} title={t.campaignSetup.design.title} />
                            )}
                            <div className={styles.actions}>
                                <Button
                                    appearance="primary"
                                    icon={<EditRegular />}
                                    disabled={!editable}
                                    onClick={() => router.visit(campaignDesignUrl(campaign.id))}
                                >
                                    {t.campaignSetup.design.edit}
                                </Button>
                                <Button icon={<ArrowSwapRegular />} disabled={!editable} onClick={() => setPickerOpen(true)}>
                                    {t.campaignSetup.design.change}
                                </Button>
                                <Button appearance="subtle" icon={<EyeRegular />} onClick={onPreview}>
                                    {t.campaignSetup.design.preview}
                                </Button>
                            </div>
                        </div>
                    ) : (
                        <Text>{t.campaignSetup.design.noDesign}</Text>
                    )
                }
                extraActions={
                    complete ? undefined : (
                        <Button appearance="primary" icon={<PaintBrushRegular />} disabled={!editable} onClick={() => setPickerOpen(true)}>
                            {t.campaignSetup.design.start}
                        </Button>
                    )
                }
            />
            <TemplatePicker
                open={pickerOpen}
                onClose={() => setPickerOpen(false)}
                campaignId={campaign.id}
                onApplied={(updated: CampaignV2Detail) => {
                    setPickerOpen(false);
                    onSaved(updated);
                }}
            />
        </>
    );
}
