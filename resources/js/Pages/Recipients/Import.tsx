import { useState } from 'react';
import { Head } from '@inertiajs/react';
import {
    Button,
    Card,
    Dropdown,
    Field,
    Option,
    Spinner,
    Text,
    Title1,
    Title3,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import { commitImport, fetchImportRows, submitColumnMapping, uploadImportFile } from '@/Lib/api/imports';
import type { ImportJob, ImportRow } from '@/Lib/types/imports';

const useStyles = makeStyles({
    card: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        padding: tokens.spacingVerticalXL,
        maxWidth: '560px',
    },
    counts: {
        display: 'flex',
        gap: tokens.spacingHorizontalXL,
    },
    countBlock: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
    },
});

type Step = 'upload' | 'mapping' | 'review' | 'done';

const MAPPABLE_FIELDS = ['email', 'first_name', 'last_name', 'company_name'] as const;

/**
 * docs/14-import-export.md §14.2 — Pipeline Stages (CSV path).
 */
export default function Import() {
    const styles = useStyles();
    const t = useText();

    const [step, setStep] = useState<Step>('upload');
    const [uploading, setUploading] = useState(false);
    const [job, setJob] = useState<ImportJob | null>(null);
    const [headers, setHeaders] = useState<string[]>([]);
    const [mapping, setMapping] = useState<Record<string, string>>({});
    const [rows, setRows] = useState<ImportRow[]>([]);

    async function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }
        setUploading(true);
        try {
            const sourceType = file.name.toLowerCase().endsWith('.xlsx') ? 'excel' : 'csv';
            const { job: uploadedJob, detectedHeaders } = await uploadImportFile(file, sourceType);
            setJob(uploadedJob);
            setHeaders(detectedHeaders);
            setStep('mapping');
        } finally {
            setUploading(false);
        }
    }

    async function handleMappingSubmit() {
        if (!job) {
            return;
        }
        const updated = await submitColumnMapping(job.id, mapping);
        setJob(updated);

        const rowsResponse = await fetchImportRows(job.id);
        setRows(rowsResponse.data);
        setStep('review');
    }

    async function handleCommit() {
        if (!job) {
            return;
        }
        const invalidRowIds = rows.filter((r) => r.validation_status !== 'valid').map((r) => r.id);
        const updated = await commitImport(job.id, invalidRowIds);
        setJob(updated);
        setStep('done');
    }

    const validCount = rows.filter((r) => r.validation_status === 'valid').length;
    const invalidCount = rows.filter((r) => r.validation_status === 'invalid').length;
    const duplicateCount = rows.filter((r) => r.validation_status === 'duplicate').length;

    return (
        <AppShell>
            <Head title={t.imports.title} />
            <Title1>{t.imports.title}</Title1>

            {step === 'upload' && (
                <Card className={styles.card}>
                    <Field label={t.imports.chooseFile}>
                        <input type="file" accept=".csv,.txt,.xlsx" onChange={handleFileChange} disabled={uploading} />
                    </Field>
                    {uploading && <Spinner label={t.common.loading} />}
                </Card>
            )}

            {step === 'mapping' && (
                <Card className={styles.card}>
                    <Title3>{t.imports.stepMapping}</Title3>
                    {MAPPABLE_FIELDS.map((field) => (
                        <Field key={field} label={t.imports[toMappingLabelKey(field)]}>
                            <Dropdown
                                value={mapping[field] ?? t.imports.selectColumn}
                                onOptionSelect={(_, data) =>
                                    setMapping((m) => ({ ...m, [field]: data.optionValue ?? '' }))
                                }
                            >
                                {headers.map((header) => (
                                    <Option key={header} value={header}>
                                        {header}
                                    </Option>
                                ))}
                            </Dropdown>
                        </Field>
                    ))}
                    <Button appearance="primary" onClick={handleMappingSubmit} disabled={!mapping.email}>
                        {t.imports.continue}
                    </Button>
                </Card>
            )}

            {step === 'review' && (
                <Card className={styles.card}>
                    <Title3>{t.imports.stepReview}</Title3>
                    <div className={styles.counts}>
                        <div className={styles.countBlock}>
                            <Text weight="bold">{job?.total_rows}</Text>
                            <Text>{t.imports.totalRows}</Text>
                        </div>
                        <div className={styles.countBlock}>
                            <Text weight="bold">{validCount}</Text>
                            <Text>{t.imports.validRows}</Text>
                        </div>
                        <div className={styles.countBlock}>
                            <Text weight="bold">{invalidCount}</Text>
                            <Text>{t.imports.invalidRows}</Text>
                        </div>
                        <div className={styles.countBlock}>
                            <Text weight="bold">{duplicateCount}</Text>
                            <Text>{t.imports.duplicateRows}</Text>
                        </div>
                    </div>
                    <Button appearance="primary" onClick={handleCommit}>
                        {t.imports.commit}
                    </Button>
                </Card>
            )}

            {step === 'done' && job && (
                <Card className={styles.card}>
                    <Title3>{t.imports.summary}</Title3>
                    <Text>
                        {t.imports.imported} : {job.imported_count}
                    </Text>
                    <Text>
                        {t.imports.duplicateRows} : {job.duplicate_count}
                    </Text>
                    <Text>
                        {t.imports.invalidRows} : {job.error_count}
                    </Text>
                </Card>
            )}
        </AppShell>
    );
}

function toMappingLabelKey(field: (typeof MAPPABLE_FIELDS)[number]): 'mapEmail' | 'mapFirstName' | 'mapLastName' | 'mapCompanyName' {
    return {
        email: 'mapEmail' as const,
        first_name: 'mapFirstName' as const,
        last_name: 'mapLastName' as const,
        company_name: 'mapCompanyName' as const,
    }[field];
}
