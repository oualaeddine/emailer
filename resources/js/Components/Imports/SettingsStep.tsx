import { Button, Field, Switch, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM, maxWidth: '560px' },
    actions: { display: 'flex', gap: tokens.spacingHorizontalS },
});

export interface ImportOptions {
    update_existing: boolean;
    empty_values_overwrite: boolean;
    mark_blocklisted: boolean;
    mark_opt_in: boolean;
}

interface Props {
    options: ImportOptions;
    saving: boolean;
    onChange: (options: ImportOptions) => void;
    onBack: () => void;
    onNext: () => void;
}

/** Step 4 — Paramètres: update existing, treat as blocklisted, mark opt-in. */
export function SettingsStep({ options, saving, onChange, onBack, onNext }: Props) {
    const styles = useStyles();
    const t = useText();
    const set = (patch: Partial<ImportOptions>) => onChange({ ...options, ...patch });

    return (
        <div className={styles.root}>
            <Field hint={t.imports.wizard.updateExistingHelp}>
                <Switch
                    checked={options.update_existing}
                    label={t.imports.wizard.updateExisting}
                    onChange={(_, d) => set({ update_existing: d.checked })}
                />
            </Field>
            {options.update_existing && (
                <Switch
                    checked={options.empty_values_overwrite}
                    label={t.imports.wizard.emptyValuesOverwrite}
                    onChange={(_, d) => set({ empty_values_overwrite: d.checked })}
                />
            )}
            <Switch
                checked={options.mark_blocklisted}
                label={t.imports.wizard.markBlocklisted}
                onChange={(_, d) => set({ mark_blocklisted: d.checked })}
            />
            <Switch
                checked={options.mark_opt_in}
                label={t.imports.wizard.markOptIn}
                onChange={(_, d) => set({ mark_opt_in: d.checked })}
            />
            <Text size={200} />
            <div className={styles.actions}>
                <Button onClick={onBack}>{t.common.back}</Button>
                <Button appearance="primary" disabled={saving} onClick={onNext}>
                    {t.common.next}
                </Button>
            </div>
        </div>
    );
}
