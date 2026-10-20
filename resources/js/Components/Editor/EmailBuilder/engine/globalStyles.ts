/**
 * WP-60 — storage helpers (docs/46-editor-spike.md §2). No GrapesJS import:
 * pure string/JSON work, shared by the editor and its tests.
 */
import type { EmailGlobalStyles } from '@/Lib/types/editor';
import type { DynamicBlockRecord, EmailBuilderSource, EmailDesignWithDynamic } from '../types';
import { text } from '../strings';

export const DEFAULT_GLOBAL_STYLES: EmailGlobalStyles = {
    font_family: 'Arial, sans-serif',
    text_color: '#242424',
    link_color: '#0F6CBD',
    link_underline: true,
    background_color: '#F5F5F5',
    content_background_color: '#FFFFFF',
    content_width: 600,
    button: { background_color: '#0F6CBD', text_color: '#FFFFFF', border_radius: 4, font_weight: '600' },
    mobile: { font_scale: 1, hide_on_mobile_padding: false },
};

function escapeAttr(value: string | number): string {
    return String(value).replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
}

/** Fills missing keys of a stored `global_styles` with the defaults. */
export function normaliseGlobalStyles(gs: Partial<EmailGlobalStyles> | null | undefined): EmailGlobalStyles {
    return {
        ...DEFAULT_GLOBAL_STYLES,
        ...(gs ?? {}),
        button: { ...DEFAULT_GLOBAL_STYLES.button, ...(gs?.button ?? {}) },
        mobile: { ...DEFAULT_GLOBAL_STYLES.mobile, ...(gs?.mobile ?? {}) },
    };
}

/** Font stacks offered by the *Style* tab (web-safe, rendered by every client). */
export const FONT_FAMILIES: string[] = [
    'Arial, sans-serif',
    'Helvetica, Arial, sans-serif',
    'Verdana, Geneva, sans-serif',
    'Tahoma, Geneva, sans-serif',
    "'Trebuchet MS', Helvetica, sans-serif",
    'Georgia, serif',
    "'Times New Roman', Times, serif",
    "'Courier New', Courier, monospace",
];

function cssValue(value: string): string {
    return value.replace(/[;{}<>]/g, '');
}

/**
 * Mobile settings (*Style › Paramètres mobiles*), applied below 480 px:
 * text scale through `text-size-adjust` (iOS / Apple Mail), and block
 * side padding removed when `hide_on_mobile_padding`.
 */
export function mobileCss(gs: EmailGlobalStyles): string {
    const rules: string[] = [];
    const scale = Number(gs.mobile.font_scale);
    if (Number.isFinite(scale) && scale > 0 && scale !== 1) {
        const percent = Math.round(scale * 100);
        rules.push(`body, table, td, div, p, a { -webkit-text-size-adjust: ${percent}% !important; text-size-adjust: ${percent}% !important; }`);
    }
    if (gs.mobile.hide_on_mobile_padding) {
        rules.push('div.mj-outlook-group-fix > table > tbody > tr > td { padding-left: 0 !important; padding-right: 0 !important; }');
    }

    return rules.length > 0 ? `@media only screen and (max-width: 480px) { ${rules.join(' ')} }` : '';
}

/** `global_styles` → `<mj-head>` (mj-attributes + mj-style), applied by every compile. */
export function globalStylesToMjmlHead(gs: EmailGlobalStyles): string {
    const linkDecoration = gs.link_underline ? 'underline' : 'none';
    const mobile = mobileCss(gs);

    return [
        '<mj-head>',
        '<mj-attributes>',
        `<mj-all font-family="${escapeAttr(gs.font_family)}"></mj-all>`,
        `<mj-text color="${escapeAttr(gs.text_color)}" font-size="14px" line-height="1.5"></mj-text>`,
        `<mj-section background-color="${escapeAttr(gs.content_background_color)}"></mj-section>`,
        `<mj-button background-color="${escapeAttr(gs.button.background_color)}" color="${escapeAttr(gs.button.text_color)}" border-radius="${escapeAttr(gs.button.border_radius)}px" font-weight="${escapeAttr(gs.button.font_weight)}"></mj-button>`,
        '</mj-attributes>',
        `<mj-style>a { color: ${cssValue(gs.link_color)}; text-decoration: ${linkDecoration}; }</mj-style>`,
        mobile ? `<mj-style>${mobile}</mj-style>` : '',
        '</mj-head>',
    ].join('');
}

export function splitMjml(mjml: string): { head: string | null; body: string } {
    const match = mjml.match(/<mj-head[\s>][\s\S]*?<\/mj-head>/);

    return { head: match ? match[0] : null, body: match ? mjml.replace(match[0], '') : mjml };
}

/**
 * The editor only holds `<mjml><mj-body>…`; the head is generated from
 * `global_styles` at save time (docs/46 §2 rule 2). `lang="fr"` makes the
 * compiled HTML `lang="fr"`.
 */
