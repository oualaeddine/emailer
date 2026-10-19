// grapesjs ships its locale files without type declarations.
declare module 'grapesjs/locale/fr.mjs' {
    const messages: Record<string, unknown>;
    export default messages;
}

// mjml-browser ships no types; minimal surface used by the spike.
declare module 'mjml-browser' {
    interface MjmlBrowserError {
        line?: number;
        message: string;
        tagName?: string;
        formattedMessage?: string;
    }
    interface MjmlBrowserResult {
        html: string;
        errors: MjmlBrowserError[];
    }
    export default function mjml2html(mjml: string, options?: Record<string, unknown>): MjmlBrowserResult;
}
