/**
 * WP-60 — GrapesJS + grapesjs-mjml engine (docs/46-editor-spike.md §5, §7).
 *
 * Only reached through the lazy `EmailBuilder.tsx` chunk: never import this
 * file (or anything under engine/ that imports `grapesjs`) from a page.
 */
import grapesjs, { type Component, type Editor, type ProjectData, type Sector } from 'grapesjs';
import grapesjsMjml from 'grapesjs-mjml';
import grapesFr from 'grapesjs/locale/fr.mjs';
import 'grapesjs/dist/css/grapes.min.css';
import './fluent-gjs.css';
import type { EmailBlockType, EmailGlobalStyles } from '@/Lib/types/editor';
import type { MergeTag } from '@/Lib/types/attributes';
import { text } from '../strings';
import { BLOCK_PROP, registerBlocks, type EmailBlockDefinition } from './blocks';
import { composeMjml, expandSelfClosingMjml, splitMjml, type ResolvedSource } from './globalStyles';
import { registerMergeTagAction } from './mergeTags';

export type BuilderDevice = 'desktop' | 'mobile';

export interface BuilderSelection {
    id: string;
    type: string;
    name: string;
    block: EmailBlockType | null;
    attributes: Record<string, string>;
}

export interface ClientCompileResult {
    html: string;
    errors: { line: number | null; message: string; tagName: string | null }[];
}

export interface EngineHandle {
    readonly editor: Editor;
    blocks(): EmailBlockDefinition[];
    /** Full MJML: editor body + `<mj-head>` generated from the global styles. */
    getMjml(): string;
    getProject(): Record<string, unknown>;
    getGlobalStyles(): EmailGlobalStyles;
    /** Compile with the parser bundled in grapesjs-mjml (preview only; the server re-compiles). */
    compile(mjml?: string): ClientCompileResult;
    load(source: ResolvedSource): void;
    setDevice(device: BuilderDevice): void;
    undo(): void;
    redo(): void;
    hasUndo(): boolean;
    hasRedo(): boolean;
    /** Unsaved changes since the last load / save. */
    isDirty(): boolean;
    markSaved(): void;
    startBlockDrag(id: string, ev: DragEvent): void;
    endBlockDrag(cancel?: boolean): void;
    setAttributes(values: Record<string, string>): void;
    onSelect(cb: (selection: BuilderSelection | null) => void): () => void;
    onChange(cb: () => void): () => void;
    destroy(): void;
}

export interface CreateEditorOptions {
    container: HTMLElement;
    styleManagerEl: HTMLElement;
    layerManagerEl: HTMLElement;
    source: ResolvedSource;
    mergeTags: MergeTag[];
    /** Extension point for WP-61 (product, dynamic content, sections). */
    extraBlocks?: EmailBlockDefinition[];
}

/** Selection/hover colours inside the canvas iframe (CSS variables do not cross the frame). */
const CANVAS_CSS = `
.gjs-selected { outline: 2px solid #E6BF00 !important; outline-offset: -2px; }
.gjs-hovered { outline: 1px dashed #A68200 !important; outline-offset: -1px; }
`;

type ComponentTypeDefaults = { defaults?: Record<string, unknown> };

/**
 * "Style" tab → canvas (docs/46 §5): the canvas renders without `<mj-head>`,
 * so global styles are written into each type's `style-default` before
 * components load. grapesjs-mjml strips attributes equal to `style-default`
 * on export, so the MJML stays clean and the generated head applies them.
 */
export function applyGlobalStyleDefaults(editor: Editor, gs: EmailGlobalStyles): void {
    const patch = (type: string, values: Record<string, string>) => {
        const def = editor.Components.getType(type);
        if (!def) {
            return;
        }
        const proto = def.model.prototype as ComponentTypeDefaults;
        const current = (proto.defaults?.['style-default'] ?? {}) as Record<string, string>;
        editor.Components.addType(type, { model: { defaults: { 'style-default': { ...current, ...values } } } });
    };

    patch('mj-text', { color: gs.text_color, 'font-family': gs.font_family });
    patch('mj-button', {
        'background-color': gs.button.background_color,
        color: gs.button.text_color,
        'border-radius': `${gs.button.border_radius}px`,
        'font-weight': gs.button.font_weight,
        'font-family': gs.font_family,
    });
    patch('mj-section', { 'background-color': gs.content_background_color });
}

function toSelection(cmp: Component | undefined): BuilderSelection | null {
    if (!cmp) {
        return null;
    }
    const attributes: Record<string, string> = {};
    for (const [key, value] of Object.entries(cmp.getAttributes())) {
        if (key !== 'id' && key !== 'style' && value !== undefined && value !== null) {
            attributes[key] = String(value);
        }
    }
    const block = cmp.get(BLOCK_PROP) as EmailBlockType | undefined;

    return { id: cmp.getId(), type: cmp.get('type') ?? '', name: cmp.getName(), block: block ?? null, attributes };
}

