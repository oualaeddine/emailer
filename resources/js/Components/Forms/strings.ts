/**
 * WP-90U — French strings the builder needs that are not (yet) in
 * `fr.forms` (fr.ts is frozen for UI agents). Reported in the WP return as
 * missing keys; once the orchestrator adds them under `fr.forms.*` these
 * constants are replaced by `t.forms.*` lookups.
 */
export const formText = {
    fields: {
        emailLabel: 'E-mail',
        moveUp: 'Monter',
        moveDown: 'Descendre',
        removeField: 'Retirer le champ',
        emailLocked: 'Le champ E-mail est toujours présent et obligatoire.',
        noAttributeLeft: 'Tous les attributs sont déjà dans le formulaire.',
        gdprRequired: 'Le consentement est obligatoire pour s’inscrire',
        defaultGdprText:
            'J’accepte de recevoir vos e-mails et confirme avoir pris connaissance de votre politique de confidentialité.',
    },
    design: {
        content: 'Contenu',
        appearance: 'Apparence',
        buttonTextColor: 'Couleur du texte du bouton',
        borderRadius: 'Arrondi des angles (px)',
    },
    settings: {
        listsPlaceholder: 'Sélectionnez une ou plusieurs listes',
        listRequired: 'Sélectionnez au moins une liste avant de publier.',
        afterSubmit: 'Après l’inscription',
        showMessage: 'Afficher un message de remerciement',
        redirect: 'Rediriger vers une URL',
        notifyEmailsHelp: 'Une adresse e-mail par ligne.',
        invalidUrl: 'Saisissez une URL complète commençant par http:// ou https://.',
        invalidEmails: 'Une ou plusieurs adresses e-mail sont invalides.',
    },
    share: {
        open: 'Ouvrir',
        inactive: 'Publiez le formulaire pour que ce lien et ces codes fonctionnent.',
    },
    list: {
        unpublished: 'Formulaire désactivé.',
        duplicated: 'Formulaire dupliqué.',
        deleted: 'Formulaire supprimé.',
    },
    preview: {
        captcha: 'Vérification anti-robot',
        selectPlaceholder: 'Sélectionner…',
    },
} as const;
