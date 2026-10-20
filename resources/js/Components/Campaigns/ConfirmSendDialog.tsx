import { useEffect, useState } from 'react';
import { isAxiosError } from 'axios';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Divider,
    MessageBar,
    MessageBarBody,
    Spinner,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { estimateCampaignAudience, scheduleCampaignV2 } from '@/Lib/api/campaigns';
import type { CampaignChecklistKey, CampaignSendMode, CampaignV2Detail } from '@/Lib/types/campaigns';
import {
    SendModeOptions,
    buildScheduleInput,
    defaultSendModeValue,
    zonedDateTime,
    type SendModeError,
    type SendModeValue,
} from '@/Components/Campaigns/SendModeOptions';
import { apiErrorMessage, apiValidationErrors, formatText } from '@/Components/Campaigns/campaignUi';

const useStyles = makeStyles({
    surface: {
        maxWidth: '640px',
        width: '100%',
    },
    content: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    summary: {
        display: 'grid',
        gridTemplateColumns: 'max-content minmax(0, 1fr)',
        columnGap: tokens.spacingHorizontalL,
        rowGap: tokens.spacingVerticalS,
        padding: tokens.spacingVerticalM,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground2,
    },
    label: {
        color: tokens.colorNeutralForeground3,
    },
    value: {
        overflowWrap: 'anywhere',
    },
});

/**
 * Delivery options offered for the campaign. An A/B test campaign only
 * offers `now` / `scheduled`: the server rejects `best_time` and `batches`
 * for it (docs/45 §45.7.2 — the sample split and the winner pick need the
 * whole sample released at once).
 */
export function allowedSendModes(campaign: CampaignV2Detail): CampaignSendMode[] {
    const modes: CampaignSendMode[] = [];
    if (campaign.available_actions.includes('send_now')) {
        modes.push('now');
    }
    if (campaign.available_actions.includes('schedule')) {
        const abTest = Boolean(campaign.ab_test_enabled || campaign.ab_test);
        modes.push(...(abTest ? (['scheduled'] as const) : (['scheduled', 'best_time', 'batches'] as const)));
    }

    return modes;
}

export interface ConfirmSendDialogProps {
    open: boolean;
    campaign: CampaignV2Detail;
    onClose: () => void;
    onDone: (campaign: CampaignV2Detail, mode: CampaignSendMode) => void;
}

/**
 * **Confirmer l'envoi** (docs/43 §43.4.2): summary (De, À + count, Objet,
 * Envoi prévu) and the four delivery options → `POST /campaigns/{id}/schedule`.
 */
