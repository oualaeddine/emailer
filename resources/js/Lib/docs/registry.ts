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
    /**
     * Parameterised page URL, e.g. '/campaigns/{campaign}/setup': each `{name}`
     * matches one path segment. Used by topicForUrl() only — it is deliberately
     * NOT a `route` because the "Ouvrir la page" action visits `route` as-is.
     */
    routePattern?: string;
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
        related: ['two-factor', 'admin-users'],
    },
    {
        slug: 'two-factor',
        kind: 'pages',
        title: { fr: 'Double authentification', ar: 'المصادقة الثنائية' },
        group: 'general',
        // Covers both /two-factor/setup (enrolment) and /two-factor/challenge (login code).
        routePattern: '/two-factor/{step}',
        keywords: {
            fr: ['double authentification', '2fa', 'code', 'qr code', 'authenticator', 'récupération', 'téléphone perdu'],
            ar: ['المصادقة الثنائية', 'رمز التحقق', 'رموز الاسترداد', 'تطبيق المصادقة'],
        },
        related: ['login', 'admin-users', 'dashboard'],
    },
    {
        slug: 'dashboard',
        kind: 'pages',
        title: { fr: 'Accueil', ar: 'لوحة القيادة' },
        group: 'general',
        route: '/dashboard',
        keywords: { fr: ['accueil', 'tableau de bord', 'widgets', 'statistiques'], ar: ['الرئيسية', 'إحصائيات'] },
    },
    {
        slug: 'recipients',
        kind: 'pages',
        title: { fr: "Contacts", ar: "جهات الاتصال" },
        group: 'contacts',
        route: '/contacts',
        permissions: ['recipients.view'],
        keywords: { fr: ['contact', 'vue', 'filtre', 'colonnes', 'recherche'], ar: ['جهة اتصال', 'وسم'] },
        related: ['contact-detail', 'lists', 'segments', 'attributes', 'recipients-import', 'suppression'],
    },
    {
        slug: 'contact-detail',
        kind: 'pages',
        title: { fr: "Fiche contact", ar: "بطاقة جهة الاتصال" },
        group: 'contacts',
        routePattern: '/contacts/{recipient}',
        permissions: ['recipients.view'],
        keywords: { fr: ['activité', 'notes', 'liste noire', 'attributs'], ar: [] },
        related: ['recipients', 'lists', 'suppression'],
    },
    {
        slug: 'lists',
        kind: 'pages',
        title: { fr: "Listes", ar: "القوائم" },
        group: 'contacts',
        route: '/contacts/lists',
        permissions: ['recipients.view'],
        keywords: { fr: ['liste', 'dossier', 'exporter'], ar: [] },
        related: ['list-detail', 'recipients-import', 'segments'],
    },
    {
        slug: 'list-detail',
        kind: 'pages',
        title: { fr: "Détail d’une liste", ar: "تفاصيل القائمة" },
        group: 'contacts',
        routePattern: '/contacts/lists/{list}',
        permissions: ['recipients.view'],
        keywords: { fr: ['membres', 'importer dans la liste'], ar: [] },
        related: ['lists', 'recipients-import'],
    },
    {
        slug: 'segments',
        kind: 'pages',
        title: { fr: "Segments", ar: "الشرائح" },
        group: 'contacts',
        route: '/contacts/segments',
        permissions: ['recipients.view', 'segments.manage'],
        keywords: { fr: ['segment', 'audience', 'condition'], ar: [] },
        related: ['segment-edit', 'lists', 'campaign-setup'],
    },
    {
        slug: 'segment-edit',
        kind: 'pages',
        title: { fr: "Constructeur de segment", ar: "منشئ الشرائح" },
        group: 'contacts',
        routePattern: '/contacts/segments/{segment}',
        permissions: ['recipients.view', 'segments.manage'],
        keywords: { fr: ['condition', 'engagement', 'et ou'], ar: [] },
        related: ['segments', 'attributes'],
    },
    {
        slug: 'attributes',
        kind: 'pages',
        title: { fr: "Attributs", ar: "السمات" },
        group: 'contacts',
        route: '/contacts/attributes',
        permissions: ['recipients.view'],
        keywords: { fr: ['attribut', 'champ personnalisé', 'type'], ar: [] },
        related: ['recipients', 'recipients-import', 'segments'],
    },
    {
        slug: 'imports',
        kind: 'pages',
        title: { fr: "Historique des imports", ar: "سجل الاستيراد" },
        group: 'contacts',
        route: '/contacts/imports',
        permissions: ['recipients.import'],
        keywords: { fr: ['import', 'erreurs', 'historique'], ar: [] },
        related: ['recipients-import', 'recipients'],
    },
    {
        slug: 'recipients-import',
        kind: 'pages',
        title: { fr: "Assistant d’import de contacts", ar: "معالج استيراد جهات الاتصال" },
        group: 'contacts',
        route: '/contacts/imports/new',
        permissions: ['recipients.import'],
        keywords: { fr: ['import', 'csv', 'excel', 'colonnes', 'correspondance'], ar: ['استيراد', 'ملف'] },
        related: ['flow-import-recipients-csv', 'imports', 'attributes', 'lists'],
    },
    {
        slug: 'pagejaunes-search',
        kind: 'pages',
        title: { fr: "Entreprises PagesJaunes", ar: "شركات PageJaunes" },
        group: 'contacts',
        route: '/contacts/pagejaunes',
        permissions: ['recipients.import'],
        keywords: { fr: ['pagejaunes', 'annuaire', 'entreprises', 'recherche'], ar: ['دليل الأعمال'] },
        related: ['flow-source-pagejaunes', 'recipients-import'],
    },
    {
        slug: 'suppression',
        kind: 'pages',
        title: { fr: "Liste noire", ar: "قائمة الحظر" },
        group: 'contacts',
        route: '/contacts/blocklist',
        permissions: ['suppression.view'],
        keywords: { fr: ['liste noire', 'désabonnement', 'blocage', 'rebonds'], ar: ['إلغاء الاشتراك', 'حظر'] },
        related: ['dialog-suppression-entry-form', 'recipients', 'unsubscribe-pages'],
    },
    {
        slug: 'campaigns',
        kind: 'pages',
        title: { fr: "Campagnes", ar: "الحملات" },
        group: 'marketing',
        route: '/campaigns',
        permissions: ['campaigns.view'],
        keywords: { fr: ['campagne', 'brouillon', 'programmer', 'archiver', 'dupliquer'], ar: ['حملة', 'جدولة', 'إلغاء'] },
        related: ['campaign-setup', 'campaign-report', 'flow-create-send-campaign', 'reporting'],
    },
    {
        slug: 'campaign-setup',
        kind: 'pages',
        title: { fr: "Configuration d’une campagne", ar: "إعداد الحملة" },
        group: 'marketing',
        routePattern: '/campaigns/{campaign}/setup',
        permissions: ['campaigns.view'],
        keywords: { fr: ['expéditeur', 'objet', 'destinataires', 'programmer', 'test'], ar: [] },
        related: ['campaign-design', 'senders', 'segments', 'lists', 'flow-create-send-campaign'],
    },
    {
        slug: 'campaign-design',
        kind: 'pages',
        title: { fr: "Éditeur de design", ar: "محرر التصميم" },
        group: 'marketing',
        routePattern: '/campaigns/{campaign}/design',
        permissions: ['campaigns.create'],
        keywords: { fr: ['éditeur', 'blocs', 'sections', 'mobile'], ar: [] },
        related: ['campaign-setup', 'templates'],
    },
    {
        slug: 'campaign-report',
        kind: 'pages',
        title: { fr: "Rapport de campagne", ar: "تقرير الحملة" },
        group: 'marketing',
        routePattern: '/campaigns/{campaign}/report',
        permissions: ['campaigns.view', 'reporting.view'],
        keywords: { fr: ['rapport', 'ouvertures', 'clics', 'délivrabilité'], ar: [] },
        related: ['campaigns', 'reporting', 'flow-analyze-results'],
    },
    {
        slug: 'forms',
        kind: 'pages',
        title: { fr: "Formulaires d’inscription", ar: "نماذج الاشتراك" },
        group: 'marketing',
        route: '/marketing/forms',
        permissions: ['forms.manage'],
        keywords: { fr: ['formulaire', 'inscription', 'double opt-in', 'intégration'], ar: [] },
        related: ['form-edit', 'lists', 'attributes'],
    },
    {
        slug: 'form-edit',
        kind: 'pages',
        title: { fr: "Éditeur de formulaire d’inscription", ar: "محرر نموذج الاشتراك" },
        group: 'marketing',
        routePattern: '/marketing/forms/{form}',
        permissions: ['forms.manage'],
        keywords: {
            fr: ['formulaire', 'champs', 'double opt-in', 'captcha', 'publier', 'iframe', 'intégration', 'consentement', 'redirection'],
            ar: ['نموذج', 'حقول', 'تأكيد مزدوج', 'كابتشا', 'نشر', 'تضمين'],
        },
        related: ['forms', 'lists', 'attributes', 'templates', 'automations'],
    },
    {
        slug: 'templates',
        kind: 'pages',
        title: { fr: "Modèles", ar: "القوالب" },
        group: 'marketing',
        route: '/templates',
        permissions: ['templates.view'],
        keywords: { fr: ['modèle', 'galerie', 'dupliquer', 'archiver'], ar: ['قالب', 'أرشفة'] },
        related: ['template-edit', 'dialog-template-form', 'campaign-design'],
    },
    {
        slug: 'template-edit',
        kind: 'pages',
        title: { fr: "Édition d’un modèle", ar: "تحرير القالب" },
        group: 'marketing',
        routePattern: '/templates/{template}/edit',
        permissions: ['templates.manage'],
        keywords: { fr: ['éditeur', 'versions', 'restaurer'], ar: [] },
        related: ['templates', 'campaign-design'],
    },
    {
        slug: 'reporting',
        kind: 'pages',
        title: { fr: "Statistiques", ar: "الإحصائيات" },
        group: 'marketing',
        route: '/statistics',
        permissions: ['reporting.view'],
        keywords: { fr: ['statistiques', 'période', 'taux', 'performance'], ar: ['إحصائيات', 'تصدير'] },
        related: ['campaign-report', 'flow-analyze-results', 'campaigns'],
    },
    {
        slug: 'composer',
        kind: 'pages',
        title: { fr: "Rédaction d’un e-mail", ar: "تحرير بريد إلكتروني" },
        group: 'transactional',
        route: '/compose',
        permissions: ['composer.compose'],
        keywords: { fr: ['composer', 'rédiger', 'signature', 'brouillon', 'aperçu'], ar: ['تحرير', 'مسودة', 'توقيع'] },
        related: ['flow-compose-send-email', 'templates'],
    },
    {
        slug: 'mailbox',
        kind: 'pages',
        title: { fr: "Messagerie", ar: "صندوق الوارد" },
        group: 'transactional',
        route: '/mailbox',
        permissions: ['mailbox.view_own', 'mailbox.view_all'],
        keywords: { fr: ['boîte', 'brouillons', 'envoyés', 'programmés'], ar: ['صندوق', 'مسودات', 'مرسلة'] },
        related: ['composer', 'transactional-logs'],
    },
    {
        slug: 'transactional-logs',
        kind: 'pages',
        title: { fr: "Journaux transactionnels", ar: "سجلات المعاملات" },
        group: 'transactional',
        route: '/transactional/logs',
        permissions: ['transactional.view'],
        keywords: { fr: ['journal', 'événement', 'rebond', 'délivré'], ar: [] },
        related: ['transactional-settings', 'suppression', 'mailbox'],
    },
    {
        slug: 'transactional-settings',
        kind: 'pages',
        title: { fr: "SMTP & API", ar: "SMTP وواجهة API" },
        group: 'transactional',
        route: '/transactional/settings',
        permissions: ['transactional.view'],
        keywords: { fr: ['api', 'clé', 'webhook', 'smtp'], ar: [] },
        related: ['transactional-logs', 'smtp'],
    },
    {
        slug: 'senders',
        kind: 'pages',
        title: { fr: "Expéditeurs", ar: "المرسلون" },
        group: 'settings',
        route: '/settings/senders',
        permissions: ['senders.manage'],
        keywords: { fr: ['expéditeur', 'vérification', 'par défaut'], ar: [] },
        related: ['domains', 'campaign-setup'],
    },
    {
        slug: 'domains',
        kind: 'pages',
        title: { fr: "Domaines", ar: "النطاقات" },
        group: 'settings',
        route: '/settings/domains',
        permissions: ['senders.manage'],
        keywords: { fr: ['domaine', 'dkim', 'spf', 'dmarc', 'dns'], ar: [] },
        related: ['senders', 'smtp'],
    },
    {
        slug: 'unsubscribe-pages',
        kind: 'pages',
        title: { fr: "Pages de désinscription", ar: "صفحات إلغاء الاشتراك" },
        group: 'settings',
        route: '/settings/unsubscribe-pages',
        permissions: ['settings.manage'],
        keywords: { fr: ['désinscription', 'préférences', 'page publique'], ar: [] },
        related: ['update-profile-forms', 'suppression'],
    },
    {
        slug: 'update-profile-forms',
        kind: 'pages',
        title: { fr: "Formulaires de mise à jour", ar: "نماذج تحديث الملف الشخصي" },
        group: 'settings',
        route: '/settings/update-profile-forms',
        permissions: ['settings.manage'],
        keywords: { fr: ['mise à jour', 'profil', 'formulaire'], ar: [] },
        related: ['unsubscribe-pages', 'attributes'],
    },
    {
        slug: 'automations',
        kind: 'pages',
        title: { fr: "Automatisations", ar: "الأتمتة" },
        group: 'automations',
        route: '/automations',
        permissions: ['automations.manage'],
        keywords: { fr: ['automatisation', 'scénario', 'workflow', 'déclencheur', 'étapes'], ar: ['أتمتة', 'سيناريو'] },
        related: ['automation-edit', 'lists', 'segments', 'templates', 'senders'],
    },
    {
        slug: 'automation-edit',
        kind: 'pages',
        title: { fr: "Éditeur d’automatisation", ar: "محرر الأتمتة" },
        group: 'automations',
        routePattern: '/automations/{automation}',
        permissions: ['automations.manage'],
        keywords: {
            fr: ['automatisation', 'déclencheur', 'étape', 'attendre', 'condition', 'expéditeur', 'activer', 'pause', 'réentrée', 'activité'],
            ar: ['أتمتة', 'مُشغِّل', 'خطوة', 'انتظار', 'شرط', 'تفعيل'],
        },
        related: ['automations', 'forms', 'lists', 'segments', 'templates', 'senders'],
    },
    {
        slug: 'smtp',
        kind: 'pages',
        title: { fr: "Comptes SMTP", ar: "حسابات SMTP" },
        group: 'settings',
        route: '/smtp',
        permissions: ['smtp.view'],
        keywords: { fr: ['smtp', 'expédition', 'quota', 'rotation'], ar: ['إرسال', 'حصة'] },
        related: ['dialog-smtp-account-form', 'domains', 'transactional-settings'],
    },
    {
        slug: 'admin-users',
        kind: 'pages',
        title: { fr: "Utilisateurs & rôles", ar: "المستخدمون والأدوار" },
        group: 'settings',
        route: '/admin/users',
        permissions: ['users.manage'],
        keywords: { fr: ['utilisateur', 'rôle', 'compte'], ar: ['مستخدم', 'دور'] },
        related: ['dialog-user-form'],
    },
    {
        slug: 'audit-log',
        kind: 'pages',
        title: { fr: "Journal d’audit", ar: "سجل التدقيق" },
        group: 'settings',
        route: '/admin/audit-log',
        permissions: ['audit.view'],
        keywords: { fr: ['audit', 'historique', 'export'], ar: ['سجل', 'تصدير'] },
    },
    {
        slug: 'settings-branding',
        kind: 'pages',
        title: { fr: "Identité visuelle", ar: "الهوية البصرية" },
        group: 'settings',
        route: '/settings/branding',
        permissions: ['settings.branding_only', 'settings.manage'],
        keywords: { fr: ['couleur', 'logo', 'thème'], ar: ['لون', 'سمة'] },
        related: ['unsubscribe-pages'],
    },

    // ---- Dialogs -----------------------------------------------------------
    {
        slug: 'dialog-user-form',
        kind: 'dialogs',
        title: { fr: 'Fenêtre — Nouvel utilisateur', ar: 'نافذة — مستخدم جديد' },
        group: 'settings',
        permissions: ['users.manage'],
        related: ['admin-users'],
    },
    // Dialog topics are gated on the permission that opens the dialog (create/
    // manage), not the page's read permission — otherwise a read-only user is
    // offered instructions for a button they never see.
    {
        slug: 'dialog-recipient-form',
        kind: 'dialogs',
        title: { fr: 'Fenêtre — Nouveau contact', ar: 'نافذة — مستلم جديد' },
        group: 'contacts',
        permissions: ['recipients.manage'],
        related: ['recipients'],
    },
    {
        slug: 'dialog-smtp-account-form',
        kind: 'dialogs',
        title: { fr: 'Fenêtre — Nouveau compte SMTP', ar: 'نافذة — حساب SMTP جديد' },
        group: 'settings',
        permissions: ['smtp.manage_credentials'],
        related: ['smtp'],
    },
    {
        slug: 'dialog-template-form',
        kind: 'dialogs',
        title: { fr: 'Fenêtre — Nouveau modèle', ar: 'نافذة — قالب جديد' },
        group: 'marketing',
        permissions: ['templates.manage'],
        related: ['templates'],
    },
    {
        slug: 'dialog-suppression-entry-form',
        kind: 'dialogs',
        title: { fr: 'Fenêtre — Ajouter à la liste noire', ar: 'نافذة — إضافة عنوان محظور' },
        group: 'contacts',
        permissions: ['suppression.manage'],
        related: ['suppression'],
    },

    // ---- Flows -----------------------------------------------------------
    {
        slug: 'flow-import-recipients-csv',
        kind: 'flows',
        title: { fr: 'Guide — Importer des contacts depuis un fichier CSV/Excel', ar: 'دليل — استيراد المستلمين من ملف CSV/Excel' },
        group: 'flows',
        permissions: ['recipients.import'],
        keywords: { fr: ['import', 'csv', 'excel', 'colonnes'], ar: ['استيراد', 'ملف'] },
        related: ['recipients-import', 'recipients', 'imports'],
    },
    {
        slug: 'flow-source-pagejaunes',
        kind: 'flows',
        title: { fr: 'Guide — Trouver des contacts via l’annuaire PageJaunes', ar: 'دليل — إيجاد المستلمين عبر دليل PageJaunes' },
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
        title: { fr: 'Guide — Envoyer une campagne pas à pas', ar: 'دليل — إرسال حملة خطوة بخطوة' },
        group: 'flows',
        permissions: ['campaigns.create'],
        keywords: {
            fr: ['envoyer', 'campagne', 'étapes', 'pas à pas', 'programmer', 'planifier', 'test', 'aperçu', 'liste de vérification', 'expéditeur', 'destinataires', 'lots', 'meilleur moment', 'pause', 'dupliquer'],
            ar: ['حملة', 'إرسال', 'خطوات', 'جدولة', 'اختبار', 'معاينة', 'قائمة التحقق', 'المرسل', 'المستلمون', 'دفعات', 'أفضل وقت', 'إيقاف مؤقت', 'مضاعفة'],
        },
        related: ['campaigns', 'campaign-setup', 'campaign-design', 'campaign-report', 'flow-analyze-results'],
    },
    {
        slug: 'flow-analyze-results',
        kind: 'flows',
        title: { fr: 'Guide — Analyser les résultats et gérer les rebonds', ar: 'دليل — تحليل النتائج ومعالجة الارتدادات' },
        group: 'flows',
        permissions: ['reporting.view'],
        keywords: { fr: ['rapport', 'rebond', 'ouverture', 'clic'], ar: ['تقرير', 'ارتداد'] },
        related: ['campaign-report', 'reporting', 'suppression'],
    },
];

