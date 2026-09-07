import { Head, router, usePage } from '@inertiajs/react';
import {
    Button,
    Card,
    Subtitle1,
    Title1,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { ShieldProhibitedRegular, HomeRegular, ArrowLeftRegular } from '@fluentui/react-icons';
import { AppShell } from '@/Components/Shell/AppShell';
import type { AuthenticatedUser } from '@/Lib/types/identity';
import { useText } from '@/Hooks/useText';

const useStyles = makeStyles({
    container: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '60vh',
        padding: tokens.spacingVerticalXXL,
        textAlign: 'center',
    },
    standalonePage: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        minHeight: '100vh',
        padding: tokens.spacingHorizontalM,
        boxSizing: 'border-box',
        backgroundImage: `radial-gradient(circle at 50% 0%, ${tokens.colorBrandBackground2} 0%, ${tokens.colorNeutralBackground3} 55%)`,
    },
    card: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        gap: tokens.spacingVerticalL,
        padding: tokens.spacingVerticalXXL,
        maxWidth: '520px',
        width: '100%',
        boxShadow: tokens.shadow16,
        borderRadius: tokens.borderRadiusLarge,
    },
    iconWrapper: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: '80px',
        height: '80px',
        borderRadius: tokens.borderRadiusCircular,
        backgroundColor: tokens.colorPaletteRedBackground2,
        color: tokens.colorPaletteRedForeground1,
        fontSize: '40px',
    },
    actions: {
        display: 'flex',
        gap: tokens.spacingHorizontalM,
        marginTop: tokens.spacingVerticalM,
        flexWrap: 'wrap',
        justifyContent: 'center',
    },
    codeBadge: {
        fontSize: tokens.fontSizeBase200,
        fontWeight: tokens.fontWeightSemibold,
        color: tokens.colorNeutralForeground3,
        textTransform: 'uppercase',
        letterSpacing: '1px',
    },
});

interface ErrorProps {
    status?: number;
    message?: string;
}

export default function ErrorPage({ status = 403, message }: ErrorProps) {
    const styles = useStyles();
    const t = useText();
    const { props } = usePage<{ auth?: { user?: AuthenticatedUser } }>();
    const isAuthenticated = Boolean(props?.auth?.user);

    const title = status === 403
        ? 'Accès refusé'
        : status === 404
        ? 'Page introuvable'
        : 'Erreur inattendue';

    const defaultMessage = status === 403
        ? "Vous n'avez pas les permissions requises pour accéder à cette ressource. Veuillez vous rapprocher d'un administrateur."
        : status === 404
        ? "La page que vous recherchez n'existe pas ou a été déplacée."
        : "Une erreur est survenue lors du traitement de votre requête.";

    const content = (
        <div className={isAuthenticated ? styles.container : styles.standalonePage}>
            <Card className={styles.card}>
                <div className={styles.iconWrapper}>
                    <ShieldProhibitedRegular />
                </div>
                <span className={styles.codeBadge}>Erreur HTTP {status}</span>
                <Title1>{title}</Title1>
                <Subtitle1>{message || defaultMessage}</Subtitle1>
                <div className={styles.actions}>
                    <Button
                        appearance="primary"
                        icon={<HomeRegular />}
                        onClick={() => router.visit('/dashboard')}
                    >
                        {t.nav.dashboard}
                    </Button>
                    <Button
                        appearance="secondary"
                        icon={<ArrowLeftRegular />}
                        onClick={() => window.history.back()}
                    >
                        Page précédente
                    </Button>
                </div>
            </Card>
        </div>
    );

    if (isAuthenticated) {
        return (
            <AppShell>
                <Head title={`${title} (${status})`} />
                {content}
            </AppShell>
        );
    }

    return (
        <>
            <Head title={`${title} (${status})`} />
            {content}
        </>
    );
}
