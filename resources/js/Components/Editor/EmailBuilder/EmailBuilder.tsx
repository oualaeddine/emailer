/**
 * WP-60 — EmailBuilder (doc 43 §43.4.4, docs/46-editor-spike.md §7).
 *
 * Brevo-like three panes inside the Fluent shell: left "Contenu" (Blocs;
 * WP-61 adds Sections / Enregistrés and the "Style" tab), centre GrapesJS
 * canvas with the desktop / mobile toggle, right "Propriétés" (plus the
 * GrapesJS style and layer managers as advanced tabs). Top bar: back, undo /
 * redo, restore the saved version, *Aperçu & test*, *Enregistrer* and
 * *Enregistrer et quitter*.
 *
 * Reached only through `EmailBuilderLazy` (dynamic import): this module and
 * everything it imports (grapesjs, grapesjs-mjml, CSS) stay out of the main chunk.
 */
import { useEffect, useState } from 'react';
import {
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    MessageBar,
    MessageBarActions,
    MessageBarBody,
    MessageBarTitle,
    Spinner,
    Tab,
    TabList,
    Text,
    Toolbar,
    ToolbarButton,
    ToolbarDivider,
    ToolbarRadioButton,
    ToolbarRadioGroup,
    Tooltip,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import {
    ArrowLeftRegular,
    ArrowRedoRegular,
    ArrowUndoRegular,
    DesktopRegular,
    DismissRegular,
    EyeRegular,
    HistoryRegular,
    PhoneRegular,
    SaveRegular,
} from '@fluentui/react-icons';
import { toDesignSaveError } from '@/Lib/api/editor';
import { BlockPalette } from './panels/BlockPalette';
import { PropertiesPanel } from './panels/PropertiesPanel';
import { PreviewDialog } from './PreviewDialog';
import { useEmailBuilder } from './useEmailBuilder';
import type { BuilderDevice } from './engine/createEditor';
import type { EmailBuilderProps } from './types';
import { format, text } from './strings';

type LeftTab = 'content';
type RightTab = 'properties' | 'advanced' | 'layers';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalS,
        height: 'calc(100vh - 120px)',
        minHeight: '560px',
    },
    topBar: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        flexWrap: 'wrap',
        padding: `${tokens.spacingVerticalXS} ${tokens.spacingHorizontalS}`,
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        boxShadow: tokens.shadow2,
    },
    name: {
        flex: '1 1 160px',
        minWidth: 0,
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        whiteSpace: 'nowrap',
    },
    toolbar: { flexWrap: 'wrap', padding: 0 },
    status: { color: tokens.colorNeutralForeground3 },
    workspace: {
        flex: '1 1 auto',
        minHeight: 0,
        display: 'grid',
        gridTemplateColumns: '248px minmax(0, 1fr) 300px',
        borderRadius: tokens.borderRadiusMedium,
        overflow: 'hidden',
        boxShadow: tokens.shadow4,
        backgroundColor: tokens.colorNeutralBackground1,
        '@media (max-width: 1023px)': { gridTemplateColumns: '200px minmax(0, 1fr) 240px' },
    },
    side: {
        display: 'flex',
        flexDirection: 'column',
        minHeight: 0,
        borderRight: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        backgroundColor: tokens.colorNeutralBackground1,
    },
    sideRight: { borderRight: 'none', borderLeft: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}` },
    sideBody: { flex: '1 1 auto', overflowY: 'auto', padding: tokens.spacingVerticalS },
    canvas: { position: 'relative', minWidth: 0, minHeight: 0 },
    canvasHost: { position: 'absolute', inset: 0 },
    overlay: {
        position: 'absolute',
        inset: 0,
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        backgroundColor: tokens.colorNeutralBackground3,
    },
    hidden: { display: 'none' },
    errorList: { margin: 0, paddingLeft: tokens.spacingHorizontalL },
});

export default function EmailBuilder({ name, source, onSave, onExit, onSendTest, restore }: EmailBuilderProps) {
    const styles = useStyles();
    const builder = useEmailBuilder({ source, onSave });
    const [leftTab, setLeftTab] = useState<LeftTab>('content');
    const [rightTab, setRightTab] = useState<RightTab>('properties');
    const [previewOpen, setPreviewOpen] = useState(false);
    const [previewHtml, setPreviewHtml] = useState('');
    const [confirm, setConfirm] = useState<'leave' | 'restore' | null>(null);
    const { save, ready } = builder;

    // Ctrl/Cmd + S saves.
    useEffect(() => {
        const onKeyDown = (event: KeyboardEvent) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 's') {
                event.preventDefault();
                if (ready) {
                    void save();
                }
            }
        };
        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
    }, [ready, save]);

    const saveAndQuit = async () => {
        if (await builder.save()) {
            onExit();
        }
    };

    const back = () => {
        if (builder.isDirty()) {
            setConfirm('leave');
        } else {
            onExit();
        }
    };

    const openPreview = () => {
        setPreviewHtml(builder.previewHtml());
        setPreviewOpen(true);
    };

    const sendTest = onSendTest
        ? async (emails: string[]) => {
              if (builder.isDirty() && !(await builder.save())) {
                  throw new Error(text.validationTitle);
              }
              try {
                  await onSendTest(emails);
              } catch (error) {
                  const parsed = toDesignSaveError(error);
                  throw new Error(parsed.errors[0]?.message ?? parsed.message ?? text.common.genericError);
              }
          }
        : undefined;

    const feedback = builder.feedback;

    return (
        <div className={styles.root}>
            <div className={styles.topBar}>
                <Tooltip content={text.common.back} relationship="label">
                    <Button appearance="subtle" icon={<ArrowLeftRegular />} onClick={back} />
                </Tooltip>
                <Text weight="semibold" size={400} className={styles.name} title={name}>
                    {name}
                </Text>
                <Text size={200} className={styles.status} aria-live="polite">
                    {builder.saving
                        ? text.saving
                        : builder.dirty
                          ? text.common.unsavedChanges
                          : builder.savedAt
                            ? format(text.common.savedAt, builder.savedAt.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' }))
                            : ''}
                </Text>
                <Toolbar
                    aria-label={text.toolbar}
                    className={styles.toolbar}
                    checkedValues={{ device: [builder.device] }}
                    onCheckedValueChange={(_, data) => data.name === 'device' && builder.setDevice(data.checkedItems[0] as BuilderDevice)}
                >
                    <Tooltip content={text.undo} relationship="label">
                        <ToolbarButton icon={<ArrowUndoRegular />} disabled={!builder.ready || !builder.canUndo} onClick={builder.undo} />
                    </Tooltip>
                    <Tooltip content={text.redo} relationship="label">
                        <ToolbarButton icon={<ArrowRedoRegular />} disabled={!builder.ready || !builder.canRedo} onClick={builder.redo} />
                    </Tooltip>
                    {restore && (
                        <ToolbarButton
                            icon={<HistoryRegular />}
                            disabled={!builder.ready || !builder.dirty}
                            onClick={() => setConfirm('restore')}
                        >
                            {text.restorePrevious}
                        </ToolbarButton>
                    )}
                    <ToolbarDivider />
                    <ToolbarRadioGroup aria-label={text.device}>
                        <Tooltip content={text.desktop} relationship="label">
                            <ToolbarRadioButton name="device" value="desktop" icon={<DesktopRegular />} disabled={!builder.ready} appearance="subtle" />
                        </Tooltip>
                        <Tooltip content={text.mobile} relationship="label">
                            <ToolbarRadioButton name="device" value="mobile" icon={<PhoneRegular />} disabled={!builder.ready} appearance="subtle" />
                        </Tooltip>
                    </ToolbarRadioGroup>
                    <ToolbarDivider />
                    <ToolbarButton icon={<EyeRegular />} disabled={!builder.ready} onClick={openPreview}>
                        {text.previewAndTest}
                    </ToolbarButton>
                    <ToolbarButton icon={<SaveRegular />} disabled={!builder.ready || builder.saving} onClick={() => void builder.save()}>
                        {builder.saving ? text.saving : text.save}
                    </ToolbarButton>
                </Toolbar>
                <Button appearance="primary" disabled={!builder.ready || builder.saving} onClick={() => void saveAndQuit()}>
                    {text.saveAndQuit}
                </Button>
            </div>

            {builder.loadError && (
                <MessageBar intent="error">
                    <MessageBarBody>{text.loadError}</MessageBarBody>
                    <MessageBarActions>
                        <Button onClick={() => window.location.reload()}>{text.common.retry}</Button>
                    </MessageBarActions>
                </MessageBar>
            )}
            {feedback && (
                <MessageBar intent={feedback.intent}>
                    <MessageBarBody>
                        {feedback.intent === 'error' && <MessageBarTitle>{text.validationTitle}</MessageBarTitle>}
                        {feedback.message}
                        {feedback.errors.length > 0 && (
                            <ul className={styles.errorList}>
                                {feedback.errors.map((error, index) => (
                                    <li key={index}>
                                        {error.line !== null && `${format(text.errorLine, error.line)} — `}
                                        {error.tag_name && `<${error.tag_name}> `}
                                        {error.message}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </MessageBarBody>
                    <MessageBarActions
                        containerAction={
                            <Button aria-label={text.common.close} appearance="transparent" icon={<DismissRegular />} onClick={builder.dismissFeedback} />
                        }
                    />
                </MessageBar>
            )}

            <div className={mergeClasses('pj-gjs', styles.workspace)}>
                <aside className={styles.side} aria-label={text.tabs.content}>
                    {/* WP-61 adds the "Style" tab (global_styles). */}
                    <TabList selectedValue={leftTab} onTabSelect={(_, data) => setLeftTab(data.value as LeftTab)}>
                        <Tab value="content">{text.tabs.content}</Tab>
                    </TabList>
                    <BlockPalette
                        blocks={builder.blocks}
                        disabled={!builder.ready}
                        onDragStart={builder.startBlockDrag}
                        onDragEnd={builder.endBlockDrag}
                    />
                </aside>

                <div className={styles.canvas} aria-label={text.canvas}>
                    <div ref={builder.refs.canvasRef} className={styles.canvasHost} />
                    {!builder.ready && !builder.loadError && (
                        <div className={styles.overlay}>
                            <Spinner label={text.common.loading} />
                        </div>
                    )}
                </div>

                <aside className={mergeClasses(styles.side, styles.sideRight)} aria-label={text.properties}>
                    <TabList size="small" selectedValue={rightTab} onTabSelect={(_, data) => setRightTab(data.value as RightTab)}>
                        <Tab value="properties">{text.properties}</Tab>
                        <Tab value="advanced">{text.advancedStyle}</Tab>
                        <Tab value="layers">{text.layers}</Tab>
                    </TabList>
                    <div className={mergeClasses(styles.sideBody, rightTab !== 'properties' && styles.hidden)}>
                        <PropertiesPanel selection={builder.selection} onChange={builder.setAttributes} />
                    </div>
                    <div ref={builder.refs.styleManagerRef} className={mergeClasses(styles.sideBody, rightTab !== 'advanced' && styles.hidden)} />
                    <div ref={builder.refs.layerManagerRef} className={mergeClasses(styles.sideBody, rightTab !== 'layers' && styles.hidden)} />
                </aside>
            </div>

            <PreviewDialog open={previewOpen} html={previewHtml} onClose={() => setPreviewOpen(false)} onSendTest={sendTest} />

            <Dialog open={confirm !== null} onOpenChange={(_, data) => !data.open && setConfirm(null)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>{confirm === 'restore' ? text.restoreTitle : text.leaveTitle}</DialogTitle>
                        <DialogContent>
                            {confirm === 'restore' && restore ? format(text.restoreConfirm, restore.versionNumber) : text.leaveConfirm}
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setConfirm(null)}>
                                {text.common.cancel}
                            </Button>
                            <Button
                                appearance="primary"
                                onClick={() => {
                                    const action = confirm;
                                    setConfirm(null);
                                    if (action === 'restore') {
                                        builder.restoreSaved();
                                    } else {
                                        onExit();
                                    }
                                }}
                            >
                                {confirm === 'restore' ? text.common.confirm : text.leave}
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </div>
    );
}
