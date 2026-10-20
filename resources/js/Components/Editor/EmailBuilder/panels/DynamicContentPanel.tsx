/**
 * WP-61 — dynamic-content block (doc 43 §43.4.4 "conditional block on
 * attribute/segment", docs/45 §45.7.1): the "Afficher si" card shown in the
 * properties panel for a dynamic section, and the rule dialog built on the
 * shared ConditionBuilder (`compact` variant, docs/45 §45.5.3).
 */
import { useEffect, useState } from 'react';
import {
    Button,
    Card,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    MessageBar,
    MessageBarBody,
    Text,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { EditRegular, FilterRegular } from '@fluentui/react-icons';
import ConditionBuilder, { defaultConditionOptionSources, isRuleTreeComplete } from '@/Components/Contacts/ConditionBuilder';
import type { FieldCatalog, RuleTree } from '@/Lib/types/segments';
import type { DynamicEditing } from '../useEmailBuilder';
import { emptyCondition } from '../engine/dynamicContent';
import { text } from '../strings';
import { summariseCondition } from './conditionSummary';

const useStyles = makeStyles({
    card: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalXS, padding: tokens.spacingVerticalS },
    title: { display: 'flex', alignItems: 'center', gap: tokens.spacingHorizontalXS },
    summary: { color: tokens.colorNeutralForeground2, wordBreak: 'break-word' },
    actions: { display: 'flex', gap: tokens.spacingHorizontalXS, flexWrap: 'wrap' },
    surface: { maxWidth: '760px', width: 'calc(100vw - 48px)' },
    content: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
});

export interface DynamicContentCardProps {
    dynamic: { id: string; condition: RuleTree | null };
    fieldCatalog: FieldCatalog;
    disabled?: boolean;
    onEdit: () => void;
    onRemove: () => void;
}

/** Properties panel: "Contenu dynamique — Afficher si : …" with edit / remove. */
export function DynamicContentCard({ dynamic, fieldCatalog, disabled, onEdit, onRemove }: DynamicContentCardProps) {
    const styles = useStyles();

    return (
        <Card className={styles.card} appearance="filled-alternative" size="small">
            <Text weight="semibold" className={styles.title}>
                <FilterRegular />
                {text.dynamicContent.title}
            </Text>
            <Text size={200} className={styles.summary}>
                {text.dynamicContent.condition} : {summariseCondition(dynamic.condition, fieldCatalog)}
            </Text>
            <div className={styles.actions}>
                <Button size="small" icon={<EditRegular />} disabled={disabled} onClick={onEdit}>
                    {text.common.edit}
                </Button>
                <Button size="small" appearance="subtle" disabled={disabled} onClick={onRemove}>
                    {text.common.remove}
                </Button>
            </div>
        </Card>
    );
}

export interface DynamicContentDialogProps {
    editing: DynamicEditing | null;
    fieldCatalog: FieldCatalog;
    onApply: (id: string, condition: RuleTree) => void;
    onCancel: () => void;
}

/** "Afficher si" rule of a dynamic section, edited with the compact ConditionBuilder. */
export function DynamicContentDialog({ editing, fieldCatalog, onApply, onCancel }: DynamicContentDialogProps) {
    const styles = useStyles();
    const [draft, setDraft] = useState<RuleTree>(emptyCondition());

    useEffect(() => {
        if (editing) {
            setDraft(editing.condition ?? emptyCondition());
        }
    }, [editing]);

    const complete = isRuleTreeComplete(draft, fieldCatalog);

    return (
        <Dialog open={editing !== null} onOpenChange={(_, data) => !data.open && onCancel()}>
            <DialogSurface className={styles.surface}>
                <DialogBody>
                    <DialogTitle>{text.dynamicContent.title}</DialogTitle>
                    <DialogContent className={styles.content}>
                        <Text weight="semibold">{text.dynamicContent.condition}</Text>
                        <ConditionBuilder
                            variant="compact"
                            value={draft}
                            onChange={setDraft}
                            fieldCatalog={fieldCatalog}
                            optionSources={defaultConditionOptionSources}
                        />
                        <Text size={200} className={styles.summary} aria-live="polite">
                            {text.dynamicContent.condition} : {summariseCondition(draft, fieldCatalog)}
                        </Text>
                        {!complete && (
                            <MessageBar intent="warning">
                                <MessageBarBody>{text.condition.incomplete}</MessageBarBody>
                            </MessageBar>
                        )}
                    </DialogContent>
                    <DialogActions>
                        <Button appearance="secondary" onClick={onCancel}>
                            {text.common.cancel}
                        </Button>
                        <Button appearance="primary" disabled={!editing || !complete} onClick={() => editing && onApply(editing.id, draft)}>
                            {text.common.apply}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
