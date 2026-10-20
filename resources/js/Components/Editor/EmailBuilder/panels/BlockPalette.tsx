/**
 * WP-60 — left panel "Contenu" › Blocs (doc 43 §43.4.4). Draggable Fluent
 * cards call `Blocks.startDrag` / `endDrag` (docs/46 §5). WP-61 adds the
 * "Sections" and "Enregistrés" sub-tabs: blocks with those categories are
 * listed under their own tab as soon as they are registered.
 */
import { useMemo, useState, type DragEvent as ReactDragEvent, type ReactElement } from 'react';
import { Tab, TabList, Text, makeStyles, tokens } from '@fluentui/react-components';
import {
    ButtonRegular,
    CodeRegular,
    ColumnRegular,
    ImageRegular,
    LayoutColumnFourRegular,
    LayoutColumnThreeRegular,
    LayoutColumnTwoRegular,
    LineHorizontal1Regular,
    ArrowAutofitHeightRegular,
    NavigationRegular,
    ShareRegular,
    StarRegular,
    TextHeader1Regular,
    TextTRegular,
    VideoClipRegular,
} from '@fluentui/react-icons';
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
};

const useStyles = makeStyles({
    root: { display: 'flex', flexDirection: 'column', minHeight: 0, flex: '1 1 auto' },
    body: { flex: '1 1 auto', overflowY: 'auto', padding: tokens.spacingVerticalS },
    grid: { display: 'grid', gridTemplateColumns: 'repeat(2, minmax(0, 1fr))', gap: tokens.spacingHorizontalS },
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
});

export interface BlockPaletteProps {
    blocks: EmailBlockDefinition[];
    disabled: boolean;
    onDragStart: (id: string, event: DragEvent) => void;
    onDragEnd: () => void;
}

const SUB_TABS: BlockCategory[] = ['blocks', 'sections', 'saved'];

export function BlockPalette({ blocks, disabled, onDragStart, onDragEnd }: BlockPaletteProps) {
    const styles = useStyles();
    const [tab, setTab] = useState<BlockCategory>('blocks');
    // Only the sub-tabs that have content this wave (WP-61 fills Sections / Enregistrés).
    const tabs = useMemo(() => SUB_TABS.filter((key) => key === 'blocks' || blocks.some((b) => b.category === key)), [blocks]);
    const visible = blocks.filter((block) => block.category === tab);

    const handleDragStart = (id: string) => (event: ReactDragEvent<HTMLDivElement>) => {
        if (disabled) {
            event.preventDefault();

            return;
        }
        event.dataTransfer.effectAllowed = 'copy';
        onDragStart(id, event.nativeEvent);
    };

    return (
        <div className={styles.root}>
            <TabList size="small" selectedValue={tab} onTabSelect={(_, data) => setTab(data.value as BlockCategory)}>
                {tabs.map((key) => (
                    <Tab key={key} value={key}>
                        {text.contentTabs[key]}
                    </Tab>
                ))}
            </TabList>
            <div className={styles.body}>
                <div className={styles.grid} role="list">
                    {visible.map((block) => (
                        <div
                            key={block.id}
                            role="listitem"
                            className={styles.card}
                            draggable={!disabled}
                            aria-disabled={disabled}
                            onDragStart={handleDragStart(block.id)}
                            onDragEnd={onDragEnd}
                            title={block.label}
                        >
                            {ICONS[block.icon] ?? <ColumnRegular />}
                            <Text size={200}>{block.label}</Text>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
