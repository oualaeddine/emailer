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
import type { RuleTree } from '@/Lib/types/segments';
import type { DynamicBlockRecord } from '../types';
import { text } from '../strings';
import { BLOCK_PROP, registerBlocks, tagInsertedBlock, type EmailBlockDefinition } from './blocks';
import {
    DYNAMIC_PROP,
    closestDynamic,
    collectDynamicBlocks,
    dynamicConfigOf,
    liftDynamicMarkers,
    registerDynamicContent,
    setPendingRules,
    walkComponents,
} from './dynamicContent';
import { composeMjml, expandSelfClosingMjml, splitMjml, type ResolvedSource } from './globalStyles';
import { registerMergeTagAction } from './mergeTags';

export type BuilderDevice = 'desktop' | 'mobile';

export interface BuilderSelection {
    id: string;
    type: string;
    name: string;
    block: EmailBlockType | null;
    attributes: Record<string, string>;
    /** A whole section (`mj-section` / `mj-wrapper`): can be saved under *Enregistrés*. */
    isSection: boolean;
    /** Dynamic-content section containing the selection (or the selection itself). */
    dynamic: { id: string; condition: RuleTree | null } | null;
}

/** Whole section of the selection, as saved by `POST /saved-sections`. */
export interface SectionSnapshot {
    mjml: string;
    component: Record<string, unknown>;
}

/** Component types that are whole sections (Brevo: only whole sections can be saved). */
export const SECTION_TYPES = ['mj-section', 'mj-wrapper'];

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
    /** *Style* tab: new global styles (component defaults, existing components, body, canvas). */
    setGlobalStyles(next: EmailGlobalStyles): void;
    /** `design_json.dynamic_blocks` — call before `getMjml()` (renews duplicate ids). */
    getDynamicBlocks(): DynamicBlockRecord[];
    setDynamicCondition(dynamicId: string, condition: RuleTree | null): void;
    /** Turns a dynamic section back into a plain wrapper (always shown). */
    clearDynamic(dynamicId: string): void;
    /** The selected whole section, or null when the selection is not a section. */
    getSelectedSection(): SectionSnapshot | null;
    addBlocks(blocks: EmailBlockDefinition[]): EmailBlockDefinition[];
    removeBlock(id: string): EmailBlockDefinition[];
    /** Inserts a block / section after the selected section, or at the end of the body. */
    insertBlock(id: string): void;
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
    /** A dynamic-content block was dropped: the host opens its rule dialog. */
    onDynamicDrop?: (dynamicId: string) => void;
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

    for (const [type, values] of Object.entries(defaultsFor(gs))) {
        patch(type, values);
    }
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
    const type = String(cmp.get('type') ?? '');
    const dynamic = dynamicConfigOf(closestDynamic(cmp));

    return {
        id: cmp.getId(),
        type,
        name: cmp.getName(),
        block: block ?? (dynamicConfigOf(cmp) ? 'dynamic_content' : null),
        attributes,
        isSection: SECTION_TYPES.includes(type),
        dynamic,
    };
}

/** Values of `global_styles` written into each type's `style-default` (see `applyGlobalStyleDefaults`). */
function defaultsFor(gs: EmailGlobalStyles): Record<string, Record<string, string>> {
    return {
        'mj-text': { color: gs.text_color, 'font-family': gs.font_family },
        'mj-button': {
            'background-color': gs.button.background_color,
            color: gs.button.text_color,
            'border-radius': `${gs.button.border_radius}px`,
            'font-weight': gs.button.font_weight,
            'font-family': gs.font_family,
        },
        'mj-section': { 'background-color': gs.content_background_color },
    };
}

function canvasCss(gs: EmailGlobalStyles): string {
    const color = gs.link_color.replace(/[;{}<>]/g, '');

    return `a { color: ${color}; text-decoration: ${gs.link_underline ? 'underline' : 'none'}; }`;
}

function findBody(editor: Editor): Component | null {
    let body: Component | null = null;
    walkComponents(editor.getWrapper() ?? undefined, (cmp) => {
        if (!body && cmp.get('type') === 'mj-body') {
            body = cmp;
        }
    });

    return body;
}