export function createEditor(options: CreateEditorOptions): EngineHandle {
    let globalStyles = options.source.globalStyles;

    const editor = grapesjs.init({
        container: options.container,
        height: '100%',
        width: 'auto',
        fromElement: false,
        // Save is explicit: PUT /campaigns/{id}/design | PATCH /templates/{id}.
        storageManager: false,
        // Fluent chrome lives in React; GrapesJS renders only canvas + managers.
        panels: { defaults: [] },
        blockManager: { custom: true },
        styleManager: { appendTo: options.styleManagerEl },
        layerManager: { appendTo: options.layerManagerEl },
        selectorManager: { componentFirst: true },
        canvasCss: CANVAS_CSS,
        deviceManager: {
            default: 'desktop',
            devices: [
                { id: 'desktop', name: text.desktop, width: '' },
                { id: 'mobile', name: text.mobile, width: '375px', widthMedia: '480px' },
            ],
        },
        i18n: { locale: 'fr', localeFallback: 'fr', detectLocale: false, messages: { fr: grapesFr } },
        plugins: [
            (ed: Editor) =>
                grapesjsMjml(ed, {
                    blocks: [],
                    resetBlocks: true,
                    resetDevices: false,
                    useCustomTheme: false,
                    hideSelector: true,
                    i18n: { fr: { 'grapesjs-mjml': { components: { names: { ...text.componentNames } } } } },
                }),
        ],
    });

    const loadSource = (source: ResolvedSource) => {
        globalStyles = source.globalStyles;
        // Global styles must be component defaults *before* components are created.
        applyGlobalStyleDefaults(editor, globalStyles);
        if (source.kind === 'project') {
            editor.loadProjectData(source.project as ProjectData);
        } else {
            editor.setComponents(expandSelfClosingMjml(splitMjml(source.mjml).body));
        }
        editor.UndoManager.clear();
        editor.clearDirtyCount();
    };

    loadSource(options.source);
    const registry = registerBlocks(editor, options.extraBlocks);
    registerMergeTagAction(editor, options.mergeTags);

    editor.onReady(() => {
        const sectors = text.sectors as Record<string, string>;
        editor.StyleManager.getSectors().forEach((sector: Sector) => {
            const label = sectors[sector.getName()];
            if (label) {
                sector.set('name', label);
            }
        });
    });

    const handle: EngineHandle = {
        editor,
        blocks() {
            return Array.from(registry.values());
        },
        getMjml() {
            return composeMjml(String(editor.runCommand('mjml-code') ?? editor.getHtml()), globalStyles);
        },
        getProject() {
            return editor.getProjectData() as Record<string, unknown>;
        },
        getGlobalStyles() {
            return globalStyles;
        },
        compile(mjml) {
            const result = editor.runCommand('mjml-code-to-html', { mjml: mjml ?? handle.getMjml() }) as
                | { html: string; errors?: { line?: number; message: string; tagName?: string }[] }
                | undefined;

            return {
                html: result?.html ?? '',
                errors: (result?.errors ?? []).map((e) => ({ line: e.line ?? null, message: e.message, tagName: e.tagName ?? null })),
            };
        },
        load(source) {
            editor.select(undefined);
            loadSource(source);
        },
        setDevice(device) {
            editor.setDevice(device);
        },
        undo() {
            editor.UndoManager.undo();
        },
        redo() {
            editor.UndoManager.redo();
        },
        hasUndo() {
            return editor.UndoManager.hasUndo();
        },
        hasRedo() {
            return editor.UndoManager.hasRedo();
        },
        isDirty() {
            return editor.getDirtyCount() > 0;
        },
        markSaved() {
            editor.clearDirtyCount();
        },
        startBlockDrag(id, ev) {
            const block = editor.Blocks.get(id);
            if (block) {
                editor.Blocks.startDrag(block, ev);
            }
        },
        endBlockDrag(cancel) {
            editor.Blocks.endDrag(cancel);
        },
        setAttributes(values) {
            const cmp = editor.getSelected();
            if (!cmp) {
                return;
            }
            const remove = Object.keys(values).filter((name) => values[name] === '');
            const add = Object.fromEntries(Object.entries(values).filter(([, value]) => value !== ''));
            if (remove.length > 0) {
                cmp.removeAttributes(remove);
            }
            if (Object.keys(add).length > 0) {
                cmp.addAttributes(add);
            }
        },
        onSelect(cb) {
            const listener = () => cb(toSelection(editor.getSelected()));
            editor.on('component:toggled', listener);
            editor.on('component:update', listener);

            return () => {
                editor.off('component:toggled', listener);
                editor.off('component:update', listener);
            };
        },
        onChange(cb) {
            editor.on('update', cb);
            editor.on('undo', cb);
            editor.on('redo', cb);

            return () => {
                editor.off('update', cb);
                editor.off('undo', cb);
                editor.off('redo', cb);
            };
        },
        destroy() {
            editor.destroy();
        },
    };

    return handle;
}
