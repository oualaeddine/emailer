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
    MegaphoneLoudRegular,
    MegaphoneLoudFilled,
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
const MegaphoneLoudIcon = bundleIcon(MegaphoneLoudFilled, MegaphoneLoudRegular);

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
/**
 * Groups mirror how the modules are used day-to-day rather than the order
 * modules were built in (docs/42's build order): Tableau de bord stands
 * alone as the entry point, then Messagerie (day-to-day sending),
 * Campagnes (bulk sends + their results), Contacts (recipient data
 * lifecycle: list → PageJaunes sourcing → import → suppression), and
 * Administration (infrastructure/config, used far less often) last.
 */
export type NavGroup = 'messaging' | 'campaigns' | 'contacts' | 'administration';

export interface NavItem {
    href: string;
    icon: ComponentType;
    label: (t: TranslationDictionary) => string;
    /** 'exact' matches `url === href`; 'prefix' matches `url.startsWith(href)`. */
    match: 'exact' | 'prefix';
    /** Omit for the standalone Tableau de bord entry above all groups. */
    group?: NavGroup;
    /** Item is shown if the user holds ANY of these permissions; omit to always show. */
    permissions?: string[];
}

export const navGroupOrder: NavGroup[] = ['messaging', 'campaigns', 'contacts', 'administration'];

export const navGroupLabels: Record<NavGroup, (t: TranslationDictionary) => string> = {
    messaging: (t) => t.nav.groups.messaging,
    campaigns: (t) => t.nav.groups.campaigns,
    contacts: (t) => t.nav.groups.contacts,
    administration: (t) => t.nav.groups.administration,
};

export const navItems: NavItem[] = [
    {
        href: '/dashboard',
        icon: HomeIcon,
        label: (t) => t.nav.dashboard,
        match: 'prefix',
    },

    // Messagerie — composing and reading mail day-to-day.
    {
        href: '/compose',
        icon: MailIcon,
        label: (t) => t.composer.title,
        match: 'exact',
        group: 'messaging',
        permissions: ['composer.compose'],
    },
    {
        href: '/mailbox',
        // Reuses the Compose entry's MailIcon rather than guessing at an
        // unverified `MailInboxRegular`/`MailInboxFilled` import — this repo
        // has no npm/tsc available to confirm icon names exist before the
        // wave's build gate (docs/42-parallel-execution-plan.md §42.9).
        icon: MailIcon,
        label: (t) => t.nav.mailbox,
        match: 'exact',
        group: 'messaging',
        permissions: ['mailbox.view_own', 'mailbox.view_all'],
    },
    {
        href: '/templates',
        icon: DocumentIcon,
        label: (t) => t.nav.templates,
        match: 'exact',
        group: 'messaging',
        permissions: ['templates.view'],
    },

    // Campagnes — bulk sends and their results.
    {
        href: '/campaigns',
        icon: MegaphoneLoudIcon,
        label: (t) => t.nav.campaigns,
        match: 'exact',
        group: 'campaigns',
        permissions: ['campaigns.view'],
    },
    {
        href: '/reporting',
        // Reuses the Templates entry's DocumentIcon (already imported/verified
        // above) rather than guessing an unverified icon export name — same
        // reasoning as the `/mailbox` entry's MailIcon reuse (this repo has no
        // npm/tsc available to confirm icon names exist before the wave's
        // build gate, docs/42-parallel-execution-plan.md §42.9).
        icon: DocumentIcon,
        label: (t) => t.nav.reporting,
        match: 'exact',
        group: 'campaigns',
        permissions: ['reporting.view'],
    },

    // Contacts — recipient data lifecycle: list, source, import, suppress.
    {
        href: '/recipients',
        icon: ContactCardIcon,
        label: (t) => t.nav.recipients,
        match: 'exact',
        group: 'contacts',
        permissions: ['recipients.view'],
    },
    {
        href: '/recipients/import/pagejaunes',
        icon: BuildingIcon,
        label: (t) => t.nav.pagejaunes,
        match: 'prefix',
        group: 'contacts',
        permissions: ['recipients.import'],
    },
    {
        href: '/recipients/import',
        icon: PeopleIcon,
        label: (t) => t.nav.import,
        match: 'exact',
        group: 'contacts',
        permissions: ['recipients.import'],
    },
    {
        href: '/suppression',
        icon: ShieldProhibitedIcon,
        label: (t) => t.nav.suppression,
        match: 'exact',
        group: 'contacts',
        permissions: ['suppression.view'],
    },

    // Administration — infrastructure and account config, used less often.
    {
        href: '/smtp',
        icon: ServerIcon,
        label: (t) => t.smtp.title,
        match: 'exact',
        group: 'administration',
        permissions: ['smtp.view'],
    },
    {
        href: '/admin/users',
        icon: PeopleIcon,
        label: (t) => t.nav.users,
        match: 'prefix',
        group: 'administration',
        permissions: ['users.manage'],
    },
    {
        href: '/settings/branding',
        icon: SettingsIcon,
        label: (t) => t.nav.settings,
        match: 'prefix',
        group: 'administration',
        permissions: ['settings.branding_only', 'settings.manage'],
    },
    {
        href: '/admin/audit-log',
        // Reuses the Templates entry's DocumentIcon (already imported above)
        // rather than guessing an unverified icon export name, mirroring
        // the `/mailbox` entry's rationale above.
        icon: DocumentIcon,
        label: (t) => t.nav.audit,
        match: 'exact',
        group: 'administration',
        permissions: ['audit.view'],
    },
];
