import { useMemo } from 'react';
import { Dropdown, Field, Input, Option, Radio, RadioGroup, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { CampaignScheduleInput, CampaignSendMode } from '@/Lib/types/campaigns';

export const DEFAULT_TIMEZONE = 'Africa/Algiers';
const BATCH_SIZE_MIN = 50;
const BATCH_SIZE_MAX = 100000;
const BATCH_INTERVAL_MIN = 5;
const BATCH_INTERVAL_MAX = 1440;

const COMMON_TIMEZONES = [
    'Africa/Algiers',
    'Africa/Tunis',
    'Africa/Casablanca',
    'Africa/Cairo',
    'Europe/Paris',
    'Europe/London',
    'Europe/Madrid',
    'Europe/Berlin',
    'UTC',
    'America/New_York',
    'America/Montreal',
    'Asia/Dubai',
];

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
    },
    option: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        padding: tokens.spacingVerticalS,
        borderRadius: tokens.borderRadiusMedium,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
    },
    help: {
        color: tokens.colorNeutralForeground3,
        paddingLeft: '32px',
    },
    fields: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalM,
        paddingLeft: '32px',
    },
    field: {
        minWidth: '150px',
    },
});

/** Form state of the four Brevo delivery options. */
export interface SendModeValue {
    mode: CampaignSendMode;
    date: string;
    time: string;
    timezone: string;
    batchSize: string;
    batchInterval: string;
}

function pad(value: number): string {
    return String(value).padStart(2, '0');
}

/** Tomorrow at 09:00 (wall clock), the default of *Programmer pour plus tard*. */
export function defaultScheduleSlot(): { date: string; time: string } {
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);

    return { date: `${tomorrow.getFullYear()}-${pad(tomorrow.getMonth() + 1)}-${pad(tomorrow.getDate())}`, time: '09:00' };
}

/** Date / time stay empty (= "start now") for best time and batches until the user fills them. */
export function defaultSendModeValue(timezone: string | null, mode: CampaignSendMode = 'now'): SendModeValue {
    const slot = mode === 'scheduled' ? defaultScheduleSlot() : { date: '', time: '' };

    return {
        mode,
        date: slot.date,
        time: slot.time,
        timezone: timezone || DEFAULT_TIMEZONE,
        batchSize: '500',
        batchInterval: '30',
    };
}

function tzOffsetMinutes(utcMs: number, timeZone: string): number {
    const parts: Record<string, string> = {};
    new Intl.DateTimeFormat('en-US', {
        timeZone,
        hourCycle: 'h23',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
    })
        .formatToParts(new Date(utcMs))
        .forEach((part) => {
            parts[part.type] = part.value;
        });
    const asUtc = Date.UTC(+parts.year, +parts.month - 1, +parts.day, +parts.hour % 24, +parts.minute, +parts.second);

    return Math.round((asUtc - utcMs) / 60000);
}

/** Wall-clock date + time in an IANA zone → ISO-8601 with offset (`2026-10-21T09:00:00+01:00`) and its instant. */
export function zonedDateTime(date: string, time: string, timeZone: string): { iso: string; instant: number } | null {
    const [y, m, d] = date.split('-').map(Number);
    const [hh, mm] = time.split(':').map(Number);
    if (![y, m, d, hh, mm].every((n) => Number.isFinite(n))) {
        return null;
    }
    const naive = Date.UTC(y, m - 1, d, hh, mm);
    let offset: number;
    try {
        offset = tzOffsetMinutes(naive, timeZone);
        offset = tzOffsetMinutes(naive - offset * 60000, timeZone);
    } catch {
        return null;
    }
    const sign = offset >= 0 ? '+' : '-';
    const abs = Math.abs(offset);

    return {
        iso: `${date}T${pad(hh)}:${pad(mm)}:00${sign}${pad(Math.floor(abs / 60))}:${pad(abs % 60)}`,
        instant: naive - offset * 60000,
    };
}

export type SendModeError = 'date' | 'batchSize' | 'batchInterval';

/** Validates the form and builds `CampaignScheduleInput` (docs/45 §45.4.2). */
export function buildScheduleInput(value: SendModeValue): { input: CampaignScheduleInput } | { error: SendModeError } {
    const { mode } = value;
    if (mode === 'now') {
        return { input: { mode: 'now' } };
    }
    const hasDate = value.date !== '' && value.time !== '';
    const zoned = hasDate ? zonedDateTime(value.date, value.time, value.timezone) : null;
    if (mode === 'scheduled' && (!zoned || zoned.instant <= Date.now())) {
        return { error: 'date' };
    }
    if (hasDate && (!zoned || zoned.instant <= Date.now()) && mode !== 'scheduled') {
        return { error: 'date' };
    }
    const input: CampaignScheduleInput = {
        mode,
        scheduled_at: zoned?.iso ?? null,
        timezone: value.timezone,
    };
    if (mode === 'batches') {
        const size = Number(value.batchSize);
        const interval = Number(value.batchInterval);
        if (!Number.isInteger(size) || size < BATCH_SIZE_MIN || size > BATCH_SIZE_MAX) {
            return { error: 'batchSize' };
        }
        if (!Number.isInteger(interval) || interval < BATCH_INTERVAL_MIN || interval > BATCH_INTERVAL_MAX) {
            return { error: 'batchInterval' };
        }
        input.batch_size = size;
        input.batch_interval_minutes = interval;
    }

    return { input };
}

