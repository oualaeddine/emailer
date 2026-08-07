import { makeStyles, tokens } from '@fluentui/react-components';
import type { PropsWithChildren } from 'react';
import { useState } from 'react';
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
        minWidth: 0,
        overflowY: 'auto',
        padding: tokens.spacingVerticalXL,
        backgroundColor: tokens.colorNeutralBackground3,
        '@media (max-width: 639px)': {
            padding: tokens.spacingVerticalM,
        },
    },
});

/**
 * docs/08-navigation.md §8.1 — App Shell Layout.
 * docs/07-ui-design.md §7.7 — Responsive Behavior: the nav rail collapses to
 * an icon-only rail on tablet and an off-canvas drawer (opened from TopBar's
 * hamburger button) on mobile — `mobileNavOpen` is owned here so both TopBar
 * (the toggle) and NavRail (the drawer + persistent rail) share it.
 */
export function AppShell({ children }: PropsWithChildren) {
    const styles = useStyles();
    const { props } = usePage<{ auth: { user: AuthenticatedUser } }>();
    const [mobileNavOpen, setMobileNavOpen] = useState(false);

    return (
        <div className={styles.root}>
            <TopBar user={props.auth.user} onToggleNav={() => setMobileNavOpen((open) => !open)} />
            <div className={styles.body}>
                <NavRail mobileOpen={mobileNavOpen} onMobileOpenChange={setMobileNavOpen} />
                <main className={styles.content}>{children}</main>
            </div>
        </div>
    );
}