export function composeMjml(editorMjml: string, gs: EmailGlobalStyles): string {
    const { body } = splitMjml(editorMjml);
    const trimmed = body.trim();
    const withRoot = /^<mjml[\s>]/.test(trimmed) ? trimmed : `<mjml>${trimmed}</mjml>`;

    return withRoot.replace(/^<mjml[^>]*>/, `<mjml lang="fr">${globalStylesToMjmlHead(gs)}`);
}

const VOID_MJML = /<(mj-[a-z-]+)((?:\s+[^\s=>/]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+))?)*)\s*\/>/g;

/** The GrapesJS HTML parser nests siblings inside `<mj-image />`: expand self-closing tags (docs/46 §2 rule 3). */
export function expandSelfClosingMjml(mjml: string): string {
    return mjml.replace(VOID_MJML, (_m, tag: string, attrs: string) => `<${tag}${attrs}></${tag}>`);
}

/** Editor body for an empty design. */
export function blankMjml(gs: EmailGlobalStyles): string {
    return [
        `<mjml><mj-body background-color="${escapeAttr(gs.background_color)}" width="${escapeAttr(gs.content_width)}px">`,
        '<mj-section><mj-column>',
        `<mj-text font-size="28px" font-weight="700" line-height="1.2">${text.defaults.title}</mj-text>`,
        `<mj-text>${text.defaults.text}</mj-text>`,
        '</mj-column></mj-section>',
        '</mj-body></mjml>',
    ].join('');
}

/**
 * Legacy HTML (a design with no `design_json`) → one `mj-raw` block, so the
 * content survives and can be edited around. Only the `<body>` and the head
 * `<style>` tags are kept.
 */
export function htmlToMjml(html: string, gs: EmailGlobalStyles): string {
    const bodyMatch = html.match(/<body[^>]*>([\s\S]*?)<\/body>/i);
    const styles = (html.match(/<style[^>]*>[\s\S]*?<\/style>/gi) ?? []).join('');
    const inner = bodyMatch ? bodyMatch[1] : html.replace(/<!doctype[^>]*>/i, '').replace(/<\/?(html|head)[^>]*>/gi, '');
    const content = bodyMatch ? `${styles}${inner}` : inner;

    return `<mjml><mj-body background-color="${escapeAttr(gs.background_color)}" width="${escapeAttr(gs.content_width)}px"><mj-raw>${content}</mj-raw></mj-body></mjml>`;
}

export function hasProjectPages(project: unknown): boolean {
    if (typeof project !== 'object' || project === null) {
        return false;
    }
    const pages = (project as { pages?: unknown }).pages;

    return Array.isArray(pages) && pages.length > 0;
}

export type ResolvedSource =
    | { kind: 'project'; project: Record<string, unknown>; globalStyles: EmailGlobalStyles; dynamicBlocks: DynamicBlockRecord[] }
    | { kind: 'mjml'; mjml: string; globalStyles: EmailGlobalStyles; dynamicBlocks: DynamicBlockRecord[] };

function readDynamicBlocks(design: EmailDesignWithDynamic | null): DynamicBlockRecord[] {
    const blocks = design?.dynamic_blocks;

    return Array.isArray(blocks) ? blocks.filter((block) => typeof block === 'object' && block !== null && typeof block.id === 'string') : [];
}

/**
 * Load order: GrapesJS project (faithful reopen, docs/46 §2 rule 1) → the
 * design's MJML when the project is an empty shell (seeded gallery designs,
 * WP-62) → legacy HTML as an HTML block → blank design.
 */
export function resolveSource(source: EmailBuilderSource): ResolvedSource {
    const design = source.design as EmailDesignWithDynamic | null;
    const globalStyles = normaliseGlobalStyles(design?.global_styles);
    const dynamicBlocks = readDynamicBlocks(design);

    if (design && hasProjectPages(design.project)) {
        return { kind: 'project', project: design.project, globalStyles, dynamicBlocks };
    }
    if (design && typeof design.mjml === 'string' && design.mjml.trim() !== '') {
        return { kind: 'mjml', mjml: design.mjml, globalStyles, dynamicBlocks };
    }
    if (source.html && source.html.trim() !== '') {
        return { kind: 'mjml', mjml: htmlToMjml(source.html, globalStyles), globalStyles, dynamicBlocks: [] };
    }

    return { kind: 'mjml', mjml: blankMjml(globalStyles), globalStyles, dynamicBlocks: [] };
}

/** `design_json` (docs/45 §45.6.1 `EmailDesign`) + WP-61 `dynamic_blocks`. */
export function buildDesign(
    mjml: string,
    project: Record<string, unknown>,
    gs: EmailGlobalStyles,
    dynamicBlocks: DynamicBlockRecord[] = [],
): EmailDesignWithDynamic {
    return { format: 'grapesjs-mjml', version: 1, mjml, project, global_styles: gs, dynamic_blocks: dynamicBlocks };
}
