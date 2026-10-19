/**
 * WP-60S editor spike — the heavy module (GrapesJS + grapesjs-mjml + CSS).
 *
 * Only ever loaded through `import('./editorSpike/engine')` from the Lab page,
 * so Vite emits it (and its CSS) as a lazy chunk. It is a `.ts` file on
 * purpose: app.tsx eagerly globs `./Pages/**\/*.tsx`, so any `.tsx` helper
 * under Pages/ would land in the main chunk.
 */
import grapesjs, { type Block, type Component, type Editor, type ProjectData, type Sector } from 'grapesjs';
import grapesjsMjml from 'grapesjs-mjml';
import grapesFr from 'grapesjs/locale/fr.mjs';
import 'grapesjs/dist/css/grapes.min.css';
import './fluent-gjs.css';
import type { EmailGlobalStyles } from '@/Lib/types/editor';
import { composeMjml, expandSelfClosingMjml, splitMjml } from './design';
import { addSavedSectionBlock, registerSpikeBlocks, type BlockSupport, type SpikeBlock } from './blocks';

export type SpikeDevice = 'desktop' | 'mobile';

export interface SpikeSelection {
    id: string;
    type: string;
    name: string;
    attributes: Record<string, string>;
}

export interface SpikeBlockItem {
    id: string;
    label: string;
    category: string;
    support: BlockSupport;
}

export interface SpikeCompileError {
    line: number | null;
    message: string;
    tagName: string | null;
}

export interface SpikeEditorHandle {
    readonly editor: Editor;
    readonly version: string;
    blocks(): SpikeBlockItem[];
    /** Full MJML: editor body + <mj-head> generated from global styles. */
    getMjml(): string;
    getProject(): Record<string, unknown>;
    /** Compile with the parser bundled inside grapesjs-mjml (no extra chunk). */
    compileInEditor(): { html: string; errors: SpikeCompileError[] };
    loadMjml(mjml: string): void;
    loadProject(project: Record<string, unknown>): void;
    setDevice(device: SpikeDevice): void;
    undo(): void;
    redo(): void;
    startBlockDrag(id: string, ev: DragEvent): void;
    endBlockDrag(cancel?: boolean): void;
    setAttribute(name: string, value: string): void;
    /** Saves the section containing the selection as an "Enregistrés" block. */
    saveSelectedSection(name: string): { id: string; mjml: string; component: Record<string, unknown> } | null;
    onSelect(cb: (selection: SpikeSelection | null) => void): () => void;
    onChange(cb: () => void): () => void;
    destroy(): void;
}

export interface MountOptions {
    container: HTMLElement;
    styleManagerEl: HTMLElement;
    layerManagerEl: HTMLElement;
    mjml?: string;
    project?: Record<string, unknown>;
    globalStyles: EmailGlobalStyles;
}

/** Selection/hover colours inside the canvas iframe (CSS vars do not cross the frame). */
const CANVAS_CSS = `
.gjs-selected { outline: 2px solid #E6BF00 !important; outline-offset: -2px; }
.gjs-hovered { outline: 1px dashed #A68200 !important; outline-offset: -1px; }
`;

/** Personalisation menu in text blocks (doc 43 §43.4.4); syntax to align with MergeVariableResolver. */
export const SPIKE_MERGE_TAGS: [string, string][] = [
    ['{{ contact.first_name }}', 'Prénom'],
    ['{{ contact.last_name }}', 'Nom'],
    ['{{ contact.company_name }}', 'Entreprise'],
    ['{{ unsubscribe_url }}', 'Lien de désinscription'],
];

const SECTOR_LABELS: Record<string, string> = {
    Dimension: 'Dimensions',
    Typography: 'Typographie',
    Decorations: 'Décorations',
};

const MJML_NAMES_FR = {
    body: 'Corps',
    button: 'Bouton',
    column: 'Colonne',
    oneColumn: '1 colonne',
    twoColumn: '2 colonnes',
    threeColumn: '3 colonnes',
    divider: 'Séparateur',
    group: 'Groupe',
    hero: 'Bannière',
    image: 'Image',
    navBar: 'Barre de navigation',
    navLink: 'Lien de navigation',
    section: 'Section',
    socialGroup: 'Réseaux sociaux',
    socialElement: 'Réseau social',
    spacer: 'Espacement',
    text: 'Texte',
    wrapper: 'Conteneur',
    raw: 'HTML',
};

type ComponentTypeDefaults = { defaults?: Record<string, unknown> };

