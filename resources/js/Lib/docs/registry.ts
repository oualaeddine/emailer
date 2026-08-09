import type { NavGroup } from '@/Components/Shell/navItems';
import { hasAnyPermission } from '@/Lib/permissions';

/**
 * In-app documentation registry — single source of truth for the docs
 * center (/help), the per-page Help menu, and every contextual HelpButton.
 * Adding a new documented topic is: drop `resources/docs/fr/<kind>/<slug>.md`
 * + `resources/docs/ar/<kind>/<slug>.md`, then append one entry here.
 */
export type DocKind = 'pages' | 'flows' | 'dialogs';

/** 'general' groups ungated topics (login, dashboard); 'flows' groups the cross-page guides. */
export type DocGroup = NavGroup | 'general' | 'flows';

export interface DocTopic {
    slug: string;
    kind: DocKind;
    title: { fr: string; ar: string };
    group: DocGroup;
    /** Shown if the user holds ANY of these permissions; omit to always show. */
    permissions?: string[];
    /** App route this topic documents — used for topicForUrl() and the "Ouvrir la page" action. */
    route?: string;
    keywords?: { fr: string[]; ar: string[] };
    /** Related topic slugs, rendered as "Voir aussi" links. */
    related?: string[];
}

export const docTopics: DocTopic[] = [
    // ---- Pages -----------------------------------------------------------
    {
        slug: 'login',
        kind: 'pages',
        title: { fr: 'Connexion', ar: 'تسجيل الدخول' },
        group: 'general',
        route: '/login',
        keywords: { fr: ['connexion', 'mot de passe', 'identifiants'], ar: ['تسجيل الدخول', 'كلمة المرور'] },
    },
    {
        slug: 'dashboard',
        kind: 'pages',
        title: { fr: 'Tableau de bord', ar: 'لوحة القيادة' },
        group: 'general',
        route: '/dashboard',
        keywords: { fr: ['accueil', 'widgets', 'statistiques'], ar: ['الرئيسية', 'إحصائيات'] },
    },
    {
        slug: 'composer',
        kind: 'pages',
        title: { fr: 'Rédaction d’un e-mail', ar: 'تحرير بريد إلكتروني' },
        group: 'messaging',
        route: '/compose',
        permissions: ['composer.compose'],
        keywords: { fr: ['composer', 'rédiger', 'signature', 'brouillon', 'aperçu'], ar: ['تحرير', 'مسودة', 'توقيع'] },
        related: ['flow-compose-send-email', 'templates'],
    },
    {
        slug: 'mailbox',
        kind: 'pages',
        title: { fr: 'Boîte de réception', ar: 'صندوق الوارد' },
        group: 'messaging',
        route: '/mailbox',
        permissions: ['mailbox.view_own', 'mailbox.view_all'],
        keywords: { fr: ['boîte', 'brouillons', 'envoyés', 'programmés'], ar: ['صندوق', 'مسودات', 'مرسلة'] },
        related: ['composer'],
    },
    {
        slug: 'templates',
        kind: 'pages',
        title: { fr: 'Modèles', ar: 'القوالب' },
        group: 'messaging',
        route: '/templates',
        permissions: ['templates.view'],
        keywords: { fr: ['modèle', 'gabarit', 'archiver'], ar: ['قالب', 'أرشفة'] },
        related: ['dialog-template-form', 'flow-compose-send-email'],
    },
    {
        slug: 'campaigns',
        kind: 'pages',
        title: { fr: 'Campagnes', ar: 'الحملات' },
        group: 'campaigns',
        route: '/campaigns',
        permissions: ['campaigns.view'],
        keywords: { fr: ['campagne', 'diffusion', 'planifier', 'annuler', 'cloner'], ar: ['حملة', 'جدولة', 'إلغاء'] },
        related: ['dialog-campaign-wizard', 'flow-create-send-campaign', 'reporting'],
    },
    {
        slug: 'reporting',
        kind: 'pages',
        title: { fr: 'Rapports', ar: 'التقارير' },
        group: 'campaigns',
        route: '/reporting',
        permissions: ['reporting.view'],
        keywords: { fr: ['statistiques', 'taux', 'export', 'ouverture', 'clic', 'rebond'], ar: ['إحصائيات', 'تصدير'] },
        related: ['flow-analyze-results', 'campaigns'],
    },
    {
        slug: 'recipients',
        kind: 'pages',
        title: { fr: 'Destinataires', ar: 'المستلمون' },
        group: 'contacts',
        route: '/recipients',
        permissions: ['recipients.view'],
        keywords: { fr: ['contact', 'destinataire', 'tag', 'recherche'], ar: ['جهة اتصال', 'وسم'] },
        related: ['dialog-recipient-form', 'recipients-import', 'suppression'],
    },
    {
        slug: 'recipients-import',
        kind: 'pages',
        title: { fr: 'Import de destinataires', ar: 'استيراد المستلمين' },
        group: 'contacts',
        route: '/recipients/import',
        permissions: ['recipients.import'],
        keywords: { fr: ['import', 'csv', 'excel', 'colonnes', 'mapping'], ar: ['استيراد', 'ملف'] },
        related: ['flow-import-recipients-csv', 'recipients'],
    },
    {
        slug: 'pagejaunes-search',
        kind: 'pages',
        title: { fr: 'Annuaire PageJaunes', ar: 'دليل PageJaunes' },
        group: 'contacts',
        route: '/recipients/import/pagejaunes',
        permissions: ['recipients.import'],
        keywords: { fr: ['pagejaunes', 'annuaire', 'entreprises', 'recherche'], ar: ['دليل الأعمال'] },
        related: ['flow-source-pagejaunes', 'recipients-import'],
    },
    {
        slug: 'suppression',
        kind: 'pages',
        title: { fr: 'Liste de suppression', ar: 'قائمة الحظر' },
        group: 'contacts',
        route: '/suppression',
        permissions: ['suppression.view'],
        keywords: { fr: ['désabonnement', 'blocage', 'suppression'], ar: ['إلغاء الاشتراك', 'حظر'] },
        related: ['dialog-suppression-entry-form', 'recipients'],
    },
    {
        slug: 'smtp',
        kind: 'pages',
        title: { fr: 'Comptes SMTP', ar: 'حسابات SMTP' },
        group: 'administration',
        route: '/smtp',
        permissions: ['smtp.view'],
        keywords: { fr: ['smtp', 'expédition', 'quota', 'test de connexion'], ar: ['إرسال', 'حصة'] },
        related: ['dialog-smtp-account-form'],
    },
    {
        slug: 'admin-users',
        kind: 'pages',
        title: { fr: 'Utilisateurs', ar: 'المستخدمون' },
        group: 'administration',
        route: '/admin/users',
        permissions: ['users.manage'],
        keywords: { fr: ['utilisateur', 'rôle', 'compte', 'actif'], ar: ['مستخدم', 'دور'] },
        related: ['dialog-user-form'],
    },
    {
        slug: 'audit-log',
        kind: 'pages',
        title: { fr: 'Journal d’audit', ar: 'سجل التدقيق' },
        group: 'administration',
        route: '/admin/audit-log',
        permissions: ['audit.view'],
        keywords: { fr: ['audit', 'historique', 'export'], ar: ['سجل', 'تصدير'] },
    },
    {
        slug: 'settings-branding',
        kind: 'pages',
        title: { fr: 'Paramètres de marque', ar: 'إعدادات العلامة' },
        group: 'administration',
        route: '/settings/branding',
        permissions: ['settings.branding_only', 'settings.manage'],
        keywords: { fr: ['couleur', 'thème', 'organisation'], ar: ['لون', 'سمة'] },
    },

    // ---- Dialogs -----------------------------------------------------------
    {
        slug: 'dialog-user-form',
        kind: 'dialogs',
        title: { fr: 'Fenêtre — Nouvel utilisateur', ar: 'نافذة — مستخدم جديد' },
        group: 'administration',
        permissions: ['users.manage'],
        related: ['admin-users'],
    },
    // Dialog topics are gated on the permission that opens the dialog (create/
    // manage), not the page's read permission — otherwise a read-only user is
    // offered instructions for a button they never see.
    {
        slug: 'dialog-recipient-form',
        kind: 'dialogs',
        title: { fr: 'Fenêtre — Nouveau destinataire', ar: 'نافذة — مستلم جديد' },
        group: 'contacts',
        permissions: ['recipients.manage'],
        related: ['recipients'],
    },
    {
        slug: 'dialog-smtp-account-form',
        kind: 'dialogs',
        title: { fr: 'Fenêtre — Nouveau compte SMTP', ar: 'نافذة — حساب SMTP جديد' },
        group: 'administration',
        permissions: ['smtp.manage_credentials'],
        related: ['smtp'],
    },
    {
        slug: 'dialog-template-form',
        kind: 'dialogs',
        title: { fr: 'Fenêtre — Nouveau modèle', ar: 'نافذة — قالب جديد' },
        group: 'messaging',
        permissions: ['templates.manage'],
        related: ['templates'],
    },
    {
        slug: 'dialog-suppression-entry-form',
        kind: 'dialogs',
        title: { fr: 'Fenêtre — Ajouter une adresse supprimée', ar: 'نافذة — إضافة عنوان محظور' },
        group: 'contacts',
        permissions: ['suppression.manage'],
        related: ['suppression'],
    },
    {
        slug: 'dialog-campaign-wizard',
        kind: 'dialogs',
        title: { fr: 'Assistant — Nouvelle campagne', ar: 'معالج — حملة جديدة' },
        group: 'campaigns',
        permissions: ['campaigns.create'],
        related: ['campaigns', 'flow-create-send-campaign'],
    },

    // ---- Flows -----------------------------------------------------------
    {
        slug: 'flow-import-recipients-csv',
        kind: 'flows',
        title: { fr: 'Guide — Importer des destinataires depuis un fichier CSV/Excel', ar: 'دليل — استيراد المستلمين من ملف CSV/Excel' },
        group: 'flows',
        permissions: ['recipients.import'],
        keywords: { fr: ['import', 'csv', 'excel', 'colonnes'], ar: ['استيراد', 'ملف'] },
        related: ['recipients-import', 'recipients'],
    },
    {
        slug: 'flow-source-pagejaunes',
        kind: 'flows',
        title: { fr: 'Guide — Trouver des destinataires via l’annuaire PageJaunes', ar: 'دليل — إيجاد المستلمين عبر دليل PageJaunes' },
        group: 'flows',
        permissions: ['recipients.import'],
        keywords: { fr: ['pagejaunes', 'sourcing', 'annuaire'], ar: ['دليل الأعمال'] },
        related: ['pagejaunes-search', 'recipients-import'],
    },
    {
        slug: 'flow-compose-send-email',
        kind: 'flows',
        title: { fr: 'Guide — Rédiger et envoyer un e-mail', ar: 'دليل — تحرير وإرسال بريد إلكتروني' },
        group: 'flows',
        permissions: ['composer.compose'],
        keywords: { fr: ['composer', 'envoyer', 'smtp'], ar: ['إرسال', 'تحرير'] },
        related: ['composer', 'templates', 'mailbox'],
    },
    {
        slug: 'flow-create-send-campaign',
        kind: 'flows',
        title: { fr: 'Guide — Créer et envoyer une campagne', ar: 'دليل — إنشاء وإرسال حملة' },
        group: 'flows',
        permissions: ['campaigns.create'],
        keywords: { fr: ['campagne', 'assistant', 'planifier'], ar: ['حملة', 'جدولة'] },
        related: ['campaigns', 'dialog-campaign-wizard', 'reporting'],
    },
    {
        slug: 'flow-analyze-results',
        kind: 'flows',
        title: { fr: 'Guide — Analyser les résultats et gérer les rebonds', ar: 'دليل — تحليل النتائج ومعالجة الارتدادات' },
        group: 'flows',
        permissions: ['reporting.view'],
        keywords: { fr: ['rapport', 'rebond', 'ouverture', 'clic'], ar: ['تقرير', 'ارتداد'] },
        related: ['reporting', 'suppression'],
    },
];

export function getTopic(slug: string): DocTopic | undefined {
    return docTopics.find((topic) => topic.slug === slug);
}

/**
 * Longest-route-prefix match against the current Inertia URL, so nested
 * routes (e.g. /recipients/import/pagejaunes) resolve to their own topic
 * rather than a parent's (/recipients/import, /recipients).
 */
export function topicForUrl(url: string): DocTopic | undefined {
    const path = url.split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';
    let best: DocTopic | undefined;
    for (const topic of docTopics) {
        const route = topic.route;
        if (!route) continue;
        if (path !== route && !path.startsWith(`${route}/`)) continue;
        if (!best || route.length > (best.route?.length ?? 0)) {
            best = topic;
        }
    }
    return best;
}

export function visibleTopics(permissions: string[]): DocTopic[] {
    return docTopics.filter((topic) => !topic.permissions || hasAnyPermission(permissions, ...topic.permissions));
}
