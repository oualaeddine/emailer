import { useEffect, useRef, useState } from 'react';
import { Button, MessageBar, MessageBarBody, ProgressBar, Spinner, Text, makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { fetchImportJob, importErrorMessage, launchImport } from '@/Lib/api/imports';
import type { ImportJobV2 } from '@/Lib/types/imports';
import { missingLabels } from '@/Components/Imports/labels';

const FINISHED = ['completed', 'completed_with_errors', 'failed'];

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM, maxWidth: '560px' },
    summary: { display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: tokens.spacingVerticalXS },
    actions: { display: 'flex', gap: tokens.spacingHorizontalS },
});

interface Props {
    job: ImportJobV2;
    listCount: number;
    onJob: (job: ImportJobV2) => void;
    onBack: () => void;
}

/** Step 5 — Lancer: confirm, poll progress, show the summary. */
export function LaunchStep({ job, listCount, onJob, onBack }: Props) {
    const styles = useStyles();
    const t = useText();
    const [error, setError] = useState<string | null>(null);
    const [launching, setLaunching] = useState(false);
    const timer = useRef<number | null>(null);

    const finished = FINISHED.includes(job.status);
    const ready = job.status === 'ready';
    const running = job.status === 'importing' || job.status === 'processing' || launching;
    const parsing = !ready && !finished && !running;

    // Poll while the file is being analysed (until `ready`) or imported (until finished).
    useEffect(() => {
        if (finished) {
            return;
        }
        timer.current = window.setInterval(() => {
            fetchImportJob(job.id)
                .then(onJob)
                .catch(() => undefined);
        }, 2000);

        return () => {
            if (timer.current !== null) {
                window.clearInterval(timer.current);
            }
        };
    }, [finished, job.id, onJob]);

    async function launch() {
        setLaunching(true);
        setError(null);
        try {
            onJob(await launchImport(job.id));
        } catch (e) {
            setError(importErrorMessage(e, t.common.genericError));
        } finally {
            setLaunching(false);
        }
    }

    const summary = job.summary;
    const progress = job.total_rows > 0 ? Math.min(1, job.processed_rows / job.total_rows) : undefined;

    return (
        <div className={styles.root}>
            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}

            {!finished && (
                <Text>
                    {t.imports.wizard.launchSummary.replace('%d', String(job.total_rows)).replace('%d', String(listCount))}
                </Text>
            )}

            {parsing && <Spinner label={t.imports.statusLabels[job.status as keyof typeof t.imports.statusLabels] ?? job.status} />}
            {running && !finished && (
                <>
                    <Text>{missingLabels.importing}</Text>
                    <ProgressBar value={progress} />
                </>
            )}

            {finished && (
                <MessageBar intent={job.status === 'failed' ? 'error' : job.status === 'completed' ? 'success' : 'warning'}>
                    <MessageBarBody>
                        {t.imports.statusLabels[job.status as keyof typeof t.imports.statusLabels] ?? job.status}
                    </MessageBarBody>
                </MessageBar>
            )}

            {summary && (
                <div className={styles.summary} role="list">
                    <Text role="listitem">{missingLabels.summaryCreated} : {summary.created}</Text>
                    <Text role="listitem">{t.imports.history.updated} : {summary.updated}</Text>
                    <Text role="listitem">{t.imports.history.skipped} : {summary.skipped}</Text>
                    <Text role="listitem">{t.imports.history.failed} : {summary.failed}</Text>
                    <Text role="listitem">{missingLabels.summaryBlocklisted} : {summary.blocklisted}</Text>
                    <Text role="listitem">{missingLabels.summaryAddedToLists} : {summary.added_to_lists}</Text>
                </div>
            )}

            <div className={styles.actions}>
                {!finished && !running && <Button onClick={onBack}>{t.common.back}</Button>}
                {!finished && (
                    <Button appearance="primary" disabled={!ready || launching} onClick={launch}>
                        {t.imports.wizard.launch}
                    </Button>
                )}
                {finished && job.errors_url && (
                    <Button as="a" href={job.errors_url}>
                        {t.imports.history.downloadErrors}
                    </Button>
                )}
                {finished && (
                    <Button appearance="primary" as="a" href="/contacts/imports">
                        {t.imports.history.title}
                    </Button>
                )}
            </div>
        </div>
    );
}
