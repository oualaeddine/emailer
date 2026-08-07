import { makeStyles, tokens } from '@fluentui/react-components';
import { Link, usePage } from '@inertiajs/react';
import { useText } from '@/Hooks/useText';
import { hasAnyPermission } from '@/Lib/permissions';
import { navGroupLabels, navGroupOrder, navItems, type NavGroup, type NavItem } from './navItems';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
        width: '220px',
        padding: tokens.spacingVerticalM,
        borderRightWidth: tokens.strokeWidthThin,
        borderRightStyle: 'solid',
        borderRightColor: tokens.colorNeutralStroke2,
        backgroundColor: tokens.colorNeutralBackground2,
        overflowY: 'auto',
    },
    group: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalXS,
    },
    groupLabel: {
        padding: `0 ${tokens.spacingHorizontalM}`,
        color: tokens.colorNeutralForeground3,
        textTransform: 'uppercase',
        letterSpacing: '0.04em',
        fontSize: tokens.fontSizeBase200,
        fontWeight: tokens.fontWeightSemibold,
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
 * Renders from the shared `navItems` data array (see ./navItems.ts),
 * grouped under `navGroupOrder`'s section headers so later modules append
 * entries to a group there instead of editing this component
 * (docs/42-parallel-execution-plan.md §42.5/§42.11 — items are hidden
 * entirely, not merely disabled, when the current user lacks every
 * permission within them, per §8.4). A group renders only if at least one
 * of its items survives the permission check.
 */
export function NavRail() {
    const styles = useStyles();
    const t = useText();
    const { url, props } = usePage<{ auth: { permissions: string[] } }>();
    const permissions = props.auth.permissions;

    const visible = (item: NavItem) => !item.permissions || hasAnyPermission(permissions, ...item.permissions);

    function renderItem(item: NavItem) {
        const Icon = item.icon;
        const isActive = item.match === 'exact' ? url === item.href : url.startsWith(item.href);

        return (
            <Link key={item.href} href={item.href} className={`${styles.item} ${isActive ? styles.itemActive : ''}`}>
                <Icon />
                {item.label(t)}
            </Link>
        );
    }

    const standaloneItems = navItems.filter((item) => !item.group && visible(item));
    const groupedItems = (group: NavGroup) => navItems.filter((item) => item.group === group && visible(item));

    return (
        <nav className={styles.root}>
            {standaloneItems.map(renderItem)}
            {navGroupOrder.map((group) => {
                const items = groupedItems(group);

                if (items.length === 0) {
                    return null;
                }

                return (
                    <div key={group} className={styles.group}>
                        <span className={styles.groupLabel}>{navGroupLabels[group](t)}</span>
                        {items.map(renderItem)}
                    </div>
                );
            })}
        </nav>
    );
}
