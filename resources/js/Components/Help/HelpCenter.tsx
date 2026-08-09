import { useEffect, useMemo, useState } from 'react';
import {
    Button,
    Link as FluentLink,
    SearchBox,
    Text,
    Title2,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import { OpenRegular } from '@fluentui/react-icons';
import { router, usePage } from '@inertiajs/react';
import { useDocsLocale } from '@/Hooks/useDocsLocale';
import { loadDoc, toPlainText } from '@/Lib/docs/content';
import { getTopic, visibleTopics, type DocGroup, type DocTopic } from '@/Lib/docs/registry';
import { DocsLocaleToggle } from '@/Components/Help/DocsLocaleToggle';
import { DocContent } from '@/Components/Help/DocContent';

/** Guides first — they answer "how do I do X", which is what most readers arrive with. */
const groupOrder: DocGroup[] = ['flows', 'general', 'messaging', 'campaigns', 'contacts', 'administration'];

const useStyles = makeStyles({
    root: {
        display: 'flex',
        gap: tokens.spacingHorizontalXL,
        alignItems: 'flex-start',
        '@media (max-width: 899px)': {
            flexDirection: 'column',
        },
    },
    toc: {
        width: '280px',
        flexShrink: 0,
        position: 'sticky',
        top: 0,
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        '@media (max-width: 899px)': {
            position: 'static',
            width: '100%',
        },
    },
    tocList: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
    group: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
        marginBottom: tokens.spacingVerticalM,
    },
    groupLabel: {
        padding: `0 ${tokens.spacingHorizontalS}`,
        marginBottom: tokens.spacingVerticalXXS,
        color: tokens.colorNeutralForeground3,
        textTransform: 'uppercase',
        letterSpacing: '0.04em',
        fontSize: tokens.fontSizeBase200,
        fontWeight: tokens.fontWeightSemibold,
    },
    tocItem: {
        textAlign: 'start',
        justifyContent: 'flex-start',
        fontWeight: tokens.fontWeightRegular,
    },
    tocItemNested: {
        paddingInlineStart: tokens.spacingHorizontalXXL,
    },
    tocItemActive: {
        backgroundColor: tokens.colorBrandBackground2,
        color: tokens.colorBrandForeground2,
    },
    content: {
        flex: '1 1 auto',
        minWidth: 0,
        padding: tokens.spacingVerticalXL,
        borderRadius: tokens.borderRadiusLarge,
        backgroundColor: tokens.colorNeutralBackground1,
        boxShadow: tokens.shadow4,
    },
    contentHeader: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalM,
        marginBottom: tokens.spacingVerticalL,
    },
    headerActions: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    related: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalM,
        marginTop: tokens.spacingVerticalXL,
        paddingTop: tokens.spacingVerticalM,
        borderTop: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        fontSize: tokens.fontSizeBase200,
    },
    empty: {
        color: tokens.colorNeutralForeground3,
        padding: tokens.spacingVerticalM,
    },
});

/**
 * Centre de documentation (/help). Le sommaire n'affiche que les rubriques
 * autorisées par les permissions de l'utilisateur ; la rubrique courante est
 * reflétée dans l'URL (?topic=…) pour que les liens « Documentation complète »
 * des fenêtres d'aide arrivent directement au bon endroit.
 */
