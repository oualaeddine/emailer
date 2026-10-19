import {
    Avatar,
    Button,
    MenuDivider,
    MenuGroup,
    MenuGroupHeader,
    Menu,
    MenuItem,
    MenuList,
    MenuPopover,
    MenuTrigger,
    Toolbar,
    ToolbarButton,
    makeStyles,
    tokens,
} from '@fluentui/react-components';
import { NavigationRegular, SignOutRegular, ArrowDownloadRegular, AddRegular } from '@fluentui/react-icons';
import { router, usePage } from '@inertiajs/react';
import type { AuthenticatedUser } from '@/Lib/types/identity';
import { useText } from '@/Hooks/useText';
import { usePwaInstall } from '@/Hooks/usePwaInstall';
import { NotificationBell } from '@/Components/Shell/NotificationBell';
import { HelpMenuButton } from '@/Components/Help/HelpMenuButton';
import { BrandMark } from '@/Components/Shell/BrandMark';
import { hasAnyPermission } from '@/Lib/permissions';
import { navItems } from '@/Components/Shell/navItems';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        gap: tokens.spacingHorizontalS,
        padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalL}`,
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorNeutralStroke2,
        backgroundColor: tokens.colorNeutralBackground1,
        '@media (max-width: 639px)': {
            padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalM}`,
        },
    },
    brandGroup: {
        display: 'flex',
        alignItems: 'center',
        gap: tokens.spacingHorizontalS,
        minWidth: 0,
    },
    menuButton: {
        display: 'none',
        '@media (max-width: 639px)': {
            display: 'inline-flex',
        },
    },
    brand: {
        fontWeight: tokens.fontWeightSemibold,
        fontSize: tokens.fontSizeBase400,
        color: tokens.colorNeutralForeground1,
        whiteSpace: 'nowrap',
        overflow: 'hidden',
        textOverflow: 'ellipsis',
    },
    userName: {
        '@media (max-width: 479px)': {
            display: 'none',
        },
    },
});

interface TopBarProps {
    user: AuthenticatedUser;
    onToggleNav: () => void;
}

export function TopBar({ user, onToggleNav }: TopBarProps) {
    const styles = useStyles();
    const t = useText();
    const { isInstallable, install } = usePwaInstall();
    const { props } = usePage<{ auth: { permissions: string[] } }>();
    const permissions = props.auth.permissions;

    // docs/43 §43.3 — Paramètres live in the account menu, not the rail.
    const settingsItems = navItems.filter(
        (item) =>
            item.group === 'settings' &&
            !item.hidden &&
            (!item.permissions || hasAnyPermission(permissions, ...item.permissions)),
    );
    const canCreateCampaign = hasAnyPermission(permissions, 'campaigns.create') && hasAnyPermission(permissions, 'campaigns.view');

    function handleLogout() {
        router.post('/logout');
    }

    return (
        <header className={styles.root}>
            <span className={styles.brandGroup}>
                <ToolbarButton
                    className={styles.menuButton}
                    icon={<NavigationRegular />}
                    aria-label={t.nav.openMenu}
                    onClick={onToggleNav}
                />
                <BrandMark size={28} />
                <span className={styles.brand}>PageJaunes Mailer</span>
            </span>
            <Toolbar>
                {canCreateCampaign && (
                    <Button
                        appearance="primary"
                        icon={<AddRegular />}
                        as="a"
                        href="/campaigns"
                        onClick={(e: React.MouseEvent) => {
                            e.preventDefault();
                            router.visit('/campaigns');
                        }}
                    >
                        <span className={styles.userName}>{t.nav.createCampaign}</span>
                    </Button>
                )}
                {isInstallable && (
                    <ToolbarButton
                        icon={<ArrowDownloadRegular />}
                        aria-label={t.pwa.installTitle}
                        title={t.pwa.installTitle}
                        onClick={() => void install()}
                    />
                )}
                <HelpMenuButton />
                <NotificationBell />
                <Menu>
                    <MenuTrigger disableButtonEnhancement>
                        <ToolbarButton
                            icon={<Avatar name={user.name} size={28} color="colorful" />}
                            aria-label={`${t.nav.accountMenu} — ${user.name}`}
                        >
                            <span className={styles.userName}>{user.name}</span>
                        </ToolbarButton>
                    </MenuTrigger>
                    <MenuPopover>
                        <MenuList>
                            {settingsItems.length > 0 && (
                                <>
                                    <MenuGroup>
                                        <MenuGroupHeader>{t.nav.groups.settings}</MenuGroupHeader>
                                        {settingsItems.map((item) => {
                                            const Icon = item.icon;
                                            return (
                                                <MenuItem key={item.href} icon={<Icon />} onClick={() => router.visit(item.href)}>
                                                    {item.label(t)}
                                                </MenuItem>
                                            );
                                        })}
                                    </MenuGroup>
                                    <MenuDivider />
                                </>
                            )}
                            <MenuItem icon={<SignOutRegular />} onClick={handleLogout}>
                                {t.nav.logout}
                            </MenuItem>
                        </MenuList>
                    </MenuPopover>
                </Menu>
            </Toolbar>
        </header>
    );
}
