import {
    DrawerBody,
    DrawerHeader,
    DrawerHeaderTitle,
    OverlayDrawer,
    Button,
    Tooltip,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { DismissRegular } from '@fluentui/react-icons';
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
        flexShrink: 0,
        padding: tokens.spacingVerticalM,
        borderRightWidth: tokens.strokeWidthThin,
        borderRightStyle: 'solid',
        borderRightColor: tokens.colorNeutralStroke2,
        backgroundColor: tokens.colorNeutralBackground2,
        overflowY: 'auto',
        overflowX: 'hidden',
        // docs/07-ui-design.md §7.7 — tablet: icon-only rail; mobile: the
        // persistent rail hides entirely in favor of the off-canvas drawer
        // below, opened from TopBar's hamburger button.
        '@media (max-width: 1023px)': {
            width: '64px',
            padding: `${tokens.spacingVerticalM} ${tokens.spacingHorizontalXS}`,
        },
        '@media (max-width: 639px)': {
            display: 'none',
        },
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
        whiteSpace: 'nowrap',
        '@media (max-width: 1023px)': {
            display: 'none',
        },
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
        whiteSpace: 'nowrap',
    },
    itemCollapsed: {
        '@media (max-width: 1023px)': {
            justifyContent: 'center',
            padding: tokens.spacingVerticalSNudge,
        },
    },
    itemLabel: {
        overflow: 'hidden',
        textOverflow: 'ellipsis',
        '@media (max-width: 1023px)': {
            display: 'none',
        },
    },
    itemActive: {
        backgroundColor: tokens.colorBrandBackground2,
        color: tokens.colorBrandForeground2,
        borderLeftWidth: tokens.strokeWidthThick,
        borderLeftStyle: 'solid',
        borderLeftColor: tokens.colorBrandStroke1,
    },
    drawerBody: {
        display: 'flex',
        flexDirection: 'column',
        gap: tokens.spacingVerticalM,
    },
});

interface NavRailProps {
    mobileOpen: boolean;
    onMobileOpenChange: (open: boolean) => void;
}

/**
 * docs/08-navigation.md §8.2 — Primary Navigation Tree.
 * Renders from the shared `navItems` data array (see ./navItems.ts),
 * grouped under `navGroupOrder`'s section headers so later modules append
 * entries to a group there instead of editing this component
 * (docs/42-parallel-execution-plan.md §42.5/§42.11 — items are hidden
 * entirely, not merely disabled, when the current user lacks every
 * permission within them, per §8.4). A group renders only if at least one
 * of its items survives the permission check.
 *
 * docs/07-ui-design.md §7.7 — the same item list backs three presentations:
 * a full labeled rail (desktop), an icon-only rail with hover tooltips
 * (tablet, via CSS — no separate markup), and an off-canvas drawer
 * (mobile, opened from TopBar's hamburger button).
 */
export function NavRail({ mobileOpen, onMobileOpenChange }: NavRailProps) {
    const styles = useStyles();
    const t = useText();
    const { url, props } = usePage<{ auth: { permissions: string[] } }>();
    const permissions = props.auth.permissions;

    const visible = (item: NavItem) => !item.permissions || hasAnyPermission(permissions, ...item.permissions);

    function renderItem(item: NavItem, options?: { collapsible?: boolean; onNavigate?: () => void }) {
        const Icon = item.icon;
        const isActive = item.match === 'exact' ? url === item.href : url.startsWith(item.href);
        const label = item.label(t);

        const link = (
            <Link
                key={item.href}
                href={item.href}
                onClick={options?.onNavigate}
                className={`${styles.item} ${options?.collapsible ? styles.itemCollapsed : ''} ${isActive ? styles.itemActive : ''}`}
            >
                <Icon />
                <span className={options?.collapsible ? styles.itemLabel : undefined}>{label}</span>
            </Link>
        );

        if (!options?.collapsible) {
            return link;
        }

        return (
            <Tooltip key={item.href} content={label} relationship="label" withArrow positioning="after">
                {link}
            </Tooltip>
        );
    }

    const standaloneItems = navItems.filter((item) => !item.group && visible(item));
    const groupedItems = (group: NavGroup) => navItems.filter((item) => item.group === group && visible(item));

    function renderNavContent(options?: { collapsible?: boolean; onNavigate?: () => void }) {
        return (
            <>
                {standaloneItems.map((item) => renderItem(item, options))}
                {navGroupOrder.map((group) => {
                    const items = groupedItems(group);

                    if (items.length === 0) {
                        return null;
                    }

                    return (
                        <div key={group} className={styles.group}>
                            <span className={styles.groupLabel}>{navGroupLabels[group](t)}</span>
                            {items.map((item) => renderItem(item, options))}
                        </div>
                    );
                })}
            </>
        );
    }

    return (
        <>
            <nav className={styles.root} aria-label={t.nav.menuTitle}>
                {renderNavContent({ collapsible: true })}
            </nav>

            <OverlayDrawer
                open={mobileOpen}
                onOpenChange={(_, data) => onMobileOpenChange(data.open)}
                position="start"
            >
                <DrawerHeader>
                    <DrawerHeaderTitle
                        action={
                            <Button
                                appearance="subtle"
                                aria-label={t.common.close}
                                icon={<DismissRegular />}
                                onClick={() => onMobileOpenChange(false)}
                            />
                        }
                    >
                        {t.nav.menuTitle}
                    </DrawerHeaderTitle>
                </DrawerHeader>
                <DrawerBody className={styles.drawerBody}>
                    {renderNavContent({ onNavigate: () => onMobileOpenChange(false) })}
                </DrawerBody>
            </OverlayDrawer>
        </>
    );
}
