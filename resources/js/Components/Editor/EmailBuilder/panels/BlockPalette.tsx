/**
 * WP-60 / WP-61 — left panel "Contenu" (doc 43 §43.4.4) with Brevo's three
 * sub-tabs: **Blocs**, **Sections** (pre-made row layouts, `GET
 * /editor/sections`) and **Enregistrés** (saved sections, `GET
 * /saved-sections`; only whole sections can be saved). Draggable Fluent
 * cards call `Blocks.startDrag` / `endDrag` (docs/46 §5); Enter or a double
 * click inserts the card after the selected section.
 */
import { useMemo, useState, type DragEvent as ReactDragEvent, type KeyboardEvent, type ReactElement } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Field,
    Input,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    Spinner,
    Tab,
    TabList,
    Text,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import {
    AddRegular,
    ArrowAutofitHeightRegular,
    BookmarkRegular,
    ButtonRegular,
    CartRegular,
    CodeRegular,
    ColumnRegular,
    DeleteRegular,
    FilterRegular,
    ImageRegular,
    LayoutColumnFourRegular,
    LayoutColumnThreeRegular,
    LayoutColumnTwoRegular,
    LineHorizontal1Regular,
    MoreHorizontalRegular,
    NavigationRegular,
    RenameRegular,
    SaveRegular,
    SearchRegular,
    ShareRegular,
    StarRegular,
    TextHeader1Regular,
    TextTRegular,
    VideoClipRegular,
} from '@fluentui/react-icons';
import type { PremadeSectionCategory } from '@/Lib/types/editor';
import type { BlockCategory, EmailBlockDefinition } from '../engine/blocks';
import { text } from '../strings';

const ICONS: Record<string, ReactElement> = {
    title: <TextHeader1Regular />,
    text: <TextTRegular />,
    image: <ImageRegular />,
    button: <ButtonRegular />,
    divider: <LineHorizontal1Regular />,
    spacer: <ArrowAutofitHeightRegular />,
    social: <ShareRegular />,
    'columns-1': <ColumnRegular />,
    'columns-2': <LayoutColumnTwoRegular />,
    'columns-3': <LayoutColumnThreeRegular />,
    'columns-4': <LayoutColumnFourRegular />,
    html: <CodeRegular />,
    video: <VideoClipRegular />,
    logo: <StarRegular />,
    navigation: <NavigationRegular />,
    product: <CartRegular />,
    dynamic: <FilterRegular />,
    saved: <BookmarkRegular />,
};

