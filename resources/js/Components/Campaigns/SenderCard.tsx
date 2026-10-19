import { useEffect, useState } from 'react';
import { Field, Input, Link, MessageBar, MessageBarBody, Text, makeStyles, tokens } from '@fluentui/react-components';
import { SenderPicker } from '@/Components/Senders/SenderPicker';
import { useText } from '@/Hooks/useText';
import { updateCampaignV2 } from '@/Lib/api/campaigns';
import type { Sender } from '@/Lib/types/senders';
import { SetupCard } from '@/Components/Campaigns/SetupCard';
import type { SetupStepCardProps } from '@/Components/Campaigns/cardTypes';
import { apiErrorMessage, apiValidationErrors, isEmailLike } from '@/Components/Campaigns/campaignUi';

const useStyles = makeStyles({
    summary: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
    warning: {
        color: tokens.colorPaletteDarkOrangeForeground1,
    },
});

export function isSenderComplete(campaign: SetupStepCardProps['campaign']): boolean {
    return campaign.sender !== null && campaign.sender.is_verified;
}

/** Card 1 — **Expéditeur** (verified sender + reply-to override, docs/43 §43.4.2). */
export function SenderCard({ campaign, editable, expanded, onExpand, onCollapse, onSaved }: SetupStepCardProps) {
    const styles = useStyles();
    const t = useText();
    const [senderId, setSenderId] = useState<string | null>(campaign.sender?.id ?? null);
    const [replyTo, setReplyTo] = useState(campaign.reply_to ?? '');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (expanded) {
            setSenderId(campaign.sender?.id ?? null);
            setReplyTo(campaign.reply_to ?? '');
            setErrors({});
            setError(null);
        }
    }, [expanded, campaign.sender?.id, campaign.reply_to]);

    function handleSenderChange(id: string | null, sender: Sender | null) {
        setSenderId(id);
        // Brevo pre-fills the reply-to with the sender's own reply-to address.
        if (sender?.reply_to && replyTo.trim() === '') {
            setReplyTo(sender.reply_to);
        }
    }

    const replyToInvalid = replyTo.trim() !== '' && !isEmailLike(replyTo.trim());

    async function handleSave() {
        if (!senderId || replyToInvalid) {
            return;
        }
        setSaving(true);
        setError(null);
        setErrors({});
        try {
            const updated = await updateCampaignV2(campaign.id, {
                sender_id: senderId,
                reply_to: replyTo.trim() === '' ? null : replyTo.trim(),
            });
            onSaved(updated);
            onCollapse();
        } catch (err) {
            const validation = apiValidationErrors(err);
            setErrors(validation);
            if (Object.keys(validation).length === 0) {
                setError(apiErrorMessage(err));
            }
        } finally {
            setSaving(false);
        }
    }

    const sender = campaign.sender;
    const complete = isSenderComplete(campaign);

    return (
        <SetupCard
            title={t.campaignSetup.sender.title}
            complete={complete}
            summary={
                sender ? (
                    <div className={styles.summary}>
                        <Text>
                            <Text weight="semibold">{sender.name}</Text> &lt;{sender.email}&gt;
                        </Text>
                        {campaign.reply_to && (
                            <Text size={200}>
                                {t.campaignSetup.sender.replyTo} : {campaign.reply_to}
                            </Text>
                        )}
                        {!sender.is_verified && (
                            <Text size={200} className={styles.warning}>
                                {t.campaignSetup.sender.notVerified}
                            </Text>
                        )}
                    </div>
                ) : (
                    <Text>{t.campaigns.noSender}</Text>
                )
            }
            actionLabel={sender ? t.campaignSetup.sender.edit : t.campaignSetup.sender.add}
            onAction={onExpand}
            disabled={!editable}
            expanded={expanded}
            onCancel={onCollapse}
            onSave={() => void handleSave()}
            saving={saving}
            saveDisabled={!senderId || replyToInvalid}
        >
            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
            <Field validationMessage={errors.sender_id}>
                <SenderPicker
                    value={senderId}
                    onChange={handleSenderChange}
                    verifiedOnly
                    disabled={saving}
                    label={t.campaignSetup.sender.label}
                />
            </Field>
            <Link href="/settings/senders" target="_blank" rel="noreferrer">
                {t.campaignSetup.sender.manage}
            </Link>
            <Field
                label={t.campaignSetup.sender.replyTo}
                hint={t.campaignSetup.sender.replyToHelp}
                validationMessage={errors.reply_to}
                validationState={errors.reply_to || replyToInvalid ? 'error' : 'none'}
            >
                <Input type="email" value={replyTo} disabled={saving} onChange={(_, data) => setReplyTo(data.value)} />
            </Field>
        </SetupCard>
    );
}