export interface SendModeOptionsProps {
    value: SendModeValue;
    onChange: (value: SendModeValue) => void;
    allowedModes: CampaignSendMode[];
    disabled?: boolean;
    /** Field highlighted after a failed submit. */
    error?: SendModeError | null;
}

const MODES: CampaignSendMode[] = ['now', 'scheduled', 'best_time', 'batches'];

/**
 * The four delivery radios of *Confirmer l'envoi* (docs/43 §43.4.2):
 * Envoyer maintenant · Programmer pour plus tard (date, heure, fuseau) ·
 * Envoyer au meilleur moment · Envoyer par lots (taille + intervalle).
 */
export function SendModeOptions({ value, onChange, allowedModes, disabled, error }: SendModeOptionsProps) {
    const styles = useStyles();
    const t = useText();
    const c = t.campaignSetup.confirm;
    const timezones = useMemo(() => {
        const browser = Intl.DateTimeFormat().resolvedOptions().timeZone;

        return [...new Set([value.timezone, ...COMMON_TIMEZONES, browser].filter(Boolean))];
    }, [value.timezone]);

    function set<K extends keyof SendModeValue>(key: K, next: SendModeValue[K]) {
        onChange({ ...value, [key]: next });
    }

    function dateFields(optional: boolean) {
        return (
            <div className={styles.fields}>
                <Field
                    className={styles.field}
                    label={c.date}
                    required={!optional}
                    validationState={error === 'date' ? 'error' : 'none'}
                >
                    <Input type="date" value={value.date} disabled={disabled} onChange={(_, data) => set('date', data.value)} />
                </Field>
                <Field className={styles.field} label={c.time} required={!optional} validationState={error === 'date' ? 'error' : 'none'}>
                    <Input type="time" value={value.time} disabled={disabled} onChange={(_, data) => set('time', data.value)} />
                </Field>
                <Field className={styles.field} label={c.timezone}>
                    <Dropdown
                        value={value.timezone}
                        selectedOptions={[value.timezone]}
                        disabled={disabled}
                        onOptionSelect={(_, data) => set('timezone', data.optionValue ?? DEFAULT_TIMEZONE)}
                    >
                        {timezones.map((zone) => (
                            <Option key={zone} value={zone}>
                                {zone}
                            </Option>
                        ))}
                    </Dropdown>
                </Field>
            </div>
        );
    }

    return (
        <RadioGroup
            className={styles.root}
            value={value.mode}
            disabled={disabled}
            aria-label={c.deliveryOptions}
            onChange={(_, data) => {
                const mode = data.value as CampaignSendMode;
                onChange(
                    mode === 'scheduled' && (value.date === '' || value.time === '')
                        ? { ...value, ...defaultScheduleSlot(), mode }
                        : { ...value, mode },
                );
            }}
        >
            {MODES.filter((mode) => allowedModes.includes(mode)).map((mode) => (
                <div key={mode} className={styles.option}>
                    <Radio value={mode} label={t.campaignSetup.sendModes[mode]} />
                    <Text size={200} className={styles.help}>
                        {t.campaignSetup.sendModeHelp[mode]}
                    </Text>
                    {value.mode === mode && mode === 'scheduled' && dateFields(false)}
                    {value.mode === mode && mode === 'best_time' && (
                        <>
                            <Text size={200} className={styles.help}>
                                {t.campaignSetup.bestTimeHelp}
                            </Text>
                            {dateFields(true)}
                        </>
                    )}
                    {value.mode === mode && mode === 'batches' && (
                        <>
                            <div className={styles.fields}>
                                <Field
                                    className={styles.field}
                                    label={c.batchSize}
                                    required
                                    validationState={error === 'batchSize' ? 'error' : 'none'}
                                    hint={`${BATCH_SIZE_MIN} – ${BATCH_SIZE_MAX.toLocaleString('fr-FR')}`}
                                >
                                    <Input
                                        type="number"
                                        min={BATCH_SIZE_MIN}
                                        max={BATCH_SIZE_MAX}
                                        value={value.batchSize}
                                        disabled={disabled}
                                        onChange={(_, data) => set('batchSize', data.value)}
                                    />
                                </Field>
                                <Field
                                    className={styles.field}
                                    label={c.batchInterval}
                                    required
                                    validationState={error === 'batchInterval' ? 'error' : 'none'}
                                    hint={`${BATCH_INTERVAL_MIN} – ${BATCH_INTERVAL_MAX.toLocaleString('fr-FR')}`}
                                >
                                    <Input
                                        type="number"
                                        min={BATCH_INTERVAL_MIN}
                                        max={BATCH_INTERVAL_MAX}
                                        value={value.batchInterval}
                                        disabled={disabled}
                                        onChange={(_, data) => set('batchInterval', data.value)}
                                    />
                                </Field>
                            </div>
                            {dateFields(true)}
                        </>
                    )}
                </div>
            ))}
        </RadioGroup>
    );
}
