import { makeStyles, tokens } from '@fluentui/react-components';
import type { PropsWithChildren } from 'react';
import { usePage } from '@inertiajs/react';
import { TopBar } from '@/Components/Shell/TopBar';
import { NavRail } from '@/Components/Shell/NavRail';
import type { AuthenticatedUser } from '@/Lib/types/identity';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        height: '100vh',
    },
    body: {
        display: 'flex',
        flex: '1 1 auto',
        minHeight: 0,
    },
    content: {
        flex: '1 1 auto',
        overflowY: 'auto',
        padding: tokens.spacingVerticalXL,
        backgroundColor: tokens.colorNeutralBackground3,
    },
});

/**
 * docs/08-navigation.md §8.1 — App Shell Layout.
 * Minimal shell for the Identity module (top bar + nav rail skeleton) —
 * the full three-pane mailbox layout and richer nav tree are delivered by
 * their own modules as they land.
 */
export function AppShell({ children }: PropsWithChildren) {
    const styles = useStyles();
    const { props } = usePage<{ auth: { user: AuthenticatedUser } }>();

    return (
        <div className={styles.root}>
            <TopBar user={props.auth.user} />
            <div className={styles.body}>
                <NavRail />
                <main className={styles.content}>{children}</main>
            </div>
        </div>
    );
}
