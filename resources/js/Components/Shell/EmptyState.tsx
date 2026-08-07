import type { ComponentType, ReactNode } from 'react';
import { Text, makeStyles, tokens } from '@fluentui/react-components';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        alignItems: 'center',
        justifyContent: 'center',
        textAlign: 'center',
        gap: tokens.spacingVerticalS,
        padding: tokens.spacingVerticalXXL,
        color: tokens.colorNeutralForeground3,
    },
    icon: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'center',
        width: '56px',
        height: '56px',
        borderRadius: tokens.borderRadiusCircular,
        backgroundColor: tokens.colorNeutralBackground3,
        color: tokens.colorNeutralForeground3,
        fontSize: '28px',
        marginBottom: tokens.spacingVerticalXS,
    },
    title: {
        color: tokens.colorNeutralForeground1,
    },
});

interface EmptyStateProps {
    icon: ComponentType;
    title: string;
    body?: string;
    action?: ReactNode;
}

/** Consistent icon + title + body treatment for "nothing here yet" states across the app. */
export function EmptyState({ icon: Icon, title, body, action }: EmptyStateProps) {
    const styles = useStyles();

    return (
        <div className={styles.root}>
            <span className={styles.icon}>
                <Icon />
            </span>
            <Text weight="semibold" size={500} className={styles.title}>
                {title}
            </Text>
            {body && <Text>{body}</Text>}
            {action}
        </div>
    );
}
