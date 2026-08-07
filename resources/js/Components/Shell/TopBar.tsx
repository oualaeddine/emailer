import {
    Avatar,
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
import { NavigationRegular, SignOutRegular } from '@fluentui/react-icons';
import { router } from '@inertiajs/react';
import type { AuthenticatedUser } from '@/Lib/types/identity';
import { useText } from '@/Hooks/useText';
import { NotificationBell } from '@/Components/Shell/NotificationBell';
import { BrandMark } from '@/Components/Shell/BrandMark';

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
                <NotificationBell />
                <Menu>
                    <MenuTrigger disableButtonEnhancement>
                        <ToolbarButton
                            icon={<Avatar name={user.name} size={28} color="colorful" />}
                            aria-label={user.name}
                        >
                            <span className={styles.userName}>{user.name}</span>
                        </ToolbarButton>
                    </MenuTrigger>
                    <MenuPopover>
                        <MenuList>
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