export function ConfirmSendDialog({ open, campaign, onClose, onDone }: ConfirmSendDialogProps) {
    const styles = useStyles();
    const t = useText();
    const c = t.campaignSetup.confirm;
    const modes = allowedSendModes(campaign);
    const [value, setValue] = useState<SendModeValue>(() => defaultSendModeValue(campaign.timezone, modes[0] ?? 'now'));
    const [recipients, setRecipients] = useState<number | null>(campaign.audience.estimated_count);
    const [busy, setBusy] = useState(false);
    const [fieldError, setFieldError] = useState<SendModeError | null>(null);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        setValue(defaultSendModeValue(campaign.timezone, allowedSendModes(campaign)[0] ?? 'now'));
        setFieldError(null);
        setError(null);
        setRecipients(campaign.audience.estimated_count);
        let cancelled = false;
        estimateCampaignAudience(campaign.id)
            .then((estimate) => !cancelled && setRecipients(estimate.total))
            .catch(() => undefined);

        return () => {
            cancelled = true;
        };
    }, [open, campaign]);

    function scheduledForLabel(): string {
        if (value.mode === 'now') {
            return t.campaignSetup.sendModes.now;
        }
        const zoned = value.date && value.time ? zonedDateTime(value.date, value.time, value.timezone) : null;
        const when = zoned
            ? `${new Date(zoned.instant).toLocaleString('fr-FR', {
                  dateStyle: 'full',
                  timeStyle: 'short',
                  timeZone: value.timezone,
              })} (${value.timezone})`
            : null;
        if (value.mode === 'scheduled') {
            return when ?? t.common.emptyValue;
        }

        return when ? `${t.campaignSetup.sendModes[value.mode]} — ${when}` : t.campaignSetup.sendModes[value.mode];
    }

    async function handleSubmit() {
        const built = buildScheduleInput(value);
        if ('error' in built) {
            setFieldError(built.error);

            return;
        }
        setFieldError(null);
        setError(null);
        setBusy(true);
        try {
            const updated = await scheduleCampaignV2(campaign.id, built.input);
            onDone(updated, value.mode);
        } catch (err) {
            if (isAxiosError(err) && err.response?.status === 422) {
                const raw = (err.response.data as { errors?: { checklist?: unknown } } | undefined)?.errors?.checklist;
                if (Array.isArray(raw) && raw.length > 0) {
                    const labels = raw.map((key) => t.campaignSetup.checklistItems[key as CampaignChecklistKey] ?? String(key));
                    setError(formatText(c.checklistBlocked, labels.join(', ')));

                    return;
                }
                const validation = apiValidationErrors(err);
                setError(Object.values(validation)[0] ?? apiErrorMessage(err));

                return;
            }
            setError(apiErrorMessage(err));
        } finally {
            setBusy(false);
        }
    }

    const submitLabel: Record<CampaignSendMode, string> = {
        now: c.submitNow,
        scheduled: c.submitSchedule,
        best_time: c.submitBestTime,
        batches: c.submitBatches,
    };
    const targets = [...campaign.audience.lists, ...campaign.audience.segments].map((target) => target.name).join(', ');

    return (
        <Dialog open={open} onOpenChange={(_, data) => !data.open && !busy && onClose()}>
            <DialogSurface className={styles.surface}>
                <DialogBody>
                    <DialogTitle>{c.title}</DialogTitle>
                    <DialogContent className={styles.content}>
                        <Text weight="semibold">{c.summary}</Text>
                        <div className={styles.summary}>
                            <Text className={styles.label}>{c.from}</Text>
                            <Text className={styles.value}>
                                {campaign.sender ? `${campaign.sender.name} <${campaign.sender.email}>` : t.campaigns.noSender}
                            </Text>
                            <Text className={styles.label}>{c.to}</Text>
                            <Text className={styles.value}>
                                {targets || t.common.emptyValue}
                                {recipients !== null && (
                                    <>
                                        {' — '}
                                        <Text weight="semibold">{formatText(c.recipientsCount, recipients)}</Text>
                                    </>
                                )}
                            </Text>
                            <Text className={styles.label}>{c.subject}</Text>
                            <Text className={styles.value}>{campaign.subject ?? t.common.emptyValue}</Text>
                            <Text className={styles.label}>{c.scheduledFor}</Text>
                            <Text className={styles.value}>{scheduledForLabel()}</Text>
                        </div>
                        <Divider />
                        <Text weight="semibold">{c.deliveryOptions}</Text>
                        <SendModeOptions
                            campaignId={campaign.id}
                            value={value}
                            onChange={(next) => {
                                setValue(next);
                                setFieldError(null);
                            }}
                            allowedModes={modes}
                            disabled={busy}
                            error={fieldError}
                        />
                        {error && (
                            <MessageBar intent="error">
                                <MessageBarBody>{error}</MessageBarBody>
                            </MessageBar>
                        )}
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" disabled={busy} onClick={onClose}>
                            {t.common.cancel}
                        </Button>
                        <Button
                            appearance="primary"
                            disabled={busy || modes.length === 0}
                            icon={busy ? <Spinner size="tiny" /> : undefined}
                            onClick={() => void handleSubmit()}
                        >
                            {submitLabel[value.mode]}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
