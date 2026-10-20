/**
 * WP-60 — doc 43 §43.4.4 block set on grapesjs-mjml component types
 * (docs/46-editor-spike.md §3). WP-61 adds `product`, `dynamic_content`,
 * pre-made and saved sections through `registerBlocks(editor, extra)`.
 */
import type { Component, Editor } from 'grapesjs';
import type { EmailBlockType } from '@/Lib/types/editor';
import { format, text } from '../strings';

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
    /** MJML dropped on the canvas. */
    content: string;
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
        id: 'pj-navigation',
        key: 'navigation',
        label: text.blocks.navigation,
        category: 'blocks',
        icon: 'navigation',
        content: `<mj-navbar><mj-navbar-link href="https://">${d.navHome}</mj-navbar-link><mj-navbar-link href="https://">${d.navOffers}</mj-navbar-link><mj-navbar-link href="https://">${d.navContact}</mj-navbar-link></mj-navbar>`,
    },
];

/**
 * Registers the blocks and tags each dropped root with its block key
 * (`BLOCK_PROP`, a GrapesJS component prop — MJML attributes cannot carry it).
 */
export function registerBlocks(editor: Editor, extra: EmailBlockDefinition[] = []): Map<string, EmailBlockDefinition> {
    const registry = new Map<string, EmailBlockDefinition>();

    for (const block of [...EMAIL_BLOCKS, ...extra]) {
        editor.Blocks.add(block.id, { label: block.label, category: block.category, content: block.content });
        registry.set(block.id, block);
    }

    editor.on('block:drag:stop', (dropped: Component | Component[] | undefined, block: { getId(): string } | undefined) => {
        const def = block ? registry.get(block.getId()) : undefined;
        const roots = Array.isArray(dropped) ? dropped : dropped ? [dropped] : [];
        if (def) {
            roots.forEach((cmp) => cmp.set(BLOCK_PROP, def.key));
        }
    });

    return registry;
}
