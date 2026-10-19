import { useEffect, useMemo, useState, type DragEvent, type KeyboardEvent } from 'react';
import {
    Button,
    Checkbox,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Input,
    Subtitle2,
    Text,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import { DismissRegular, LockClosedRegular, ReOrderDotsVerticalRegular, SearchRegular } from '@fluentui/react-icons';
import { useText } from '@/Hooks/useText';
import type { ContactColumn, ContactColumnKey } from '@/Lib/types/contacts';
import {
    DEFAULT_COLUMN_WIDTH,
    DEFAULT_CONTACT_COLUMNS,
    PINNED_COLUMN_KEY,
    describeColumn,
    indexColumnCatalog,
    normaliseColumns,
    type ContactColumnDescriptor,
} from '@/Components/Contacts/contactColumns';

const useStyles = makeStyles({
    surface: {
        maxWidth: '760px',
        width: '100%',
    },
    panes: {
        display: 'grid',
        gridTemplateColumns: '1fr 1fr',
        gap: tokens.spacingHorizontalL,
        '@media (max-width: 639px)': {
            gridTemplateColumns: '1fr',
        },
    },
    pane: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        minWidth: 0,
    },
    scroll: {
        maxHeight: '360px',
        overflowY: 'auto',
        display: 'flex',
        flexDirection: 'column',
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        borderRadius: tokens.borderRadiusMedium,
        padding: tokens.spacingVerticalXS,
    },
    selectedRow: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
        padding: `${tokens.spacingVerticalXXS} ${tokens.spacingHorizontalXS}`,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground1,
    },
    dragOver: {
        backgroundColor: tokens.colorNeutralBackground1Hover,
        outline: `${tokens.strokeWidthThin} dashed ${tokens.colorBrandStroke1}`,
    },
    label: {
        flexGrow: 1,
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
    },
    lock: {
        color: tokens.colorNeutralForeground3,
        padding: `0 ${tokens.spacingHorizontalS}`,
    },
});

export interface ColumnChooserDialogProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    /** Current visible columns, in order. */
    columns: ContactColumn[];
    /** Every displayable column (GET /contact-attributes + static contact columns). */
    catalog: ContactColumnDescriptor[];
    onApply: (columns: ContactColumn[]) => void;
}

/**
 * docs/43-brevo-parity-plan.md §43.4.6 — *Personnaliser les colonnes*: any
 * contact attribute becomes a column; order (drag handle, or ↑/↓ on the
 * handle) and width are part of the view.
 */
