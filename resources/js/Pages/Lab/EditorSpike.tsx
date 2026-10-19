/**
 * WP-60S — GrapesJS + grapesjs-mjml spike inside the Fluent shell.
 * Isolated Lab page (GET /lab/editor-spike, not in the nav); deleted after
 * WP-60 lands. Findings: docs/46-editor-spike.md.
 *
 * The page itself stays light: GrapesJS, grapesjs-mjml and their CSS live in
 * `./editorSpike/engine.ts` and are loaded with a dynamic import() so Vite
 * emits them as a lazy chunk. Labels are hardcoded in French because fr.ts is
 * frozen for this package and this is not a product page.
 */
import { useCallback, useEffect, useMemo, useRef, useState, type DragEvent as ReactDragEvent } from 'react';
import { Head } from '@inertiajs/react';
import {
    Badge,
    Button,
    Dialog,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogSurface,
    DialogTitle,
    Field,
    Input,
    MessageBar,
    MessageBarBody,
    MessageBarTitle,
    Select,
    Spinner,
    Tab,
    TabList,
    Text,
    Textarea,
    Title3,
    Toolbar,
    ToolbarButton,
    ToolbarDivider,
    ToolbarRadioButton,
    ToolbarRadioGroup,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import {
    ArrowImportRegular,
    ArrowRedoRegular,
    ArrowResetRegular,
    ArrowUndoRegular,
    BookmarkAddRegular,
    DesktopRegular,
    FolderOpenRegular,
    PhoneRegular,
    PlayRegular,
    ReOrderDotsVerticalRegular,
    SaveRegular,
} from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import type { EmailDesign } from '@/Lib/types/editor';
import { DEFAULT_GLOBAL_STYLES, buildDesign, byteSize, loadLocal, sampleMjml, saveLocal } from './editorSpike/design';
import { FIELDS_BY_TYPE, type PropertyFieldDef } from './editorSpike/properties';
import type { SpikeBlockItem, SpikeDevice, SpikeEditorHandle, SpikeSelection } from './editorSpike/engine';

type LeftTab = 'Blocs' | 'Sections' | 'Enregistrés';
type RightTab = 'props' | 'style' | 'layers';
type OutputTab = 'mjml' | 'html' | 'design';

interface SaveReport {
    design: EmailDesign;
    html: string;
    errors: number;
    compileMs: number;
    sizes: { mjml: number; project: number; html: number; design: number };
}

const useStyles = makeStyles({
    page: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalM },
    header: { display: 'flex', alignItems: 'center', gap: tokens.spacingHorizontalM, flexWrap: 'wrap' },
    toolbar: {
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        boxShadow: tokens.shadow2,
        flexWrap: 'wrap',
    },
    workspace: {
        display: 'grid',
        gridTemplateColumns: '240px minmax(0, 1fr) 300px',
        height: 'calc(100vh - 260px)',
        minHeight: '520px',
        borderRadius: tokens.borderRadiusMedium,
        overflow: 'hidden',
        boxShadow: tokens.shadow4,
        backgroundColor: tokens.colorNeutralBackground1,
    },
    side: {
        display: 'flex',
        flexDirection: 'column',
        minHeight: 0,
        borderRight: `1px solid ${tokens.colorNeutralStroke2}`,
        backgroundColor: tokens.colorNeutralBackground1,
    },
    sideRight: { borderRight: 'none', borderLeft: `1px solid ${tokens.colorNeutralStroke2}` },
    sideBody: { flex: '1 1 auto', overflowY: 'auto', padding: tokens.spacingVerticalS },
    canvas: { position: 'relative', minWidth: 0, minHeight: 0 },
    canvasHost: { position: 'absolute', inset: 0 },
    loading: { position: 'absolute', inset: 0, display: 'flex', alignItems: 'center', justifyContent: 'center' },
    blockGrid: { display: 'grid', gridTemplateColumns: '1fr 1fr', gap: tokens.spacingHorizontalS },
    block: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: tokens.spacingVerticalXS,
        padding: tokens.spacingVerticalS,
        border: `1px solid ${tokens.colorNeutralStroke1}`,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorNeutralBackground1,
        cursor: 'grab',
        textAlign: 'center',
        userSelect: 'none',
        ':hover': { backgroundColor: tokens.colorNeutralBackground1Hover, border: `1px solid ${tokens.colorBrandStroke1}` },
    },
    hidden: { display: 'none' },
    props: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS },
    output: { display: 'flex', flexDirection: 'column', gap: tokens.spacingVerticalS },
    code: { fontFamily: tokens.fontFamilyMonospace, fontSize: tokens.fontSizeBase200 },
    stats: { display: 'flex', gap: tokens.spacingHorizontalS, flexWrap: 'wrap' },
});

