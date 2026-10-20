import { useEffect, useState, type FormEvent } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Dropdown,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    Option,
    Spinner,
    Textarea,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';
import { createSegment, fetchSegmentFolders } from '@/Lib/api/segments';
import type { Folder, Uuid } from '@/Lib/types/common';
import type { FieldCatalog, RuleTree, Segment, SegmentInput } from '@/Lib/types/segments';
import { errorMessage, validationErrors } from './errors';
import { emptyRuleTree, isRuleTreeComplete } from './tree';

const useStyles = makeStyles({
    form: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
});

const NO_FOLDER = '__none';

export interface SaveSegmentDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Tree to persist as the segment's rules (`null` = all contacts). */
    rules: RuleTree | null;
    /** Called with the created segment; the dialog closes itself. */
    onSaved?: (segment: Segment) => void;
    defaultName?: string;
    defaultDescription?: string | null;
    defaultFolderId?: Uuid | null;
    /** When given, the rules are checked with `isRuleTreeComplete(rules, fieldCatalog)` before saving. */
    fieldCatalog?: FieldCatalog;
    /** Override persistence; default POST /segments. */
    onSubmit?: (input: SegmentInput) => Promise<Segment>;
    /** Override the folder choices; default GET /list-folders?kind=segment. */
    folders?: Folder[];
}

/**
 * *Enregistrer comme segment* — name / description / folder dialog that
 * creates a segment from a rule tree. Reused by the Contacts filter bar
 * (WP-51) and anywhere a ConditionBuilder tree should become a segment.
 */
export function SaveSegmentDialog({
    open,
    onOpenChange,
    rules,
    onSaved,
    defaultName = '',
    defaultDescription = null,
    defaultFolderId = null,
    fieldCatalog,
    onSubmit,
    folders: providedFolders,
}: SaveSegmentDialogProps) {
    const styles = useStyles();
    const t = useText();
    const [name, setName] = useState(defaultName);
    const [description, setDescription] = useState(defaultDescription ?? '');
    const [folderId, setFolderId] = useState<Uuid | null>(defaultFolderId);
    const [folders, setFolders] = useState<Folder[]>(providedFolders ?? []);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [failure, setFailure] = useState<string | null>(null);
    const [saving, setSaving] = useState(false);

    useEffect(() => {
        if (!open) return;
        setName(defaultName);
        setDescription(defaultDescription ?? '');
        setFolderId(defaultFolderId);
        setErrors({});
        setFailure(null);
    }, [open, defaultName, defaultDescription, defaultFolderId]);

    useEffect(() => {
        if (!open) return undefined;
        if (providedFolders) {
            setFolders(providedFolders);

            return undefined;
        }
        let cancelled = false;
        fetchSegmentFolders()
            .then((rows) => {
                if (!cancelled) setFolders(rows);
            })
            .catch(() => {
                if (!cancelled) setFolders([]);
            });

        return () => {
            cancelled = true;
        };
    }, [open, providedFolders]);

    const tree = rules ?? emptyRuleTree();
    const rulesComplete = isRuleTreeComplete(tree, fieldCatalog);
    const nameMissing = name.trim() === '';
    const folderLabel = folders.find((f) => f.id === folderId)?.name ?? t.common.noFolder;

    async function submit(event?: FormEvent) {
        event?.preventDefault();
        if (nameMissing || !rulesComplete || saving) return;
        setSaving(true);
        setErrors({});
        setFailure(null);
        const input: SegmentInput = {
            name: name.trim(),
            description: description.trim() === '' ? null : description.trim(),
            folder_id: folderId,
            rules: tree,
        };
        try {
            const segment = await (onSubmit ?? createSegment)(input);
            onSaved?.(segment);
            onOpenChange(false);
        } catch (error) {
            const fieldErrors = validationErrors(error);
            if (fieldErrors) {
                setErrors(fieldErrors);
                const ruleMessages = Object.entries(fieldErrors)
                    .filter(([key]) => key === 'rules' || key.startsWith('rules.'))
                    .map(([, message]) => message);
                if (ruleMessages.length > 0) setFailure(ruleMessages.join(' '));
            } else {
                setFailure(errorMessage(error) ?? t.common.genericError);
            }
        } finally {
            setSaving(false);
        }
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => !saving && onOpenChange(data.open)}>
            <DialogSurface>
                <form onSubmit={submit}>
                    <DialogBody>
                        <DialogTitle>{t.contacts.saveAsSegment}</DialogTitle>
                        <DialogContent className={styles.form}>
                            {!rulesComplete && (
                                <MessageBar intent="warning">
                                    <MessageBarBody>{t.conditionBuilder.incomplete}</MessageBarBody>
                                </MessageBar>
                            )}
                            {failure && (
                                <MessageBar intent="error">
                                    <MessageBarBody>{failure}</MessageBarBody>
                                </MessageBar>
                            )}
                            <Field
                                label={t.segments.name}
                                required
                                validationMessage={errors.name}
                                validationState={errors.name ? 'error' : 'none'}
                            >
                                <Input value={name} maxLength={255} onChange={(_, data) => setName(data.value)} autoFocus />
                            </Field>
                            <Field
                                label={`${t.segments.description} (${t.common.optional})`}
                                validationMessage={errors.description}
                                validationState={errors.description ? 'error' : 'none'}
                            >
                                <Textarea value={description} resize="vertical" onChange={(_, data) => setDescription(data.value)} />
                            </Field>
                            <Field
                                label={t.segments.folder}
                                validationMessage={errors.folder_id}
                                validationState={errors.folder_id ? 'error' : 'none'}
                            >
                                <Dropdown
                                    selectedOptions={[folderId ?? NO_FOLDER]}
                                    value={folderLabel}
                                    onOptionSelect={(_, data) =>
                                        setFolderId(!data.optionValue || data.optionValue === NO_FOLDER ? null : data.optionValue)
                                    }
                                >
                                    <Option value={NO_FOLDER}>{t.common.noFolder}</Option>
                                    {folders.map((folder) => (
                                        <Option key={folder.id} value={folder.id}>
                                            {folder.name}
                                        </Option>
                                    ))}
                                </Dropdown>
                            </Field>
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" disabled={saving} onClick={() => onOpenChange(false)}>
                                {t.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                type="submit"
                                disabled={saving || nameMissing || !rulesComplete}
                                icon={saving ? <Spinner size="tiny" /> : undefined}
                            >
                                {saving ? t.common.saving : t.common.save}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </form>
            </DialogSurface>
        </Dialog>
    );
}

export default SaveSegmentDialog;
