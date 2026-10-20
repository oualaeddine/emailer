/**
 * WP-60 — the only builder component pages render. The editor (GrapesJS +
 * grapesjs-mjml + CSS) is a dynamic import, so it stays a lazy chunk and the
 * main chunk keeps its budget (docs/46 §7 "Lazy-load rule").
 */
import { Suspense, lazy } from 'react';
import { Spinner, makeStyles, tokens } from '@fluentui/react-components';
import type { EmailBuilderProps } from './types';
import { text } from './strings';

const EmailBuilder = lazy(() => import('./EmailBuilder'));

/** Starts downloading the editor chunk early (e.g. on the campaign "Design" step). */
export function prefetchEmailBuilder(): void {
    void import('./EmailBuilder');
}

const useStyles = makeStyles({
    fallback: { display: 'flex', justifyContent: 'center', alignItems: 'center', minHeight: '480px', padding: tokens.spacingVerticalXXL },
});

export function EmailBuilderLazy(props: EmailBuilderProps) {
    const styles = useStyles();

    return (
        <Suspense
            fallback={
                <div className={styles.fallback}>
                    <Spinner label={text.common.loading} />
                </div>
            }
        >
            <EmailBuilder {...props} />
        </Suspense>
    );
}
