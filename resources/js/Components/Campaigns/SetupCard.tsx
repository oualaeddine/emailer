import type { ReactNode } from 'react';
import { Button, Spinner, Text, Tooltip, makeStyles, mergeClasses, tokens } from '@fluentui/react-components';
import { CheckmarkCircleFilled, CircleRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';

const useStyles = makeStyles({
    card: {
        display: 'flex',
        gap: tokens.spacingHorizontalM,
        padding: `${tokens.spacingVerticalL} ${tokens.spacingHorizontalL}`,
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusLarge,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        boxShadow: tokens.shadow2,
    },
    cardActive: {
        border: `${tokens.strokeWidthThick} solid ${tokens.colorBrandStroke1}`,
    },
    status: {
        fontSize: '24px',
        lineHeight: '24px',
        paddingTop: tokens.spacingVerticalXXS,
        flexShrink: 0,
    },
    complete: {
        color: tokens.colorPaletteGreenForeground1,
    },
    incomplete: {
        color: tokens.colorNeutralForeground4,
    },
    body: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        flexGrow: 1,
        minWidth: 0,
    },
    header: {
        display: 'flex',
        alignItems: 'flex-start',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalM,
        flexWrap: 'wrap',
    },
    titleBlock: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
        minWidth: 0,
    },
    summary: {
        color: tokens.colorNeutralForeground2,
        overflowWrap: 'anywhere',
    },
    editor: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        paddingTop: tokens.spacingVerticalS,
    },
    footer: {
        display: 'flex',
        justifyContent: 'flex-end',
        gap: tokens.spacingHorizontalS,
    },
});

export interface SetupCardProps {
    title: string;
    complete: boolean;
    /** Shown under the title while collapsed (current value of the step). */
    summary?: ReactNode;
    /** Label of the Add/Edit action button; omitted = no button. */
    actionLabel?: string;
    onAction?: () => void;
    /** Inline editing mode (Brevo expands the card in place). */
    expanded?: boolean;
    children?: ReactNode;
    onSave?: () => void;
    onCancel?: () => void;
    saving?: boolean;
    saveDisabled?: boolean;
    disabled?: boolean;
    /** Extra actions rendered next to the main action (e.g. Design card). */
    extraActions?: ReactNode;
}

/**
 * One step of the Brevo "campaign dashboard" (docs/43 §43.4.2): completion
 * check mark, title, summary and an Add/Edit action; expands inline with
 * Annuler / Enregistrer while editing.
 */
export function SetupCard({
    title,
    complete,
    summary,
    actionLabel,
    onAction,
    expanded = false,
    children,
    onSave,
    onCancel,
    saving = false,
    saveDisabled = false,
    disabled = false,
    extraActions,
}: SetupCardProps) {
    const styles = useStyles();
    const t = useText();
    const statusLabel = complete ? t.campaignSetup.stepComplete : t.campaignSetup.stepIncomplete;

    return (
        <section className={mergeClasses(styles.card, expanded && styles.cardActive)} aria-label={title}>
            <Tooltip content={statusLabel} relationship="label">
                <span className={mergeClasses(styles.status, complete ? styles.complete : styles.incomplete)} role="img">
                    {complete ? <CheckmarkCircleFilled /> : <CircleRegular />}
                </span>
            </Tooltip>
            <div className={styles.body}>
                <div className={styles.header}>
                    <div className={styles.titleBlock}>
                        <Text weight="semibold" size={500}>
                            {title}
                        </Text>
                        {!expanded && summary && <div className={styles.summary}>{summary}</div>}
                    </div>
                    {!expanded && (
                        <div className={styles.footer}>
                            {extraActions}
                            {actionLabel && onAction && (
                                <Button appearance={complete ? 'secondary' : 'primary'} disabled={disabled} onClick={onAction}>
                                    {actionLabel}
                                </Button>
                            )}
                        </div>
                    )}
                </div>
                {expanded && (
                    <div className={styles.editor}>
                        {children}
                        <div className={styles.footer}>
                            <Button appearance="secondary" disabled={saving} onClick={onCancel}>
                                {t.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                disabled={saving || saveDisabled}
                                icon={saving ? <Spinner size="tiny" /> : undefined}
                                onClick={onSave}
                            >
                                {t.common.save}
                            </Button>
                        </div>
                    </div>
                )}
            </div>
        </section>
    );
}