const CATEGORY_ORDER: PremadeSectionCategory[] = ['header', 'content', 'product', 'footer'];

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', minHeight: 0, flex: '1 1 auto' },
    body: { flex: '1 1 auto', overflowY: 'auto', padding: tokens.spacingVerticalS, display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS },
    grid: { display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: tokens.spacingHorizontalS },
    stack: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS },
    card: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: tokens.spacingVerticalXS,
        padding: `${tokens.spacingVerticalM} ${tokens.spacingHorizontalXS}`,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke1}`,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground1,
        cursor: 'grab',
        textAlign: 'center',
        userSelect: 'none',
        fontSize: tokens.fontSizeBase500,
        ':hover': { backgroundColor: tokens.colorNeutralBackground1Hover, border: `${tokens.strokeWidthThin} solid ${tokens.colorBrandStroke1}` },
        ':focus-visible': { outline: `${tokens.strokeWidthThick} solid ${tokens.colorStrokeFocus2}` },
    },
    sectionCard: { padding: tokens.spacingVerticalXS, alignItems: 'stretch', fontSize: tokens.fontSizeBase300 },
    thumb: { width: '100%', height: 'auto', display: 'block', borderRadius: tokens.borderRadiusSmall, pointerEvents: 'none' },
    savedRow: { display: 'flex', alignItems: 'center', gap: tokens.spacingHorizontalXS, textAlign: 'left' },
    savedLabel: { flex: '1 1 auto', minWidth: 0, overflow: 'hidden', textOverflow: 'ellipsis', whiteSpace: 'nowrap' },
    muted: { color: tokens.colorNeutralForeground3 },
    groupTitle: { marginTop: tokens.spacingVerticalXS },
});

export interface BlockPaletteProps {
    blocks: EmailBlockDefinition[];
    disabled: boolean;
    /** Pre-made and saved sections fetched (spinner until then). */
    sectionsLoaded: boolean;
    /** The selection is a whole section (`mj-section` / `mj-wrapper`). */
    canSaveSection: boolean;
    onDragStart: (id: string, event: DragEvent) => void;
    onDragEnd: () => void;
    onInsert: (id: string) => void;
    onSaveSection: (name: string) => Promise<boolean>;
    onRenameSection: (savedId: string, name: string) => Promise<boolean>;
    onDeleteSection: (savedId: string) => Promise<boolean>;
}

const SUB_TABS: BlockCategory[] = ['blocks', 'sections', 'saved'];

type NameDialog = { mode: 'save' } | { mode: 'rename'; block: EmailBlockDefinition };

export function BlockPalette({
    blocks,
    disabled,
    sectionsLoaded,
    canSaveSection,
    onDragStart,
    onDragEnd,
    onInsert,
    onSaveSection,
    onRenameSection,
    onDeleteSection,
}: BlockPaletteProps) {
    const styles = useStyles();
    const [tab, setTab] = useState<BlockCategory>('blocks');
    const [query, setQuery] = useState('');
    const [nameDialog, setNameDialog] = useState<NameDialog | null>(null);
    const [name, setName] = useState('');
    const [deleting, setDeleting] = useState<EmailBlockDefinition | null>(null);
    const [busy, setBusy] = useState(false);

    const visible = blocks.filter((block) => block.category === tab);
    const saved = useMemo(() => {
        const q = query.trim().toLowerCase();

        return blocks.filter((block) => block.category === 'saved' && (q === '' || block.label.toLowerCase().includes(q)));
    }, [blocks, query]);

    const handleDragStart = (id: string) => (event: ReactDragEvent<HTMLDivElement>) => {
        if (disabled) {
            event.preventDefault();

            return;
        }
        event.dataTransfer.effectAllowed = 'copy';
        onDragStart(id, event.nativeEvent);
    };

    const handleKeyDown = (id: string) => (event: KeyboardEvent<HTMLDivElement>) => {
        if (!disabled && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            onInsert(id);
        }
    };

    const cardProps = (block: EmailBlockDefinition) => ({
        role: 'listitem',
        tabIndex: disabled ? -1 : 0,
        draggable: !disabled,
        'aria-disabled': disabled,
        onDragStart: handleDragStart(block.id),
        onDragEnd,
        onKeyDown: handleKeyDown(block.id),
        onDoubleClick: () => !disabled && onInsert(block.id),
        title: block.label,
    });

    const openName = (dialog: NameDialog) => {
        setName(dialog.mode === 'rename' ? dialog.block.label : '');
        setNameDialog(dialog);
    };

    const submitName = async () => {
        const value = name.trim();
        if (!nameDialog || value === '' || value.length > 100) {
            return;
        }
        setBusy(true);
        const ok =
            nameDialog.mode === 'save' ? await onSaveSection(value) : nameDialog.block.savedId ? await onRenameSection(nameDialog.block.savedId, value) : false;
        setBusy(false);
        if (ok) {
            setNameDialog(null);
            if (nameDialog.mode === 'save') {
                setTab('saved');
            }
        }
    };

    const confirmDelete = async () => {
        if (!deleting?.savedId) {
            return;
        }
        setBusy(true);
        await onDeleteSection(deleting.savedId);
        setBusy(false);
        setDeleting(null);
    };

    const renderSections = () => {
        if (!sectionsLoaded) {
            return <Spinner size="small" label={text.common.loading} />;
        }
        if (visible.length === 0) {
            return <Text size={200}>{text.common.noResults}</Text>;
        }

        return CATEGORY_ORDER.map((category) => {
            const rows = visible.filter((block) => block.group === category);
            if (rows.length === 0) {
                return null;
            }

            return (
                <div key={category} className={styles.stack}>
                    <Text weight="semibold" size={200} className={styles.groupTitle}>
                        {text.sectionCategories[category]}
                    </Text>
                    <div className={styles.stack} role="list">
                        {rows.map((block) => (
                            <div key={block.id} className={mergeClasses(styles.card, styles.sectionCard)} {...cardProps(block)}>
                                {block.thumbnail ? <img className={styles.thumb} src={block.thumbnail} alt="" draggable={false} /> : null}
                                <Text size={200}>{block.label}</Text>
                            </div>
                        ))}
                    </div>
                </div>
            );
        });
    };

    const renderSaved = () => (
        <>
            <Button icon={<SaveRegular />} disabled={disabled || !canSaveSection} onClick={() => openName({ mode: 'save' })}>
                {text.saveSection}
            </Button>
            {!canSaveSection && (
                <Text size={200} className={styles.muted}>
                    {text.onlySectionsCanBeSaved}
                </Text>
            )}
            <Input
                size="small"
                contentBefore={<SearchRegular />}
                placeholder={text.common.searchPlaceholder}
                value={query}
                onChange={(_, data) => setQuery(data.value)}
                aria-label={text.common.searchPlaceholder}
            />
            {!sectionsLoaded && <Spinner size="small" label={text.common.loading} />}
            {sectionsLoaded && saved.length === 0 && <Text size={200}>{query ? text.common.noResults : text.noSavedSections}</Text>}
            <div className={styles.stack} role="list">
                {saved.map((block) => (
                    <div key={block.id} className={mergeClasses(styles.card, styles.sectionCard)} {...cardProps(block)}>
                        {block.thumbnail ? <img className={styles.thumb} src={block.thumbnail} alt="" draggable={false} /> : null}
                        <div className={styles.savedRow}>
                            <BookmarkRegular />
                            <Text size={200} className={styles.savedLabel}>
                                {block.label}
                            </Text>
                            <Menu>
                                <MenuTrigger disableButtonEnhancement>
                                    <Button
                                        size="small"
                                        appearance="subtle"
                                        icon={<MoreHorizontalRegular />}
                                        aria-label={text.common.moreActions}
                                        disabled={disabled}
                                        onDoubleClick={(event) => event.stopPropagation()}
                                    />
                                </MenuTrigger>
                                <MenuPopover>
                                    <MenuList>
                                        <MenuItem icon={<AddRegular />} onClick={() => onInsert(block.id)}>
                                            {text.common.add}
                                        </MenuItem>
                                        <MenuItem icon={<RenameRegular />} onClick={() => openName({ mode: 'rename', block })}>
                                            {text.common.rename}
                                        </MenuItem>
                                        <MenuItem icon={<DeleteRegular />} onClick={() => setDeleting(block)}>
                                            {text.common.delete}
                                        </MenuItem>
                                    </MenuList>
                                </MenuPopover>
                            </Menu>
                        </div>
                    </div>
                ))}
            </div>
        </>
    );

    return (
        <div className={styles.root}>
            <TabList size="small" selectedValue={tab} onTabSelect={(_, data) => setTab(data.value as BlockCategory)}>
                {SUB_TABS.map((key) => (
                    <Tab key={key} value={key}>
                        {text.contentTabs[key]}
                    </Tab>
                ))}
            </TabList>
            <div className={styles.body}>
                {tab === 'blocks' && (
                    <div className={styles.grid} role="list">
                        {visible.map((block) => (
                            <div key={block.id} className={styles.card} {...cardProps(block)}>
                                {ICONS[block.icon] ?? <ColumnRegular />}
                                <Text size={200}>{block.label}</Text>
                            </div>
                        ))}
                    </div>
                )}
                {tab === 'sections' && renderSections()}
                {tab === 'saved' && renderSaved()}
            </div>

            <Dialog open={nameDialog !== null} onOpenChange={(_, data) => !data.open && !busy && setNameDialog(null)}>
                <DialogSurface>
                    <form
                        onSubmit={(event) => {
                            event.preventDefault();
                            void submitName();
                        }}
                    >
                        <DialogBody>
                            <DialogTitle>{nameDialog?.mode === 'rename' ? text.common.rename : text.saveSection}</DialogTitle>
                            <DialogContent>
                                <Field label={text.sectionName} required>
                                    <Input value={name} maxLength={100} autoFocus onChange={(_, data) => setName(data.value)} />
                                </Field>
                            </DialogContent>
                            <DialogActions>
                                <Button appearance="secondary" disabled={busy} onClick={() => setNameDialog(null)}>
                                    {text.common.cancel}
                                </Button>
                                <Button appearance="primary" type="submit" disabled={busy || name.trim() === ''}>
                                    {busy ? <Spinner size="tiny" /> : text.save}
                                </Button>
                            </DialogActions>
                        </DialogBody>
                    </form>
                </DialogSurface>
            </Dialog>

            <Dialog open={deleting !== null} onOpenChange={(_, data) => !data.open && !busy && setDeleting(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{text.common.deleteConfirmTitle}</DialogTitle>
                        <DialogContent>
                            <Text weight="semibold">{deleting?.label}</Text>
                            <br />
                            {text.common.deleteConfirmBody}
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" disabled={busy} onClick={() => setDeleting(null)}>
                                {text.common.cancel}
                            </Button>
                            <Button appearance="primary" disabled={busy} onClick={() => void confirmDelete()}>
                                {text.common.delete}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </div>
    );
}
