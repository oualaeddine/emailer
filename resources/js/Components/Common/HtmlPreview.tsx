import { CSSProperties } from 'react';

interface HtmlPreviewProps {
    /** Untrusted, user-authored email HTML (template / campaign body). */
    html: string;
    className?: string;
    style?: CSSProperties;
    title?: string;
}

/**
 * docs/28-security.md — safe preview of user-authored email HTML.
 *
 * Email bodies are authored by users and rendered elsewhere in the app; a
 * body containing `<script>` or an `onerror` handler injected into the app
 * DOM would be stored XSS in the app's own origin. Rendering it via
 * `srcDoc` inside a fully sandboxed iframe (empty `sandbox` = no scripts, no
 * same-origin, no forms) isolates it in an opaque origin where nothing can
 * execute or reach the parent document, cookies, or session.
 */
export function HtmlPreview({ html, className, style, title = 'Aperçu HTML' }: HtmlPreviewProps) {
    return (
        <iframe
            title={title}
            className={className}
            sandbox=""
            srcDoc={html}
            style={{ width: '100%', border: 'none', ...style }}
        />
    );
}
