# 49 — Notes de version : mise en service de PageJaunes Mailer

*À diffuser aux équipes avant l’ouverture. Version française destinée aux utilisateurs ;
la procédure technique de déploiement est dans [48-deploy-runbook.md](48-deploy-runbook.md).*

---

**Objet : PageJaunes Mailer est disponible**

Bonjour à toutes et à tous,

Notre nouvel outil d’e-mailing, **PageJaunes Mailer**, est en service à l’adresse
communiquée par l’équipe technique. Il remplace nos envois manuels : campagnes,
automatisations, e-mails transactionnels et suivi des résultats, au même endroit.

## Première connexion

1. Connectez-vous avec l’adresse e-mail et le mot de passe provisoire transmis par votre
   administrateur, puis changez ce mot de passe : menu du compte (en haut à droite) →
   *Changer le mot de passe*.
2. **La double authentification est obligatoire.** À la première connexion, scannez le
   QR code avec une application d’authentification (Google Authenticator, Microsoft
   Authenticator, Authy…) puis saisissez le code à 6 chiffres. Conservez les **codes de
   récupération** affichés : ils servent si vous perdez votre téléphone.
3. Votre rôle (Administrateur, Responsable marketing, Opérateur marketing, Lecteur) détermine les
   menus visibles. Un menu absent est normal si votre rôle n’y donne pas accès.

## Ce que vous trouverez

| Menu | À quoi ça sert |
|---|---|
| **Accueil** | Vue d’ensemble : derniers envois, statistiques clés, raccourcis. |
| **Contacts** | Contacts, **Listes**, **Segments** (filtres dynamiques), **Attributs** (champs personnalisés), **Imports** (CSV/Excel), **Liste noire**, **Entreprises PagesJaunes** (annuaire). |
| **Campagnes → E-mails** | Créer, tester, programmer et analyser les campagnes. |
| **Formulaires** | Formulaires d’inscription à intégrer sur un site, avec double opt-in. |
| **Automatisations** | Scénarios automatiques (bienvenue, relance, anniversaire…). |
| **Transactionnel** | Journaux et modèles des e-mails envoyés par nos applications (confirmations, notifications). |
| **Statistiques** | Ouvertures, clics, désinscriptions, rebonds, par campagne et dans le temps. |
| **Messagerie** | Boîte de réception et envoi de messages individuels. |
| **Aide** (icône « ? ») | Centre d’aide : guides pas à pas pour chaque fonctionnalité. |

## Créer une campagne, en bref

1. **Campagnes → E-mails → Créer une campagne.**
2. Renseignez l’**objet**, le **texte d’aperçu** (la ligne grise affichée après l’objet
   dans la boîte de réception) et l’**expéditeur**.
3. Choisissez un **modèle** dans la galerie ou partez d’une page blanche. L’éditeur
   fonctionne par glisser-déposer ; enregistrez une section pour la réutiliser plus tard
   (onglet « Enregistrés »).
4. Choisissez les **destinataires** (listes ou segments).
5. Consultez la **liste de contrôle** à droite : elle signale ce qui manque avant l’envoi.
6. **Envoyez un test** à vous-même, puis **programmez** ou envoyez.

## Points importants

- **Expéditeurs vérifiés.** Une campagne ne peut partir qu’au nom d’une adresse
  d’expédition vérifiée. Pour en ajouter une, demandez à un administrateur
  (*Paramètres → Expéditeurs, domaines & IP*).
- **Champs de fusion.** `{{ contact.PRENOM }}` et les autres champs sont remplacés par la
  valeur de chaque contact. L’outil vérifie qu’ils existent et vous alerte en cas de
  faute de frappe, avant l’envoi.
- **Vérification des liens.** Le bouton « Vérifier les liens » de la liste de contrôle
  détecte les liens cassés.
- **Test A/B.** Comparez deux objets ou deux contenus sur une partie de la liste ; la
  meilleure version part automatiquement au reste. L’aperçu et l’envoi de test
  permettent de basculer entre la version A et la version B.
- **Meilleur moment d’envoi.** L’option « Envoyer au meilleur moment » envoie à chaque contact à
  l’heure où il ouvre habituellement ses e-mails.
- **Désinscription.** Chaque campagne contient automatiquement un lien de désinscription
  et l’en-tête « désinscription en un clic » reconnu par Gmail et Outlook. Ne le retirez
  pas : c’est une obligation légale.
- **Double opt-in.** Les inscriptions via formulaire doivent être confirmées par le
  contact (e-mail de confirmation) avant de recevoir nos campagnes.
- **Clés API.** Les intégrations utilisent des clés limitées à des autorisations
  précises. Ne partagez jamais une clé par e-mail ou messagerie.

## Confort

- **Thème sombre** : menu du compte (en haut à droite) → *Thème* → Clair, Sombre ou
  Système.
- L’outil fonctionne aussi sur **téléphone** pour consulter les statistiques et les
  contacts ; la création de campagnes reste plus confortable sur ordinateur.

## Besoin d’aide ?

Consultez d’abord le **centre d’aide** (icône « ? »). Pour un problème d’accès ou un
comportement anormal, ou si vous avez oublié votre mot de passe, contactez l’administrateur de la plateforme en précisant la page,
l’heure et, si possible, une capture d’écran.

Bons envois !
