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
    QuestionCircleRegular,
    QuestionCircleFilled,
    bundleIcon,
} from '@fluentui/react-icons';
import type { TranslationDictionary } from '@/Lib/i18n/fr';
import { hasAnyPermission } from '@/Lib/permissions';

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
const QuestionCircleIcon = bundleIcon(QuestionCircleFilled, QuestionCircleRegular);

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
 * docs/43-brevo-parity-plan.md §43.3 — Target Information Architecture.
 * Accueil stands alone, then Contacts / Marketing / Automatisations /
 * Transactionnel sections in the rail. The `settings` group is NOT rendered
 * in the rail: TopBar shows it in the account menu (top-right).
 * The first three legacy groups are kept in the type only because the help
 * registry (Lib/docs/registry.ts) still groups topics with them.
 *
 * WP-40N pre-registers every §43.3 entry. Entries whose page does not exist
 * yet carry `hidden: true`; the work package that builds the page only
 * deletes that flag on its own entry (this file is frozen after WP-40N).
 */
export type NavGroup =
    | 'messaging'
    | 'campaigns'
    | 'contacts'
    | 'administration'
    | 'marketing'
    | 'automations'
    | 'transactional'
    | 'settings';

export interface NavItem {
    href: string;
    icon: ComponentType;
    label: (t: TranslationDictionary) => string;
    /** 'exact' matches `url === href`; 'prefix' matches `url.startsWith(href)`. */
    match: 'exact' | 'prefix';
    /** Omit for standalone entries (Accueil above the groups, Aide below them). */
    group?: NavGroup;
    /** Standalone entries render above the groups unless this puts them below. */
    footer?: boolean;
    /** Item is shown if the user holds ANY of these permissions; omit to always show. */
    permissions?: string[];
    /** Page not built yet: NavRail/TopBar skip the entry. The owning WP deletes this flag. */
    hidden?: boolean;
    /** Non-Inertia page (e.g. Horizon): navigate with a full page load. */
    external?: boolean;
}

/** Groups rendered as section headers in the left rail (settings lives in the account menu). */
export const navGroupOrder: NavGroup[] = ['contacts', 'marketing', 'automations', 'transactional'];

export const navGroupLabels: Record<NavGroup, (t: TranslationDictionary) => string> = {
    messaging: (t) => t.nav.groups.messaging,
    campaigns: (t) => t.nav.groups.campaigns,
    contacts: (t) => t.nav.groups.contacts,
    administration: (t) => t.nav.groups.administration,
    marketing: (t) => t.nav.groups.marketing,
    automations: (t) => t.nav.groups.automations,
    transactional: (t) => t.nav.groups.transactional,
    settings: (t) => t.nav.groups.settings,
};

