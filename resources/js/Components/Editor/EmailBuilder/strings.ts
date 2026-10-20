/**
 * WP-60 — every label the EmailBuilder shows.
 *
 * Existing keys come from `fr.ts` (`editor`, `common`, `templates`,
 * `campaignSetup`). `fr.ts` is frozen this wave (doc 44 §44.12), so the keys
 * it does not have yet live in `PENDING_EDITOR_KEYS` below, written with the
 * exact `fr.editor.*` path they should take. The orchestrator moves them into
 * `fr.ts` between waves; call sites read `text.*` and do not change.
 */
import { fr } from '@/Lib/i18n/fr';

/** Missing `fr.editor.*` keys (reported in the WP-60 return). */
export const PENDING_EDITOR_KEYS = {
    loadError: 'Impossible de charger l’éditeur.',
    advancedStyle: 'Style avancé',
    layers: 'Calques',
    canvas: 'Canevas de l’e-mail',
    toolbar: 'Barre d’outils de l’éditeur',
    device: 'Affichage',
    noFields: 'Aucune propriété rapide pour cet élément. Utilisez l’onglet « Style avancé ».',
    validationTitle: 'Le design n’a pas été enregistré',
    compilerUnavailable: 'Compilateur MJML indisponible',
    errorLine: 'Ligne %d',
    restoreTitle: 'Restaurer la version enregistrée',
    restoredLocal: 'La dernière version enregistrée a été rétablie.',
    leaveTitle: 'Modifications non enregistrées',
    leave: 'Quitter sans enregistrer',
    sendTestSaveFirst: 'Le design est enregistré avant l’envoi du test.',
    columns: {
        one: '1 colonne',
        many: '%d colonnes',
    },
    fields: {
        align: 'Alignement',
        alignLeft: 'Gauche',
        alignCenter: 'Centre',
        alignRight: 'Droite',
        padding: 'Marge intérieure',
        fontSize: 'Taille du texte',
        fontWeight: 'Graisse',
        lineHeight: 'Interligne',
        width: 'Largeur',
        height: 'Hauteur',
        color: 'Couleur',
        borderWidth: 'Épaisseur',
        borderStyle: 'Style de bordure',
        backgroundImage: 'Image de fond',
        iconSize: 'Taille des icônes',
        network: 'Réseau',
        titleLevel: 'Niveau de titre',
        videoUrl: 'Lien de la vidéo',
        thumbnail: 'Miniature',
        hamburger: 'Menu mobile (hamburger)',
        verticalAlign: 'Alignement vertical',
        mode: 'Disposition',
        horizontal: 'Horizontale',
        vertical: 'Verticale',
        top: 'Haut',
        middle: 'Milieu',
        bottom: 'Bas',
        solid: 'Continu',
        dashed: 'Tirets',
        dotted: 'Pointillés',
        weightNormal: 'Normale',
        weightSemibold: 'Semi-grasse',
        weightBold: 'Grasse',
        website: 'Site web',
    },
    titleLevels: {
        h1: 'Titre 1',
        h2: 'Titre 2',
        h3: 'Titre 3',
    },
    /** Default content dropped by each block. */
    defaults: {
        title: 'Votre titre',
        text: 'Saisissez votre texte ici.',
        button: 'Cliquez ici',
        column: 'Déposez un bloc ici',
        videoAlt: 'Lire la vidéo',
        logoAlt: 'Logo',
        navHome: 'Accueil',
        navOffers: 'Offres',
        navContact: 'Contact',
        html: 'Votre code HTML',
    },
    /** grapesjs-mjml component names (layer manager, badges). */
    componentNames: {
        body: 'Corps',
        button: 'Bouton',
        column: 'Colonne',
        oneColumn: '1 colonne',
        twoColumn: '2 colonnes',
        threeColumn: '3 colonnes',
        divider: 'Séparateur',
        group: 'Groupe',
        hero: 'Bannière',
        image: 'Image',
        navBar: 'Barre de navigation',
        navLink: 'Lien de navigation',
        section: 'Section',
        socialGroup: 'Réseaux sociaux',
        socialElement: 'Réseau social',
        spacer: 'Espace',
        text: 'Texte',
        wrapper: 'Conteneur',
        raw: 'HTML',
    },
    /** GrapesJS style-manager sectors (shipped in English). */
    sectors: {
        Dimension: 'Dimensions',
        Typography: 'Typographie',
        Decorations: 'Décorations',
        Settings: 'Paramètres',
    },
} as const;

const e = fr.editor;

export const text = {
    ...PENDING_EDITOR_KEYS,
    title: e.title,
    undo: e.undo,
    redo: e.redo,
    restorePrevious: e.restorePrevious,
    previewAndTest: e.previewAndTest,
    saveAndQuit: e.saveAndQuit,
    save: e.save,
    saving: e.saving,
    saved: e.saved,
    compileError: e.compileError,
    desktop: e.desktop,
    mobile: e.mobile,
    tabs: e.tabs,
    contentTabs: e.contentTabs,
    blocks: e.blocks,
    properties: e.properties,
    noSelection: e.noSelection,
    personalize: e.personalize,
    insertMergeTag: e.insertMergeTag,
    style: e.style,
    image: e.image,
    button: e.button,
    leaveConfirm: e.leaveConfirm,
    mergeTagGroups: fr.attributes.mergeTagGroups,
    restoreConfirm: fr.templates.restoreConfirm,
    preview: fr.campaignSetup.previewPanel,
    common: {
        loading: fr.common.loading,
        back: fr.common.back,
        cancel: fr.common.cancel,
        close: fr.common.close,
        confirm: fr.common.confirm,
        retry: fr.common.retry,
        default: fr.common.default,
        genericError: fr.common.genericError,
        forbidden: fr.common.forbidden,
        notFound: fr.common.notFound,
        conflict: fr.common.conflict,
        unsavedChanges: fr.common.unsavedChanges,
        savedAt: fr.common.savedAt,
    },
};

/** Replaces each `%d` / `%s` placeholder of a label, in order. */
export function format(template: string, ...values: (string | number)[]): string {
    let index = 0;

    return template.replace(/%[ds]/g, (match) => (index < values.length ? String(values[index++]) : match));
}
