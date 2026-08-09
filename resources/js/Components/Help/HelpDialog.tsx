import { Suspense, lazy } from 'react';
import { Dialog, DialogSurface, Spinner, makeStyles } from '@fluentui/react-components';

const HelpDialogBody = lazy(() =>
    import('@/Components/Help/HelpDialogBody').then((module) => ({ default: module.HelpDialogBody })),
);

const useStyles = makeStyles({
    surface: {
        maxWidth: '760px',
        width: '92vw',
    },
    fallback: {
        padding: '48px',
        display: 'flex',
        justifyContent: 'center',
    },
});

interface HelpDialogProps {
    slug: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * Contextual help overlay. Rendered as its own Fluent Dialog (each Dialog gets
 * an independent portal + focus trap), so it can be opened from inside a form
 * dialog and layered above it — Esc closes only the help layer.
 */
export function HelpDialog({ slug, open, onOpenChange }: HelpDialogProps) {
    const styles = useStyles();

    return (
        <Dialog open={open} onOpenChange={(_, data) => onOpenChange(data.open)}>
            <DialogSurface className={styles.surface}>
                <Suspense
                    fallback={
                        <div className={styles.fallback}>
                            <Spinner size="small" />
                        </div>
                    }
                >
                    <HelpDialogBody slug={slug} onClose={() => onOpenChange(false)} />
                </Suspense>
            </DialogSurface>
        </Dialog>
    );
}
