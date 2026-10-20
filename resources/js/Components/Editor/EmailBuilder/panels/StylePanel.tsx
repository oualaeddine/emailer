/**
 * WP-61 — left panel *Style* tab (doc 43 §43.4.4, docs/46 §2 / §5): the
 * global design settings set once for the whole email (`global_styles`):
 * font, colours, background, links, button defaults and mobile settings.
 * Each committed change is applied to the canvas as component defaults and
 * written into the `<mj-head>` at save time.
 */
import { useEffect, useState, type ReactNode } from 'react';
import { Divider, Field, Input, Select, SpinButton, Switch, Text, makeStyles, tokens } from '@fluentui/react-components';
import type { EmailGlobalStyles } from '@/Lib/types/editor';
import { FONT_FAMILIES } from '../engine/globalStyles';
import { text } from '../strings';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS, padding: tokens.spacingVerticalS, overflowY: 'auto', flex: '1 1 auto' },
    heading: { marginTop: tokens.spacingVerticalS },
    swatch: {
        width: '12px',
        height: '12px',
        borderRadius: tokens.borderRadiusSmall,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke1}`,
    },
});

const HEX = /^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i;

function ColorField({ label, value, disabled, onCommit }: { label: string; value: string; disabled: boolean; onCommit: (value: string) => void }) {
    const styles = useStyles();
    const [draft, setDraft] = useState(value);
    useEffect(() => setDraft(value), [value]);
    const valid = HEX.test(draft.trim());

    const commit = () => {
        const next = draft.trim();
        if (HEX.test(next) && next.toUpperCase() !== value.toUpperCase()) {
            onCommit(next.toUpperCase());
        } else if (!HEX.test(next)) {
            setDraft(value);
        }
    };

    return (
        <Field label={label} size="small" validationState={valid ? 'none' : 'warning'}>
            <Input
                size="small"
                value={draft}
                disabled={disabled}
                placeholder="#242424"
                contentBefore={<span className={styles.swatch} style={{ background: valid ? draft : value }} />}
                onChange={(_, data) => setDraft(data.value)}
                onBlur={commit}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        commit();
                    }
                }}
            />
        </Field>
    );
}

function NumberField({
    label,
    value,
    min,
    max,
    step = 1,
    disabled,
    onCommit,
}: {
    label: string;
    value: number;
    min: number;
    max: number;
    step?: number;
    disabled: boolean;
    onCommit: (value: number) => void;
}) {
    return (
        <Field label={label} size="small">
            <SpinButton
                size="small"
                value={value}
                min={min}
                max={max}
                step={step}
                disabled={disabled}
                onChange={(_, data) => {
                    const raw = data.value ?? (data.displayValue !== undefined ? Number(data.displayValue.replace(',', '.')) : NaN);
                    if (typeof raw === 'number' && Number.isFinite(raw)) {
                        onCommit(Math.min(max, Math.max(min, Math.round(raw / step) * step)));
                    }
                }}
            />
        </Field>
    );
}

function Section({ title, children }: { title: string; children: ReactNode }) {
    const styles = useStyles();

    return (
        <>
            <Divider className={styles.heading} />
            <Text weight="semibold" size={200}>
                {title}
            </Text>
            {children}
        </>
    );
}

export interface StylePanelProps {
    value: EmailGlobalStyles;
    disabled: boolean;
    onChange: (next: EmailGlobalStyles) => void;
}

export function StylePanel({ value, disabled, onChange }: StylePanelProps) {
    const styles = useStyles();
    const s = text.style;
    const set = (patch: Partial<EmailGlobalStyles>) => onChange({ ...value, ...patch });
    const setButton = (patch: Partial<EmailGlobalStyles['button']>) => onChange({ ...value, button: { ...value.button, ...patch } });
    const setMobile = (patch: Partial<EmailGlobalStyles['mobile']>) => onChange({ ...value, mobile: { ...value.mobile, ...patch } });
    const fonts = FONT_FAMILIES.includes(value.font_family) ? FONT_FAMILIES : [value.font_family, ...FONT_FAMILIES];

    return (
        <div className={styles.root}>
            <Field label={s.fontFamily} size="small">
                <Select size="small" value={value.font_family} disabled={disabled} onChange={(_, data) => set({ font_family: data.value })}>
                    {fonts.map((font) => (
                        <option key={font} value={font}>
                            {font.split(',')[0].replace(/'/g, '')}
                        </option>
                    ))}
                </Select>
            </Field>
            <ColorField label={s.textColor} value={value.text_color} disabled={disabled} onCommit={(text_color) => set({ text_color })} />
            <ColorField label={s.backgroundColor} value={value.background_color} disabled={disabled} onCommit={(background_color) => set({ background_color })} />
            <ColorField
                label={s.contentBackgroundColor}
                value={value.content_background_color}
                disabled={disabled}
                onCommit={(content_background_color) => set({ content_background_color })}
            />
            <NumberField
                label={`${s.contentWidth} (px)`}
                value={value.content_width}
                min={480}
                max={900}
                step={10}
                disabled={disabled}
                onCommit={(content_width) => set({ content_width })}
            />

            <ColorField label={s.linkColor} value={value.link_color} disabled={disabled} onCommit={(link_color) => set({ link_color })} />
            <Switch
                label={s.linkUnderline}
                checked={value.link_underline}
                disabled={disabled}
                onChange={(_, data) => set({ link_underline: data.checked })}
            />

            <Section title={s.buttonDefaults}>
                <ColorField
                    label={s.buttonBackground}
                    value={value.button.background_color}
                    disabled={disabled}
                    onCommit={(background_color) => setButton({ background_color })}
                />
                <ColorField label={s.buttonText} value={value.button.text_color} disabled={disabled} onCommit={(text_color) => setButton({ text_color })} />
                <NumberField
                    label={`${s.borderRadius} (px)`}
                    value={value.button.border_radius}
                    min={0}
                    max={40}
                    disabled={disabled}
                    onCommit={(border_radius) => setButton({ border_radius })}
                />
                <Field label={text.fields.fontWeight} size="small">
                    <Select size="small" value={value.button.font_weight} disabled={disabled} onChange={(_, data) => setButton({ font_weight: data.value })}>
                        <option value="400">{text.fields.weightNormal}</option>
                        <option value="600">{text.fields.weightSemibold}</option>
                        <option value="700">{text.fields.weightBold}</option>
                    </Select>
                </Field>
            </Section>

            <Section title={s.mobileSettings}>
                <NumberField
                    label={`${s.fontScale} (%)`}
                    value={Math.round(value.mobile.font_scale * 100)}
                    min={80}
                    max={150}
                    step={5}
                    disabled={disabled}
                    onCommit={(percent) => setMobile({ font_scale: percent / 100 })}
                />
                {/* Label: fr.editor.fields.padding — "Marge intérieure" shown on mobile (on) / hidden (off). */}
                <Switch
                    label={text.fields.padding}
                    checked={!value.mobile.hide_on_mobile_padding}
                    disabled={disabled}
                    onChange={(_, data) => setMobile({ hide_on_mobile_padding: !data.checked })}
                />
            </Section>
        </div>
    );
}
