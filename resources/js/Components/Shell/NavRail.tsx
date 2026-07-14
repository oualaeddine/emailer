import { makeStyles, tokens } from '@fluentui/react-components';
import {
    HomeRegular,
    HomeFilled,
    PeopleRegular,
    PeopleFilled,
    SettingsRegular,
    SettingsFilled,
    BuildingRegular,
    BuildingFilled,
    ContactCardRegular,
    ContactCardFilled,
    MailRegular,
    MailFilled,
    DocumentRegular,
    DocumentFilled,
    ServerRegular,
    ServerFilled,
    bundleIcon,
} from '@fluentui/react-icons';
import { Link, usePage } from '@inertiajs/react';
import { useText } from '@/Hooks/useText';
import { hasPermission } from '@/Lib/permissions';

const HomeIcon = bundleIcon(HomeFilled, HomeRegular);
const PeopleIcon = bundleIcon(PeopleFilled, PeopleRegular);
const SettingsIcon = bundleIcon(SettingsFilled, SettingsRegular);
const BuildingIcon = bundleIcon(BuildingFilled, BuildingRegular);
const ContactCardIcon = bundleIcon(ContactCardFilled, ContactCardRegular);
const MailIcon = bundleIcon(MailFilled, MailRegular);
const DocumentIcon = bundleIcon(DocumentFilled, DocumentRegular);
const ServerIcon = bundleIcon(ServerFilled, ServerRegular);

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
 * Only the items the Identity module actually delivers are wired here;
 * the remaining nav tree is filled in by each consuming module as it
 * lands (§8.4 — items are hidden entirely, not merely disabled, when the
 * current user lacks every permission within them).
 */
export function NavRail() {
    const styles = useStyles();
    const t = useText();
    const { url, props } = usePage<{ auth: { permissions: string[] } }>();
    const permissions = props.auth.permissions;

    const isActive = (path: string) => url.startsWith(path);

    return (
        <nav className={styles.root}>
            <Link
                href="/dashboard"
                className={`${styles.item} ${isActive('/dashboard') ? styles.itemActive : ''}`}
            >
                <HomeIcon />
                {t.nav.dashboard}
            </Link>
            {hasPermission(permissions, 'composer.compose') && (
                <Link href="/compose" className={`${styles.item} ${url === '/compose' ? styles.itemActive : ''}`}>
                    <MailIcon />
                    {t.composer.title}
                </Link>
            )}
            {hasPermission(permissions, 'templates.view') && (
                <Link href="/templates" className={`${styles.item} ${url === '/templates' ? styles.itemActive : ''}`}>
                    <DocumentIcon />
                    {t.nav.templates}
                </Link>
            )}
            {hasPermission(permissions, 'users.manage') && (
                <Link
                    href="/admin/users"
                    className={`${styles.item} ${isActive('/admin/users') ? styles.itemActive : ''}`}
                >
                    <PeopleIcon />
                    {t.nav.users}
                </Link>
            )}
            {(hasPermission(permissions, 'settings.branding_only') ||
                hasPermission(permissions, 'settings.manage')) && (
                <Link
                    href="/settings/branding"
                    className={`${styles.item} ${isActive('/settings/branding') ? styles.itemActive : ''}`}
                >
                    <SettingsIcon />
                    {t.nav.settings}
                </Link>
            )}
            {hasPermission(permissions, 'smtp.view') && (
                <Link href="/smtp" className={`${styles.item} ${url === '/smtp' ? styles.itemActive : ''}`}>
                    <ServerIcon />
                    {t.smtp.title}
                </Link>
            )}
            {hasPermission(permissions, 'recipients.view') && (
                <Link
                    href="/recipients"
                    className={`${styles.item} ${url === '/recipients' ? styles.itemActive : ''}`}
                >
                    <ContactCardIcon />
                    {t.nav.recipients}
                </Link>
            )}
            {hasPermission(permissions, 'recipients.import') && (
                <Link
                    href="/recipients/import/pagejaunes"
                    className={`${styles.item} ${isActive('/recipients/import/pagejaunes') ? styles.itemActive : ''}`}
                >
                    <BuildingIcon />
                    {t.nav.pagejaunes}
                </Link>
            )}
            {hasPermission(permissions, 'recipients.import') && (
                <Link
                    href="/recipients/import"
                    className={`${styles.item} ${url === '/recipients/import' ? styles.itemActive : ''}`}
                >
                    <PeopleIcon />
                    {t.nav.import}
                </Link>
            )}
        </nav>
    );
}
