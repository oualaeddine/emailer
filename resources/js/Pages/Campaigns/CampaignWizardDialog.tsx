import { useCallback, useEffect, useState } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    DialogTrigger,
    Dropdown,
    Field,
    Input,
    Option,
    Radio,
    RadioGroup,
    Tab,
    TabList,
    Text,
    makeStyles,
    tokens,
    type SelectTabData,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { createCampaign, scheduleCampaign, sendCampaign } from '@/Lib/api/campaigns';
import { fetchTemplates } from '@/Lib/api/templates';
import type { CampaignTargetType } from '@/Lib/types/campaigns';
import type { Template } from '@/Lib/types/templates';

interface CampaignWizardDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onCreated: () => Promise<void> | void;
}

type WizardStep = 'basics' | 'target' | 'schedule' | 'review';
type SendMode = 'now' | 'scheduled';

const STEPS: WizardStep[] = ['basics', 'target', 'schedule', 'review'];

const useStyles = makeStyles({
    body: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        width: 'min(480px, 100%)',
    },
    tabList: {
        overflowX: 'auto',
        flexWrap: 'nowrap',
    },
    summaryRow: {
        display: 'flex',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalM,
    },
});

/**
 * docs/15-campaign-management.md §15.2 — Campaign Wizard, built as a
 * dialog (not a dedicated page): the doc describes a bounded 5-field-group
 * flow with no long-lived per-step editing session, so a `TabList`-as-stepper
 * inside a Dialog (same weight class as Templates/TemplateFormDialog.tsx)
 * is sufficient — no need for full-page routing between steps. The
 * Content step (rich-text composer, doc step 2) is intentionally not
 * reproduced here: campaign content is copied from the chosen template
 * (docs/12-template-system.md §12.6) and edited afterwards from the
 * Campaign Detail "Content" tab while the campaign is still `draft`
 * (§15.8), not during creation.
 */