const SUPPORT_LABEL: Record<SpikeBlockItem['support'], string> = {
    native: 'natif',
    composed: 'composé',
    custom: 'à créer',
};

function PropertyField({ def, value, onCommit }: { def: PropertyFieldDef; value: string; onCommit: (v: string) => void }) {
    const [draft, setDraft] = useState(value);
    useEffect(() => setDraft(value), [value]);

    if (def.kind === 'select') {
        return (
            <Field label={def.label} size="small">
                <Select size="small" value={value} onChange={(_, d) => onCommit(d.value)}>
                    <option value="">(défaut)</option>
                    {def.options?.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </Select>
            </Field>
        );
    }

    return (
        <Field label={def.label} size="small">
            <Input
                size="small"
                value={draft}
                placeholder={def.placeholder}
                contentBefore={
                    def.kind === 'color' && draft ? (
                        <span style={{ width: 12, height: 12, borderRadius: 2, background: draft, border: `1px solid ${tokens.colorNeutralStroke1}` }} />
                    ) : undefined
                }
                onChange={(_, d) => setDraft(d.value)}
                onBlur={() => draft !== value && onCommit(draft)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter') {
                        onCommit(draft);
                    }
                }}
            />
        </Field>
    );
}

export default function EditorSpike() {
    const styles = useStyles();
    const canvasRef = useRef<HTMLDivElement>(null);
    const styleRef = useRef<HTMLDivElement>(null);
    const layerRef = useRef<HTMLDivElement>(null);
    const handleRef = useRef<SpikeEditorHandle | null>(null);

    const [ready, setReady] = useState(false);
    const [loadError, setLoadError] = useState<string | null>(null);
    const [loadMs, setLoadMs] = useState<number | null>(null);
    const [version, setVersion] = useState('');
    const [blocks, setBlocks] = useState<SpikeBlockItem[]>([]);
    const [selection, setSelection] = useState<SpikeSelection | null>(null);
    const [device, setDevice] = useState<SpikeDevice>('desktop');
    const [leftTab, setLeftTab] = useState<LeftTab>('Blocs');
    const [rightTab, setRightTab] = useState<RightTab>('props');
    const [outputTab, setOutputTab] = useState<OutputTab>('design');
    const [report, setReport] = useState<SaveReport | null>(null);
    const [notice, setNotice] = useState<string | null>(null);
    const [importOpen, setImportOpen] = useState(false);
    const [importText, setImportText] = useState('');

    useEffect(() => {
        let cancelled = false;
        const started = performance.now();

        import('./editorSpike/engine')
            .then(({ mountSpikeEditor }) => {
                if (cancelled || !canvasRef.current || !styleRef.current || !layerRef.current) {
                    return;
                }
                const saved = loadLocal();
                const handle = mountSpikeEditor({
                    container: canvasRef.current,
                    styleManagerEl: styleRef.current,
                    layerManagerEl: layerRef.current,
                    project: saved?.project,
                    mjml: saved ? undefined : sampleMjml(DEFAULT_GLOBAL_STYLES),
                    globalStyles: saved?.global_styles ?? DEFAULT_GLOBAL_STYLES,
                });
                handleRef.current = handle;
                handle.onSelect(setSelection);
                handle.onChange(() => setBlocks(handle.blocks()));
                setBlocks(handle.blocks());
                setVersion(handle.version);
                setLoadMs(Math.round(performance.now() - started));
                setNotice(saved ? 'Sauvegarde locale rechargée depuis le JSON du projet.' : 'Modèle MJML d’exemple chargé.');
                setReady(true);
            })
            .catch((error: unknown) => {
                if (!cancelled) {
                    setLoadError(error instanceof Error ? error.message : String(error));
                }
            });

        return () => {
            cancelled = true;
            handleRef.current?.destroy();
            handleRef.current = null;
        };
    }, []);

    const save = useCallback(async () => {
        const handle = handleRef.current;
        if (!handle) {
            return;
        }
        const mjml = handle.getMjml();
        const project = handle.getProject();
        // Standalone mjml-browser chunk (what WP-60 uses outside the editor, e.g. preview).
        const { compileMjml } = await import('./editorSpike/compile');
        const compiled = compileMjml(mjml);
        const design = buildDesign(mjml, project, DEFAULT_GLOBAL_STYLES);
        saveLocal(design);
        setReport({
            design,
            html: compiled.html,
            errors: compiled.errors.length,
            compileMs: compiled.ms,
            sizes: {
                mjml: byteSize(mjml),
                project: byteSize(JSON.stringify(project)),
                html: byteSize(compiled.html),
                design: byteSize(JSON.stringify(design)),
            },
        });
        setNotice(
            compiled.errors.length
                ? `Enregistré avec ${compiled.errors.length} erreur(s) MJML : ${compiled.errors.map((e) => e.message).join(' ; ')}`
                : 'Enregistré (design_json + HTML compilé dans le navigateur).',
        );
    }, []);

    const compileInEditor = useCallback(() => {
        const handle = handleRef.current;
        if (!handle) {
            return;
        }
        const started = performance.now();
        const result = handle.compileInEditor();
        setNotice(
            `Compilation via le parseur intégré à grapesjs-mjml : ${byteSize(result.html)} octets, ${result.errors.length} erreur(s), ${Math.round(performance.now() - started)} ms.`,
        );
    }, []);

    const reloadSaved = useCallback(() => {
        const saved = loadLocal();
        if (saved && handleRef.current) {
            handleRef.current.loadProject(saved.project);
            setNotice('Projet rechargé depuis design_json.project.');
        } else {
            setNotice('Aucune sauvegarde locale.');
        }
    }, []);

    const resetSample = useCallback(() => {
        handleRef.current?.loadMjml(sampleMjml(DEFAULT_GLOBAL_STYLES));
        setNotice('Modèle MJML d’exemple rechargé.');
    }, []);

    const saveSection = useCallback(() => {
        const result = handleRef.current?.saveSelectedSection(`Section ${new Date().toLocaleTimeString('fr-FR')}`);
        if (result && handleRef.current) {
            setBlocks(handleRef.current.blocks());
            setLeftTab('Enregistrés');
            setNotice(`Section enregistrée (${byteSize(result.mjml)} octets de MJML).`);
        } else {
            setNotice('Sélectionnez un élément dans une section pour l’enregistrer.');
        }
    }, []);

    const changeDevice = useCallback((next: SpikeDevice) => {
        setDevice(next);
        handleRef.current?.setDevice(next);
    }, []);

    const visibleBlocks = useMemo(() => blocks.filter((b) => b.category === leftTab), [blocks, leftTab]);
    const fields = selection ? FIELDS_BY_TYPE[selection.type] ?? [] : [];

    const onBlockDragStart = (id: string) => (e: ReactDragEvent<HTMLDivElement>) => {
        e.dataTransfer.effectAllowed = 'copy';
        handleRef.current?.startBlockDrag(id, e.nativeEvent);
    };

    return (
        <AppShell>
            <Head title="Lab — éditeur (spike)" />
            <div className={styles.page}>
                <div className={styles.header}>
                    <Title3>Lab — éditeur glisser-déposer (spike WP-60S)</Title3>
                    <Badge appearance="tint" color="warning">
                        Page de test, hors navigation
                    </Badge>
                    {version && <Text size={200}>GrapesJS {version}</Text>}
                    {loadMs !== null && <Text size={200}>Chargement du module : {loadMs} ms</Text>}
                </div>

                <Toolbar
                    aria-label="Barre d’outils de l’éditeur"
                    className={styles.toolbar}
                    checkedValues={{ device: [device] }}
                    onCheckedValueChange={(_, d) => d.name === 'device' && changeDevice(d.checkedItems[0] as SpikeDevice)}
                >
                    <ToolbarButton icon={<ArrowUndoRegular />} disabled={!ready} onClick={() => handleRef.current?.undo()}>
                        Annuler
                    </ToolbarButton>
                    <ToolbarButton icon={<ArrowRedoRegular />} disabled={!ready} onClick={() => handleRef.current?.redo()}>
                        Rétablir
                    </ToolbarButton>
                    <ToolbarDivider />
                    <ToolbarRadioGroup>
                        <ToolbarRadioButton name="device" value="desktop" icon={<DesktopRegular />} disabled={!ready} appearance="subtle">
                            Bureau
                        </ToolbarRadioButton>
                        <ToolbarRadioButton name="device" value="mobile" icon={<PhoneRegular />} disabled={!ready} appearance="subtle">
                            Mobile
                        </ToolbarRadioButton>
                    </ToolbarRadioGroup>
                    <ToolbarDivider />
                    <ToolbarButton icon={<BookmarkAddRegular />} disabled={!ready} onClick={saveSection}>
                        Enregistrer la section
                    </ToolbarButton>
                    <ToolbarButton icon={<ArrowImportRegular />} disabled={!ready} onClick={() => setImportOpen(true)}>
                        Importer du MJML
                    </ToolbarButton>
                    <ToolbarButton icon={<FolderOpenRegular />} disabled={!ready} onClick={reloadSaved}>
                        Recharger la sauvegarde
                    </ToolbarButton>
                    <ToolbarButton icon={<ArrowResetRegular />} disabled={!ready} onClick={resetSample}>
                        Modèle d’exemple
                    </ToolbarButton>
                    <ToolbarButton icon={<PlayRegular />} disabled={!ready} onClick={compileInEditor}>
                        Compiler (grapesjs-mjml)
                    </ToolbarButton>
                    <ToolbarButton appearance="primary" icon={<SaveRegular />} disabled={!ready} onClick={() => void save()}>
                        Enregistrer
                    </ToolbarButton>
                </Toolbar>

                {loadError && (
                    <MessageBar intent="error">
                        <MessageBarBody>
                            <MessageBarTitle>Échec du chargement de l’éditeur</MessageBarTitle>
                            {loadError}
                        </MessageBarBody>
                    </MessageBar>
                )}
                {notice && (
                    <MessageBar intent="info">
                        <MessageBarBody>{notice}</MessageBarBody>
                    </MessageBar>
                )}

                <div className={mergeClasses('pj-gjs', styles.workspace)}>
                    <aside className={styles.side} aria-label="Contenu">
                        <TabList size="small" selectedValue={leftTab} onTabSelect={(_, d) => setLeftTab(d.value as LeftTab)}>
                            <Tab value="Blocs">Blocs</Tab>
                            <Tab value="Sections">Sections</Tab>
                            <Tab value="Enregistrés">Enregistrés</Tab>
                        </TabList>
                        <div className={styles.sideBody}>
                            {visibleBlocks.length === 0 && <Text size={200}>Aucun élément.</Text>}
                            <div className={styles.blockGrid}>
                                {visibleBlocks.map((block) => (
                                    <div
                                        key={block.id}
                                        className={styles.block}
                                        draggable
                                        onDragStart={onBlockDragStart(block.id)}
                                        onDragEnd={() => handleRef.current?.endBlockDrag()}
                                        title={`Bloc ${SUPPORT_LABEL[block.support]}`}
                                    >
                                        <ReOrderDotsVerticalRegular />
                                        <Text size={200}>{block.label}</Text>
                                        <Badge size="small" appearance="outline" color={block.support === 'native' ? 'informative' : 'brand'}>
                                            {SUPPORT_LABEL[block.support]}
                                        </Badge>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </aside>

                    <div className={styles.canvas}>
                        <div ref={canvasRef} className={styles.canvasHost} />
                        {!ready && !loadError && (
                            <div className={styles.loading}>
                                <Spinner label="Chargement de l’éditeur…" />
                            </div>
                        )}
                    </div>

                    <aside className={mergeClasses(styles.side, styles.sideRight)} aria-label="Propriétés">
                        <TabList size="small" selectedValue={rightTab} onTabSelect={(_, d) => setRightTab(d.value as RightTab)}>
                            <Tab value="props">Propriétés</Tab>
                            <Tab value="style">Style avancé</Tab>
                            <Tab value="layers">Calques</Tab>
                        </TabList>
                        <div className={mergeClasses(styles.sideBody, rightTab !== 'props' && styles.hidden)}>
                            {!selection && <Text size={200}>Sélectionnez un bloc dans le canevas.</Text>}
                            {selection && (
                                <div className={styles.props}>
                                    <Text weight="semibold">{selection.name}</Text>
                                    <Text size={200} className={styles.code}>
                                        {selection.type}
                                    </Text>
                                    {fields.length === 0 && <Text size={200}>Pas de propriété rapide pour ce composant ; voir « Style avancé ».</Text>}
                                    {fields.map((def) => (
                                        <PropertyField
                                            key={`${selection.id}-${def.name}`}
                                            def={def}
                                            value={selection.attributes[def.name] ?? ''}
                                            onCommit={(v) => handleRef.current?.setAttribute(def.name, v)}
                                        />
                                    ))}
                                </div>
                            )}
                        </div>
                        <div ref={styleRef} className={mergeClasses(styles.sideBody, rightTab !== 'style' && styles.hidden)} />
                        <div ref={layerRef} className={mergeClasses(styles.sideBody, rightTab !== 'layers' && styles.hidden)} />
                    </aside>
                </div>

                {report && (
                    <div className={styles.output}>
                        <div className={styles.stats}>
                            <Badge appearance="tint">MJML : {report.sizes.mjml} o</Badge>
                            <Badge appearance="tint">Projet GrapesJS : {report.sizes.project} o</Badge>
                            <Badge appearance="tint">design_json : {report.sizes.design} o</Badge>
                            <Badge appearance="tint">HTML : {report.sizes.html} o</Badge>
                            <Badge appearance="tint" color={report.errors ? 'danger' : 'success'}>
                                Erreurs MJML : {report.errors}
                            </Badge>
                            <Badge appearance="tint">Compilation mjml-browser : {report.compileMs} ms</Badge>
                        </div>
                        <TabList size="small" selectedValue={outputTab} onTabSelect={(_, d) => setOutputTab(d.value as OutputTab)}>
                            <Tab value="design">design_json</Tab>
                            <Tab value="mjml">MJML</Tab>
                            <Tab value="html">HTML compilé</Tab>
                        </TabList>
                        <Textarea
                            readOnly
                            resize="vertical"
                            textarea={{ className: styles.code, rows: 14 }}
                            value={
                                outputTab === 'design'
                                    ? JSON.stringify(report.design, null, 2)
                                    : outputTab === 'mjml'
                                      ? report.design.mjml
                                      : report.html
                            }
                        />
                    </div>
                )}
            </div>

            <Dialog open={importOpen} onOpenChange={(_, d) => setImportOpen(d.open)}>
                <DialogSurface>
                    <DialogBody>
                        <DialogTitle>Importer du MJML</DialogTitle>
                        <DialogContent>
                            <Textarea
                                textarea={{ className: styles.code, rows: 12 }}
                                value={importText}
                                placeholder="<mjml><mj-body>…</mj-body></mjml>"
                                onChange={(_, d) => setImportText(d.value)}
                                style={{ width: '100%' }}
                            />
                        </DialogContent>
                        <DialogActions>
                            <Button appearance="secondary" onClick={() => setImportOpen(false)}>
                                Annuler
                            </Button>
                            <Button
                                appearance="primary"
                                disabled={!importText.trim()}
                                onClick={() => {
                                    handleRef.current?.loadMjml(importText);
                                    setImportOpen(false);
                                    setNotice('MJML importé.');
                                }}
                            >
                                Importer
                            </Button>
                        </DialogActions>
                    </DialogBody>
                </DialogSurface>
            </Dialog>
        </AppShell>
    );
}
