import { useEffect, useState } from 'react';
import {
    Button,
    DrawerBody,
    DrawerFooter,
    DrawerHeader,
    DrawerHeaderTitle,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    OverlayDrawer,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { DismissRegular, SaveRegular } from '@fluentui/react-icons';
import ConditionBuilder from '@/Components/Contacts/ConditionBuilder';
import { useText } from '@/Hooks/useText';
import type { ConditionBuilderProps, ConditionOptionSources, FieldCatalog, RuleTree } from '@/Lib/types/segments';
import { countConditions, isFilterTreeComplete, normaliseFilterTree } from '@/Components/Contacts/contactColumns';

const useStyles = makeStyles({
    footerActions: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
        alignItems: 'flex-end',
    },
    segmentForm: {
        display: 'flex',
        flexWrap: 'wrap',
        gap: tokens.spacingHorizontalS,
        alignItems: 'flex-end',
        marginTop: tokens.spacingVerticalS,
    },
    segmentName: {
        flexGrow: 1,
        minWidth: '200px',
    },
    message: {
        marginTop: tokens.spacingVerticalS,
    },
});

/** Error thrown by `onSaveSegment` to surface 422 messages next to the inputs. */
export interface SegmentSaveErrors {
    name?: string;
    /** Keyed by dot path relative to the tree root (e.g. "children.0.value"). */
    rules?: Record<string, string>;
    message?: string;
}

export interface ContactFilterDrawerProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Applied filter; the drawer edits a draft copy until *Appliquer*. */
    value: RuleTree | null;
    /** `null` when the draft has no condition. */
    onApply: (tree: RuleTree | null) => void;
    fieldCatalog: FieldCatalog;
    /** Live count of the draft within the current scope. */
    onCount?: (tree: RuleTree, signal: AbortSignal) => Promise<number>;
    optionSources?: ConditionOptionSources;
    /** Shows *Enregistrer comme segment* (`segments.manage`). */
    canSaveSegment: boolean;
    /** POST /segments; reject with `SegmentSaveErrors` to show validation messages. */
    onSaveSegment: (name: string, tree: RuleTree) => Promise<void>;
}

/**
 * docs/43-brevo-parity-plan.md §43.4.6 — *Ajouter un filtre*: the shared
 * ConditionBuilder (§45.5.3, compact variant) in a side drawer with live
 * count, *Appliquer* and *Enregistrer comme segment*.
 */
export function ContactFilterDrawer({
    open,
    onOpenChange,
    value,
    onApply,
    fieldCatalog,
    onCount,
    optionSources,
    canSaveSegment,
    onSaveSegment,
}: ContactFilterDrawerProps) {
    const styles = useStyles();
    const t = useText();
    const [draft, setDraft] = useState<RuleTree | null>(value);
    const [segmentFormOpen, setSegmentFormOpen] = useState(false);
    const [segmentName, setSegmentName] = useState('');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<SegmentSaveErrors>({});
    const [savedSegment, setSavedSegment] = useState(false);
    const [showIncomplete, setShowIncomplete] = useState(false);

    useEffect(() => {
        if (open) {
            setDraft(value);
            setSegmentFormOpen(false);
            setSegmentName('');
            setErrors({});
            setSavedSegment(false);
            setShowIncomplete(false);
        }
    }, [open, value]);

    const hasConditions = countConditions(draft) > 0;
    const complete = !hasConditions || isFilterTreeComplete(draft);

    function apply() {
        if (!complete) {
            setShowIncomplete(true);
            return;
        }
        onApply(normaliseFilterTree(draft));
        onOpenChange(false);
    }

    async function saveSegment() {
        if (!draft || !hasConditions || !complete) {
            setShowIncomplete(true);
            return;
        }
        setSaving(true);
        setErrors({});
        try {
            await onSaveSegment(segmentName.trim(), draft);
            setSavedSegment(true);
            setSegmentFormOpen(false);
        } catch (error) {
            setErrors(isSegmentSaveErrors(error) ? error : { message: t.common.genericError });
        } finally {
            setSaving(false);
        }
    }

    const footer = (
        <div>
            <div className={styles.footerActions}>
                <Button appearance="primary" onClick={apply}>
                    {t.common.apply}
                </Button>
                {canSaveSegment && (
                    <Button
                        icon={<SaveRegular />}
                        disabled={!hasConditions}
                        onClick={() => {
                            setSavedSegment(false);
                            setSegmentFormOpen((current) => !current);
                        }}
                    >
                        {t.contacts.saveAsSegment}
                    </Button>
                )}
            </div>
            {segmentFormOpen && (
                <div className={styles.segmentForm}>
                    <Field
                        className={styles.segmentName}
                        label={t.contacts.segmentName}
                        required
                        validationState={errors.name ? 'error' : 'none'}
                        validationMessage={errors.name}
                    >
                        <Input value={segmentName} onChange={(_, data) => setSegmentName(data.value)} maxLength={100} />
                    </Field>
                    <Button appearance="primary" disabled={saving || segmentName.trim() === ''} onClick={() => void saveSegment()}>
                        {saving ? t.common.saving : t.common.save}
                    </Button>
                </div>
            )}
            {showIncomplete && !complete && (
                <MessageBar intent="warning" className={styles.message}>
                    <MessageBarBody>{t.conditionBuilder.incomplete}</MessageBarBody>
                </MessageBar>
            )}
            {errors.message && (
                <MessageBar intent="error" className={styles.message}>
                    <MessageBarBody>{errors.message}</MessageBarBody>
                </MessageBar>
            )}
            {savedSegment && (
                <MessageBar intent="success" className={styles.message}>
                    <MessageBarBody>{t.common.saved}</MessageBarBody>
                </MessageBar>
            )}
        </div>
    );

    // Typed against the frozen §45.5.3 contract (Lib/types/segments.ts).
    const builderProps: ConditionBuilderProps = {
        value: draft,
        onChange: (tree) => {
            setDraft(tree);
            setSavedSegment(false);
        },
        fieldCatalog,
        onCount,
        optionSources,
        errors: errors.rules,
        disabled: saving,
        variant: 'compact',
        footer,
    };

    return (
        <OverlayDrawer open={open} onOpenChange={(_, data) => onOpenChange(data.open)} position="end" size="large">
            <DrawerHeader>
                <DrawerHeaderTitle
                    action={
                        <Button
                            appearance="subtle"
                            aria-label={t.common.close}
                            icon={<DismissRegular />}
                            onClick={() => onOpenChange(false)}
                        />
                    }
                >
                    {t.contacts.addFilter}
                </DrawerHeaderTitle>
            </DrawerHeader>
            <DrawerBody>
                <ConditionBuilder {...builderProps} />
            </DrawerBody>
            <DrawerFooter>
                <Button
                    appearance="secondary"
                    disabled={!hasConditions && value === null}
                    onClick={() => {
                        onApply(null);
                        onOpenChange(false);
                    }}
                >
                    {t.common.resetFilters}
                </Button>
                <Button appearance="subtle" onClick={() => onOpenChange(false)}>
                    {t.common.cancel}
                </Button>
            </DrawerFooter>
        </OverlayDrawer>
    );
}

function isSegmentSaveErrors(error: unknown): error is SegmentSaveErrors {
    return (
        typeof error === 'object' &&
        error !== null &&
        !(error instanceof Error) &&
        ('name' in error || 'rules' in error || 'message' in error)
    );
}
