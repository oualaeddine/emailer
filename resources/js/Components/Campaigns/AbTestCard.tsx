import { useEffect, useState } from 'react';
import {
    Badge,
    Button,
    Checkbox,
    Dropdown,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Option,
    Radio,
    RadioGroup,
    Slider,
    Switch,
    Text,
    Textarea,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { CopyRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { saveAbTest } from '@/Lib/api/abTest';
import type { AbTestConfig, AbTestType, AbTestVariantInput, AbWinnerMetric, CampaignV2Detail } from '@/Lib/types/campaigns';
import { EmailThumbnail } from '@/Components/Campaigns/EmailFrame';
import { SetupCard } from '@/Components/Campaigns/SetupCard';
import type { SetupStepCardProps } from '@/Components/Campaigns/cardTypes';
import { apiErrorMessage, apiValidationErrors, formatText } from '@/Components/Campaigns/campaignUi';

const SUBJECT_MAX = 255;
const SAMPLE_MIN = 5;
const SAMPLE_MAX = 50;
const DURATION_HOURS = [1, 2, 3, 4, 6, 8, 12, 24, 48, 72, 96, 120, 144, 168];

const DEFAULT_CONFIG: AbTestConfig = {
    type: 'subject',
    sample_percent: 20,
    winner_metric: 'opens',
    test_duration_minutes: 240,
    auto_send_winner: true,
};

const useStyles = makeStyles({
    row: {
        display: 'grid',
        gridTemplateColumns: 'repeat(auto-fit, minmax(220px, 1fr))',
        gap: tokens.spacingHorizontalM,
    },
    summary: {
        display: 'flex',
        alignItems: 'center',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
    },
    placeholder: {
        padding: tokens.spacingVerticalS,
        paddingLeft: tokens.spacingHorizontalM,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground2,
        color: tokens.colorNeutralForeground2,
    },
    html: {
        fontFamily: tokens.fontFamilyMonospace,
        minHeight: '160px',
    },
    copy: {
        alignSelf: 'flex-start',
    },
});

function variantOf(campaign: CampaignV2Detail, label: 'A' | 'B') {
    return campaign.ab_test?.variants.find((variant) => variant.label === label) ?? null;
}

/**
 * Readiness mirrored from the server guard (`errors.ab_test`): always true
 * for a classic campaign; an A/B campaign needs two distinct subjects
 * (subject test) or a version B html (content test).
 */
export function isAbTestReady(campaign: CampaignV2Detail): boolean {
    const abTest = campaign.ab_test;
    if (!abTest) {
        return campaign.type !== 'ab_test';
    }
    const a = variantOf(campaign, 'A');
    const b = variantOf(campaign, 'B');
    if (abTest.config.type === 'subject') {
        const subjectA = (a?.subject ?? '').trim();
        const subjectB = (b?.subject ?? '').trim();

        return subjectA !== '' && subjectB !== '' && subjectA !== subjectB;
    }

    return (b?.html_body ?? '').trim() !== '';
}

/**
 * Setup card **Test A/B** (docs/43 §43.4.2, §43.6.2, WP-80): enable toggle,
 * tested element (subject A/B, or content: version A = campaign design,
 * version B = its own html), sample size, winner criterion, test duration
 * and automatic sending of the winner. Saved with `PUT /campaigns/{id}/ab-test`.
 */
export function AbTestCard({ campaign, editable, expanded, onExpand, onCollapse, onSaved }: SetupStepCardProps) {
    const styles = useStyles();
    const t = useText();
    const s = t.campaignSetup;

    const [enabled, setEnabled] = useState(campaign.ab_test_enabled);
    const [config, setConfig] = useState<AbTestConfig>(campaign.ab_test?.config ?? DEFAULT_CONFIG);
    const [subjectA, setSubjectA] = useState('');
    const [subjectB, setSubjectB] = useState('');
    const [htmlB, setHtmlB] = useState('');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<string[]>([]);

    useEffect(() => {
        if (expanded) {
            setEnabled(campaign.ab_test_enabled || campaign.type === 'ab_test');
            setConfig(campaign.ab_test?.config ?? DEFAULT_CONFIG);
            setSubjectA(variantOf(campaign, 'A')?.subject || campaign.subject || '');
            setSubjectB(variantOf(campaign, 'B')?.subject ?? '');
            setHtmlB(variantOf(campaign, 'B')?.html_body ?? '');
            setErrors([]);
        }
    }, [expanded, campaign]);

    function update<K extends keyof AbTestConfig>(key: K, value: AbTestConfig[K]) {
        setConfig((current) => ({ ...current, [key]: value }));
    }

    async function handleSave() {
        setSaving(true);
        setErrors([]);
        const variants: AbTestVariantInput[] =
            config.type === 'subject'
                ? [
                      { label: 'A', subject: subjectA.trim() },
                      { label: 'B', subject: subjectB.trim() },
                  ]
                : [
                      { label: 'A', subject: '', html_body: null },
                      { label: 'B', subject: '', html_body: htmlB },
                  ];
        try {
            onSaved(await saveAbTest(campaign.id, { enabled, config, variants }));
            onCollapse();
        } catch (err) {
            const validation = Object.values(apiValidationErrors(err));
            setErrors(validation.length > 0 ? validation : [apiErrorMessage(err)]);
        } finally {
            setSaving(false);
        }
    }

    const abTest = campaign.ab_test;
    const half = config.sample_percent / 2;
    const durationHours = Math.max(1, Math.round(config.test_duration_minutes / 60));
    const hours = DURATION_HOURS.includes(durationHours) ? DURATION_HOURS : [...DURATION_HOURS, durationHours].sort((x, y) => x - y);
    const subjectInvalid = config.type === 'subject' && (subjectA.trim() === '' || subjectB.trim() === '');

    const summary = abTest ? (
        <div className={styles.summary}>
            <Text>
                {[
                    s.abTestTypes[abTest.config.type],
                    formatText(t.common.percent, abTest.config.sample_percent),
                    s.abWinnerMetrics[abTest.config.winner_metric],
                    formatText(s.abTest.durationHours, Math.round(abTest.config.test_duration_minutes / 60)),
                ].join(' · ')}
            </Text>
            {abTest.phase !== 'not_started' && (
                <Badge appearance="tint" color={abTest.phase === 'testing' ? 'warning' : 'success'}>
                    {s.abPhases[abTest.phase]}
                </Badge>
            )}
        </div>
    ) : (
        <Text>{t.common.disabled}</Text>
    );

    return (
        <SetupCard
            title={s.abTest.title}
            complete={abTest !== null && isAbTestReady(campaign)}
            summary={summary}
            actionLabel={abTest ? t.common.edit : s.abTest.enable}
            onAction={onExpand}
            disabled={!editable || campaign.status !== 'draft'}
            expanded={expanded}
            onCancel={onCollapse}
            onSave={() => void handleSave()}
            saving={saving}
            saveDisabled={enabled && subjectInvalid}
        >
            {errors.length > 0 && (
                <MessageBar intent="error">
                    <MessageBarBody>{errors.join(' ')}</MessageBarBody>
                </MessageBar>
            )}
            <Switch checked={enabled} label={s.abTest.enable} disabled={saving} onChange={(_, data) => setEnabled(data.checked)} />

            {enabled && (
                <>
                    <Field label={s.abTest.type}>
                        <RadioGroup
                            layout="horizontal"
                            value={config.type}
                            onChange={(_, data) => update('type', data.value as AbTestType)}
                        >
                            <Radio value="subject" label={s.abTestTypes.subject} />
                            <Radio value="content" label={s.abTestTypes.content} />
                        </RadioGroup>
                    </Field>

                    {config.type === 'subject' ? (
                        <div className={styles.row}>
                            <Field label={s.abTest.subjectA} required>
                                <Input
                                    value={subjectA}
                                    maxLength={SUBJECT_MAX}
                                    placeholder={s.subject.subjectPlaceholder}
                                    disabled={saving}
                                    onChange={(_, data) => setSubjectA(data.value)}
                                />
                            </Field>
                            <Field label={s.abTest.subjectB} required>
                                <Input
                                    value={subjectB}
                                    maxLength={SUBJECT_MAX}
                                    placeholder={s.subject.subjectPlaceholder}
                                    disabled={saving}
                                    onChange={(_, data) => setSubjectB(data.value)}
                                />
                            </Field>
                        </div>
                    ) : (
                        <>
                            <div className={styles.row}>
                                <Field label={s.abTest.variantA}>
                                    {campaign.html_body ? (
                                        <EmailThumbnail html={campaign.html_body} title={s.abTest.variantA} />
                                    ) : (
                                        <Text className={styles.placeholder}>{s.design.noDesign}</Text>
                                    )}
                                </Field>
                                <Field label={s.abTest.variantB}>
                                    {htmlB.trim() !== '' ? (
                                        <EmailThumbnail html={htmlB} title={s.abTest.variantB} />
                                    ) : (
                                        <Text className={styles.placeholder}>{s.design.noDesign}</Text>
                                    )}
                                </Field>
                            </div>
                            <Field label={s.abTest.variantBHtml} required>
                                <Textarea
                                    className={styles.html}
                                    value={htmlB}
                                    resize="vertical"
                                    disabled={saving}
                                    onChange={(_, data) => setHtmlB(data.value)}
                                />
                            </Field>
                            <Button
                                className={styles.copy}
                                icon={<CopyRegular />}
                                disabled={saving || !campaign.html_body}
                                onClick={() => setHtmlB(campaign.html_body ?? '')}
                            >
                                {s.abTest.copyFromVariantA}
                            </Button>
                        </>
                    )}

                    <Field label={s.abTest.samplePercent} hint={formatText(s.abTest.samplePercentHelp, half, half)}>
                        <Slider
                            min={SAMPLE_MIN}
                            max={SAMPLE_MAX}
                            step={5}
                            value={config.sample_percent}
                            disabled={saving}
                            onChange={(_, data) => update('sample_percent', data.value)}
                        />
                    </Field>

                    <div className={styles.row}>
                        <Field label={s.abTest.winnerMetric}>
                            <RadioGroup
                                value={config.winner_metric}
                                onChange={(_, data) => update('winner_metric', data.value as AbWinnerMetric)}
                            >
                                <Radio value="opens" label={s.abWinnerMetrics.opens} />
                                <Radio value="clicks" label={s.abWinnerMetrics.clicks} />
                            </RadioGroup>
                        </Field>
                        <Field label={s.abTest.duration}>
                            <Dropdown
                                value={formatText(s.abTest.durationHours, durationHours)}
                                selectedOptions={[String(durationHours)]}
                                disabled={saving}
                                onOptionSelect={(_, data) => update('test_duration_minutes', Number(data.optionValue) * 60)}
                            >
                                {hours.map((hour) => (
                                    <Option key={hour} value={String(hour)}>
                                        {formatText(s.abTest.durationHours, hour)}
                                    </Option>
                                ))}
                            </Dropdown>
                        </Field>
                    </div>

                    <Checkbox
                        checked={config.auto_send_winner}
                        label={s.abTest.autoSendWinner}
                        disabled={saving}
                        onChange={(_, data) => update('auto_send_winner', data.checked === true)}
                    />
                </>
            )}
        </SetupCard>
    );
}
