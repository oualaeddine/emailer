import { Dropdown, Field, Input, Option, Radio, RadioGroup, SpinButton, Text, Textarea, makeStyles, tokens } from '@fluentui/react-components';
import { DesignFields } from '@/Components/Preferences/DesignFields';
import { FONT_FAMILIES } from '@/Components/Forms/model';
import { formText } from '@/Components/Forms/strings';
import { useText } from '@/Hooks/useText';
import type { FormDesign, FormLayout } from '@/Lib/types/forms';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
    grid: {
        display: 'grid',
        gridTemplateColumns: 'repeat(2, minmax(0, 1fr))',
        gap: tokens.spacingHorizontalM,
        '@media (max-width: 599px)': { gridTemplateColumns: 'minmax(0, 1fr)' },
    },
    heading: { marginTop: tokens.spacingVerticalS },
});

const LAYOUTS: FormLayout[] = ['vertical', 'horizontal'];

interface Props {
    design: FormDesign;
    onChange: (design: FormDesign) => void;
    errors?: Record<string, string>;
}

/** *Design* tab — texts, colours and logo (shared DesignFields), font, button, layout. */
export function DesignPanel({ design, onChange, errors = {} }: Props) {
    const styles = useStyles();
    const t = useText();
    const set = <K extends keyof FormDesign>(key: K, value: FormDesign[K]) => onChange({ ...design, [key]: value });
    const errorFor = (key: string) =>
        errors[`design.${key}`] ? { validationMessage: errors[`design.${key}`], validationState: 'error' as const } : {};
    const fonts: string[] = FONT_FAMILIES.includes(design.font_family as (typeof FONT_FAMILIES)[number])
        ? [...FONT_FAMILIES]
        : [design.font_family, ...FONT_FAMILIES];

    return (
        <div className={styles.root}>
            <Text weight="semibold">{formText.design.content}</Text>
            <Field label={t.forms.design.title} {...errorFor('title')}>
                <Input value={design.title} maxLength={255} onChange={(_, d) => set('title', d.value)} />
            </Field>
            <Field label={t.forms.design.description} {...errorFor('description')}>
                <Textarea value={design.description} resize="vertical" maxLength={2000} onChange={(_, d) => set('description', d.value)} />
            </Field>
            <Field label={t.forms.design.buttonText} required {...errorFor('button_text')}>
                <Input value={design.button_text} maxLength={100} onChange={(_, d) => set('button_text', d.value)} />
            </Field>

            <Text weight="semibold" className={styles.heading}>
                {formText.design.appearance}
            </Text>
            <DesignFields design={design} onChange={(page) => onChange({ ...design, ...page })} />
            <div className={styles.grid}>
                <Field label={formText.design.buttonTextColor} {...errorFor('button_text_color')}>
                    <Input
                        maxLength={7}
                        placeholder="#FFFFFF"
                        value={design.button_text_color}
                        onChange={(_, d) => set('button_text_color', d.value)}
                    />
                </Field>
                <Field label={formText.design.borderRadius} {...errorFor('border_radius')}>
                    <SpinButton
                        min={0}
                        max={40}
                        value={design.border_radius}
                        onChange={(_, d) => {
                            const raw = d.value ?? Number(d.displayValue);
                            if (raw !== undefined && raw !== null && Number.isFinite(raw)) {
                                set('border_radius', Math.max(0, Math.min(40, Math.round(raw))));
                            }
                        }}
                    />
                </Field>
                <Field label={t.forms.design.font} {...errorFor('font_family')}>
                    <Dropdown
                        value={design.font_family.split(',')[0]}
                        selectedOptions={[design.font_family]}
                        onOptionSelect={(_, d) => d.optionValue && set('font_family', d.optionValue)}
                    >
                        {fonts.map((font) => (
                            <Option key={font} value={font} text={font.split(',')[0]}>
                                <span style={{ fontFamily: font }}>{font.split(',')[0]}</span>
                            </Option>
                        ))}
                    </Dropdown>
                </Field>
                <Field label={t.forms.design.layout}>
                    <RadioGroup layout="horizontal" value={design.layout} onChange={(_, d) => set('layout', d.value as FormLayout)}>
                        {LAYOUTS.map((layout) => (
                            <Radio key={layout} value={layout} label={t.forms.design.layouts[layout]} />
                        ))}
                    </RadioGroup>
                </Field>
            </div>
        </div>
    );
}
