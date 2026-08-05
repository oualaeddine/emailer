import { makeStyles, tokens } from '@fluentui/react-components';
import { Link, usePage } from '@inertiajs/react';
import { useText } from '@/Hooks/useText';
import { hasAnyPermission } from '@/Lib/permissions';
import { navItems } from './navItems';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
        width: '220px',
        padding: tokens.spacingVerticalM,
        borderRightWidth: tokens.strokeWidthThin,
        borderRightStyle: 'solid',
        borderRightColor: tokens.colorNeutralStroke2,
        backgroundColor: tokens.colorNeutralBackground2,
    },
    item: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        padding: `${tokens.spacingVerticalSNudge} ${tokens.spacingHorizontalM}`,
        borderRadius: tokens.borderRadiusMedium,
        color: tokens.colorNeutralForeground2,
        textDecorationLine: 'none',
        fontSize: tokens.fontSizeBase300,
    },
    itemActive: {
        backgroundColor: tokens.colorBrandBackground2,
        color: tokens.colorBrandForeground2,
        borderLeftWidth: tokens.strokeWidthThick,
        borderLeftStyle: 'solid',
        borderLeftColor: tokens.colorBrandStroke1,
    },
});

/**
 * docs/08-navigation.md §8.2 — Primary Navigation Tree.
 * Renders from the shared `navItems` data array (see ./navItems.ts) so
 * later modules append entries there instead of editing this component
 * (docs/42-parallel-execution-plan.md §42.5/§42.11 — items are hidden
 * entirely, not merely disabled, when the current user lacks every
 * permission within them, per §8.4).
 */
export function NavRail() {
    const styles = useStyles();
    const t = useText();
    const { url, props } = usePage<{ auth: { permissions: string[] } }>();
    const permissions = props.auth.permissions;

    return (
        <nav className={styles.root}>
            {navItems.map((item) => {
                if (item.permissions && !hasAnyPermission(permissions, ...item.permissions)) {
                    return null;
                }

                const Icon = item.icon;
                const isActive = item.match === 'exact' ? url === item.href : url.startsWith(item.href);

                return (
                    <Link
                        key={item.href}
                        href={item.href}
                        className={`${styles.item} ${isActive ? styles.itemActive : ''}`}
                    >
                        <Icon />
                        {item.label(t)}
                    </Link>
                );
            })}
        </nav>
    );
}
