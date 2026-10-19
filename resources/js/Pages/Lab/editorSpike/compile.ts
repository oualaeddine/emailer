/**
 * WP-60S editor spike — standalone browser compile with `mjml-browser`.
 *
 * Loaded lazily on its own (`import('./editorSpike/compile')`) so the size of
 * the parser can be measured separately from the editor. grapesjs-mjml
 * already bundles the same parser, see docs/46-editor-spike.md §1.
 */
import mjml2html from 'mjml-browser';

export interface BrowserCompileResult {
    html: string;
    errors: { line: number | null; message: string; tagName: string | null }[];
    ms: number;
}

export function compileMjml(mjml: string): BrowserCompileResult {
    const started = performance.now();
    const result = mjml2html(mjml, { validationLevel: 'soft', keepComments: false });

    return {
        html: result.html,
        errors: result.errors.map((e) => ({ line: e.line ?? null, message: e.message, tagName: e.tagName ?? null })),
        ms: Math.round(performance.now() - started),
    };
}
