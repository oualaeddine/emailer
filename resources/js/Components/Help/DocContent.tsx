import { useEffect, useState } from 'react';
import { MessageBar, MessageBarBody, Spinner, makeStyles, tokens } from '@fluentui/react-components';
import { loadDoc } from '@/Lib/docs/content';
import type { DocTopic } from '@/Lib/docs/registry';
import type { DocsLocale, DocsUiStrings } from '@/Lib/docs/ui';
import { MarkdownView } from '@/Components/Help/MarkdownView';

const useStyles = makeStyles({
    state: {
        display: 'flex',
        justifyContent: 'center',
        padding: tokens.spacingVerticalXXL,
    },
    notice: {
        marginBottom: tokens.spacingVerticalM,
    },
});

interface DocContentProps {
    topic: DocTopic;
    locale: DocsLocale;
    dir: 'ltr' | 'rtl';
    ui: DocsUiStrings;
    onTopicLink?: (slug: string) => void;
}

/**
 * Loads and renders one topic's markdown. Shared by the help dialog and the
 * docs center so both behave identically around loading, missing files, and
 * the French fallback for untranslated Arabic topics.
 */
export function DocContent({ topic, locale, dir, ui, onTopicLink }: DocContentProps) {
    const styles = useStyles();
    const [state, setState] = useState<{ markdown: string; fallback: boolean } | null | 'loading'>('loading');

    useEffect(() => {
        let active = true;
        setState('loading');

        loadDoc(locale, topic.kind, topic.slug).then((result) => {
            if (active) setState(result);
        });

        return () => {
            active = false;
        };
    }, [locale, topic.kind, topic.slug]);

    if (state === 'loading') {
        return (
            <div className={styles.state}>
                <Spinner size="small" label={ui.loading} />
            </div>
        );
    }

    if (state === null) {
        return (
            <MessageBar intent="warning">
                <MessageBarBody>{ui.notFound}</MessageBarBody>
            </MessageBar>
        );
    }

    return (
        <>
            {state.fallback && (
                <MessageBar intent="info" className={styles.notice}>
                    <MessageBarBody>{ui.fallbackNotice}</MessageBarBody>
                </MessageBar>
            )}
            <MarkdownView markdown={state.markdown} dir={state.fallback ? 'ltr' : dir} onTopicLink={onTopicLink} />
        </>
    );
}
