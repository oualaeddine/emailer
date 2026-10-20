import { useEffect, useMemo, useRef, useState } from 'react';
import {
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Field,
    Link,
    MessageBar,
    MessageBarBody,
    ProgressBar,
    Radio,
    RadioGroup,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { ArrowDownloadRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import { exportContacts, pollOperation } from '@/Lib/api/contacts';
import type { ContactColumnKey, ContactExportFormat, ContactExportOperation, ContactSelection } from '@/Lib/types/contacts';
import { formatText, type ContactColumnDescriptor } from '@/Components/Contacts/contactColumns';
import { CheckboxGroup } from '@/Components/Common/CheckboxGroup';

const useStyles = makeStyles({
    content: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
    columns: {
        maxHeight: '240px',
        overflowY: 'auto',
        display: 'flex',
        flexDirection: 'column',
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalXS,
    },
});

export interface ExportContactsDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Contacts to export (selected ids, or everything matching the current table). */
    selection: ContactSelection | null;
    /** Number of contacts designated, when known. */
    count: number | null;
    /** Pre-checked columns (the table's visible columns). */
    defaultColumns: ContactColumnKey[];
    /** Every exportable column. */
    catalog: ContactColumnDescriptor[];
}

type Phase = 'configure' | 'running' | 'ready' | 'failed';

/**
 * docs/45-brevo-api-contracts.md §45.5.4 — *Exporter*: POST
 * /recipients/export (format + columns + ContactSelection) → poll the
 * AsyncOperation → signed download link.
 */
export function ExportContactsDialog({ open, onOpenChange, selection, count, defaultColumns, catalog }: ExportContactsDialogProps) {
    const styles = useStyles();
    const t = useText();
    const [format, setFormat] = useState<ContactExportFormat>('csv');
    const [columns, setColumns] = useState<ContactColumnKey[]>(defaultColumns);
    const [phase, setPhase] = useState<Phase>('configure');
    const [operation, setOperation] = useState<ContactExportOperation | null>(null);
    const [error, setError] = useState<string | null>(null);
    const abortRef = useRef<AbortController | null>(null);

    useEffect(() => {
        if (open) {
            setFormat('csv');
            setColumns(defaultColumns);
            setPhase('configure');
            setOperation(null);
            setError(null);
        } else {
            abortRef.current?.abort();
        }
    }, [open, defaultColumns]);

    useEffect(() => () => abortRef.current?.abort(), []);

    const orderedCatalog = useMemo(() => {
        const position = new Map(defaultColumns.map((key, index) => [key, index]));

        return [...catalog].sort(
            (a, b) => (position.get(a.key) ?? Number.MAX_SAFE_INTEGER) - (position.get(b.key) ?? Number.MAX_SAFE_INTEGER),
        );
    }, [catalog, defaultColumns]);

    async function start() {
        if (!selection || columns.length === 0) {
            return;
        }
        const controller = new AbortController();
        abortRef.current?.abort();
        abortRef.current = controller;
        setPhase('running');
        setError(null);
        try {
            const ordered = orderedCatalog.map((column) => column.key).filter((key) => columns.includes(key));
            const started = await exportContacts({ selection, columns: ordered, format });
            setOperation(started);
            const finished = await pollOperation(started, setOperation, controller.signal);
            setOperation(finished);
            if (finished.status === 'completed' && finished.result?.download_url) {
                setPhase('ready');
            } else {
                setError(finished.error ?? t.common.genericError);
                setPhase('failed');
            }
        } catch (reason) {
            if (controller.signal.aborted) {
                return;
            }
            setError(httpStatus(reason) === 403 ? t.common.forbidden : t.common.genericError);
            setPhase('failed');
        }
    }

    const progress = operation && operation.total > 0 ? Math.min(1, operation.processed / operation.total) : undefined;

    return (
        <Dialog open={open} onOpenChange={(_, data) => onOpenChange(data.open)}>
            <DialogSurface>
                <DialogBody>
                    <DialogTitle>{t.contacts.exportTitle}</DialogTitle>
                    <DialogContent className={styles.content}>
                        {count !== null && <Text>{formatText(t.contacts.totalContacts, count)}</Text>}
                        {phase === 'configure' && (
                            <>
                                <Field label={t.contacts.exportFormat}>
                                    <RadioGroup
                                        layout="horizontal"
                                        value={format}
                                        onChange={(_, data) => setFormat(data.value as ContactExportFormat)}
                                    >
                                        <Radio value="csv" label={t.contacts.exportFormats.csv} />
                                        <Radio value="xlsx" label={t.contacts.exportFormats.xlsx} />
                                    </RadioGroup>
                                </Field>
                                <Field label={t.contacts.exportColumns} required>
                                    <CheckboxGroup className={styles.columns}>
                                        {orderedCatalog.map((column) => (
                                            <Checkbox
                                                key={column.key}
                                                label={column.label}
                                                checked={columns.includes(column.key)}
                                                onChange={(_, data) =>
                                                    setColumns((current) =>
                                                        data.checked === true
                                                            ? [...current, column.key]
                                                            : current.filter((key) => key !== column.key),
                                                    )
                                                }
                                            />
                                        ))}
                                    </CheckboxGroup>
                                </Field>
                            </>
                        )}
                        {phase === 'running' && (
                            <>
                                <Text>{t.contacts.exportStarted}</Text>
                                <ProgressBar value={progress} />
                            </>
                        )}
                        {phase === 'ready' && operation?.result && (
                            <MessageBar intent="success">
                                <MessageBarBody>
                                    {t.contacts.exportReady}{' '}
                                    <Link href={operation.result.download_url} target="_blank" rel="noopener">
                                        {t.contacts.exportDownload}
                                    </Link>
                                </MessageBarBody>
                            </MessageBar>
                        )}
                        {phase === 'failed' && error && (
                            <MessageBar intent="error">
                                <MessageBarBody>{error}</MessageBarBody>
                            </MessageBar>
                        )}
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" onClick={() => onOpenChange(false)}>
                            {phase === 'configure' ? t.common.cancel : t.common.close}
                        </Button>
                        {phase === 'configure' && (
                            <Button
                                appearance="primary"
                                icon={<ArrowDownloadRegular />}
                                disabled={!selection || columns.length === 0}
                                onClick={() => void start()}
                            >
                                {t.common.export}
                            </Button>
                        )}
                        {phase === 'failed' && (
                            <Button appearance="primary" onClick={() => setPhase('configure')}>
                                {t.common.retry}
                            </Button>
                        )}
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}

function httpStatus(error: unknown): number | undefined {
    return (error as { response?: { status?: number } } | null)?.response?.status;
}