export const navItems: NavItem[] = [
    { href: '/dashboard', icon: HomeIcon, label: (t) => t.nav.home, match: 'prefix' },

    // Contacts
    { href: '/contacts', icon: ContactCardIcon, label: (t) => t.nav.contacts, match: 'exact', group: 'contacts', permissions: ['recipients.view'] },
    { href: '/contacts/lists', icon: PeopleIcon, label: (t) => t.nav.lists, match: 'prefix', group: 'contacts', permissions: ['recipients.view'] },
    { href: '/contacts/segments', icon: PeopleIcon, label: (t) => t.nav.segments, match: 'prefix', group: 'contacts', permissions: ['recipients.view', 'segments.manage'] },
    { href: '/contacts/pagejaunes', icon: BuildingIcon, label: (t) => t.nav.pagejaunesCompanies, match: 'prefix', group: 'contacts', permissions: ['recipients.import'] },
    { href: '/contacts/attributes', icon: DocumentIcon, label: (t) => t.nav.attributes, match: 'prefix', group: 'contacts', permissions: ['recipients.manage'] },
    // Wizard entry; the import-history page (/contacts/imports) is reached from it.
    { href: '/contacts/imports/new', icon: PeopleIcon, label: (t) => t.nav.imports, match: 'prefix', group: 'contacts', permissions: ['recipients.import'] },
    { href: '/contacts/blocklist', icon: ShieldProhibitedIcon, label: (t) => t.nav.blocklist, match: 'exact', group: 'contacts', permissions: ['suppression.view'] },

    // Marketing
    { href: '/campaigns', icon: MegaphoneLoudIcon, label: (t) => t.nav.emailCampaigns, match: 'prefix', group: 'marketing', permissions: ['campaigns.view'] },
    { href: '/marketing/forms', icon: DocumentIcon, label: (t) => t.nav.forms, match: 'prefix', group: 'marketing', permissions: ['forms.manage'], hidden: true },
    { href: '/templates', icon: DocumentIcon, label: (t) => t.nav.templates, match: 'exact', group: 'marketing', permissions: ['templates.view'] },
    { href: '/statistics', icon: DocumentIcon, label: (t) => t.nav.statistics, match: 'exact', group: 'marketing', permissions: ['reporting.view'] },

    // Automatisations (phase F)
    { href: '/automations', icon: SettingsIcon, label: (t) => t.nav.automations, match: 'prefix', group: 'automations', permissions: ['automations.manage'], hidden: true },

    // Transactionnel
    { href: '/compose', icon: MailIcon, label: (t) => t.nav.compose, match: 'exact', group: 'transactional', permissions: ['composer.compose'] },
    // Reuses MailIcon: no npm/tsc-verified inbox icon export is available.
    { href: '/mailbox', icon: MailIcon, label: (t) => t.nav.messaging, match: 'exact', group: 'transactional', permissions: ['mailbox.view_own', 'mailbox.view_all'] },
    { href: '/transactional/logs', icon: DocumentIcon, label: (t) => t.nav.transactionalLogs, match: 'prefix', group: 'transactional', permissions: ['transactional.view'], hidden: true },
    { href: '/templates?scope=transactional', icon: DocumentIcon, label: (t) => t.nav.transactionalTemplates, match: 'exact', group: 'transactional', permissions: ['templates.view'], hidden: true },
    { href: '/transactional/settings', icon: ServerIcon, label: (t) => t.nav.transactionalSettings, match: 'prefix', group: 'transactional', permissions: ['transactional.view'], hidden: true },

    // Paramètres (account menu, top-right)
    { href: '/settings/senders', icon: MailIcon, label: (t) => t.nav.senders, match: 'prefix', group: 'settings', permissions: ['senders.manage'] },
    { href: '/smtp', icon: ServerIcon, label: (t) => t.nav.smtp, match: 'exact', group: 'settings', permissions: ['smtp.view'] },
    { href: '/settings/unsubscribe-pages', icon: ShieldProhibitedIcon, label: (t) => t.nav.unsubscribePages, match: 'prefix', group: 'settings', permissions: ['settings.manage'], hidden: true },
    { href: '/settings/update-profile-forms', icon: DocumentIcon, label: (t) => t.nav.updateProfileForms, match: 'prefix', group: 'settings', permissions: ['settings.manage'], hidden: true },
    { href: '/settings/tracking', icon: SettingsIcon, label: (t) => t.nav.tracking, match: 'prefix', group: 'settings', permissions: ['settings.manage'], hidden: true },
    { href: '/settings/branding', icon: SettingsIcon, label: (t) => t.nav.branding, match: 'prefix', group: 'settings', permissions: ['settings.branding_only', 'settings.manage'] },
    { href: '/admin/users', icon: PeopleIcon, label: (t) => t.nav.usersRoles, match: 'prefix', group: 'settings', permissions: ['users.manage'] },
    { href: '/settings/api', icon: SettingsIcon, label: (t) => t.nav.apiKeys, match: 'prefix', group: 'settings', permissions: ['api_keys.manage', 'webhooks.manage'], hidden: true },
    { href: '/admin/audit-log', icon: DocumentIcon, label: (t) => t.nav.audit, match: 'exact', group: 'settings', permissions: ['audit.view'] },
    { href: '/horizon', icon: ServerIcon, label: (t) => t.nav.horizon, match: 'prefix', group: 'settings', permissions: ['queues.view'], external: true },

    // Aide
    { href: '/help', icon: QuestionCircleIcon, label: (t) => t.nav.help, match: 'prefix', footer: true },
];

/** Shared visibility predicate used by NavRail and TopBar (review W0 #11). */
export function isNavItemVisible(item: NavItem, permissions: string[]): boolean {
    return !item.hidden && (!item.permissions || hasAnyPermission(permissions, ...item.permissions));
}