export function createEditor(options: CreateEditorOptions): EngineHandle {
    let globalStyles = options.source.globalStyles;

    const editor = grapesjs.init({
        container: options.container,
        height: '100%',
        width: 'auto',
        fromElement: false,
        // Save is explicit: PUT /campaigns/{id}/design | PATCH /templates/{id}.
        // `false` keeps GrapesJS's autosave on, which clears the dirty counter after every change.
        storageManager: { type: 'none', autosave: false, autoload: false },
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

    // Dynamic-content wrappers (markers on export) must exist before any component is loaded.
    registerDynamicContent(editor);

    const applyCanvasCss = () => {
        const doc = editor.Canvas.getDocument();
        if (!doc?.head) {
            return;
        }
        let el = doc.getElementById('pj-global-styles');
        if (!el) {
            el = doc.createElement('style');
            el.id = 'pj-global-styles';
            doc.head.appendChild(el);
        }
        el.textContent = canvasCss(globalStyles);
    };

    const loadSource = (source: ResolvedSource) => {
        globalStyles = source.globalStyles;
        // Global styles must be component defaults *before* components are created.
        applyGlobalStyleDefaults(editor, globalStyles);
        setPendingRules(source.dynamicBlocks);
        if (source.kind === 'project') {
            editor.loadProjectData(source.project as ProjectData);
        } else {
            editor.setComponents(expandSelfClosingMjml(liftDynamicMarkers(splitMjml(source.mjml).body)));
        }
        setPendingRules([]);
        applyCanvasCss();
        editor.UndoManager.clear();
        editor.clearDirtyCount();
    };

    loadSource(options.source);
    const registry = registerBlocks(editor, options.extraBlocks, options.onDynamicDrop);
    registerMergeTagAction(editor, options.mergeTags);
    editor.on('canvas:frame:load', applyCanvasCss);

    const findDynamic = (dynamicId: string): Component | null => {
        let found: Component | null = null;
        walkComponents(editor.getWrapper() ?? undefined, (cmp) => {
            if (!found && dynamicConfigOf(cmp)?.id === dynamicId) {
                found = cmp;
            }
        });

        return found;
    };

    /**
     * Prop-only changes (dynamic rule, global styles) are not always counted
     * by GrapesJS: count one change. The counter is debounced (one per tick)
     * and fires the editor `update` event that refreshes the dirty state.
     */
    const markChanged = () => {
        const model = editor.getModel() as unknown as { changesUp?: (opts: Record<string, unknown>, data: Record<string, unknown>) => void };
        model.changesUp?.({}, {});
    };

    editor.onReady(() => {
        applyCanvasCss();
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
            return registry.list();
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
        setGlobalStyles(next) {
            const previous = defaultsFor(globalStyles);
            const target = defaultsFor(next);
            globalStyles = next;
            applyGlobalStyleDefaults(editor, next);

            // Existing components: move every value still equal to the old default (docs/46 §5).
            walkComponents(editor.getWrapper() ?? undefined, (cmp) => {
                const type = String(cmp.get('type') ?? '');
                const from = previous[type];
                const to = target[type];
                if (!from || !to) {
                    return;
                }
                const styleDefault = { ...((cmp.get('style-default') as Record<string, string> | undefined) ?? {}) };
                const attributes = cmp.getAttributes() as Record<string, string>;
                const patch: Record<string, string> = {};
                for (const name of Object.keys(to)) {
                    if (from[name] === to[name]) {
                        continue;
                    }
                    if (styleDefault[name] === undefined || styleDefault[name] === from[name]) {
                        styleDefault[name] = to[name];
                    }
                    if (attributes[name] === undefined || attributes[name] === from[name]) {
                        patch[name] = to[name];
                    }
                }
                cmp.set('style-default', styleDefault);
                if (Object.keys(patch).length > 0) {
                    cmp.addAttributes(patch);
                }
            });

            findBody(editor)?.addAttributes({ 'background-color': next.background_color, width: `${next.content_width}px` });
            applyCanvasCss();
            markChanged();
        },
        getDynamicBlocks() {
            return collectDynamicBlocks(editor);
        },
        setDynamicCondition(dynamicId, condition) {
            const cmp = findDynamic(dynamicId);
            if (!cmp) {
                return;
            }
            cmp.set(DYNAMIC_PROP, { id: dynamicId, condition });
            markChanged();
        },
        clearDynamic(dynamicId) {
            const cmp = findDynamic(dynamicId);
            if (!cmp) {
                return;
            }
            cmp.unset(DYNAMIC_PROP);
            if (cmp.get(BLOCK_PROP) === 'dynamic_content') {
                cmp.unset(BLOCK_PROP);
            }
            markChanged();
        },
        getSelectedSection() {
            const cmp = editor.getSelected();
            if (!cmp || !SECTION_TYPES.includes(String(cmp.get('type') ?? ''))) {
                return null;
            }

            return {
                mjml: cmp.toHTML(),
                component: JSON.parse(JSON.stringify(cmp.toJSON())) as Record<string, unknown>,
            };
        },
        addBlocks(blocks) {
            registry.add(blocks);

            return registry.list();
        },
        removeBlock(id) {
            registry.remove(id);

            return registry.list();
        },
        insertBlock(id) {
            const def = registry.list().find((block) => block.id === id);
            const body = findBody(editor);
            if (!def || !body) {
                return;
            }
            const content = typeof def.content === 'string' ? expandSelfClosingMjml(def.content) : def.content;
            const selected = editor.getSelected();
            const isSection = typeof content !== 'string' || /^\s*<(mj-section|mj-wrapper|mj-hero)\b/.test(content);
            const childOf = (parent: Component, cmp: Component | undefined | null): Component | null => {
                let current: Component | undefined | null = cmp;
                while (current && current.parent() !== parent) {
                    current = current.parent();
                }

                return current ?? null;
            };
            let added: Component[];
            let tagged: Component[];

            if (!isSection) {
                // Content block: into the selected column, else in a new one-column section.
                let column: Component | undefined | null = selected;
                while (column && column.get('type') !== 'mj-column') {
                    column = column.parent();
                }
                if (column) {
                    const anchor = childOf(column, selected);
                    added = column.append(content as never, anchor ? { at: anchor.index() + 1 } : {});
                    tagged = added;
                } else {
                    const anchor = childOf(body, selected);
                    added = body.append(`<mj-section><mj-column>${content}</mj-column></mj-section>` as never, anchor ? { at: anchor.index() + 1 } : {});
                    tagged = added.flatMap((section) => section.components().at(0)?.components().models ?? []);
                }
            } else {
                const anchor = childOf(body, selected);
                added = body.append(content as never, anchor ? { at: anchor.index() + 1 } : {});
                tagged = added;
            }
            tagged.forEach((cmp) => tagInsertedBlock(cmp, def, options.onDynamicDrop));
            if (tagged[0]) {
                editor.select(tagged[0]);
            }
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
