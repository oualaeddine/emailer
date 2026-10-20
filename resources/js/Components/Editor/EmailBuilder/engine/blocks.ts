/**
 * WP-60 — doc 43 §43.4.4 block set on grapesjs-mjml component types
 * (docs/46-editor-spike.md §3). WP-61 adds `product`, `dynamic_content`,
 * and the pre-made / saved sections, registered at runtime through the
 * registry returned by `registerBlocks`.
 */
import type { Component, Editor } from 'grapesjs';
import type { EmailBlockType } from '@/Lib/types/editor';
import { format, text } from '../strings';
import { DYNAMIC_PROP, newDynamicId, renewDynamicIds } from './dynamicContent';

/** Palette sub-tab ("Contenu" › Blocs / Sections / Enregistrés). */
export type BlockCategory = 'blocks' | 'sections' | 'saved';

export interface EmailBlockDefinition {
    /** GrapesJS block id. */
    id: string;
    key: EmailBlockType;
    label: string;
    category: BlockCategory;
    /** Fluent icon key for the palette card (see panels/BlockPalette.tsx). */
    icon: string;
    /** MJML (or GrapesJS component JSON, for saved sections) dropped on the canvas. */
    content: string | Record<string, unknown>;
    /** Pre-made / saved sections: preview image. */
    thumbnail?: string | null;
    /** Pre-made sections: category label (`fr.editor.sectionCategories`). */
    group?: string;
    /** Saved sections: `SavedSection.id`. */
    savedId?: string;
}

/** Component property set on the dropped root so the properties panel can show block presets. */
export const BLOCK_PROP = 'pjBlock';

const d = text.defaults;
const placeholder = `<mj-text align="center" color="#707070">${d.column}</mj-text>`;
const columns = (n: number) => `<mj-section>${Array.from({ length: n }, () => `<mj-column>${placeholder}</mj-column>`).join('')}</mj-section>`;

export const EMAIL_BLOCKS: EmailBlockDefinition[] = [
    {
        id: 'pj-title',
        key: 'title',
        label: text.blocks.title,
        category: 'blocks',
        icon: 'title',
        content: `<mj-text font-size="28px" font-weight="700" line-height="1.2">${d.title}</mj-text>`,
    },
    { id: 'pj-text', key: 'text', label: text.blocks.text, category: 'blocks', icon: 'text', content: `<mj-text>${d.text}</mj-text>` },
    {
        id: 'pj-image',
        key: 'image',
        label: text.blocks.image,
        category: 'blocks',
        icon: 'image',
        content: '<mj-image src="https://placehold.co/560x240?text=Image" alt=""></mj-image>',
    },
    {
        id: 'pj-button',
        key: 'button',
        label: text.blocks.button,
        category: 'blocks',
        icon: 'button',
        content: `<mj-button href="https://">${d.button}</mj-button>`,
    },
    {
        id: 'pj-divider',
        key: 'divider',
        label: text.blocks.divider,
        category: 'blocks',
        icon: 'divider',
        content: '<mj-divider border-width="1px" border-color="#E0E0E0"></mj-divider>',
    },
    { id: 'pj-spacer', key: 'spacer', label: text.blocks.spacer, category: 'blocks', icon: 'spacer', content: '<mj-spacer height="24px"></mj-spacer>' },
    {
        id: 'pj-social',
        key: 'social',
        label: text.blocks.social,
        category: 'blocks',
        icon: 'social',
        content:
            '<mj-social icon-size="24px" mode="horizontal"><mj-social-element name="facebook" href="https://facebook.com">Facebook</mj-social-element><mj-social-element name="instagram" href="https://instagram.com">Instagram</mj-social-element><mj-social-element name="linkedin" href="https://linkedin.com">LinkedIn</mj-social-element></mj-social>',
    },
    ...[1, 2, 3, 4].map(
        (n): EmailBlockDefinition => ({
            id: `pj-columns-${n}`,
            key: 'columns',
            label: n === 1 ? text.columns.one : format(text.columns.many, n),
            category: 'blocks',
            icon: `columns-${n}`,
            content: columns(n),
        }),
    ),
    { id: 'pj-html', key: 'html', label: text.blocks.html, category: 'blocks', icon: 'html', content: `<mj-raw><p>${d.html}</p></mj-raw>` },
    {
        id: 'pj-video',
        key: 'video',
        label: text.blocks.video,
        category: 'blocks',
        icon: 'video',
        content: `<mj-image src="https://placehold.co/560x315/111111/FFFFFF?text=%E2%96%B6" href="https://www.youtube.com/" alt="${d.videoAlt}"></mj-image>`,
    },
    {
        id: 'pj-logo',
        key: 'logo',
        label: text.blocks.logo,
        category: 'blocks',
        icon: 'logo',
        content: `<mj-image src="https://placehold.co/240x60/FFD400/111111?text=LOGO" width="120px" align="left" alt="${d.logoAlt}"></mj-image>`,
    },
    {
        id: 'pj-product',
        key: 'product',
        label: text.blocks.product,
        category: 'blocks',
        icon: 'product',
        content:
            '<mj-section><mj-column vertical-align="middle"><mj-image src="https://placehold.co/300x300?text=Image" alt=""></mj-image></mj-column>' +
            `<mj-column vertical-align="middle"><mj-text font-size="20px" font-weight="700" line-height="1.25">${d.title}</mj-text><mj-text>${d.text}</mj-text>` +
            `<mj-text font-size="18px" font-weight="700">0 DA</mj-text><mj-button href="https://" align="left">${d.button}</mj-button></mj-column></mj-section>`,
    },
    {
        id: 'pj-dynamic-content',
        key: 'dynamic_content',
        label: text.blocks.dynamic_content,
        category: 'blocks',
        icon: 'dynamic',
        content: `<mj-wrapper padding="0px"><mj-section><mj-column><mj-text>${d.text}</mj-text></mj-column></mj-section></mj-wrapper>`,
    },
    {
        id: 'pj-navigation',
        key: 'navigation',
        label: text.blocks.navigation,
        category: 'blocks',
        icon: 'navigation',
        content: `<mj-navbar><mj-navbar-link href="https://">${d.navHome}</mj-navbar-link><mj-navbar-link href="https://">${d.navOffers}</mj-navbar-link><mj-navbar-link href="https://">${d.navContact}</mj-navbar-link></mj-navbar>`,
    },
];

