import type { DocGroup } from '@/Lib/docs/registry';

/**
 * The application interface is French-only (docs/07-ui-design.md §7.12).
 * That rule is untouched: only the *documentation* surfaces are bilingual,
 * so the handful of chrome strings that must follow the reader's chosen
 * documentation language live here rather than in `Lib/i18n/fr.ts`.
 */
export type DocsLocale = 'fr' | 'ar';

export const docsLocales: DocsLocale[] = ['fr', 'ar'];

export const docsLocaleNames: Record<DocsLocale, string> = {
    fr: 'Français',
    ar: 'العربية',
};

export interface DocsUiStrings {
    centerTitle: string;
    searchPlaceholder: string;
    noResults: string;
    loading: string;
    notFound: string;
    fallbackNotice: string;
    fullDocs: string;
    openPage: string;
    seeAlso: string;
    language: string;
    back: string;
    close: string;
    screenshotPending: string;
    enlarge: string;
    tableOfContents: string;
    groups: Record<DocGroup, string>;
}

export const docsUi: Record<DocsLocale, DocsUiStrings> = {
    fr: {
        centerTitle: 'Centre de documentation',
        searchPlaceholder: 'Rechercher dans la documentation…',
        noResults: 'Aucun résultat.',
        loading: 'Chargement de la documentation…',
        notFound: 'Cette rubrique n’est pas encore rédigée.',
        fallbackNotice: 'La traduction arabe de cette rubrique n’est pas encore disponible. Texte affiché en français.',
        fullDocs: 'Documentation complète',
        openPage: 'Ouvrir la page',
        seeAlso: 'Voir aussi',
        language: 'Langue de la documentation',
        back: 'Retour',
        close: 'Fermer',
        screenshotPending: 'Capture d’écran à venir',
        enlarge: 'Agrandir la capture',
        tableOfContents: 'Sommaire',
        groups: {
            flows: 'Guides pas à pas',
            general: 'Généralités',
            messaging: 'Messagerie',
            campaigns: 'Campagnes',
            contacts: 'Contacts',
            administration: 'Administration',
        },
    },
    ar: {
        centerTitle: 'مركز التوثيق',
        searchPlaceholder: 'ابحث في التوثيق…',
        noResults: 'لا توجد نتائج.',
        loading: 'جارٍ تحميل التوثيق…',
        notFound: 'لم يُكتب هذا الموضوع بعد.',
        fallbackNotice: 'الترجمة العربية لهذا الموضوع غير متوفرة بعد. يُعرض النص بالفرنسية.',
        fullDocs: 'التوثيق الكامل',
        openPage: 'فتح الصفحة',
        seeAlso: 'انظر أيضاً',
        language: 'لغة التوثيق',
        back: 'رجوع',
        close: 'إغلاق',
        screenshotPending: 'لقطة الشاشة قادمة',
        enlarge: 'تكبير اللقطة',
        tableOfContents: 'الفهرس',
        groups: {
            flows: 'أدلة خطوة بخطوة',
            general: 'عام',
            messaging: 'المراسلة',
            campaigns: 'الحملات',
            contacts: 'جهات الاتصال',
            administration: 'الإدارة',
        },
    },
};
