import {
    Avatar,
    Button,
    MenuDivider,
    MenuGroup,
    MenuGroupHeader,
    Menu,
    MenuItem,
    MenuItemRadio,
    MenuList,
    MenuPopover,
    MenuTrigger,
    Toolbar,
    ToolbarButton,
    makeStyles,
    mergeClasses,
    tokens,
} from '@fluentui/react-components';
import { NavigationRegular, SignOutRegular, KeyRegular, ArrowDownloadRegular, AddRegular, DarkThemeRegular } from '@fluentui/react-icons';
import { useState } from 'react';
import { router, usePage } from '@inertiajs/react';
import type { AuthenticatedUser } from '@/Lib/types/identity';
import { useText } from '@/Hooks/useText';
import { usePwaInstall } from '@/Hooks/usePwaInstall';
import { NotificationBell } from '@/Components/Shell/NotificationBell';
import { HelpMenuButton } from '@/Components/Help/HelpMenuButton';
import { ChangePasswordDialog } from '@/Components/Shell/ChangePasswordDialog';
import { BrandMark } from '@/Components/Shell/BrandMark';
import { hasAnyPermission } from '@/Lib/permissions';
import { navItems } from '@/Components/Shell/navItems';
import { useThemeSettings, type UserThemeChoice } from '@/Theme/ThemeContext';

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
    createButton: {
        '@media (max-width: 479px)': {
            minWidth: 'auto',
        },
    },
    brandText: {
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
    const [passwordDialogOpen, setPasswordDialogOpen] = useState(false);
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

    const { choice: themeChoice, setChoice: setThemeChoice, organizationDefault } = useThemeSettings();
    const themeLabels: Record<UserThemeChoice, string> = {
        light: t.nav.themeLight,
        dark: t.nav.themeDark,
        system: t.nav.themeSystem,
        organization: t.nav.themeOrganization,
    };

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
                <span className={mergeClasses(styles.brand, styles.brandText)}>PageJaunes Mailer</span>
            </span>
            <Toolbar>
                {canCreateCampaign && (
                    <Button
                        appearance="primary"
                        className={styles.createButton}
                        icon={<AddRegular />}
                        as="a"
                        href="/campaigns?create=1"
                        aria-label={t.nav.createCampaign}
                        onClick={(e: React.MouseEvent) => {
                            e.preventDefault();
                            router.visit('/campaigns?create=1');
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
                                                <MenuItem key={item.href} icon={<Icon />} onClick={() => (item.external ? (window.location.href = item.href) : router.visit(item.href))}>
                                                    {item.label(t)}
                                                </MenuItem>
                                            );
                                        })}
                                    </MenuGroup>
                                    <MenuDivider />
                                </>
                            )}
                            <Menu
                                checkedValues={{ theme: [themeChoice] }}
                                onCheckedValueChange={(_, data) => {
                                    if (data.name === 'theme' && data.checkedItems[0]) {
                                        setThemeChoice(data.checkedItems[0] as UserThemeChoice);
                                    }
                                }}
                            >
                                <MenuTrigger disableButtonEnhancement>
                                    <MenuItem icon={<DarkThemeRegular />}>
                                        {`${t.nav.theme} : ${themeLabels[themeChoice]}`}
                                    </MenuItem>
                                </MenuTrigger>
                                <MenuPopover>
                                    <MenuList>
                                        <MenuItemRadio name="theme" value="light">{themeLabels.light}</MenuItemRadio>
                                        <MenuItemRadio name="theme" value="dark">{themeLabels.dark}</MenuItemRadio>
                                        <MenuItemRadio name="theme" value="system">{themeLabels.system}</MenuItemRadio>
                                        <MenuItemRadio name="theme" value="organization">
                                            {`${themeLabels.organization} (${themeLabels[organizationDefault]})`}
                                        </MenuItemRadio>
                                    </MenuList>
                                </MenuPopover>
                            </Menu>
                            <MenuDivider />
                            <MenuItem icon={<KeyRegular />} onClick={() => setPasswordDialogOpen(true)}>
                                {t.users.changePassword.menu}
                            </MenuItem>
                            <MenuItem icon={<SignOutRegular />} onClick={handleLogout}>
                                {t.nav.logout}
                            </MenuItem>
                        </MenuList>
                    </MenuPopover>
                </Menu>
            </Toolbar>
            <ChangePasswordDialog open={passwordDialogOpen} onOpenChange={setPasswordDialogOpen} />
        </header>
    );
}
