export interface PageGuideStep {
    step: number;
    title: string;
    description: string;
}

export interface PageGuideDefinition {
    topicSlug: string;
    title: string;
    purpose: string;
    howToUse: PageGuideStep[];
}

export const pageGuides: Record<string, PageGuideDefinition> = {
    dashboard: {
        topicSlug: 'dashboard',
        title: 'Tableau de bord',
        purpose:
            "Le Tableau de bord offre une vue d'ensemble en temps réel de votre activité d'e-mailing B2B : volumes d'envois quotidiens, taux de délivrabilité, consommation des quotas SMTP et état des campagnes récentes.",
        howToUse: [
            {
                step: 1,
                title: "Vérifier le volume d'envoi",
                description: 'Consultez la carte du volume pour vérifier le nombre de messages diffusés jour par jour.',
            },
            {
                step: 2,
                title: 'Surveiller la délivrabilité',
                description: "Assurez-vous que le taux reste supérieur à 90 % pour maintenir une bonne réputation d'expéditeur.",
            },
            {
                step: 3,
                title: 'Contrôler les quotas SMTP',
                description: 'Vérifiez la jauge de chaque compte avant de programmer une campagne volumineuse.',
            },
            {
                step: 4,
                title: 'Suivre les campagnes récentes',
                description: 'Accédez rapidement au statut des derniers envois (en cours, terminé, programmé).',
            },
        ],
    },
    mailbox: {
        topicSlug: 'mailbox',
        title: 'Boîte de réception & Suivi des messages',
        purpose:
            'La Boîte de réception centralise l’historique de chaque e-mail envoyé ou préparé : statuts d’acheminement (envoyé, distribué, ouvert, cliqué, rebond) et relance immédiate des envois en attente.',
        howToUse: [
            {
                step: 1,
                title: 'Naviguer dans les dossiers',
                description: 'Consultez les Brouillons, la Boîte d’envoi (en cours/échecs), les Programmés ou les Envoyés.',
            },
            {
                step: 2,
                title: 'Rechercher un destinataire',
                description: 'Saisissez une adresse e-mail dans la barre de recherche pour retrouver un message précis.',
            },
            {
                step: 3,
                title: 'Examiner la chronologie',
                description: 'Cliquez sur un message pour afficher le volet de lecture avec les heures précises d’ouverture et de clic.',
            },
            {
                step: 4,
                title: 'Relancer un échec',
                description: 'Dans la boîte d’envoi, cliquez sur « Réessayer » pour renvoyer un message temporairement bloqué.',
            },
        ],
    },
    composer: {
        topicSlug: 'composer',
        title: 'Rédaction d’un e-mail',
        purpose:
            'Le Compositeur permet de concevoir, formater et expédier un e-mail professionnel individualisé ou un message test, avec prise en charge du texte enrichi, du code HTML et des variables de personnalisation.',
        howToUse: [
            {
                step: 1,
                title: 'Renseigner le destinataire et l’objet',
                description: 'Indiquez l’adresse cible et un objet clair et engageant pour maximiser le taux d’ouverture.',
            },
            {
                step: 2,
                title: 'Rédiger et personnaliser',
                description: 'Utilisez l’éditeur visuel et insérez des balises comme {{first_name}} ou {{company_name}}.',
            },
            {
                step: 3,
                title: 'Sélectionner une signature',
                description: 'Choisissez la signature professionnelle adaptée dans la liste déroulante.',
            },
            {
                step: 4,
                title: 'Aperçu multi-écrans et envoi',
                description: 'Basculez entre les modes Bureau, Tablette et Mobile avant d’enregistrer ou d’expédier.',
            },
        ],
    },
    campaigns: {
        topicSlug: 'campaigns',
        title: 'Campagnes d’e-mailing',
        purpose:
            'Le module Campagnes permet de piloter vos diffusions massives vers vos segments de prospects : création guidée en 4 étapes, planification horaire, suivi en direct et duplication en un clic.',
        howToUse: [
            {
                step: 1,
                title: 'Créer une campagne',
                description: 'Cliquez sur « Nouvelle campagne » pour lancer l’assistant (nom, audience, modèle, planification).',
            },
            {
                step: 2,
                title: 'Sélectionner l’audience',
                description: 'Ciblez vos contacts par étiquettes, wilayas ou critères géographiques spécifiques.',
            },
            {
                step: 3,
                title: 'Piloter l’envoi en cours',
                description: 'Utilisez le menu d’action pour mettre en pause, reprendre ou annuler une campagne.',
            },
            {
                step: 4,
                title: 'Cloner une campagne existante',
                description: 'Dupliquez une campagne performante pour réutiliser sa structure sans la recréer.',
            },
        ],
    },
    templates: {
        topicSlug: 'templates',
        title: 'Modèles d’e-mails',
        purpose:
            'Le gestionnaire de modèles standardise votre communication commerciale grâce à des gabarits réutilisables, intégrant la charte graphique de votre entreprise et les champs de fusion dynamiques.',
        howToUse: [
            {
                step: 1,
                title: 'Ajouter un nouveau modèle',
                description: 'Cliquez sur « Nouveau modèle » et nommez votre gabarit selon votre typologie d’offre.',
            },
            {
                step: 2,
                title: 'Structurer le contenu',
                description: 'Rédigez le message type avec les balises {{company_name}}, {{wilaya_label}} et un bouton d’action.',
            },
            {
                step: 3,
                title: 'Organiser par catégorie',
                description: 'Classez vos modèles (Prospection, Relance, Devis, Partenariat) pour les retrouver facilement.',
            },
            {
                step: 4,
                title: 'Archiver les versions obsolètes',
                description: 'Archivez les anciens modèles sans supprimer l’historique des campagnes qui les ont utilisés.',
            },
        ],
    },
    recipients: {
        topicSlug: 'recipients',
        title: 'Gestion des destinataires',
        purpose:
            'Cette page constitue votre référentiel de contacts B2B : centralisation des fiches prospects, suivi de leur état de délivrabilité (actif, supprimé, invalide) et segmentation par étiquettes métiers.',
        howToUse: [
            {
                step: 1,
                title: 'Rechercher un prospect',
                description: 'Filtrez instantanément par nom d’entreprise, commune ou adresse e-mail.',
            },
            {
                step: 2,
                title: 'Ajouter un contact manuel',
                description: 'Cliquez sur « Nouveau destinataire » pour saisir une fiche individuelle qualifiée.',
            },
            {
                step: 3,
                title: 'Gérer les étiquettes',
                description: 'Associez des tags (ex. BTP, Grossiste, Alger) pour cibler précisément vos futures campagnes.',
            },
            {
                step: 4,
                title: 'Vérifier la délivrabilité',
                description: 'Repérez les badges de statut pour écarter les adresses signalées ou obsolètes.',
            },
        ],
    },
    'recipients-import': {
        topicSlug: 'recipients-import',
        title: 'Import de fichiers (CSV / Excel)',
        purpose:
            'L’assistant d’import permet d’intégrer rapidement des bases de prospects externes (.csv, .xlsx) avec détection automatique des colonnes, prévisualisation et élimination des doublons.',
        howToUse: [
            {
                step: 1,
                title: 'Téléverser votre fichier',
                description: 'Glissez-déposez ou sélectionnez votre fichier tableur (.csv, .txt ou .xlsx).',
            },
            {
                step: 2,
                title: 'Mapper les colonnes',
                description: 'Faites correspondre les colonnes de votre fichier avec l’e-mail, le nom et l’entreprise.',
            },
            {
                step: 3,
                title: 'Valider les lignes',
                description: 'Passez en revue le rapport d’intégrité (lignes valides, erreurs de syntaxe, doublons).',
            },
            {
                step: 4,
                title: 'Confirmer l’import',
                description: 'Cliquez sur « Importer » pour insérer définitivement les contacts dans votre base.',
            },
        ],
    },
    'pagejaunes-search': {
        topicSlug: 'pagejaunes-search',
        title: 'Annuaire PageJaunes Algérie',
        purpose:
            'Ce module interroge directement la base officielle de Pages Jaunes Algérie pour identifier de nouveaux prospects professionnels, filtrer par secteur ou wilaya et extraire leurs adresses e-mails vérifiées.',
        howToUse: [
            {
                step: 1,
                title: 'Saisir des mots-clés',
                description: 'Recherchez par activité, nom d’établissement ou secteur (ex. « menuiserie », « import export »).',
            },
            {
                step: 2,
                title: 'Analyser les fiches entreprises',
                description: 'Consultez la localisation (wilaya, commune) et les attributs professionnels (producteur, distributeur).',
            },
            {
                step: 3,
                title: 'Identifier les e-mails disponibles',
                description: 'Vérifiez la liste des adresses électroniques répertoriées pour chaque société trouvée.',
            },
            {
                step: 4,
                title: 'Alimenter vos listes de diffusion',
                description: 'Importez les contacts ciblés dans vos destinataires pour lancer vos campagnes de prospection.',
            },
        ],
    },
    suppression: {
        topicSlug: 'suppression',
        title: 'Liste de suppression & Désabonnements',
        purpose:
            'La liste de suppression garantit votre conformité légale et protège la réputation de votre nom de domaine en bloquant automatiquement tout envoi vers les adresses désabonnées, erronées ou ayant signalé du spam.',
        howToUse: [
            {
                step: 1,
                title: 'Consulter les motifs de blocage',
                description: 'Identifiez la raison du blocage : rebond définitif, plainte spam, désabonnement global ou blocage manuel.',
            },
            {
                step: 2,
                title: 'Ajouter un blocage préventif',
                description: 'Cliquez sur « Nouvelle entrée » pour inscrire manuellement une adresse concurrente ou sensible.',
            },
            {
                step: 3,
                title: 'Rechercher dans la liste noire',
                description: 'Vérifiez si une adresse spécifique est bloquée à l’aide du champ de recherche rapide.',
            },
            {
                step: 4,
                title: 'Débloquer une adresse',
                description: 'Supprimez une entrée manuelle légitime après confirmation explicite de votre prospect.',
            },
        ],
    },
    reporting: {
        topicSlug: 'reporting',
        title: 'Rapports & Statistiques avancées',
        purpose:
            'Le centre de rapports analyse la performance de délivrabilité et l’engagement de vos campagnes : taux d’ouverture, clics, rebonds et répartition détaillée par compte SMTP ou par campagne.',
        howToUse: [
            {
                step: 1,
                title: 'Définir la plage de dates',
                description: 'Sélectionnez la période d’analyse souhaitée (30 derniers jours, trimestre ou personnalisé).',
            },
            {
                step: 2,
                title: 'Filtrer par campagne ou SMTP',
                description: 'Isolez une campagne spécifique ou comparez l’efficacité de vos différents relais SMTP.',
            },
            {
                step: 3,
                title: 'Analyser les indicateurs clés',
                description: 'Examinez les cartes KPI : taux de délivrance, taux d’ouverture unique et taux de clic.',
            },
            {
                step: 4,
                title: 'Exporter les données',
                description: 'Cliquez sur « Exporter » pour télécharger un fichier tableur complet pour vos bilans.',
            },
        ],
    },
    smtp: {
        topicSlug: 'smtp',
        title: 'Gestion des comptes SMTP',
        purpose:
            'Cette page administre les serveurs d’envoi de messagerie : configuration des accès, sécurisation des protocoles (TLS/SSL), surveillance de la santé du serveur et plafonnement des quotas journaliers.',
        howToUse: [
            {
                step: 1,
                title: 'Ajouter un compte d’envoi',
                description: 'Cliquez sur « Nouveau compte » et renseignez l’hôte, le port (587/465) et vos identifiants.',
            },
            {
                step: 2,
                title: 'Définir un quota quotidien',
                description: 'Fixez une limite d’envois par jour pour respecter les conditions de votre hébergeur.',
            },
            {
                step: 3,
                title: 'Tester la connexion en direct',
                description: 'Cliquez sur « Tester » pour envoyer immédiatement un message de contrôle et vérifier la liaison.',
            },
            {
                step: 4,
                title: 'Surveiller la santé du serveur',
                description: 'Contrôlez les pastilles d’état : Sain (vert), Dégradé (orange) ou Défaillant (rouge).',
            },
        ],
    },
    'admin-users': {
        topicSlug: 'admin-users',
        title: 'Gestion des utilisateurs & Rôles',
        purpose:
            'Ce panneau administre les membres de votre équipe ayant accès à PageJaunes Mailer : création de comptes, attribution de rôles sécurisés (Administrateur, Responsable, Opérateur, Lecteur) et réinitialisation de mots de passe.',
        howToUse: [
            {
                step: 1,
                title: 'Créer un utilisateur',
                description: 'Cliquez sur « Nouvel utilisateur » et renseignez le nom, l’adresse e-mail et le mot de passe.',
            },
            {
                step: 2,
                title: 'Assigner un rôle',
                description: 'Attribuez le niveau de permissions adéquat selon les responsabilités de votre collaborateur.',
            },
            {
                step: 3,
                title: 'Gérer l’activation',
                description: 'Désactivez temporairement l’accès d’un compte sans effacer son historique d’activité.',
            },
            {
                step: 4,
                title: 'Modifier les informations',
                description: 'Mettez à jour les profils ou renouvelez les identifiants d’un membre de l’équipe.',
            },
        ],
    },
    'audit-log': {
        topicSlug: 'audit-log',
        title: 'Journal d’audit & Sécurité',
        purpose:
            'Le Journal d’audit enregistre de façon inaltérable chaque action sensible réalisée sur la plateforme : modifications de configuration, création d’utilisateurs, envois de campagnes et imports de contacts.',
        howToUse: [
            {
                step: 1,
                title: 'Filtrer par action ou utilisateur',
                description: 'Recherchez un événement précis par nom d’utilisateur, type d’entité ou date.',
            },
            {
                step: 2,
                title: 'Inspecter les détails de modification',
                description: 'Affichez les valeurs avant et après chaque changement pour vérifier qui a modifié quoi.',
            },
            {
                step: 3,
                title: 'Vérifier les adresses IP',
                description: 'Contrôlez la provenance des connexions pour garantir la sécurité de votre espace de travail.',
            },
            {
                step: 4,
                title: 'Consulter l’historique d’export',
                description: 'Gardez une traçabilité totale sur les exports de données et listes de prospects.',
            },
        ],
    },
    'settings-branding': {
        topicSlug: 'settings-branding',
        title: 'Identité visuelle & Paramètres de marque',
        purpose:
            'Personnalisez l’apparence de l’application pour l’aligner sur votre entreprise : nom officiel de l’organisation, couleur de marque maîtresse (#FFD400 Pages Jaunes DZ) et thème d’affichage par défaut (Clair, Sombre ou Système).',
        howToUse: [
            {
                step: 1,
                title: 'Définir la raison sociale',
                description: 'Renseignez le nom officiel qui apparaîtra dans la barre de titre et en pied de message.',
            },
            {
                step: 2,
                title: 'Ajuster la couleur de marque',
                description: 'Choisissez la couleur primaire hexadécimale (par défaut le jaune #FFD400 de Pages Jaunes DZ).',
            },
            {
                step: 3,
                title: 'Sélectionner le thème par défaut',
                description: 'Définissez le mode Clair, Sombre ou Système appliqué automatiquement à l’équipe.',
            },
            {
                step: 4,
                title: 'Enregistrer les paramètres',
                description: 'Cliquez sur « Enregistrer » pour diffuser instantanément les changements à tous les utilisateurs.',
            },
        ],
    },
    login: {
        topicSlug: 'login',
        title: 'Connexion à la plateforme',
        purpose:
            'Portail d’authentification sécurisé pour accéder à votre espace de travail PageJaunes Mailer.',
        howToUse: [
            {
                step: 1,
                title: 'Saisir vos identifiants',
                description: 'Entrez votre adresse e-mail professionnelle et votre mot de passe secret.',
            },
            {
                step: 2,
                title: 'Mémoriser votre session',
                description: 'Cochez « Se souvenir de moi » si vous travaillez sur votre poste de travail individuel.',
            },
            {
                step: 3,
                title: 'Accéder à l’espace',
                description: 'Cliquez sur « Se connecter » pour accéder directement au tableau de bord.',
            },
        ],
    },
};
