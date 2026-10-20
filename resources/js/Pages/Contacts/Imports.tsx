import { useEffect, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import {
    Badge,
    Button,
    Spinner,
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableHeaderCell,
    TableRow,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { ArrowDownloadRegular, ArrowUploadRegular, DocumentTableRegular } from '@fluentui/react-icons';
import { PageHelp } from '@/Components/Help/PageHelp';
import { AppShell } from '@/Components/Shell/AppShell';
import { EmptyState } from '@/Components/Shell/EmptyState';
import { useText } from '@/Hooks/useText';
import { fetchImportHistory } from '@/Lib/api/imports';
import type { ImportJobV2 } from '@/Lib/types/imports';

const useStyles = makeStyles({
    header: { display: 'flex', alignItems: 'center', justifyContent: 'space-between', marginBottom: tokens.spacingVerticalL },
    pager: { display: 'flex', gap: tokens.spacingHorizontalS, marginTop: tokens.spacingVerticalM },
});

/** docs/43 §43.4.12 — import history with errors CSV download. */
export default function Imports() {
    const styles = useStyles();
    const t = useText();
    const [page, setPage] = useState(1);
    const [jobs, setJobs] = useState<ImportJobV2[] | null>(null);
    const [lastPage, setLastPage] = useState(1);

    useEffect(() => {
        setJobs(null);
        fetchImportHistory({ page })
            .then((response) => {
                setJobs(response.data);
                setLastPage(response.meta.last_page);
            })
            .catch(() => setJobs([]));
    }, [page]);

    return (
        <AppShell>
            <Head title={t.imports.history.title} />
            <PageHelp
                topic="imports"
                title={t.imports.history.title}
                actions={
                    <>
                        <Button appearance="primary" icon={<ArrowUploadRegular />} onClick={() => router.visit('/contacts/imports/new')}>
                            {t.imports.history.newImport}
                        </Button>
                    </>
                }
            />

            {jobs === null && <Spinner label={t.common.loading} />}
            {jobs !== null && jobs.length === 0 && <EmptyState icon={DocumentTableRegular} title={t.imports.history.empty} />}
            {jobs !== null && jobs.length > 0 && (
                <>
                    <Table aria-label={t.imports.history.title}>
                        <TableHeader>
                            <TableRow>
                                <TableHeaderCell>{t.imports.history.file}</TableHeaderCell>
                                <TableHeaderCell>{t.imports.history.date}</TableHeaderCell>
                                <TableHeaderCell>{t.imports.history.user}</TableHeaderCell>
                                <TableHeaderCell>{t.imports.history.status}</TableHeaderCell>
                                <TableHeaderCell>{t.imports.history.created}</TableHeaderCell>
                                <TableHeaderCell>{t.imports.history.updated}</TableHeaderCell>
                                <TableHeaderCell>{t.imports.history.skipped}</TableHeaderCell>
                                <TableHeaderCell>{t.imports.history.failed}</TableHeaderCell>
                                <TableHeaderCell>{t.common.actions}</TableHeaderCell>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {jobs.map((job) => (
                                <TableRow key={job.id}>
                                    <TableCell>{job.original_filename ?? job.source_type}</TableCell>
                                    <TableCell>{new Date(job.created_at).toLocaleString('fr-FR')}</TableCell>
                                    <TableCell>{job.user?.name ?? '—'}</TableCell>
                                    <TableCell>
                                        <Badge appearance="tint" color={job.status === 'failed' ? 'danger' : job.status === 'completed' ? 'success' : 'informative'}>
                                            {t.imports.statusLabels[job.status as keyof typeof t.imports.statusLabels] ?? job.status}
                                        </Badge>
                                    </TableCell>
                                    <TableCell>{job.summary?.created ?? job.imported_count}</TableCell>
                                    <TableCell>{job.summary?.updated ?? 0}</TableCell>
                                    <TableCell>{job.summary?.skipped ?? job.duplicate_count}</TableCell>
                                    <TableCell>{job.summary?.failed ?? job.error_count}</TableCell>
                                    <TableCell>
                                        {job.errors_url && (
                                            <Button
                                                as="a"
                                                href={job.errors_url}
                                                size="small"
                                                icon={<ArrowDownloadRegular />}
                                                aria-label={t.imports.history.downloadErrors}
                                                title={t.imports.history.downloadErrors}
                                            />
                                        )}
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                    <div className={styles.pager}>
                        <Button disabled={page <= 1} onClick={() => setPage(page - 1)}>
                            {t.common.previous}
                        </Button>
                        <Button disabled={page >= lastPage} onClick={() => setPage(page + 1)}>
                            {t.common.next}
                        </Button>
                    </div>
                </>
            )}
        </AppShell>
    );
}
