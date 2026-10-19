/**
 * WP-60S editor spike — storage helpers (docs/46-editor-spike.md §2).
 *
 * Kept free of GrapesJS imports so the page module can use it without
 * pulling the editor into the main chunk.
 */
import type { EmailDesign, EmailGlobalStyles } from '@/Lib/types/editor';

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

/**
 * "Style" tab → `<mj-head>`: global styles are expressed as MJML defaults
 * (`mj-attributes`) so they survive the client and the server compile alike.
 */
export function globalStylesToMjmlHead(gs: EmailGlobalStyles): string {
    const linkDecoration = gs.link_underline ? 'underline' : 'none';

    return [
        '<mj-head>',
        '<mj-attributes>',
        `<mj-all font-family="${gs.font_family}"></mj-all>`,
        `<mj-text color="${gs.text_color}" font-size="14px" line-height="1.5"></mj-text>`,
        `<mj-section background-color="${gs.content_background_color}"></mj-section>`,
        `<mj-button background-color="${gs.button.background_color}" color="${gs.button.text_color}" border-radius="${gs.button.border_radius}px" font-weight="${gs.button.font_weight}"></mj-button>`,
        '</mj-attributes>',
        `<mj-style>a { color: ${gs.link_color}; text-decoration: ${linkDecoration}; }</mj-style>`,
        '</mj-head>',
    ].join('');
}

/**
 * grapesjs-mjml 1.0.8 renders <mj-attributes> children as visible components
 * and its canvas renders each component without <mj-head>. So the editor only
 * ever holds `<mjml><mj-body>…`; the head is generated from `global_styles`
 * at save time (composeMjml) and stripped on import (splitMjml).
 */
export function composeMjml(editorMjml: string, gs: EmailGlobalStyles): string {
    const { body } = splitMjml(editorMjml);

    return body.replace(/^\s*<mjml[^>]*>/, `<mjml lang="fr">${globalStylesToMjmlHead(gs)}`);
}

export function splitMjml(mjml: string): { head: string | null; body: string } {
    const match = mjml.match(/<mj-head[\s>][\s\S]*?<\/mj-head>/);

    return { head: match ? match[0] : null, body: match ? mjml.replace(match[0], '') : mjml };
}

const VOID_MJML = /<(mj-[a-z-]+)((?:\s+[^\s=>/]+(?:\s*=\s*(?:"[^"]*"|'[^']*'|[^\s>]+))?)*)\s*\/>/g;

/**
 * The GrapesJS HTML parser treats `<mj-image />` as an open tag and nests the
 * following siblings inside it; expand self-closing MJML tags before import.
 */
export function expandSelfClosingMjml(mjml: string): string {
    return mjml.replace(VOID_MJML, (_m, tag: string, attrs: string) => `<${tag}${attrs}></${tag}>`);
}

/** Body only — the head comes from global styles (composeMjml). */
export function sampleMjml(gs: EmailGlobalStyles = DEFAULT_GLOBAL_STYLES): string {
    return `<mjml>
<mj-body background-color="${gs.background_color}" width="${gs.content_width}px">
  <mj-section padding="16px 0">
    <mj-column>
      <mj-image src="https://placehold.co/240x60/FFD400/111111?text=LOGO" width="120px" align="left" alt="Logo" />
    </mj-column>
  </mj-section>
  <mj-section>
    <mj-column>
      <mj-text font-size="28px" font-weight="700" line-height="1.2">Bonjour {{ contact.first_name }}</mj-text>
      <mj-text>Ceci est un modèle de démonstration chargé depuis du MJML. Modifiez les blocs, puis enregistrez.</mj-text>
      <mj-button href="https://example.com">Découvrir l'offre</mj-button>
      <mj-divider border-width="1px" border-color="#E0E0E0" />
    </mj-column>
  </mj-section>
  <mj-section>
    <mj-column><mj-image src="https://placehold.co/280x180?text=Produit+1" alt="Produit 1" /></mj-column>
    <mj-column><mj-image src="https://placehold.co/280x180?text=Produit+2" alt="Produit 2" /></mj-column>
  </mj-section>
  <mj-section>
    <mj-column>
      <mj-social font-size="12px" icon-size="24px" mode="horizontal">
        <mj-social-element name="facebook" href="https://facebook.com">Facebook</mj-social-element>
        <mj-social-element name="linkedin" href="https://linkedin.com">LinkedIn</mj-social-element>
      </mj-social>
      <mj-text font-size="11px" color="#707070" align="center">Vous recevez cet e-mail car vous êtes inscrit. <a href="{{ unsubscribe_url }}">Se désinscrire</a></mj-text>
    </mj-column>
  </mj-section>
</mj-body>
</mjml>`;
}

export function buildDesign(mjml: string, project: Record<string, unknown>, gs: EmailGlobalStyles): EmailDesign {
    return { format: 'grapesjs-mjml', version: 1, mjml, project, global_styles: gs };
}

export function isEmailDesign(value: unknown): value is EmailDesign {
    if (typeof value !== 'object' || value === null) {
        return false;
    }
    const v = value as Partial<EmailDesign>;

    return v.format === 'grapesjs-mjml' && v.version === 1 && typeof v.mjml === 'string' && typeof v.project === 'object';
}

/** Spike-only persistence (no backend in WP-60S). */
const STORAGE_KEY = 'lab.editor-spike.design';

export function saveLocal(design: EmailDesign): void {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(design));
    } catch {
        // Private mode / quota — the spike keeps working without persistence.
    }
}

export function loadLocal(): EmailDesign | null {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        const parsed: unknown = raw ? JSON.parse(raw) : null;

        return isEmailDesign(parsed) ? parsed : null;
    } catch {
        return null;
    }
}

export function byteSize(value: string): number {
    return new TextEncoder().encode(value).length;
}
