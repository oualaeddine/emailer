import { useCallback, useState } from 'react';
import { Head, usePage } from '@inertiajs/react';
import { MessageBar, MessageBarBody, Title2, makeStyles, tokens } from '@fluentui/react-components';
import { AppShell } from '@/Components/Shell/AppShell';
import { FileStep } from '@/Components/Imports/FileStep';
import { MappingStep } from '@/Components/Imports/MappingStep';
import { ListsStep } from '@/Components/Imports/ListsStep';
import { SettingsStep, type ImportOptions } from '@/Components/Imports/SettingsStep';
import { LaunchStep } from '@/Components/Imports/LaunchStep';
import { useText } from '@/Hooks/useText';
import { importErrorMessage, saveImportSettings } from '@/Lib/api/imports';
import type { ImportColumnMapping, ImportJobV2, ImportPreview, ImportWizardStep } from '@/Lib/types/imports';

const STEPS: ImportWizardStep[] = ['file', 'mapping', 'lists', 'settings', 'launch'];

const useStyles = makeStyles({
    steps: { display: 'flex', gap: tokens.spacingHorizontalL, flexWrap: 'wrap', margin: `${tokens.spacingVerticalM} 0 ${tokens.spacingVerticalL}` },
    step: { color: tokens.colorNeutralForeground3 },
    current: { color: tokens.colorBrandForeground1, fontWeight: tokens.fontWeightSemibold },
});

/** docs/43 §43.4.12 — Fichier → Correspondance → Listes → Paramètres → Lancer. */
export default function ImportWizard() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ list_id?: string | null }>();

    const [step, setStep] = useState<ImportWizardStep>('file');
    const [job, setJob] = useState<ImportJobV2 | null>(null);
    const [preview, setPreview] = useState<ImportPreview | null>(null);
    const [mapping, setMapping] = useState<ImportColumnMapping[]>([]);
    const [listIds, setListIds] = useState<string[]>(props.list_id ? [props.list_id] : []);
    const [newListName, setNewListName] = useState('');
    const [options, setOptions] = useState<ImportOptions>({
        update_existing: true,
        empty_values_overwrite: false,
        mark_blocklisted: false,
        mark_opt_in: false,
    });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const onJob = useCallback((next: ImportJobV2) => setJob(next), []);

    async function saveSettings() {
        if (!job) {
            return;
        }
        setSaving(true);
        setError(null);
        try {
            const next = await saveImportSettings(job.id, {
                list_ids: listIds,
                new_list: newListName.trim() === '' ? null : { name: newListName.trim(), folder_id: null },
                ...options,
            });
            setJob(next);
            setStep('launch');
        } catch (e) {
            setError(importErrorMessage(e, t.common.genericError));
        } finally {
            setSaving(false);
        }
    }

    const index = STEPS.indexOf(step);

    return (
        <AppShell>
            <Head title={t.imports.wizard.title} />
            <Title2>{t.imports.wizard.title}</Title2>
            <ol className={styles.steps} style={{ listStyle: 'none', padding: 0 }}>
                {STEPS.map((s, i) => (
                    <li key={s} className={i === index ? styles.current : styles.step} aria-current={i === index ? 'step' : undefined}>
                        {i + 1}. {t.imports.wizard.steps[s]}
                    </li>
                ))}
            </ol>

            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}

            {step === 'file' && (
                <FileStep
                    onCreated={(created) => {
                        setJob(created);
                        setPreview(created.preview);
                        setMapping(created.preview?.suggested_mapping ?? []);
                        setStep('mapping');
                    }}
                />
            )}
            {step === 'mapping' && job && preview && (
                <MappingStep
                    job={job}
                    preview={preview}
                    mapping={mapping}
                    onMappingChange={setMapping}
                    onSaved={(saved) => {
                        setJob(saved);
                        setStep('lists');
                    }}
                    onBack={() => setStep('file')}
                />
            )}
            {step === 'lists' && (
                <ListsStep
                    listIds={listIds}
                    newListName={newListName}
                    onChange={(ids, name) => {
                        setListIds(ids);
                        setNewListName(name);
                    }}
                    onBack={() => setStep('mapping')}
                    onNext={() => setStep('settings')}
                />
            )}
            {step === 'settings' && (
                <SettingsStep
                    options={options}
                    saving={saving}
                    onChange={setOptions}
                    onBack={() => setStep('lists')}
                    onNext={saveSettings}
                />
            )}
            {step === 'launch' && job && (
                <LaunchStep
                    job={job}
                    listCount={listIds.length + (newListName.trim() === '' ? 0 : 1)}
                    onJob={onJob}
                    onBack={() => setStep('settings')}
                />
            )}
        </AppShell>
    );
}