export function getTopic(slug: string): DocTopic | undefined {
    return docTopics.find((topic) => topic.slug === slug);
}

/** Number of literal segments x10 + parameter segments: more specific patterns score higher. */
function matchScore(pattern: string, path: string): number {
    const patternSegments = pattern.split('/').filter(Boolean);
    const pathSegments = path.split('/').filter(Boolean);
    if (patternSegments.length > pathSegments.length) return -1;
    let score = 0;
    for (let i = 0; i < patternSegments.length; i++) {
        const segment = patternSegments[i];
        if (segment.startsWith('{') && segment.endsWith('}')) {
            score += 1;
        } else if (segment === pathSegments[i]) {
            score += 10;
        } else {
            return -1;
        }
    }
    return score;
}

/**
 * Most-specific prefix match against the current Inertia URL: both plain
 * `route` prefixes and `{param}` patterns are scored by literal segments, so
 * /contacts/lists resolves to Listes (not a /contacts/{recipient} pattern)
 * and /campaigns/abc/setup to its own topic rather than Campagnes.
 */
export function topicForUrl(url: string): DocTopic | undefined {
    const path = url.split('?')[0].split('#')[0].replace(/\/+$/, '') || '/';
    let best: DocTopic | undefined;
    let bestScore = -1;
    for (const topic of docTopics) {
        for (const pattern of [topic.route, topic.routePattern]) {
            if (!pattern) continue;
            const score = matchScore(pattern, path);
            if (score > bestScore) {
                best = topic;
                bestScore = score;
            }
        }
    }
    return best;
}

export function visibleTopics(permissions: string[]): DocTopic[] {
    return docTopics.filter((topic) => !topic.permissions || hasAnyPermission(permissions, ...topic.permissions));
}
