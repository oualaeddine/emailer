import type { ReactNode } from 'react';
import { makeStyles, tokens } from '@fluentui/react-components';
import { useText } from '@/Hooks/useText';

/** Same shape as the backend `MergeVariableResolver::PATTERN`: `{{ contact.SOCIETE }}`, `{{ x | default:"y" }}`. */
const TAG_PATTERN = /\{\{\s*([a-zA-Z0-9_.]+)\s*(?:\|\s*([^}]*?))?\s*\}\}/g;

/** `contact.SOCIETE` → `SOCIETE`, `recipient.first_name` → `first_name`. */
function tagLabel(path: string): string {
    return path.split('.').pop() ?? path;
}

/** True when the text holds at least one merge tag. */
export function hasMergeTags(text: string): boolean {
    return new RegExp(TAG_PATTERN.source).test(text);
}

/** Plain-text placeholder rendering (`{{ contact.SOCIETE }}` → `[SOCIETE]`) when no sample contact is available. */
export function placeholderMergeTags(text: string): string {
    return text.replace(TAG_PATTERN, (_match, path: string) => `[${tagLabel(path)}]`);
}

const useStyles = makeStyles({
    tag: {
        display: 'inline-block',
        padding: `0 ${tokens.spacingHorizontalXS}`,
        borderRadius: tokens.borderRadiusMedium,
        backgroundColor: tokens.colorBrandBackground2,
        color: tokens.colorBrandForeground2,
        fontSize: tokens.fontSizeBase200,
        fontWeight: tokens.fontWeightSemibold,
        lineHeight: tokens.lineHeightBase200,
        whiteSpace: 'nowrap',
    },
});

/**
 * Shows a subject / text that contains merge tags as a template: each tag is a
 * small chip (« SOCIETE ») instead of raw `{{ contact.SOCIETE }}` text that
 * reads like a rendering bug. The full tag stays in the tooltip.
 */
export function MergeTagText({ text }: { text: string }) {
    const styles = useStyles();
    const t = useText();
    const nodes: ReactNode[] = [];
    let last = 0;

    for (const match of text.matchAll(TAG_PATTERN)) {
        const index = match.index ?? 0;
        if (index > last) {
            nodes.push(text.slice(last, index));
        }
        nodes.push(
            <span key={`${index}-${match[1]}`} className={styles.tag} title={`${t.campaignReport.mergeTag} : ${match[0]}`}>
                {tagLabel(match[1])}
            </span>,
        );
        last = index + match[0].length;
    }
    if (last < text.length) {
        nodes.push(text.slice(last));
    }

    return <>{nodes}</>;
}
