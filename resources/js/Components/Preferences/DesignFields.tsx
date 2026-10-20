import { Field, Input, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import type { PageDesign } from '@/Lib/types/common';

const useStyles = makeStyles({
    grid: { display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: tokens.spacingHorizontalM },
});

const COLOR_KEYS = ['primary_color', 'background_color', 'text_color'] as const;

interface Props {
    design: PageDesign;
    onChange: (design: PageDesign) => void;
}

/** Colour / logo override fields shared by both settings dialogs (WP-74). */
export function DesignFields({ design, onChange }: Props) {
    const styles = useStyles();
    const t = useText();
    const labels: Record<(typeof COLOR_KEYS)[number], string> = {
        primary_color: t.forms.design.primaryColor,
        background_color: t.forms.design.backgroundColor,
        text_color: t.forms.design.textColor,
    };

    return (
        <div className={styles.grid}>
            {COLOR_KEYS.map((key) => (
                <Field key={key} label={labels[key]}>
                    <Input
                        maxLength={7}
                        placeholder="#0F6CBD"
                        value={design[key]}
                        onChange={(_, d) => onChange({ ...design, [key]: d.value })}
                    />
                </Field>
            ))}
            <Field label={t.forms.design.logo}>
                <Input
                    value={design.logo_url ?? ''}
                    onChange={(_, d) => onChange({ ...design, logo_url: d.value.trim() === '' ? null : d.value })}
                />
            </Field>
        </div>
    );
}
