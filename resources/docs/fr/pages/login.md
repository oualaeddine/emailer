# Connexion

La page **Connexion** est la porte d'entrée de PageJaunes Mailer. Elle vérifie votre identité avant de vous donner accès à vos e-mails, à vos destinataires et à vos campagnes. Chaque collaborateur dispose de son propre compte : ne partagez jamais vos identifiants, car toutes les actions réalisées dans l'application sont enregistrées au nom de la personne connectée.

![Page de connexion](/docs/screenshots/login/01-login.png)

## Se connecter

1. Ouvrez l'adresse de l'application dans votre navigateur. Si vous n'êtes pas connecté, la page **Connexion** s'affiche automatiquement.
2. Saisissez votre **Adresse e-mail** professionnelle, celle que l'administrateur a utilisée pour créer votre compte (par exemple `k.belkacem@menuiserie-bejaia.dz`).
3. Saisissez votre **Mot de passe**.
4. Cochez éventuellement **Se souvenir de moi**.
5. Cliquez sur **Se connecter**.

Une fois la connexion réussie, vous arrivez sur le [Tableau de bord](help:dashboard). Si votre compte est protégé par la double authentification — c'est obligatoire —, un second écran vous demande d'abord un code à 6 chiffres : voir [Double authentification](help:two-factor).

## Les champs du formulaire

| Champ | Obligatoire | Détail |
| --- | --- | --- |
| Adresse e-mail | Oui | L'adresse exacte de votre compte, en minuscules. Ex. : `contact@transport-oran.dz` |
| Mot de passe | Oui | Sensible à la casse ; les majuscules comptent |
| Se souvenir de moi | Non | Garde la session ouverte sur ce navigateur après la fermeture de l'onglet |

Le bouton **Se connecter** reste inactif pendant la vérification : un seul clic suffit, inutile de cliquer plusieurs fois.

## « Se souvenir de moi » : quand l'utiliser

Cochez cette case uniquement sur un poste dont vous êtes le seul utilisateur — votre ordinateur de bureau, par exemple. Sur un poste partagé (accueil, salle de réunion, cybercafé), laissez la case décochée : sans elle, la session se ferme lorsque vous quittez le navigateur.

Dans tous les cas, terminez votre journée par **Se déconnecter**, dans le menu de navigation.

## Ce que vous voyez après la connexion

Les pages accessibles dépendent de votre rôle. Un **Opérateur marketing** rédige et envoie, mais n'accède pas à la gestion des utilisateurs ; un **Lecteur** consulte les résultats sans pouvoir modifier quoi que ce soit. Si une rubrique attendue n'apparaît pas dans le menu, c'est une question de droits : contactez votre administrateur, qui les ajuste depuis la page [Utilisateurs](help:admin-users).

## Double authentification

Après le mot de passe, l'application demande un code généré par votre téléphone. À la toute première connexion, vous devez d'abord activer cette protection en scannant un QR code et en notant vos codes de récupération. Toute la marche à suivre, y compris le cas du téléphone perdu, est dans la page [Double authentification](help:two-factor).

## Changer votre mot de passe

Ouvrez le menu du compte (votre nom, en haut à droite) puis **Changer le mot de passe**. Saisissez votre **Mot de passe actuel**, puis deux fois le **Nouveau mot de passe** : 12 caractères minimum, avec majuscules, minuscules et chiffres, et différent de l'actuel. Après l'enregistrement, vos autres sessions ouvertes sont fermées ; celle-ci reste active. Faites-le dès votre première connexion si un administrateur vous a transmis un mot de passe initial.

## En cas de problème

- **« Identifiants incorrects ou compte désactivé. »** : ce même message apparaît pour un mot de passe erroné et pour un compte désactivé, par sécurité. Vérifiez d'abord la disposition du clavier (AZERTY/QWERTY) et la touche Verr. Maj., puis contactez l'administrateur si le message persiste.
- **Mot de passe oublié** : l'application ne propose pas de réinitialisation automatique. Un administrateur définit un nouveau mot de passe pour vous depuis la page [Utilisateurs](help:admin-users) ; changez-le ensuite vous-même avec **Changer le mot de passe**.
- **Vous êtes déconnecté sans raison apparente** : la session expire après une période d'inactivité. Reconnectez-vous ; votre travail en cours dans le [Rédacteur d'e-mail](help:composer) a été enregistré automatiquement en brouillon.
- **La page ne s'affiche pas correctement** : videz le cache du navigateur (Ctrl+F5). L'application est prévue pour les versions récentes de Chrome, Edge et Firefox.

> Après la connexion, la visite guidée continue sur le [Tableau de bord](help:dashboard).
