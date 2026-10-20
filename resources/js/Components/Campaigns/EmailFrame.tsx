import { makeStyles, tokens } from '@fluentui/react-components';

const useStyles = makeStyles({
    frame: {
        border: 'none',
        backgroundColor: '#ffffff',
        display: 'block',
    },
    thumbWrap: {
        position: 'relative',
        overflow: 'hidden',
        borderRadius: tokens.borderRadiusMedium,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        backgroundColor: '#ffffff',
    },
    thumbFrame: {
        position: 'absolute',
        top: 0,
        left: 0,
        transformOrigin: 'top left',
        pointerEvents: 'none',
    },
});

export interface EmailFrameProps {
    html: string;
    title: string;
    width?: number | string;
    height?: number | string;
}

/**
 * Renders campaign HTML in a fully sandboxed iframe (no scripts, no same
 * origin, no forms) — the e-mail body is untrusted user content.
 */
export function EmailFrame({ html, title, width = '100%', height = 600 }: EmailFrameProps) {
    const styles = useStyles();

    return <iframe className={styles.frame} title={title} sandbox="" srcDoc={html} style={{ width, height }} />;
}

export interface EmailThumbnailProps {
    html: string;
    title: string;
    /** Rendered box size in px. */
    width?: number;
    height?: number;
}

/** Scaled-down, non-interactive render of a 600 px wide e-mail (Design card thumbnail). */
export function EmailThumbnail({ html, title, width = 180, height = 240 }: EmailThumbnailProps) {
    const styles = useStyles();
    const scale = width / 640;

    return (
        <div className={styles.thumbWrap} style={{ width, height }} aria-hidden="true">
            <iframe
                className={`${styles.frame} ${styles.thumbFrame}`}
                title={title}
                sandbox=""
                tabIndex={-1}
                srcDoc={html}
                style={{ width: 640, height: height / scale, transform: `scale(${scale})` }}
            />
        </div>
    );
}
