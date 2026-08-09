import { useState, type ComponentProps } from 'react';
import Markdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { Link } from '@inertiajs/react';
import { makeStyles, mergeClasses, shorthands, tokens } from '@fluentui/react-components';
import { ImageLightbox } from '@/Components/Help/ImageLightbox';
import { useDocsLocale } from '@/Hooks/useDocsLocale';

const useStyles = makeStyles({
    root: {
        color: tokens.colorNeutralForeground1,
        fontSize: tokens.fontSizeBase300,
        lineHeight: tokens.lineHeightBase400,
        '& h1': {
            fontSize: tokens.fontSizeHero700,
            fontWeight: tokens.fontWeightSemibold,
            margin: `0 0 ${tokens.spacingVerticalM}`,
        },
        '& h2': {
            fontSize: tokens.fontSizeBase500,
            fontWeight: tokens.fontWeightSemibold,
            margin: `${tokens.spacingVerticalXXL} 0 ${tokens.spacingVerticalS}`,
            paddingBottom: tokens.spacingVerticalXS,
            borderBottom: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        },
        '& h3': {
            fontSize: tokens.fontSizeBase400,
            fontWeight: tokens.fontWeightSemibold,
            margin: `${tokens.spacingVerticalL} 0 ${tokens.spacingVerticalXS}`,
        },
        '& p': {
            margin: `0 0 ${tokens.spacingVerticalM}`,
            color: tokens.colorNeutralForeground2,
        },
        '& ul, & ol': {
            margin: `0 0 ${tokens.spacingVerticalM}`,
            paddingInlineStart: tokens.spacingHorizontalXXL,
            color: tokens.colorNeutralForeground2,
        },
        '& li': {
            marginBottom: tokens.spacingVerticalXS,
        },
        '& strong': {
            fontWeight: tokens.fontWeightSemibold,
            color: tokens.colorNeutralForeground1,
        },
        '& code': {
            fontFamily: tokens.fontFamilyMonospace,
            fontSize: tokens.fontSizeBase200,
            backgroundColor: tokens.colorNeutralBackground3,
            borderRadius: tokens.borderRadiusSmall,
            ...shorthands.padding('2px', tokens.spacingHorizontalXS),
        },
        '& pre': {
            backgroundColor: tokens.colorNeutralBackground3,
            borderRadius: tokens.borderRadiusMedium,
            padding: tokens.spacingVerticalM,
            overflowX: 'auto',
            margin: `0 0 ${tokens.spacingVerticalM}`,
        },
        '& pre code': {
            backgroundColor: 'transparent',
            ...shorthands.padding(0),
        },
        '& blockquote': {
            margin: `0 0 ${tokens.spacingVerticalM}`,
            padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalM}`,
            backgroundColor: tokens.colorNeutralBackground2,
            borderLeft: `3px solid ${tokens.colorBrandStroke1}`,
            borderRadius: tokens.borderRadiusMedium,
            color: tokens.colorNeutralForeground2,
        },
        '& blockquote p:last-child': {
            marginBottom: 0,
        },
        '& table': {
            width: '100%',
            borderCollapse: 'collapse',
            margin: `0 0 ${tokens.spacingVerticalM}`,
            fontSize: tokens.fontSizeBase200,
        },
        '& th, & td': {
            textAlign: 'start',
            padding: `${tokens.spacingVerticalXS} ${tokens.spacingHorizontalS}`,
            border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        },
        '& th': {
            backgroundColor: tokens.colorNeutralBackground2,
            fontWeight: tokens.fontWeightSemibold,
        },
        '& hr': {
            border: 'none',
            borderTop: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
            margin: `${tokens.spacingVerticalXL} 0`,
        },
        '& a': {
            color: tokens.colorBrandForegroundLink,
            textDecorationLine: 'none',
        },
        '& a:hover': {
            textDecorationLine: 'underline',
        },
    },
    rtl: {
        // The tables/lists above use logical properties, so `dir` alone flips them.
        textAlign: 'right',
    },
    figure: {
        margin: `0 0 ${tokens.spacingVerticalL}`,
    },
    image: {
        display: 'block',
        maxWidth: '100%',
        cursor: 'zoom-in',
        borderRadius: tokens.borderRadiusMedium,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        boxShadow: tokens.shadow4,
    },
    caption: {
        marginTop: tokens.spacingVerticalXS,
        fontSize: tokens.fontSizeBase200,
        color: tokens.colorNeutralForeground3,
    },
    pending: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '140px',
        borderRadius: tokens.borderRadiusMedium,
        border: `${tokens.strokeWidthThin} dashed ${tokens.colorNeutralStroke2}`,
        backgroundColor: tokens.colorNeutralBackground2,
        color: tokens.colorNeutralForeground3,
        fontSize: tokens.fontSizeBase200,
    },
});

interface MarkdownViewProps {
    markdown: string;
    dir: 'ltr' | 'rtl';
    /** Called for `help:<slug>` links so the host surface can swap topic in place. */
    onTopicLink?: (slug: string) => void;
}

/**
 * Renders documentation markdown with Fluent-token styling. Raw HTML is not
 * enabled (no rehype-raw), so documentation content can never inject markup.
 *
 * Link conventions used inside the `.md` files:
 *   [texte](help:campaigns)  → in-place topic switch (falls back to /help)
 *   [texte](/campaigns)      → Inertia navigation inside the app
 *   [texte](https://…)       → new tab
 */
export function MarkdownView({ markdown, dir, onTopicLink }: MarkdownViewProps) {
    const styles = useStyles();
    const { ui } = useDocsLocale();
    const [lightbox, setLightbox] = useState<{ src: string; alt: string } | null>(null);

    const components: ComponentProps<typeof Markdown>['components'] = {
        a({ href, children, ...rest }) {
            const target = href ?? '';

            if (target.startsWith('help:')) {
                const slug = target.slice('help:'.length);
                return (
                    <a
                        href={`/help?topic=${slug}`}
                        onClick={(event) => {
                            if (!onTopicLink) return;
                            event.preventDefault();
                            onTopicLink(slug);
                        }}
                        {...rest}
                    >
                        {children}
                    </a>
                );
            }

            if (target.startsWith('/')) {
                return <Link href={target}>{children}</Link>;
            }

            return (
                <a href={target} target="_blank" rel="noopener noreferrer" {...rest}>
                    {children}
                </a>
            );
        },
        img({ src, alt }) {
            const source = typeof src === 'string' ? src : '';
            const caption = alt ?? '';

            return (
                <figure className={styles.figure}>
                    <img
                        className={styles.image}
                        src={source}
                        alt={caption}
                        title={ui.enlarge}
                        loading="lazy"
                        onClick={() => setLightbox({ src: source, alt: caption })}
                        onError={(event) => {
                            const img = event.currentTarget;
                            const placeholder = document.createElement('div');
                            placeholder.className = styles.pending;
                            placeholder.textContent = ui.screenshotPending;
                            img.replaceWith(placeholder);
                        }}
                    />
                    {caption && <figcaption className={styles.caption}>{caption}</figcaption>}
                </figure>
            );
        },
    };

    return (
        <div dir={dir} className={mergeClasses(styles.root, dir === 'rtl' && styles.rtl)}>
            <Markdown remarkPlugins={[remarkGfm]} components={components}>
                {markdown}
            </Markdown>
            <ImageLightbox src={lightbox?.src ?? null} alt={lightbox?.alt ?? ''} onClose={() => setLightbox(null)} />
        </div>
    );
}
