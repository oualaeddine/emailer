import type { DocKind } from '@/Lib/docs/registry';
import type { DocsLocale } from '@/Lib/docs/ui';

/**
 * Markdown documentation loader. The glob is deliberately NOT eager: each
 * `.md` file becomes its own async chunk, so opening one help topic never
 * downloads the whole documentation set (and none of it lands in the main
 * bundle, which `app.tsx` builds from an eager `Pages/**` glob).
 */
const modules = import.meta.glob<string>('../../../docs/{fr,ar}/*/*.md', {
    query: '?raw',
    import: 'default',
});

const cache = new Map<string, string>();

export interface LoadedDoc {
    markdown: string;
    /** True when the requested locale had no file and the French text was used instead. */
    fallback: boolean;
}

function keyFor(locale: DocsLocale, kind: DocKind, slug: string): string {
    return `../../../docs/${locale}/${kind}/${slug}.md`;
}

async function read(key: string): Promise<string | null> {
    const cached = cache.get(key);
    if (cached !== undefined) return cached;

    const loader = modules[key];
    if (!loader) return null;

    const markdown = await loader();
    cache.set(key, markdown);
    return markdown;
}

/**
 * Returns the topic's markdown in `locale`, falling back to French when the
 * translation has not been written yet (callers surface `fallback` as a
 * notice). Returns null only when the topic has no French file either.
 */
export async function loadDoc(locale: DocsLocale, kind: DocKind, slug: string): Promise<LoadedDoc | null> {
    const requested = await read(keyFor(locale, kind, slug));
    if (requested !== null) {
        return { markdown: requested, fallback: false };
    }

    if (locale === 'fr') return null;

    const french = await read(keyFor('fr', kind, slug));
    return french === null ? null : { markdown: french, fallback: true };
}

/** Strips markdown syntax down to searchable plain text (used by the docs center search). */
export function toPlainText(markdown: string): string {
    return markdown
        .replace(/```[\s\S]*?```/g, ' ')
        .replace(/!\[[^\]]*\]\([^)]*\)/g, ' ')
        .replace(/\[([^\]]*)\]\([^)]*\)/g, '$1')
        .replace(/[#>*_`|-]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}
