import { Suspense, lazy } from 'react';
import { Head } from '@inertiajs/react';
import { Spinner, makeStyles } from '@fluentui/react-components';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';

/**
 * Coquille légère : `app.tsx` charge toutes les pages de `Pages/**` de manière
 * eager, donc le contenu réel du centre de documentation (react-markdown + les
 * fichiers .md) est chargé à la demande depuis `Components/Help/` et n'entre
 * jamais dans le bundle principal.
 */
const HelpCenter = lazy(() =>
    import('@/Components/Help/HelpCenter').then((module) => ({ default: module.HelpCenter })),
);

const useStyles = makeStyles({
    fallback: {
        display: 'flex',
        justifyContent: 'center',
        padding: '64px',
    },
});

export default function HelpIndex() {
    const styles = useStyles();
    const t = useText();

    return (
        <AppShell>
            <Head title={t.help.docsCenter} />
            <Suspense
                fallback={
                    <div className={styles.fallback}>
                        <Spinner label={t.common.loading} />
                    </div>
                }
            >
                <HelpCenter />
            </Suspense>
        </AppShell>
    );
}
