import { useEffect, useMemo, useState } from 'react';
import {
    Button,
    MessageBar,
    MessageBarBody,
    Select,
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableHeaderCell,
    TableRow,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { fetchContactAttributes, importErrorMessage, saveImportMapping } from '@/Lib/api/imports';
import type { ImportColumnMapping, ImportJobV2, ImportPreview } from '@/Lib/types/imports';
import { CreateAttributeDialog } from '@/Components/Imports/CreateAttributeDialog';
import { missingLabels } from '@/Components/Imports/labels';

const SYSTEM = ['EMAIL', 'PRENOM', 'NOM', 'SOCIETE', 'SMS', 'EXT_ID'];
const IGNORE = '__ignore__';
const CREATE = '__create__';

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
    scroll: { overflowX: 'auto' },
    actions: { display: 'flex', gap: tokens.spacingHorizontalS },
});

interface Props {
    job: ImportJobV2;
    preview: ImportPreview;
    mapping: ImportColumnMapping[];
    onMappingChange: (mapping: ImportColumnMapping[]) => void;
    onSaved: (job: ImportJobV2) => void;
    onBack: () => void;
}

/** Step 2 — Correspondance: preview rows, map each column to an attribute, create one, or ignore. */
export function MappingStep({ job, preview, mapping, onMappingChange, onSaved, onBack }: Props) {
    const styles = useStyles();
    const t = useText();
    const [custom, setCustom] = useState<string[]>([]);
    const [creatingFor, setCreatingFor] = useState<number | null>(null);
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    useEffect(() => {
        fetchContactAttributes()
            .then((attributes) => setCustom(attributes.filter((a) => !a.is_system).map((a) => a.name)))
            .catch(() => setCustom([])); // attributes API not available: system attributes only
    }, []);

    const names = useMemo(() => {
        const used = mapping.flatMap((m) => (m.target.type === 'attribute' ? [m.target.attribute] : []));

        return Array.from(new Set([...SYSTEM, ...custom, ...used]));
    }, [custom, mapping]);

    const emailCount = mapping.filter((m) => m.target.type === 'attribute' && m.target.attribute === 'EMAIL').length;

    function setTarget(column: number, value: string) {
        if (value === CREATE) {
            setCreatingFor(column);

            return;
        }
        onMappingChange(
            mapping.map((m) =>
                m.column === column
                    ? { ...m, target: value === IGNORE ? { type: 'ignore' } : { type: 'attribute', attribute: value } }
                    : m,
            ),
        );
    }

    async function save() {
        setSaving(true);
        setError(null);
        try {
            onSaved(await saveImportMapping(job.id, { mapping }));
        } catch (e) {
            setError(importErrorMessage(e, t.common.genericError));
        } finally {
            setSaving(false);
        }
    }

    return (
        <div className={styles.root}>
            <Text weight="semibold">
                {t.imports.wizard.previewRows} — {t.imports.totalRows} : {preview.total_rows}
            </Text>
            {emailCount !== 1 && (
                <MessageBar intent="warning">
                    <MessageBarBody>{t.imports.wizard.emailRequired}</MessageBarBody>
                </MessageBar>
            )}
            {error && (
                <MessageBar intent="error">
                    <MessageBarBody>{error}</MessageBarBody>
                </MessageBar>
            )}
            <div className={styles.scroll}>
                <Table aria-label={t.imports.wizard.previewRows}>
                    <TableHeader>
                        <TableRow>
                            <TableHeaderCell>{t.imports.wizard.column}</TableHeaderCell>
                            <TableHeaderCell>{missingLabels.sampleValues}</TableHeaderCell>
                            <TableHeaderCell>{t.imports.wizard.mapTo}</TableHeaderCell>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {mapping.map((m) => (
                            <TableRow key={m.column}>
                                <TableCell>
                                    <Text weight="semibold">{m.header}</Text>
                                </TableCell>
                                <TableCell>
                                    {preview.rows
                                        .slice(0, 3)
                                        .map((row) => row[m.column] ?? '')
                                        .join(' · ')}
                                </TableCell>
                                <TableCell>
                                    <Select
                                        aria-label={`${t.imports.wizard.mapTo} ${m.header}`}
                                        value={m.target.type === 'attribute' ? m.target.attribute : IGNORE}
                                        onChange={(_, d) => setTarget(m.column, d.value)}
                                    >
                                        <option value={IGNORE}>{t.imports.wizard.ignoreColumn}</option>
                                        {names.map((name) => (
                                            <option key={name} value={name}>
                                                {t.attributes.systemNames[name as keyof typeof t.attributes.systemNames] ?? name}
                                                {t.attributes.systemNames[name as keyof typeof t.attributes.systemNames] ? ` (${name})` : ''}
                                            </option>
                                        ))}
                                        <option value={CREATE}>+ {t.imports.wizard.createAttribute}</option>
                                    </Select>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>
            <div className={styles.actions}>
                <Button onClick={onBack}>{t.common.back}</Button>
                <Button appearance="primary" disabled={saving || emailCount !== 1} onClick={save}>
                    {t.common.next}
                </Button>
            </div>

            <CreateAttributeDialog
                open={creatingFor !== null}
                initialName={mapping.find((m) => m.column === creatingFor)?.header ?? ''}
                onOpenChange={(open) => !open && setCreatingFor(null)}
                onCreated={(attribute) => {
                    setCustom((c) => [...c, attribute.name]);
                    if (creatingFor !== null) {
                        setTarget(creatingFor, attribute.name);
                    }
                }}
            />
        </div>
    );
}