export function ColumnChooserDialog({ open, onOpenChange, columns, catalog, onApply }: ColumnChooserDialogProps) {
    const styles = useStyles();
    const t = useText();
    const [draft, setDraft] = useState<ContactColumn[]>(columns);
    const [query, setQuery] = useState('');
    const [dragIndex, setDragIndex] = useState<number | null>(null);
    const [overIndex, setOverIndex] = useState<number | null>(null);

    useEffect(() => {
        if (open) {
            setDraft(normaliseColumns(columns));
            setQuery('');
        }
    }, [open, columns]);

    const catalogIndex = useMemo(() => indexColumnCatalog(catalog), [catalog]);
    const selectedKeys = useMemo(() => new Set(draft.map((column) => column.key)), [draft]);
    const available = useMemo(() => {
        const needle = query.trim().toLocaleLowerCase('fr');

        return needle === ''
            ? catalog
            : catalog.filter((column) => column.label.toLocaleLowerCase('fr').includes(needle) || column.key.toLowerCase().includes(needle));
    }, [catalog, query]);

    function toggle(key: ContactColumnKey, checked: boolean) {
        if (key === PINNED_COLUMN_KEY) {
            return;
        }
        setDraft((current) =>
            checked
                ? [...current, { key, width: DEFAULT_COLUMN_WIDTH }]
                : current.filter((column) => column.key !== key),
        );
    }

    function move(from: number, to: number) {
        // Index 0 is the pinned identifier column.
        if (from < 1 || to < 1 || from >= draft.length || to >= draft.length || from === to) {
            return;
        }
        setDraft((current) => {
            const next = [...current];
            const [moved] = next.splice(from, 1);
            next.splice(to, 0, moved);

            return next;
        });
    }

    function onHandleKeyDown(event: KeyboardEvent<HTMLButtonElement>, index: number) {
        if (event.key === 'ArrowUp') {
            event.preventDefault();
            move(index, index - 1);
        } else if (event.key === 'ArrowDown') {
            event.preventDefault();
            move(index, index + 1);
        }
    }

    function onDrop(event: DragEvent<HTMLDivElement>, index: number) {
        event.preventDefault();
        if (dragIndex !== null) {
            move(dragIndex, index);
        }
        setDragIndex(null);
        setOverIndex(null);
    }

    return (
        <Dialog open={open} onOpenChange={(_, data) => onOpenChange(data.open)}>
            <DialogSurface className={styles.surface}>
                <DialogBody>
                    <DialogTitle>{t.contacts.customizeColumns}</DialogTitle>
                    <DialogContent>
                        <div className={styles.panes}>
                            <div className={styles.pane}>
                                <Subtitle2>{t.contacts.availableColumns}</Subtitle2>
                                <Input
                                    contentBefore={<SearchRegular />}
                                    placeholder={t.common.searchPlaceholder}
                                    aria-label={t.common.search}
                                    value={query}
                                    onChange={(_, data) => setQuery(data.value)}
                                />
                                <div className={styles.scroll}>
                                    {available.length === 0 ? (
                                        <Text>{t.common.noResults}</Text>
                                    ) : (
                                        available.map((column) => (
                                            <Checkbox
                                                key={column.key}
                                                label={column.label}
                                                checked={selectedKeys.has(column.key)}
                                                disabled={column.key === PINNED_COLUMN_KEY}
                                                onChange={(_, data) => toggle(column.key, data.checked === true)}
                                            />
                                        ))
                                    )}
                                </div>
                            </div>
                            <div className={styles.pane}>
                                <Subtitle2>{t.contacts.columnsTitle}</Subtitle2>
                                <div className={styles.scroll}>
                                    {draft.map((column, index) => {
                                        const descriptor = describeColumn(column.key, catalogIndex);
                                        const pinned = column.key === PINNED_COLUMN_KEY;

                                        return (
                                            <div
                                                key={column.key}
                                                className={mergeClasses(styles.selectedRow, overIndex === index && !pinned && styles.dragOver)}
                                                draggable={!pinned}
                                                onDragStart={(event) => {
                                                    event.dataTransfer.effectAllowed = 'move';
                                                    setDragIndex(index);
                                                }}
                                                onDragOver={(event) => {
                                                    if (dragIndex !== null && !pinned) {
                                                        event.preventDefault();
                                                        setOverIndex(index);
                                                    }
                                                }}
                                                onDragLeave={() => setOverIndex((current) => (current === index ? null : current))}
                                                onDrop={(event) => onDrop(event, index)}
                                                onDragEnd={() => {
                                                    setDragIndex(null);
                                                    setOverIndex(null);
                                                }}
                                            >
                                                {pinned ? (
                                                    <LockClosedRegular className={styles.lock} aria-hidden />
                                                ) : (
                                                    <Button
                                                        appearance="transparent"
                                                        size="small"
                                                        icon={<ReOrderDotsVerticalRegular />}
                                                        aria-label={`${t.common.move} : ${descriptor.label}`}
                                                        onKeyDown={(event) => onHandleKeyDown(event, index)}
                                                    />
                                                )}
                                                <Text className={styles.label}>{descriptor.label}</Text>
                                                {!pinned && (
                                                    <Button
                                                        appearance="subtle"
                                                        size="small"
                                                        icon={<DismissRegular />}
                                                        aria-label={`${t.common.remove} : ${descriptor.label}`}
                                                        onClick={() => toggle(column.key, false)}
                                                    />
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            </div>
                        </div>
                    </DialogContent>
                    <DialogActions fluid>
                        <Button appearance="subtle" onClick={() => setDraft(DEFAULT_CONTACT_COLUMNS)}>
                            {t.contacts.resetColumns}
                        </Button>
                        <Button appearance="secondary" onClick={() => onOpenChange(false)}>
                            {t.common.cancel}
                        </Button>
                        <Button
                            appearance="primary"
                            onClick={() => {
                                onApply(normaliseColumns(draft));
                                onOpenChange(false);
                            }}
                        >
                            {t.common.apply}
                        </Button>
                    </DialogActions>
                </DialogBody>
            </DialogSurface>
        </Dialog>
    );
}
