import { useState } from 'react';
import {
    Button,
    DialogActions,
    DialogBody,
    DialogContent,
    DialogTitle,
    Link as FluentLink,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { ArrowLeftRegular, DismissRegular } from '@fluentui/react-icons';
import { router } from '@inertiajs/react';
import { getTopic } from '@/Lib/docs/registry';
import { useDocsLocale } from '@/Hooks/useDocsLocale';
import { DocsLocaleToggle } from '@/Components/Help/DocsLocaleToggle';
import { DocContent } from '@/Components/Help/DocContent';

const useStyles = makeStyles({
    titleActions: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalXS,
    },
    content: {
        maxHeight: '62vh',
        overflowY: 'auto',
    },
    related: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalM,
        marginTop: tokens.spacingVerticalL,
        paddingTop: tokens.spacingVerticalM,
        borderTop: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        fontSize: tokens.fontSizeBase200,
    },
});

interface HelpDialogBodyProps {
    slug: string;
    onClose: () => void;
}

/**
 * Lazily-loaded body of the contextual help dialog — keeps react-markdown and
 * the documentation chunks out of the main bundle until help is first opened.
 */
export function HelpDialogBody({ slug, onClose }: HelpDialogBodyProps) {
    const styles = useStyles();
    const { locale, setLocale, dir, ui } = useDocsLocale();
    // History of in-dialog topic switches, so `help:` cross-links are reversible.
    const [trail, setTrail] = useState<string[]>([slug]);
    const currentSlug = trail[trail.length - 1];
    const topic = getTopic(currentSlug);

    function openFullDocs() {
        onClose();
        router.visit(`/help?topic=${currentSlug}`);
    }

    return (
        <DialogBody>
            <DialogTitle
                action={
                    <span className={styles.titleActions}>
                        <DocsLocaleToggle locale={locale} onChange={setLocale} label={ui.language} />
                        <Button
                            appearance="subtle"
                            icon={<DismissRegular />}
                            aria-label={ui.close}
                            onClick={onClose}
                        />
                    </span>
                }
            >
                {trail.length > 1 && (
                    <Button
                        appearance="subtle"
                        size="small"
                        icon={<ArrowLeftRegular />}
                        aria-label={ui.back}
                        onClick={() => setTrail((items) => items.slice(0, -1))}
                    />
                )}
                {topic ? topic.title[locale] : ui.notFound}
            </DialogTitle>
            <DialogContent className={styles.content}>
                {topic ? (
                    <>
                        <DocContent
                            topic={topic}
                            locale={locale}
                            dir={dir}
                            ui={ui}
                            onTopicLink={(next) => getTopic(next) && setTrail((items) => [...items, next])}
                        />
                        {topic.related && topic.related.length > 0 && (
                            <div className={styles.related}>
                                <strong>{ui.seeAlso}</strong>
                                {topic.related.map((relatedSlug) => {
                                    const related = getTopic(relatedSlug);
                                    if (!related) return null;
                                    return (
                                        <FluentLink
                                            key={relatedSlug}
                                            as="button"
                                            onClick={() => setTrail((items) => [...items, relatedSlug])}
                                        >
                                            {related.title[locale]}
                                        </FluentLink>
                                    );
                                })}
                            </div>
                        )}
                    </>
                ) : (
                    ui.notFound
                )}
            </DialogContent>
            <DialogActions>
                <Button appearance="secondary" onClick={onClose}>
                    {ui.close}
                </Button>
                <Button appearance="primary" onClick={openFullDocs}>
                    {ui.fullDocs}
                </Button>
            </DialogActions>
        </DialogBody>
    );
}
