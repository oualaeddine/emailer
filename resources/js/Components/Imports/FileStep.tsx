import { useRef, useState } from 'react';
import { Button, Field, Spinner, Tab, TabList, Text, Textarea, MessageBar, MessageBarBody, makeStyles, tokens } from '@fluentui/react-components';
import { ArrowUploadRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { createImportFromFile, createImportFromPaste, importErrorMessage } from '@/Lib/api/imports';
import type { ImportJobV2 } from '@/Lib/types/imports';
import { PageJaunesPicker } from '@/Components/Imports/PageJaunesPicker';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM, maxWidth: '720px' },
    drop: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: tokens.spacingVerticalS,
        padding: tokens.spacingVerticalXXL,
        border: `2px dashed ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusLarge,
    },
    hidden: { position: 'absolute', width: '1px', height: '1px', overflow: 'hidden', clip: 'rect(0 0 0 0)' },
});

type Tab = 'file' | 'paste' | 'pagejaunes';

interface Props {
    onCreated: (job: ImportJobV2) => void;
}

/** Step 1 — Fichier: CSV/XLSX/TXT upload, paste, or PagesJaunes (docs/43 §43.4.12). */
export function FileStep({ onCreated }: Props) {
    const styles = useStyles();
    const t = useText();
    const inputRef = useRef<HTMLInputElement>(null);
    const [tab, setTab] = useState<Tab>('file');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [text, setText] = useState('');

    async function run(action: () => Promise<ImportJobV2>) {
        setBusy(true);
        setError(null);
        try {
            onCreated(await action());
        } catch (e) {
            setError(importErrorMessage(e, t.common.genericError));
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className={styles.root}>
            <TabList selectedValue={tab} onTabSelect={(_, d) => setTab(d.value as Tab)}>
                <Tab value="file">{t.imports.wizard.uploadFile}</Tab>
                <Tab value="paste">{t.imports.wizard.sourceTypes.paste}</Tab>
                <Tab value="pagejaunes">{t.imports.wizard.sourceTypes.pagejaunes}</Tab>
            </TabList>

            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}

            {tab === 'file' && (
                <div
                    className={styles.drop}
                    onDragOver={(e) => e.preventDefault()}
                    onDrop={(e) => {
                        e.preventDefault();
                        const file = e.dataTransfer.files?.[0];
                        if (file) {
                            void run(() => createImportFromFile(file));
                        }
                    }}
                >
                    <Text>{t.imports.wizard.dropFile}</Text>
                    <Button icon={<ArrowUploadRegular />} disabled={busy} onClick={() => inputRef.current?.click()}>
                        {t.imports.wizard.browse}
                    </Button>
                    <Text size={200}>{t.imports.acceptedFormats}</Text>
                    <input
                        ref={inputRef}
                        type="file"
                        className={styles.hidden}
                        accept=".csv,.txt,.xlsx"
                        onChange={(e) => {
                            const file = e.target.files?.[0];
                            if (file) {
                                void run(() => createImportFromFile(file));
                            }
                        }}
                    />
                </div>
            )}

            {tab === 'paste' && (
                <>
                    <Field label={t.imports.wizard.pasteLabel}>
                        <Textarea rows={10} value={text} onChange={(_, d) => setText(d.value)} />
                    </Field>
                    <div>
                        <Button appearance="primary" disabled={busy || text.trim() === ''} onClick={() => run(() => createImportFromPaste(text))}>
                            {t.common.continue}
                        </Button>
                    </div>
                </>
            )}

            {tab === 'pagejaunes' && <PageJaunesPicker busy={busy} onSubmit={(action) => run(action)} />}

            {busy && <Spinner label={t.common.loading} />}
        </div>
    );
}
