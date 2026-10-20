import type { ReactNode } from 'react';
import { Subtitle2, Text, makeStyles, tokens } from '@fluentui/react-components';

const useStyles = makeStyles({
    card: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        backgroundColor: tokens.colorNeutralBackground1,
        borderRadius: tokens.borderRadiusMedium,
        border: `${tokens.strokeWidthThin} solid ${tokens.colorNeutralStroke2}`,
        padding: tokens.spacingVerticalL,
        minWidth: 0,
        boxSizing: 'border-box',
    },
    header: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalS,
    },
    titles: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXXS,
    },
    subtitle: {
        color: tokens.colorNeutralForeground3,
    },
    actions: {
        display: 'flex',
        flexWrap: 'wrap',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
    },
    body: {
        minWidth: 0,
        overflowX: 'auto',
    },
});

export interface ReportSectionProps {
    title: string;
    subtitle?: string;
    actions?: ReactNode;
    children: ReactNode;
}

/** One titled card of a report tab. */
export function ReportSection({ title, subtitle, actions, children }: ReportSectionProps) {
    const styles = useStyles();

    return (
        <section className={styles.card} aria-label={title}>
            <div className={styles.header}>
                <div className={styles.titles}>
                    <Subtitle2 as="h2">{title}</Subtitle2>
                    {subtitle && (
                        <Text size={200} className={styles.subtitle}>
                            {subtitle}
                        </Text>
                    )}
                </div>
                {actions && <div className={styles.actions}>{actions}</div>}
            </div>
            <div className={styles.body}>{children}</div>
        </section>
    );
}

const useGridStyles = makeStyles({
    two: {
        display: 'grid',
        gridTemplateColumns: 'repeat(2, minmax(0, 1fr))',
        gap: tokens.spacingHorizontalL,
        '@media (max-width: 1000px)': {
            gridTemplateColumns: 'minmax(0, 1fr)',
        },
    },
    stack: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalL,
    },
});

/** Two-column responsive grid of sections. */
export function SectionGrid({ children }: { children: ReactNode }) {
    return <div className={useGridStyles().two}>{children}</div>;
}

/** Vertical stack of a tab's sections. */
export function TabStack({ children }: { children: ReactNode }) {
    return <div className={useGridStyles().stack}>{children}</div>;
}
