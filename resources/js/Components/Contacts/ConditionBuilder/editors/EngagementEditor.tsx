import { useState } from 'react';
import { Dropdown, Input, Option } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { ConditionOptionSources, EngagementValue, FieldKey } from '@/Lib/types/segments';
import { MAX_ENGAGEMENT_CAMPAIGNS, defaultEngagementValue } from '../tree';
import { DateRangeEditor, DaysEditor } from './ScalarEditors';
import { AsyncOptionsPicker } from './OptionPickers';
import { ChipsInput, format, useEditorStyles, type EditorBaseProps } from './shared';

type PeriodMode = 'days' | 'ever' | 'window';

function periodModeOf(value: EngagementValue): PeriodMode {
    if (value.from != null || value.to != null) return 'window';

    return value.within_days != null ? 'days' : 'ever';
}

interface EngagementEditorProps extends EditorBaseProps {
    field: FieldKey;
    value: EngagementValue | null;
    onChange: (value: EngagementValue) => void;
    campaigns?: ConditionOptionSources['campaigns'];
}

/**
 * §45.5.2 `EngagementValue`: campaign X (1–50) or any campaign, within the last
 * N days / ever / an explicit window, plus link URL (clicked) and minimum count
 * (opened / clicked). `automation_step_id` is preserved untouched (WP-91).
 */
export function EngagementEditor({ field, value, onChange, campaigns, disabled, invalid, ariaLabel }: EngagementEditorProps) {
    const styles = useEditorStyles();
    const t = useText();
    const current: EngagementValue = value ?? defaultEngagementValue();
    const [period, setPeriod] = useState<PeriodMode>(periodModeOf(current));
    const e = t.conditionBuilder.engagement;
    const patch = (changes: Partial<EngagementValue>) => onChange({ ...current, ...changes });

    const campaignMode = current.campaign_ids === null ? 'any' : 'specific';
    const showMinCount = field === 'engagement.opened' || field === 'engagement.clicked';
    const showLink = field === 'engagement.clicked';

    function changePeriod(mode: PeriodMode) {
        setPeriod(mode);
        if (mode === 'days') patch({ within_days: current.within_days ?? 30, from: null, to: null });
        if (mode === 'ever') patch({ within_days: null, from: null, to: null });
        if (mode === 'window') patch({ within_days: null });
    }

    const periodLabels: Record<PeriodMode, string> = {
        days: t.conditionBuilder.operators.in_last_days,
        ever: e.ever,
        window: t.common.period,
    };

    return (
        <div className={styles.stack}>
            <span className={styles.inline}>
                <Dropdown
                    disabled={disabled}
                    aria-label={ariaLabel}
                    selectedOptions={[campaignMode]}
                    value={campaignMode === 'any' ? e.anyCampaign : e.specificCampaigns}
                    onOptionSelect={(_, data) => patch({ campaign_ids: data.optionValue === 'any' ? null : (current.campaign_ids ?? []) })}
                >
                    <Option value="any">{e.anyCampaign}</Option>
                    <Option value="specific">{e.specificCampaigns}</Option>
                </Dropdown>
                {campaignMode === 'specific' &&
                    (campaigns ? (
                        <AsyncOptionsPicker<string>
                            source={campaigns}
                            values={current.campaign_ids ?? []}
                            onChange={(ids) => patch({ campaign_ids: ids })}
                            decode={(raw) => raw}
                            max={MAX_ENGAGEMENT_CAMPAIGNS}
                            disabled={disabled}
                            invalid={invalid && (current.campaign_ids ?? []).length === 0}
                            ariaLabel={`${ariaLabel} — ${e.specificCampaigns}`}
                        />
                    ) : (
                        <ChipsInput<string>
                            values={current.campaign_ids ?? []}
                            onChange={(ids) => patch({ campaign_ids: ids })}
                            parse={(raw) => raw}
                            max={MAX_ENGAGEMENT_CAMPAIGNS}
                            disabled={disabled}
                            invalid={invalid && (current.campaign_ids ?? []).length === 0}
                            ariaLabel={`${ariaLabel} — ${e.specificCampaigns}`}
                        />
                    ))}
            </span>
            <span className={styles.inline}>
                <Dropdown
                    disabled={disabled}
                    aria-label={`${ariaLabel} — ${t.common.period}`}
                    selectedOptions={[period]}
                    value={periodLabels[period]}
                    onOptionSelect={(_, data) => changePeriod(data.optionValue as PeriodMode)}
                >
                    {(Object.keys(periodLabels) as PeriodMode[]).map((mode) => (
                        <Option key={mode} value={mode}>
                            {periodLabels[mode]}
                        </Option>
                    ))}
                </Dropdown>
                {period === 'days' && (
                    <DaysEditor
                        key="days"
                        value={current.within_days}
                        onChange={(v) => patch({ within_days: typeof v === 'number' ? v : null })}
                        disabled={disabled}
                        invalid={invalid}
                        ariaLabel={`${ariaLabel} — ${t.common.period}`}
                    />
                )}
                {period === 'window' && (
                    <DateRangeEditor
                        key="window"
                        value={current.from && current.to ? [current.from, current.to] : null}
                        onChange={(v) =>
                            Array.isArray(v) ? patch({ from: String(v[0]), to: String(v[1]) }) : patch({ from: null, to: null })
                        }
                        disabled={disabled}
                        invalid={invalid}
                        ariaLabel={`${ariaLabel} — ${t.common.period}`}
                    />
                )}
            </span>
            {(showLink || showMinCount) && (
                <span className={styles.inline}>
                    {showLink && (
                        <Input
                            className={styles.input}
                            type="url"
                            disabled={disabled}
                            aria-label={`${ariaLabel} — ${e.linkUrl}`}
                            placeholder={`${e.linkUrl} : ${e.anyLink}`}
                            value={current.link_url ?? ''}
                            onChange={(_, data) => patch({ link_url: data.value.trim() === '' ? null : data.value })}
                        />
                    )}
                    {showMinCount && (
                        <Input
                            className={styles.narrow}
                            type="number"
                            min={1}
                            step={1}
                            disabled={disabled}
                            aria-label={`${ariaLabel} — ${format(e.minCount, 'N')}`}
                            placeholder={format(e.minCount, 'N')}
                            value={current.min_count != null ? String(current.min_count) : ''}
                            onChange={(_, data) => {
                                const n = Number(data.value);
                                patch({ min_count: data.value === '' || !Number.isInteger(n) || n < 1 ? null : n });
                            }}
                        />
                    )}
                </span>
            )}
        </div>
    );
}