/**
 * "Style" tab → canvas: the canvas renders each component through
 * `<mjml><mj-body>…` without the head, so `mj-attributes` are invisible there.
 * Writing the same values into each type's `style-default` makes the canvas
 * match the export, and grapesjs-mjml strips attributes equal to
 * `style-default` on export so the MJML stays clean (head applies them).
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

function toSelection(cmp: Component | undefined): SpikeSelection | null {
    if (!cmp) {
        return null;
    }
    const attributes: Record<string, string> = {};
    for (const [key, value] of Object.entries(cmp.getAttributes())) {
        if (key !== 'id' && key !== 'style' && value !== undefined && value !== null) {
            attributes[key] = String(value);
        }
    }

    return { id: cmp.getId(), type: cmp.get('type') ?? '', name: cmp.getName(), attributes };
}

export function mountSpikeEditor(options: MountOptions): SpikeEditorHandle {
    const editor = grapesjs.init({
        container: options.container,
        height: '100%',
        width: 'auto',
        fromElement: false,
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
                { id: 'desktop', name: 'Bureau', width: '' },
                { id: 'mobile', name: 'Mobile', width: '375px', widthMedia: '480px' },
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
                    i18n: { fr: { 'grapesjs-mjml': { components: { names: MJML_NAMES_FR } } } },
                }),
        ],
    });

    // Global styles must be component defaults *before* components are created
    // (grapesjs-mjml copies `style-default` into attributes on init).
    applyGlobalStyleDefaults(editor, options.globalStyles);
    if (options.project) {
        editor.loadProjectData(options.project as ProjectData);
    } else {
        editor.setComponents(expandSelfClosingMjml(splitMjml(options.mjml ?? '').body));
    }
    editor.UndoManager.clear();

    const registry: Map<string, SpikeBlock> = registerSpikeBlocks(editor);

    const mergeOptions = SPIKE_MERGE_TAGS.map(([value, label]) => `<option value="${value}">${label}</option>`).join('');
    editor.RichTextEditor.add('pj-merge-tag', {
        icon: `<select class="gjs-field" title="Personnalisation"><option value="">Personnaliser…</option>${mergeOptions}</select>`,
        event: 'change',
        result: (rte, action) => {
            const select = action.btn?.querySelector('select');
            if (select?.value) {
                rte.insertHTML(select.value);
            }
        },
        update: (_rte, action) => {
            const select = action.btn?.querySelector('select');
            if (select) {
                select.value = '';
            }

            return 0;
        },
    });

    editor.onReady(() => {
        editor.StyleManager.getSectors().forEach((sector: Sector) => {
            const label = SECTOR_LABELS[sector.getName()];
            if (label) {
                sector.set('name', label);
            }
        });
    });

    const handle: SpikeEditorHandle = {
        editor,
        version: grapesjs.version,
        blocks() {
            return editor.Blocks.getAll().map((block: Block) => {
                const id = block.getId();
                const spike = registry.get(id);

                return {
                    id,
                    label: block.getLabel(),
                    category: spike?.category ?? 'Blocs',
                    support: spike?.support ?? 'native',
                };
            });
        },
        getMjml() {
            return composeMjml(String(editor.runCommand('mjml-code') ?? editor.getHtml()), options.globalStyles);
        },
        getProject() {
            return editor.getProjectData() as Record<string, unknown>;
        },
        compileInEditor() {
            const result = editor.runCommand('mjml-code-to-html', { mjml: handle.getMjml() }) as
                | { html: string; errors?: { line?: number; message: string; tagName?: string }[] }
                | undefined;

            return {
                html: result?.html ?? '',
                errors: (result?.errors ?? []).map((e) => ({ line: e.line ?? null, message: e.message, tagName: e.tagName ?? null })),
            };
        },
        loadMjml(mjml) {
            editor.setComponents(expandSelfClosingMjml(splitMjml(mjml).body));
            editor.UndoManager.clear();
        },
        loadProject(project) {
            editor.loadProjectData(project as ProjectData);
            editor.UndoManager.clear();
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
        startBlockDrag(id, ev) {
            const block = editor.Blocks.get(id);
            if (block) {
                editor.Blocks.startDrag(block, ev);
            }
        },
        endBlockDrag(cancel) {
            editor.Blocks.endDrag(cancel);
        },
        setAttribute(name, value) {
            const cmp = editor.getSelected();
            if (!cmp) {
                return;
            }
            if (value === '') {
                cmp.removeAttributes([name]);
            } else {
                cmp.addAttributes({ [name]: value });
            }
        },
        saveSelectedSection(name) {
            const selected = editor.getSelected();
            const sectionCmp = selected?.is('mj-section') ? selected : selected?.closestType('mj-section');
            if (!sectionCmp) {
                return null;
            }
            const mjml = sectionCmp.toHTML();
            const id = addSavedSectionBlock(editor, registry, name, mjml);

            return { id, mjml, component: sectionCmp.toJSON() as Record<string, unknown> };
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
            editor.on('block:add', cb);

            return () => {
                editor.off('update', cb);
                editor.off('block:add', cb);
            };
        },
        destroy() {
            editor.destroy();
        },
    };

    return handle;
}