/**
 * Tags a dropped / inserted root: block key prop, fresh ids for the dynamic
 * sections it contains, and a new rule for a `dynamic_content` block.
 */
export function tagInsertedBlock(cmp: Component, def: EmailBlockDefinition, onDynamicDrop?: (dynamicId: string) => void): void {
    renewDynamicIds(cmp);
    if (def.category !== 'sections') {
        cmp.set(BLOCK_PROP, def.key);
    }
    if (def.key === 'dynamic_content') {
        const id = newDynamicId();
        cmp.set(DYNAMIC_PROP, { id, condition: null });
        cmp.set('name', text.dynamicContent.title);
        onDynamicDrop?.(id);
    }
}

export interface BlockRegistry {
    list(): EmailBlockDefinition[];
    add(blocks: EmailBlockDefinition[]): void;
    remove(id: string): void;
}

/**
 * Registers the blocks and tags each dropped root with its block key
 * (`BLOCK_PROP`, a GrapesJS component prop — MJML attributes cannot carry
 * it). A dropped `dynamic_content` block becomes a dynamic section
 * (`onDynamicDrop` opens its rule dialog); dynamic sections inside a
 * dropped saved section get fresh ids.
 */
export function registerBlocks(
    editor: Editor,
    extra: EmailBlockDefinition[] = [],
    onDynamicDrop?: (dynamicId: string) => void,
): BlockRegistry {
    const registry = new Map<string, EmailBlockDefinition>();

    const add = (blocks: EmailBlockDefinition[]) => {
        for (const block of blocks) {
            if (editor.Blocks.get(block.id)) {
                editor.Blocks.remove(block.id);
            }
            editor.Blocks.add(block.id, { label: block.label, category: block.category, content: block.content as never });
            registry.set(block.id, block);
        }
    };
    add([...EMAIL_BLOCKS, ...extra]);

    editor.on('block:drag:stop', (dropped: Component | Component[] | undefined, block: { getId(): string } | undefined) => {
        const def = block ? registry.get(block.getId()) : undefined;
        const roots = Array.isArray(dropped) ? dropped : dropped ? [dropped] : [];
        if (!def) {
            return;
        }
        roots.forEach((cmp) => tagInsertedBlock(cmp, def, onDynamicDrop));
    });

    return {
        list: () => Array.from(registry.values()),
        add,
        remove(id) {
            registry.delete(id);
            if (editor.Blocks.get(id)) {
                editor.Blocks.remove(id);
            }
        },
    };
}
