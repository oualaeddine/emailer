import { Field, Radio, RadioGroup, SpinButton, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { automationMissingText as mt } from './text';

export type ReentryMode = 'never' | 'after' | 'always';

export function reentryMode(allowReentry: boolean, afterDays: number | null): ReentryMode {
    if (!allowReentry) return 'never';

    return afterDays !== null ? 'after' : 'always';
}

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    days: {
        maxWidth: '220px',
        marginLeft: tokens.spacingHorizontalXXL,
    },
});

export interface ReentrySettingsProps {
    allowReentry: boolean;
    reentryAfterDays: number | null;
    onChange: (value: { allow_reentry: boolean; reentry_after_days: number | null }) => void;
    errors?: Record<string, string>;
    disabled?: boolean;
}

/** Paramètres › re-entry: never / after N days / always (§45.7.5 allow_reentry + reentry_after_days). */
export function ReentrySettings({ allowReentry, reentryAfterDays, onChange, errors = {}, disabled }: ReentrySettingsProps) {
    const styles = useStyles();
    const t = useText();
    const mode = reentryMode(allowReentry, reentryAfterDays);
    const modeError = errors.allow_reentry;
    const daysError = errors.reentry_after_days;

    return (
        <div className={styles.root}>
            <Field label={mt.settings.reentryTitle} validationMessage={modeError} validationState={modeError ? 'error' : 'none'}>
                <RadioGroup
                    disabled={disabled}
                    value={mode}
                    onChange={(_, data) => {
                        const next = data.value as ReentryMode;
                        onChange({
                            allow_reentry: next !== 'never',
                            reentry_after_days: next === 'after' ? (reentryAfterDays ?? 30) : null,
                        });
                    }}
                >
                    <Radio value="never" label={mt.settings.reentryNever} />
                    <Radio value="after" label={mt.settings.reentryAfter} />
                    <Radio value="always" label={mt.settings.reentryAlways} />
                </RadioGroup>
            </Field>
            {mode === 'after' && (
                <Field
                    className={styles.days}
                    label={t.automations.reentryAfterDays}
                    validationMessage={daysError}
                    validationState={daysError ? 'error' : 'none'}
                >
                    <SpinButton
                        disabled={disabled}
                        min={1}
                        max={3650}
                        value={reentryAfterDays ?? 30}
                        onChange={(_, data) => {
                            const raw = data.value ?? Number.parseInt(data.displayValue ?? '', 10);
                            if (raw !== undefined && raw !== null && !Number.isNaN(raw)) {
                                onChange({ allow_reentry: true, reentry_after_days: Math.max(1, Math.min(3650, Math.round(raw))) });
                            }
                        }}
                    />
                </Field>
            )}
        </div>
    );
}
