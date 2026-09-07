import {
    Button,
    makeStyles,
    tokens,
    Subtitle2,
    Caption1,
} from '@fluentui/react-components';
import { ArrowDownloadRegular, DismissRegular } from '@fluentui/react-icons';
import { usePwaInstall } from '@/Hooks/usePwaInstall';
import { useText } from '@/Hooks/useText';
import { BrandMark } from '@/Components/Shell/BrandMark';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalM,
        padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalL}`,
        backgroundColor: tokens.colorBrandBackground2,
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorBrandStroke2,
        borderLeftWidth: tokens.strokeWidthThick,
        borderLeftStyle: 'solid',
        borderLeftColor: tokens.colorBrandStroke1,
        animationName: {
            from: { opacity: 0, transform: 'translateY(-8px)' },
            to: { opacity: 1, transform: 'translateY(0)' },
        },
        animationDuration: tokens.durationNormal,
        animationTimingFunction: tokens.curveDecelerateMax,
        '@media (max-width: 639px)': {
            flexDirection: 'column',
            alignItems: 'flex-start',
            padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalM}`,
        },
    },
    content: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalM,
        minWidth: 0,
    },
    textGroup: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
    title: {
        color: tokens.colorNeutralForeground1,
        fontWeight: tokens.fontWeightSemibold,
    },
    subtitle: {
        color: tokens.colorNeutralForeground2,
    },
    actions: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        flexShrink: 0,
        '@media (max-width: 639px)': {
            width: '100%',
            justifyContent: 'flex-end',
            marginTop: tokens.spacingVerticalXS,
        },
    },
});

export function PwaInstallBanner() {
    const styles = useStyles();
    const t = useText();
    const { showBanner, install, dismiss } = usePwaInstall();

    if (!showBanner) {
        return null;
    }

    return (
        <aside className={styles.root} role="region" aria-label={t.pwa.installTitle}>
            <div className={styles.content}>
                <BrandMark size={28} />
                <div className={styles.textGroup}>
                    <Subtitle2 className={styles.title}>{t.pwa.installTitle}</Subtitle2>
                    <Caption1 className={styles.subtitle}>{t.pwa.installPrompt}</Caption1>
                </div>
            </div>
            <div className={styles.actions}>
                <Button
                    appearance="primary"
                    size="small"
                    icon={<ArrowDownloadRegular />}
                    onClick={() => void install()}
                >
                    {t.pwa.installButton}
                </Button>
                <Button
                    appearance="subtle"
                    size="small"
                    icon={<DismissRegular />}
                    aria-label={t.pwa.dismissButton}
                    onClick={dismiss}
                >
                    {t.pwa.dismissButton}
                </Button>
            </div>
        </aside>
    );
}
