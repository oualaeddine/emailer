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
        title: "Campagnes",
        purpose:
            "La page Campagnes liste vos campagnes e-mail avec des onglets de statut (Toutes, Brouillons, Programmées, En cours, Envoyées, Suspendues, Archivées), la recherche, et pour chaque ligne le taux d'ouverture et de clic.",
        howToUse: [
            {
                step: 1,
                title: "Créer une campagne",
                description: "« Créer une campagne » demande un nom interne puis ouvre la page de configuration.",
            },
            {
                step: 2,
                title: "Agir sur une ligne",
                description: "Le menu « Plus d'actions » propose Modifier, Rapport, Dupliquer, Envoyer un test, Envoyer aux nouveaux contacts, Reprendre, Déplacer, Archiver, Supprimer.",
            },
            {
                step: 3,
                title: "Suivre les statuts",
                description: "Utilisez les onglets pour retrouver les brouillons à terminer ou les envois en cours.",
            },
            {
                step: 4,
                title: "Ouvrir le rapport",
                description: "Cliquez sur l'icône de rapport d'une campagne envoyée pour analyser ses résultats.",
            },
        ],
    },
    templates: {
        topicSlug: 'templates',
        title: "Modèles",
        purpose:
            "La page Modèles affiche vos modèles d'e-mails sous forme de grille avec aperçu, dans les onglets « Mes modèles » et « Galerie ». Le menu de chaque carte propose Modifier, Dupliquer, Envoyer un test, Déplacer, Archiver et Supprimer.",
        howToUse: [
            {
                step: 1,
                title: "Créer un modèle",
                description: "« Créer un modèle » ouvre le sélecteur : galerie, mes modèles, campagnes précédentes, code HTML ou texte enrichi.",
            },
            {
                step: 2,
                title: "Modifier ou dupliquer",
                description: "Ouvrez l'éditeur pour modifier ; dupliquez pour décliner un modèle sans toucher à l'original.",
            },
            {
                step: 3,
                title: "Envoyer un test",
                description: "Envoyez le modèle à vos adresses pour contrôler le rendu réel.",
            },
            {
                step: 4,
                title: "Organiser",
                description: "Classez dans des dossiers, recherchez par nom, archivez les versions obsolètes.",
            },
        ],
    },
    recipients: {
        topicSlug: 'recipients',
        title: "Contacts",
        purpose:
            "La page Contacts liste toutes vos fiches sous forme de tableau, organisé en vues : « Tous les contacts », « Mes contacts » et vos vues enregistrées. Vous y retrouvez l'e-mail, le nom, la société, le statut et les attributs de votre choix, et vous pouvez agir sur plusieurs contacts à la fois.",
        howToUse: [
            {
                step: 1,
                title: "Filtrer et charger une liste",
                description: "Utilisez la recherche, « Charger une liste ou un segment » ou « Ajouter un filtre » pour réduire le tableau. « Enregistrer comme segment » conserve le filtre pour vos campagnes.",
            },
            {
                step: 2,
                title: "Créer ou importer des contacts",
                description: "« Créer un contact » ouvre une fiche individuelle ; « Importer des contacts » lance l'assistant d'import (fichier CSV/Excel ou annuaire PageJaunes).",
            },
            {
                step: 3,
                title: "Agir sur la sélection",
                description: "Cochez des lignes pour les ajouter à une liste, les retirer, les mettre en liste noire, mettre à jour un attribut, exporter ou supprimer.",
            },
            {
                step: 4,
                title: "Personnaliser les colonnes",
                description: "« Personnaliser les colonnes » affiche n'importe quel attribut ; « Enregistrer comme vue » mémorise filtres, colonnes et tri.",
            },
        ],
    },
    'recipients-import': {
        topicSlug: 'recipients-import',
        title: "Assistant d'import de contacts",
        purpose:
            "L'assistant guide l'import en cinq étapes : Fichier, Correspondance, Listes, Paramètres, Lancer. Il accepte les fichiers CSV, XLSX et TXT, le collage de texte et la recherche dans l'annuaire PageJaunes.",
        howToUse: [
            {
                step: 1,
                title: "Charger le fichier",
                description: "Déposez votre fichier ou collez vos adresses. Un aperçu des premières lignes s'affiche.",
            },
            {
                step: 2,
                title: "Faire correspondre les colonnes",
                description: "Associez chaque colonne à un attribut, utilisez « Créer un attribut » pour un champ manquant, ou ignorez la colonne.",
            },
            {
                step: 3,
                title: "Choisir les listes et paramètres",
                description: "Ajoutez les contacts à des listes existantes ou nouvelles ; indiquez s'il faut mettre à jour les contacts existants ou les traiter comme en liste noire.",
            },
            {
                step: 4,
                title: "Lancer l'import",
                description: "Vérifiez le récapitulatif puis « Lancer » ; suivez ensuite le résultat dans l'historique des imports.",
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
        title: "Liste noire",
        purpose:
            "La liste noire regroupe les adresses auxquelles plus rien n'est envoyé. Deux onglets : « Contacts en liste noire » (désabonnement, plainte, blocage manuel) et « Rebonds » (rebonds durs et doux).",
        howToUse: [
            {
                step: 1,
                title: "Rechercher une adresse",
                description: "Saisissez tout ou partie de l'adresse, ou filtrez par motif.",
            },
            {
                step: 2,
                title: "Ajouter une adresse",
                description: "« Ajouter une adresse » bloque immédiatement une adresse, par exemple après une demande téléphonique.",
            },
            {
                step: 3,
                title: "Réactiver",
                description: "L'action de réactivation retire l'adresse de la liste : à réserver aux erreurs avérées, jamais aux plaintes ou rebonds durs.",
            },
            {
                step: 4,
                title: "Comprendre l'effet",
                description: "Une adresse bloquée est écartée de toutes les campagnes, quelle que soit la liste ou le segment ciblé.",
            },
        ],
    },
    reporting: {
        topicSlug: 'reporting',
        title: "Statistiques",
        purpose:
            "La page Statistiques synthétise vos envois sur la période choisie : envoyés, délivrés, ouvertures, clics, désinscriptions, rebonds et plaintes, avec un graphique dans le temps et le tableau des performances par campagne.",
        howToUse: [
            {
                step: 1,
                title: "Choisir la période",
                description: "Utilisez le sélecteur de période pour recalculer tous les indicateurs.",
            },
            {
                step: 2,
                title: "Comparer les campagnes",
                description: "Le tableau « Performance des campagnes » se trie par taux d'ouverture ou de clic.",
            },
            {
                step: 3,
                title: "Analyser par liste",
                description: "La performance par liste montre quelles audiences réagissent le mieux.",
            },
            {
                step: 4,
                title: "Aller au détail",
                description: "Cliquez sur une campagne pour ouvrir son Rapport complet.",
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
    'contact-detail': {
        topicSlug: 'contact-detail',
        title: "Fiche contact",
        purpose:
            "La fiche contact réunit tout ce que l'application sait d'une personne : identité, société, statut d'abonnement, historique d'activité, attributs, listes d'appartenance et notes d'équipe. Elle s'ouvre en cliquant sur une ligne du tableau Contacts.",
        howToUse: [
            {
                step: 1,
                title: "Lire l'activité",
                description: "L'onglet Activité affiche la chronologie des envois, ouvertures, clics, rebonds, désinscriptions, imports et ajouts à une liste.",
            },
            {
                step: 2,
                title: "Modifier les attributs",
                description: "L'onglet Attributs présente un formulaire généré depuis vos définitions d'attributs. Enregistrez pour mettre à jour la fiche.",
            },
            {
                step: 3,
                title: "Gérer les listes",
                description: "L'onglet Listes montre les listes du contact ; ajoutez-le à une liste ou retirez-le en un clic.",
            },
            {
                step: 4,
                title: "Mettre en liste noire",
                description: "Le commutateur « Liste noire » de la carte d'identité bloque tout envoi futur vers ce contact. Les notes servent à consigner le contexte commercial.",
            },
        ],
    },
    lists: {
        topicSlug: 'lists',
        title: "Listes",
        purpose:
            "Une liste est un regroupement statique de contacts que vous ciblez dans vos campagnes. La page Listes affiche, par dossier, le nom, le nombre de contacts, la date de création et le dernier import de chaque liste.",
        howToUse: [
            {
                step: 1,
                title: "Créer une liste",
                description: "Cliquez sur « Créer une liste », saisissez un nom et choisissez un dossier.",
            },
            {
                step: 2,
                title: "Alimenter la liste",
                description: "Ouvrez la liste puis « Importer dans cette liste », ou ajoutez des contacts depuis le tableau Contacts avec l'action « Ajouter à une liste ».",
            },
            {
                step: 3,
                title: "Exporter ou supprimer",
                description: "Depuis le détail, « Exporter » télécharge les contacts ; « Supprimer la liste » retire la liste sans supprimer les contacts.",
            },
            {
                step: 4,
                title: "Utiliser dans une campagne",
                description: "Dans l'étape Destinataires d'une campagne, cochez la liste à cibler ou à exclure.",
            },
        ],
    },
    'list-detail': {
        topicSlug: 'list-detail',
        title: "Détail d'une liste",
        purpose:
            "Le détail d'une liste affiche le tableau des contacts appartenant à cette liste, avec les mêmes outils de recherche et de filtre que la page Contacts, plus les actions propres à la liste.",
        howToUse: [
            {
                step: 1,
                title: "Parcourir les membres",
                description: "Recherchez ou filtrez les contacts de la liste ; cliquez sur une ligne pour ouvrir la fiche.",
            },
            {
                step: 2,
                title: "Importer dans cette liste",
                description: "« Importer dans cette liste » lance l'assistant d'import avec la liste déjà sélectionnée à l'étape Listes.",
            },
            {
                step: 3,
                title: "Retirer des contacts",
                description: "Cochez des lignes puis « Retirer d'une liste » : les contacts restent dans votre base.",
            },
            {
                step: 4,
                title: "Exporter ou supprimer",
                description: "« Exporter » produit un fichier des membres ; « Supprimer la liste » est définitif pour la liste uniquement.",
            },
        ],
    },
    segments: {
        topicSlug: 'segments',
        title: "Segments",
        purpose:
            "Un segment est une audience dynamique définie par des conditions : il se recalcule à chaque utilisation. La page Segments liste vos segments avec leur effectif, leur dossier et leur date de modification.",
        howToUse: [
            {
                step: 1,
                title: "Créer un segment",
                description: "Cliquez sur « Créer un segment » pour ouvrir le constructeur de conditions.",
            },
            {
                step: 2,
                title: "Consulter les membres",
                description: "Ouvrez un segment pour voir les contacts qui correspondent aujourd'hui à ses conditions.",
            },
            {
                step: 3,
                title: "Utiliser dans une campagne",
                description: "L'action « Utiliser dans une campagne » crée une campagne ciblant ce segment ; vous pouvez aussi le choisir à l'étape Destinataires.",
            },
            {
                step: 4,
                title: "Dupliquer ou supprimer",
                description: "Dupliquez un segment pour en tester une variante ; la suppression ne touche pas les contacts.",
            },
        ],
    },
    'segment-edit': {
        topicSlug: 'segment-edit',
        title: "Constructeur de segment",
        purpose:
            "Le constructeur assemble des conditions en groupes ET / OU. Chaque condition associe un champ, un opérateur et une valeur, parmi : Attributs du contact, Listes, Engagement e-mail, Dates et PagesJaunes.",
        howToUse: [
            {
                step: 1,
                title: "Ajouter des conditions",
                description: "Cliquez sur « Ajouter une condition », choisissez la catégorie (par exemple « Engagement e-mail » : a ouvert, a cliqué, n'a pas ouvert dans les N derniers jours).",
            },
            {
                step: 2,
                title: "Combiner avec ET / OU",
                description: "Regroupez les conditions et basculez ET / OU pour affiner l'audience.",
            },
            {
                step: 3,
                title: "Compter en direct",
                description: "Le bouton de calcul affiche le nombre de contacts correspondants avant d'enregistrer.",
            },
            {
                step: 4,
                title: "Enregistrer",
                description: "Donnez un nom (et un dossier) puis « Enregistrer » ; le segment devient disponible dans les campagnes.",
            },
        ],
    },
    attributes: {
        topicSlug: 'attributes',
        title: "Attributs",
        purpose:
            "Les attributs sont les champs personnalisés de vos contacts. Le tableau liste leur nom, type, nombre de contacts concernés et date de création. Ils alimentent les colonnes, les filtres, les segments et les balises de personnalisation.",
        howToUse: [
            {
                step: 1,
                title: "Créer un attribut",
                description: "« Créer un attribut » : nom en lettres majuscules, chiffres et tirets bas (50 caractères max), puis un type : Texte, Nombre, Date, Booléen, Catégorie, Choix multiple ou Utilisateur.",
            },
            {
                step: 2,
                title: "Définir les options",
                description: "Pour une Catégorie ou un Choix multiple, saisissez la liste des valeurs autorisées.",
            },
            {
                step: 3,
                title: "Respecter les attributs système",
                description: "EMAIL, PRENOM, NOM et SOCIETE sont en lecture seule : ils ne peuvent ni être modifiés ni supprimés.",
            },
            {
                step: 4,
                title: "Utiliser les attributs",
                description: "Ils apparaissent dans la correspondance des colonnes à l'import, dans « Personnaliser les colonnes » et dans le menu de personnalisation des e-mails.",
            },
        ],
    },
    imports: {
        topicSlug: 'imports',
        title: "Historique des imports",
        purpose:
            "L'historique recense chaque import de contacts : fichier, date, utilisateur, statut et compteurs (importés, mis à jour, en échec). Il permet de vérifier un import terminé et de récupérer les erreurs.",
        howToUse: [
            {
                step: 1,
                title: "Suivre un import",
                description: "Le statut passe de « En cours » à « Terminé » ; actualisez la page pour voir les compteurs évoluer.",
            },
            {
                step: 2,
                title: "Télécharger les erreurs",
                description: "Pour un import avec échecs, téléchargez le fichier CSV des erreurs, corrigez-le et réimportez-le.",
            },
            {
                step: 3,
                title: "Lancer un nouvel import",
                description: "Cliquez sur « Importer des contacts » pour ouvrir l'assistant en cinq étapes.",
            },
            {
                step: 4,
                title: "Retrouver les contacts",
                description: "Depuis une ligne, ouvrez les listes concernées pour contrôler le résultat.",
            },
        ],
    },
    'campaign-setup': {
        topicSlug: 'campaign-setup',
        title: "Configuration d'une campagne",
        purpose:
            "La page de configuration empile cinq cartes avec coche d'achèvement : Expéditeur, Objet, Destinataires, Design et Paramètres supplémentaires. L'en-tête permet de renommer la campagne, d'ouvrir « Aperçu & test » et de « Programmer ».",
        howToUse: [
            {
                step: 1,
                title: "Compléter les cartes",
                description: "Choisissez l'expéditeur vérifié ; saisissez l'objet et le texte d'aperçu ; sélectionnez les listes et segments à cibler, et ceux à exclure, avec le nombre de destinataires en direct.",
            },
            {
                step: 2,
                title: "Concevoir le message",
                description: "La carte Design ouvre le sélecteur de modèles puis l'éditeur ; réglez ensuite page de désinscription, tags, suivi UTM et pièce jointe dans les paramètres supplémentaires.",
            },
            {
                step: 3,
                title: "Envoyer un test",
                description: "« Aperçu & test » affiche le rendu bureau et mobile et envoie un test à cinq adresses au plus.",
            },
            {
                step: 4,
                title: "Programmer",
                description: "Une fois la checklist verte, « Programmer » ouvre la confirmation : envoyer maintenant, programmer plus tard, envoyer au meilleur moment ou par lots.",
            },
        ],
    },
    'campaign-design': {
        topicSlug: 'campaign-design',
        title: "Éditeur de design",
        purpose:
            "L'éditeur glisser-déposer compose l'e-mail à partir de blocs. Le panneau gauche propose Contenu (Blocs, Sections, Enregistrés) et Style (polices, couleurs, boutons) ; le centre affiche le rendu bureau ou mobile ; le panneau droit règle le bloc sélectionné.",
        howToUse: [
            {
                step: 1,
                title: "Ajouter des blocs",
                description: "Glissez un bloc (titre, texte, image, bouton, colonnes…) ou une section prête à l'emploi vers le canevas.",
            },
            {
                step: 2,
                title: "Personnaliser",
                description: "Dans un bloc texte, le menu de personnalisation insère des balises comme le prénom ou la société ; le style global s'applique à tout l'e-mail.",
            },
            {
                step: 3,
                title: "Contrôler le rendu",
                description: "Basculez entre bureau et mobile, annulez ou rétablissez, puis ouvrez « Aperçu & test ».",
            },
            {
                step: 4,
                title: "Enregistrer",
                description: "« Enregistrer et quitter » renvoie à la configuration de la campagne.",
            },
        ],
    },
    'campaign-report': {
        topicSlug: 'campaign-report',
        title: "Rapport de campagne",
        purpose:
            "Le rapport détaille les résultats d'une campagne envoyée en cinq onglets : Vue d'ensemble, Délivrabilité, Ouvertures, Clics et Désinscriptions, avec comparaison à la moyenne du compte.",
        howToUse: [
            {
                step: 1,
                title: "Lire la vue d'ensemble",
                description: "Les indicateurs Envoyés, Délivrés, Ouvertures uniques, Clics uniques, Désinscriptions et Plaintes sont comparés à votre moyenne ; le graphique suit l'activité par heure puis par jour.",
            },
            {
                step: 2,
                title: "Analyser clics et ouvertures",
                description: "L'onglet Clics classe les liens par clics ; Ouvertures détaille domaines, appareils et pays.",
            },
            {
                step: 3,
                title: "Agir sur les contacts",
                description: "« Voir les contacts » crée un segment (par exemple « a ouvert la campagne X ») à cibler de nouveau.",
            },
            {
                step: 4,
                title: "Exporter ou comparer",
                description: "« Exporter » télécharge l'onglet en CSV ; « Comparer » met plusieurs campagnes côte à côte.",
            },
        ],
    },
    forms: {
        topicSlug: 'forms',
        title: "Formulaires d'inscription",
        purpose:
            "La page Formulaires liste vos formulaires d'inscription avec leurs listes cibles, le nombre d'inscriptions et leur statut. Chaque formulaire dispose d'un lien public (/f/…) et d'un code d'intégration.",
        howToUse: [
            {
                step: 1,
                title: "Créer un formulaire",
                description: "« Créer un formulaire d'inscription » ouvre le constructeur : Champs (attributs, champs obligatoires, case RGPD), Design, Paramètres, Partager.",
            },
            {
                step: 2,
                title: "Régler l'inscription",
                description: "Choisissez les listes cibles, activez ou non la double confirmation, le message de remerciement ou la redirection.",
            },
            {
                step: 3,
                title: "Partager",
                description: "Copiez le lien public ou le code d'intégration (iframe ou HTML) pour votre site.",
            },
            {
                step: 4,
                title: "Suivre les inscriptions",
                description: "Le compteur de la liste des formulaires indique les inscriptions reçues.",
            },
        ],
    },
    'template-edit': {
        topicSlug: 'template-edit',
        title: "Édition d'un modèle",
        purpose:
            "La page d'édition ouvre le modèle dans le même éditeur glisser-déposer que les campagnes. L'historique des versions permet de revenir à un état antérieur.",
        howToUse: [
            {
                step: 1,
                title: "Modifier le contenu",
                description: "Ajoutez ou réorganisez les blocs et sections, réglez le style global.",
            },
            {
                step: 2,
                title: "Prévisualiser",
                description: "Contrôlez le rendu bureau et mobile avant d'enregistrer.",
            },
            {
                step: 3,
                title: "Restaurer une version",
                description: "Le bouton de restauration rouvre une version précédente du modèle.",
            },
            {
                step: 4,
                title: "Enregistrer",
                description: "Enregistrez pour que le modèle à jour soit proposé dans les campagnes.",
            },
        ],
    },
    'transactional-logs': {
        topicSlug: 'transactional-logs',
        title: "Journaux transactionnels",
        purpose:
            "Les journaux affichent en temps réel les messages transactionnels et leurs événements : envoyé, délivré, ouvert, cliqué, rebond, invalide, différé, plainte, désinscrit, bloqué, erreur.",
        howToUse: [
            {
                step: 1,
                title: "Filtrer par événement",
                description: "Choisissez un type d'événement pour isoler par exemple les rebonds ou les erreurs.",
            },
            {
                step: 2,
                title: "Rechercher",
                description: "Cherchez par destinataire, objet ou identifiant de message.",
            },
            {
                step: 3,
                title: "Déplier une ligne",
                description: "Une ligne se déplie pour afficher la chronologie complète des événements.",
            },
            {
                step: 4,
                title: "Diagnostiquer",
                description: "Croisez avec la Liste noire et les Domaines pour comprendre un blocage.",
            },
        ],
    },
    'transactional-settings': {
        topicSlug: 'transactional-settings',
        title: "SMTP & API",
        purpose:
            "Cette page rassemble la configuration technique des envois transactionnels : instructions du relais SMTP, clés API et webhooks sortants.",
        howToUse: [
            {
                step: 1,
                title: "Créer une clé API",
                description: "Créez une clé avec ses autorisations ; copiez-la immédiatement, elle ne sera plus affichée.",
            },
            {
                step: 2,
                title: "Révoquer",
                description: "Révoquez toute clé compromise ou inutilisée.",
            },
            {
                step: 3,
                title: "Configurer un webhook",
                description: "Indiquez l'URL, les événements à recevoir et le secret de signature ; le journal de livraison montre les tentatives.",
            },
            {
                step: 4,
                title: "Envoyer par API",
                description: "Utilisez la clé pour appeler le point d'envoi transactionnel depuis vos applications.",
            },
        ],
    },
    senders: {
        topicSlug: 'senders',
        title: "Expéditeurs",
        purpose:
            "Les expéditeurs sont les identités (nom et adresse) utilisables dans le champ « De » des campagnes. Le tableau indique le domaine, l'état de vérification et l'expéditeur par défaut.",
        howToUse: [
            {
                step: 1,
                title: "Ajouter un expéditeur",
                description: "Saisissez le nom et l'adresse ; un e-mail de vérification est envoyé, sauf si le domaine est déjà authentifié.",
            },
            {
                step: 2,
                title: "Vérifier",
                description: "Cliquez sur le lien reçu pour valider l'expéditeur.",
            },
            {
                step: 3,
                title: "Définir par défaut",
                description: "Marquez l'expéditeur proposé d'office dans les nouvelles campagnes.",
            },
            {
                step: 4,
                title: "Authentifier le domaine",
                description: "Passez par la page Domaines pour améliorer la délivrabilité.",
            },
        ],
    },
    domains: {
        topicSlug: 'domains',
        title: "Domaines",
        purpose:
            "La page Domaines affiche vos domaines d'envoi et l'état de leurs enregistrements DNS : DKIM, SPF et DMARC. Un domaine authentifié protège votre réputation d'expéditeur.",
        howToUse: [
            {
                step: 1,
                title: "Ajouter un domaine",
                description: "Saisissez le domaine ; l'application génère les enregistrements à publier.",
            },
            {
                step: 2,
                title: "Publier les enregistrements",
                description: "Copiez les valeurs DKIM, SPF et DMARC dans la zone DNS de votre hébergeur.",
            },
            {
                step: 3,
                title: "Vérifier",
                description: "« Vérifier » lance le contrôle DNS ; chaque enregistrement affiche son statut.",
            },
            {
                step: 4,
                title: "Associer les expéditeurs",
                description: "Une fois authentifié, les expéditeurs du domaine sont validés automatiquement.",
            },
        ],
    },
    'unsubscribe-pages': {
        topicSlug: 'unsubscribe-pages',
        title: "Pages de désinscription",
        purpose:
            "Cette page personnalise ce que voit un contact qui se désinscrit : textes, logo et couleurs issus de l'identité visuelle, message de confirmation, et page de préférences (/preferences/…) permettant de se retirer liste par liste.",
        howToUse: [
            {
                step: 1,
                title: "Modifier les textes",
                description: "Adaptez le titre, le message et le bouton de confirmation.",
            },
            {
                step: 2,
                title: "Activer les préférences",
                description: "Proposez aux contacts de choisir les listes dont ils se désabonnent.",
            },
            {
                step: 3,
                title: "Prévisualiser",
                description: "Ouvrez l'aperçu avant d'enregistrer.",
            },
            {
                step: 4,
                title: "Utiliser dans une campagne",
                description: "Choisissez la page voulue dans les paramètres supplémentaires d'une campagne.",
            },
        ],
    },
    'update-profile-forms': {
        topicSlug: 'update-profile-forms',
        title: "Formulaires de mise à jour",
        purpose:
            "Les formulaires de mise à jour du profil permettent à un contact de corriger lui-même ses attributs via un lien dans l'e-mail.",
        howToUse: [
            {
                step: 1,
                title: "Créer un formulaire",
                description: "Nommez le formulaire et choisissez les attributs modifiables.",
            },
            {
                step: 2,
                title: "Personnaliser",
                description: "Réglez textes, bouton et message de remerciement.",
            },
            {
                step: 3,
                title: "Prévisualiser",
                description: "Contrôlez l'affichage avant publication.",
            },
            {
                step: 4,
                title: "Associer à une campagne",
                description: "Sélectionnez-le dans les paramètres supplémentaires de la campagne.",
            },
        ],
    },
    automations: {
        topicSlug: 'automations',
        title: "Automatisations",
        purpose:
            "Une automatisation envoie automatiquement des e-mails à vos contacts lorsqu'un déclencheur se produit (inscription à une liste, entrée dans un segment, date…). La page liste vos scénarios avec leur statut et leurs résultats.",
        howToUse: [
            {
                step: 1,
                title: "Créer une automatisation",
                description: "Cliquez sur « Créer une automatisation », nommez-la et choisissez le scénario de départ.",
            },
            {
                step: 2,
                title: "Définir le déclencheur",
                description: "Indiquez ce qui fait entrer un contact dans le scénario : ajout à une liste, segment, date ou événement.",
            },
            {
                step: 3,
                title: "Composer les étapes",
                description: "Enchaînez e-mails et délais d'attente ; chaque e-mail utilise un expéditeur vérifié et un modèle.",
            },
            {
                step: 4,
                title: "Activer et suivre",
                description: "Activez l'automatisation puis suivez les contacts entrés, les envois et les résultats depuis la liste.",
            },
        ],
    },
    'two-factor': {
        topicSlug: 'two-factor',
        title: 'Double authentification',
        purpose:
            "La double authentification est obligatoire : après votre mot de passe, un code à 6 chiffres généré par une application sur votre téléphone confirme que c'est bien vous.",
        howToUse: [
            {
                step: 1,
                title: 'Scanner le QR code',
                description: "À la première connexion, ajoutez le compte dans votre application d'authentification en scannant le QR code, ou saisissez la clé manuellement.",
            },
            {
                step: 2,
                title: 'Confirmer avec un code',
                description: "Saisissez le code à 6 chiffres affiché par l'application, puis cliquez sur « Activer ».",
            },
            {
                step: 3,
                title: 'Noter les codes de récupération',
                description: 'Les 8 codes à usage unique ne sont affichés qu’une seule fois : conservez-les en lieu sûr.',
            },
            {
                step: 4,
                title: 'Se connecter ensuite',
                description: "À chaque connexion, saisissez le code courant puis « Vérifier », ou utilisez un code de récupération si votre téléphone est indisponible.",
            },
        ],
    },
    'automation-edit': {
        topicSlug: 'automation-edit',
        title: "Éditeur d'automatisation",
        purpose:
            "L'éditeur construit le scénario d'une automatisation : le déclencheur qui fait entrer un contact, puis les étapes qu'il parcourt (e-mails, attentes, conditions, listes, attributs).",
        howToUse: [
            {
                step: 1,
                title: 'Configurer le déclencheur',
                description: "Dans l'onglet Flux, cliquez sur la carte Déclencheur et complétez ses réglages (liste, formulaire, campagne, attribut ou date).",
            },
            {
                step: 2,
                title: 'Ajouter les étapes',
                description: "Utilisez le bouton « + » pour insérer un e-mail, une attente, une condition oui/non, un ajout ou retrait de liste, ou une mise à jour d'attribut.",
            },
            {
                step: 3,
                title: 'Régler les paramètres',
                description: "Dans Paramètres, vérifiez le nom, la description et la réentrée des contacts.",
            },
            {
                step: 4,
                title: 'Activer et suivre',
                description: "« Activer » démarre le scénario ; l'onglet Activité montre les contacts entrés et la performance de chaque e-mail. Mettez en pause pour modifier le flux.",
            },
        ],
    },
    'form-edit': {
        topicSlug: 'form-edit',
        title: "Éditeur de formulaire d'inscription",
        purpose:
            "L'éditeur règle les champs, le design, les listes cibles et le comportement d'un formulaire d'inscription, avec un aperçu en direct, puis le publie et fournit le lien et les codes à intégrer.",
        howToUse: [
            {
                step: 1,
                title: 'Choisir les champs',
                description: "Dans Champs, ajoutez des attributs, réglez libellés et champs obligatoires, et activez au besoin la case de consentement RGPD.",
            },
            {
                step: 2,
                title: 'Régler les paramètres',
                description: "Dans Paramètres, choisissez les listes de destination, la double confirmation, le message de remerciement ou la redirection.",
            },
            {
                step: 3,
                title: 'Publier',
                description: "« Publier » enregistre et active le formulaire ; « Désactiver » le retire. Une liste cible est obligatoire.",
            },
            {
                step: 4,
                title: 'Partager',
                description: "Dans Partager, copiez le lien public, le code iframe ou le code HTML à intégrer dans votre site.",
            },
        ],
    },
};
