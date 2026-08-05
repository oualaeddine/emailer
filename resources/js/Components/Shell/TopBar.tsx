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
import { SignOutRegular } from '@fluentui/react-icons';
import { router } from '@inertiajs/react';
import type { AuthenticatedUser } from '@/Lib/types/identity';
import { useText } from '@/Hooks/useText';
import { NotificationBell } from '@/Components/Shell/NotificationBell';

const useStyles = makeStyles({
    root: {
        display: 'flex',
        alignItems: 'center',
        justifyContent: 'space-between',
        padding: `${tokens.spacingVerticalS} ${tokens.spacingHorizontalL}`,
        borderBottomWidth: tokens.strokeWidthThin,
        borderBottomStyle: 'solid',
        borderBottomColor: tokens.colorNeutralStroke2,
        backgroundColor: tokens.colorNeutralBackground1,
    },
    brand: {
        fontWeight: tokens.fontWeightSemibold,
        fontSize: tokens.fontSizeBase400,
        color: tokens.colorNeutralForeground1,
    },
});

interface TopBarProps {
    user: AuthenticatedUser;
}

export function TopBar({ user }: TopBarProps) {
    const styles = useStyles();
    const t = useText();

    function handleLogout() {
        router.post('/logout');
    }

    return (
        <header className={styles.root}>
            <span className={styles.brand}>PageJaunes Mailer</span>
            <Toolbar>
                <NotificationBell />
                <Menu>
                    <MenuTrigger disableButtonEnhancement>
                        <ToolbarButton
                            icon={<Avatar name={user.name} size={28} />}
                            aria-label={user.name}
                        >
                            {user.name}
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
