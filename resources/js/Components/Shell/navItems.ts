import type { ComponentType } from 'react';
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
    ShieldProhibitedRegular,
    ShieldProhibitedFilled,
    bundleIcon,
} from '@fluentui/react-icons';
import type { TranslationDictionary } from '@/Lib/i18n/fr';

const HomeIcon = bundleIcon(HomeFilled, HomeRegular);
const PeopleIcon = bundleIcon(PeopleFilled, PeopleRegular);
const SettingsIcon = bundleIcon(SettingsFilled, SettingsRegular);
const BuildingIcon = bundleIcon(BuildingFilled, BuildingRegular);
const ContactCardIcon = bundleIcon(ContactCardFilled, ContactCardRegular);
const MailIcon = bundleIcon(MailFilled, MailRegular);
const DocumentIcon = bundleIcon(DocumentFilled, DocumentRegular);
const ServerIcon = bundleIcon(ServerFilled, ServerRegular);
const ShieldProhibitedIcon = bundleIcon(ShieldProhibitedFilled, ShieldProhibitedRegular);

/**
 * docs/08-navigation.md §8.2 — Primary Navigation Tree.
 * docs/42-parallel-execution-plan.md §42.5/§42.11 — extracted out of
 * NavRail.tsx so later work packages append their own entry at the
 * bottom of this array instead of editing the component. Only the
 * items the currently-delivered modules expose are wired here; the
 * remaining nav tree is filled in by each consuming module as it lands
 * (§8.4 — items are hidden entirely, not merely disabled, when the
 * current user lacks every permission within them).
 */
export interface NavItem {
    href: string;
    icon: ComponentType;
    label: (t: TranslationDictionary) => string;
    /** 'exact' matches `url === href`; 'prefix' matches `url.startsWith(href)`. */
    match: 'exact' | 'prefix';
    /** Item is shown if the user holds ANY of these permissions; omit to always show. */
    permissions?: string[];
}

export const navItems: NavItem[] = [
    {
        href: '/dashboard',
        icon: HomeIcon,
        label: (t) => t.nav.dashboard,
        match: 'prefix',
    },
    {
        href: '/compose',
        icon: MailIcon,
        label: (t) => t.composer.title,
        match: 'exact',
        permissions: ['composer.compose'],
    },
    {
        href: '/templates',
        icon: DocumentIcon,
        label: (t) => t.nav.templates,
        match: 'exact',
        permissions: ['templates.view'],
    },
    {
        href: '/admin/users',
        icon: PeopleIcon,
        label: (t) => t.nav.users,
        match: 'prefix',
        permissions: ['users.manage'],
    },
    {
        href: '/settings/branding',
        icon: SettingsIcon,
        label: (t) => t.nav.settings,
        match: 'prefix',
        permissions: ['settings.branding_only', 'settings.manage'],
    },
    {
        href: '/smtp',
        icon: ServerIcon,
        label: (t) => t.smtp.title,
        match: 'exact',
        permissions: ['smtp.view'],
    },
    {
        href: '/recipients',
        icon: ContactCardIcon,
        label: (t) => t.nav.recipients,
        match: 'exact',
        permissions: ['recipients.view'],
    },
    {
        href: '/recipients/import/pagejaunes',
        icon: BuildingIcon,
        label: (t) => t.nav.pagejaunes,
        match: 'prefix',
        permissions: ['recipients.import'],
    },
    {
        href: '/recipients/import',
        icon: PeopleIcon,
        label: (t) => t.nav.import,
        match: 'exact',
        permissions: ['recipients.import'],
    },
    {
        href: '/suppression',
        icon: ShieldProhibitedIcon,
        label: (t) => t.nav.suppression,
        match: 'exact',
        permissions: ['suppression.view'],
    },
];
