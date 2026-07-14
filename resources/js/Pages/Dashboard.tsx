import { Head, usePage } from '@inertiajs/react';
import { Card, Text, Title1, makeStyles, tokens } from '@fluentui/react-components';
import { AppShell } from '@/Components/Shell/AppShell';
import { useText } from '@/Hooks/useText';
import type { AuthenticatedUser } from '@/Lib/types/identity';

const useStyles = makeStyles({
    card: {
        padding: tokens.spacingVerticalXL,
        maxWidth: '640px',
    },
});

/**
 * docs/09-dashboard.md — placeholder pending the Dashboard module's real
 * widgets, which depend on data from modules not yet implemented
 * (campaigns, SMTP, queues, etc.). This page only establishes the
 * AppShell + routing landing point the Identity module's login flow
 * needs (docs/34-roadmap.md build order).
 */
export default function Dashboard() {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth: { user: AuthenticatedUser } }>();

    return (
        <AppShell>
            <Head title={t.nav.dashboard} />
            <Card className={styles.card}>
                <Title1>
                    {t.dashboard.welcome}, {props.auth.user.name}
                </Title1>
                <Text block>{t.dashboard.placeholder}</Text>
            </Card>
        </AppShell>
    );
}