export function CampaignWizardDialog({ open, onOpenChange, onCreated }: CampaignWizardDialogProps) {
    const styles = useStyles();
    const t = useText();

    const [step, setStep] = useState<WizardStep>('basics');
    const [templates, setTemplates] = useState<Template[]>([]);
    const [name, setName] = useState('');
    const [templateId, setTemplateId] = useState('');
    const [targetType, setTargetType] = useState<CampaignTargetType>('list');
    const [targetId, setTargetId] = useState('');
    const [sendMode, setSendMode] = useState<SendMode>('now');
    const [scheduledAt, setScheduledAt] = useState('');
    const [submitting, setSubmitting] = useState(false);

    const load = useCallback(async () => {
        setTemplates(await fetchTemplates());
    }, []);

    useEffect(() => {
        if (open) {
            void load();
        }
    }, [open, load]);

    function reset() {
        setStep('basics');
        setName('');
        setTemplateId('');
        setTargetType('list');
        setTargetId('');
        setSendMode('now');
        setScheduledAt('');
    }

    function handleOpenChange(nextOpen: boolean) {
        if (!nextOpen) {
            reset();
        }
        onOpenChange(nextOpen);
    }

    const selectedTemplate = templates.find((template) => template.id === templateId);
    const canReview = name.trim().length > 0 && templateId.length > 0 && targetId.trim().length > 0;

    /**
     * `StoreCampaignRequest` takes `recipient_list_id`/`segment_id` directly
     * (`required_without`/`prohibits` each other), not a `target_type`/
     * `target_id` pair — `targetType` here is purely a UI radio choice.
     */
    function buildPayload() {
        return {
            name,
            template_id: templateId,
            ...(targetType === 'list' ? { recipient_list_id: targetId } : { segment_id: targetId }),
        };
    }

    async function handleSaveDraft() {
        setSubmitting(true);
        try {
            await createCampaign(buildPayload());
            await onCreated();
            handleOpenChange(false);
        } finally {
            setSubmitting(false);
        }
    }

    async function handleConfirmSend() {
        setSubmitting(true);
        try {
            const campaign = await createCampaign(buildPayload());
            if (sendMode === 'scheduled' && scheduledAt) {
                await scheduleCampaign(campaign.id, new Date(scheduledAt).toISOString());
            } else {
                await sendCampaign(campaign.id);
            }
            await onCreated();
            handleOpenChange(false);
        } finally {
            setSubmitting(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => handleOpenChange(data.open)}>
            <DialogSurface>
                <DialogBody>
                    <DialogTitle>{t.campaigns.wizardTitle}</DialogTitle>
                    <DialogContent className={styles.body}>
                        <TabList
                            className={styles.tabList}
                            selectedValue={step}
                            onTabSelect={(_, data: SelectTabData) => setStep(data.value as WizardStep)}
                        >
                            <Tab value="basics">{t.campaigns.stepBasics}</Tab>
                            <Tab value="target">{t.campaigns.stepTarget}</Tab>
                            <Tab value="schedule">{t.campaigns.stepSchedule}</Tab>
                            <Tab value="review">{t.campaigns.stepReview}</Tab>
                        </TabList>

                        {step === 'basics' && (
                            <>
                                <Field label={t.campaigns.name} required>
                                    <Input value={name} onChange={(_, data) => setName(data.value)} />
                                </Field>
                                <Field label={t.campaigns.template} required>
                                    <Dropdown
                                        placeholder={t.campaigns.selectTemplate}
                                        value={selectedTemplate?.name ?? ''}
                                        onOptionSelect={(_, data) => setTemplateId(data.optionValue ?? '')}
                                    >
                                        {templates.map((template) => (
                                            <Option key={template.id} value={template.id}>
                                                {template.name}
                                            </Option>
                                        ))}
                                    </Dropdown>
                                </Field>
                            </>
                        )}

                        {step === 'target' && (
                            <>
                                <Field label={t.campaigns.target}>
                                    <RadioGroup
                                        value={targetType}
                                        onChange={(_, data) => setTargetType(data.value as CampaignTargetType)}
                                    >
                                        <Radio value="list" label={t.campaigns.targetTypeList} />
                                        <Radio value="segment" label={t.campaigns.targetTypeSegment} />
                                    </RadioGroup>
                                </Field>
                                <Field label={t.campaigns.targetId} required>
                                    <Input value={targetId} onChange={(_, data) => setTargetId(data.value)} />
                                </Field>
                            </>
                        )}

                        {step === 'schedule' && (
                            <>
                                <Field label={t.campaigns.stepSchedule}>
                                    <RadioGroup value={sendMode} onChange={(_, data) => setSendMode(data.value as SendMode)}>
                                        <Radio value="now" label={t.campaigns.sendModeNow} />
                                        <Radio value="scheduled" label={t.campaigns.sendModeScheduled} />
                                    </RadioGroup>
                                </Field>
                                {sendMode === 'scheduled' && (
                                    <Field label={t.campaigns.scheduledAt} required>
                                        <Input
                                            type="datetime-local"
                                            value={scheduledAt}
                                            onChange={(_, data) => setScheduledAt(data.value)}
                                        />
                                    </Field>
                                )}
                            </>
                        )}

                        {step === 'review' && (
                            <>
                                <Text block>{t.campaigns.reviewSummary}</Text>
                                <div className={styles.summaryRow}>
                                    <Text weight="semibold">{t.campaigns.name}</Text>
                                    <Text>{name || '—'}</Text>
                                </div>
                                <div className={styles.summaryRow}>
                                    <Text weight="semibold">{t.campaigns.template}</Text>
                                    <Text>{selectedTemplate?.name ?? '—'}</Text>
                                </div>
                                <div className={styles.summaryRow}>
                                    <Text weight="semibold">{t.campaigns.target}</Text>
                                    <Text>
                                        {targetType === 'list' ? t.campaigns.targetTypeList : t.campaigns.targetTypeSegment}{' '}
                                        ({targetId || '—'})
                                    </Text>
                                </div>
                                <div className={styles.summaryRow}>
                                    <Text weight="semibold">{t.campaigns.stepSchedule}</Text>
                                    <Text>
                                        {sendMode === 'now'
                                            ? t.campaigns.sendModeNow
                                            : `${t.campaigns.sendModeScheduled} — ${scheduledAt || '—'}`}
                                    </Text>
                                </div>
                            </>
                        )}
                    </DialogContent>
                    <DialogActions>
                        <DialogTrigger disableButtonEnhancement>
                            <Button appearance="secondary">{t.common.cancel}</Button>
                        </DialogTrigger>
                        {step === 'review' ? (
                            <>
                                <Button appearance="secondary" disabled={submitting || !canReview} onClick={() => void handleSaveDraft()}>
                                    {t.campaigns.saveDraft}
                                </Button>
                                <Button
                                    appearance="primary"
                                    disabled={submitting || !canReview || (sendMode === 'scheduled' && !scheduledAt)}
                                    onClick={() => void handleConfirmSend()}
                                >
                                    {sendMode === 'now' ? t.campaigns.sendNow : t.campaigns.schedule}
                                </Button>
                            </>
                        ) : (
                            <Button
                                appearance="primary"
                                onClick={() => setStep(STEPS[Math.min(STEPS.indexOf(step) + 1, STEPS.length - 1)])}
                            >
                                {t.common.confirm}
                            </Button>
                        )}
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