export function HelpCenter() {
    const styles = useStyles();
    const { props, url } = usePage<{ auth: { permissions: string[] } }>();
    const { locale, setLocale, dir, ui } = useDocsLocale();

    const topics = useMemo(() => visibleTopics(props.auth.permissions), [props.auth.permissions]);
    const [query, setQuery] = useState('');
    const [contentIndex, setContentIndex] = useState<Record<string, string>>({});

    const [slug, setSlug] = useState<string>(() => {
        const requested = new URL(url, window.location.origin).searchParams.get('topic');
        if (requested && topics.some((topic) => topic.slug === requested)) return requested;
        return topics[0]?.slug ?? '';
    });

    const topic = getTopic(slug);

    useEffect(() => {
        if (!slug) return;
        const next = `/help?topic=${slug}`;
        if (window.location.pathname + window.location.search !== next) {
            window.history.replaceState(window.history.state, '', next);
        }
    }, [slug]);

    // Full-text search is opt-in: topic bodies are only fetched once the reader
    // actually types, so opening the center stays a one-chunk load.
    useEffect(() => {
        if (query.trim().length === 0) return;
        let active = true;

        Promise.all(
            topics.map(async (item) => {
                const doc = await loadDoc(locale, item.kind, item.slug);
                return [item.slug, doc ? toPlainText(doc.markdown).toLowerCase() : ''] as const;
            }),
        ).then((entries) => {
            if (active) setContentIndex(Object.fromEntries(entries));
        });

        return () => {
            active = false;
        };
    }, [query.length > 0, locale, topics]);

    const matches = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) return topics;

        return topics.filter((item) => {
            if (item.title[locale].toLowerCase().includes(needle)) return true;
            if (item.keywords?.[locale].some((word) => word.toLowerCase().includes(needle))) return true;
            return (contentIndex[item.slug] ?? '').includes(needle);
        });
    }, [query, topics, locale, contentIndex]);

    function selectTopic(next: string) {
        if (!getTopic(next)) return;
        setSlug(next);
        setQuery('');
    }

    function renderGroup(group: DocGroup) {
        const groupTopics = matches.filter((item) => item.group === group);
        if (groupTopics.length === 0) return null;

        // Dialog topics hang off the page they belong to, so the sommaire reads
        // "Destinataires → Fenêtre Nouveau destinataire" rather than as a flat list.
        const pages = groupTopics.filter((item) => item.kind !== 'dialogs');
        const dialogs = groupTopics.filter((item) => item.kind === 'dialogs');
        const ordered: Array<{ topic: DocTopic; nested: boolean }> = [];

        for (const page of pages) {
            ordered.push({ topic: page, nested: false });
            for (const dialog of dialogs) {
                if (dialog.related?.includes(page.slug)) ordered.push({ topic: dialog, nested: true });
            }
        }
        for (const dialog of dialogs) {
            if (!ordered.some((entry) => entry.topic.slug === dialog.slug)) {
                ordered.push({ topic: dialog, nested: false });
            }
        }

        return (
            <div key={group} className={styles.group}>
                <span className={styles.groupLabel}>{ui.groups[group]}</span>
                {ordered.map(({ topic: item, nested }) => (
                    <Button
                        key={item.slug}
                        appearance="subtle"
                        size="small"
                        className={mergeClasses(
                            styles.tocItem,
                            nested && styles.tocItemNested,
                            item.slug === slug && styles.tocItemActive,
                        )}
                        onClick={() => selectTopic(item.slug)}
                    >
                        {item.title[locale]}
                    </Button>
                ))}
            </div>
        );
    }

    const groups = groupOrder.map(renderGroup).filter(Boolean);

    return (
        <div className={styles.root}>
            <nav className={styles.toc} aria-label={ui.tableOfContents}>
                <Title2>{ui.centerTitle}</Title2>
                <SearchBox
                    placeholder={ui.searchPlaceholder}
                    value={query}
                    onChange={(_, data) => setQuery(data.value)}
                />
                <div className={styles.tocList}>
                    {groups.length > 0 ? groups : <Text className={styles.empty}>{ui.noResults}</Text>}
                </div>
            </nav>

            <article className={styles.content}>
                <header className={styles.contentHeader}>
                    <Title2 dir={dir}>{topic ? topic.title[locale] : ui.notFound}</Title2>
                    <span className={styles.headerActions}>
                        <DocsLocaleToggle locale={locale} onChange={setLocale} label={ui.language} />
                        {topic?.route && (
                            <Button
                                appearance="secondary"
                                size="small"
                                icon={<OpenRegular />}
                                onClick={() => router.visit(topic.route!)}
                            >
                                {ui.openPage}
                            </Button>
                        )}
                    </span>
                </header>

                {topic && (
                    <>
                        <DocContent topic={topic} locale={locale} dir={dir} ui={ui} onTopicLink={selectTopic} />
                        {topic.related && topic.related.length > 0 && (
                            <div className={styles.related} dir={dir}>
                                <strong>{ui.seeAlso}</strong>
                                {topic.related.map((relatedSlug) => {
                                    const related = topics.find((item) => item.slug === relatedSlug);
                                    if (!related) return null;
                                    return (
                                        <FluentLink key={relatedSlug} as="button" onClick={() => selectTopic(relatedSlug)}>
                                            {related.title[locale]}
                                        </FluentLink>
                                    );
                                })}
                            </div>
                        )}
                    </>
                )}
            </article>
        </div>
    );
}
