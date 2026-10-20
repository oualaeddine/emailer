# Utilisateurs

La page **Utilisateurs** liste toutes les personnes autorisées à se connecter à l'application. C'est ici que l'administrateur crée les comptes, attribue un rôle — donc les droits — et désactive un compte lorsqu'un collaborateur quitte l'entreprise.

![Liste des utilisateurs](/docs/screenshots/admin-users/01-users.png)

## Ce que contient la liste

| Colonne | Contenu | Exemple |
| --- | --- | --- |
| Nom | Nom complet du collaborateur | `Nadia Cherif` |
| Adresse e-mail | Identifiant de connexion, unique dans l'application | `nadia.cherif@pagejaunes.dz` |
| Rôle | Rôle attribué, qui détermine tous les droits | `Responsable marketing` |
| Statut | Pastille verte **Actif** ou rouge **Inactif** | Actif |
| Dernière connexion | Date et heure de la dernière ouverture de session, ou **Jamais** | `12/03/2026 09:41` |
| Actions | Le bouton crayon **Modifier** et, sur les autres comptes que le vôtre, le bouton bouclier **Réinitialiser la double authentification** | — |

La colonne **Dernière connexion** est un bon indicateur de comptes oubliés : un compte actif affichant « Jamais » depuis des mois devrait être désactivé.

## Créer un utilisateur

1. Cliquez sur **Nouvel utilisateur** en haut à droite.
2. Renseignez le nom, l'adresse e-mail, un mot de passe initial et le rôle. Le détail des champs est dans [Fenêtre — Utilisateur](help:dialog-user-form).
3. Cliquez sur **Enregistrer**.

![Fenêtre de création d'un utilisateur](/docs/screenshots/admin-users/02-dialog.png)

Le compte est actif immédiatement. Transmettez le mot de passe initial à la personne par un canal séparé et demandez-lui de le changer dès sa première [connexion](help:login), avec **Changer le mot de passe** dans le menu du compte.

## Modifier un utilisateur

Le bouton crayon de la ligne ouvre la même fenêtre en mode modification. Le **nom**, le **rôle** et le **statut** sont modifiables, et vous pouvez définir un **nouveau mot de passe** (laissez le champ vide pour le conserver). L'adresse e-mail n'apparaît plus, car elle sert d'identifiant permanent. Sur votre propre compte, le champ mot de passe est absent : passez par **Changer le mot de passe** dans le menu du compte.

Changer le rôle prend effet dès la prochaine navigation de la personne concernée : les pages auxquelles elle n'a plus droit disparaissent de son menu.

## Réinitialiser la double authentification

Lorsqu'une personne a perdu son téléphone et ses codes de récupération, cliquez sur le bouton **Réinitialiser la double authentification** de sa ligne. Une fenêtre de confirmation rappelle les conséquences ; cliquez sur **Réinitialiser** pour valider, ou sur **Annuler** pour renoncer. Un message « Double authentification réinitialisée. » confirme l'opération.

La personne est déconnectée partout, ses clés API sont révoquées, et à sa prochaine [connexion](help:login) elle doit configurer de nouveau son application d'authentification (voir [Double authentification](help:two-factor)). Cette action n'est pas proposée sur votre propre ligne : un autre administrateur doit la faire pour vous. Si vous êtes le seul administrateur, l'équipe technique peut rétablir votre accès avec `php artisan users:reset-two-factor`.

## Les rôles disponibles

Chaque utilisateur possède un rôle et un seul. Le rôle détermine à la fois les pages visibles et les actions autorisées.

| Rôle | Peut faire | Ne peut pas faire |
| --- | --- | --- |
| **Administrateur** | Tout : comptes SMTP, utilisateurs, journal d'audit, paramètres, envois, campagnes | — |
| **Responsable marketing** | Composer et envoyer, gérer les modèles, les destinataires, la liste de suppression, approuver les campagnes, voir tous les envois de l'équipe, modifier l'identité visuelle | Gérer les comptes SMTP, les utilisateurs, consulter le journal d'audit |
| **Opérateur marketing** | Composer et envoyer ses propres messages et campagnes, importer et gérer des destinataires | Approuver une campagne, curer la bibliothèque de modèles, gérer les SMTP ou les utilisateurs, voir les envois des autres |
| **Lecteur** | Consulter le tableau de bord, les rapports, l'historique et les analyses de campagnes | Créer, modifier, envoyer ou importer quoi que ce soit |

Les rubriques auxquelles un rôle n'a pas accès sont entièrement masquées dans le menu : la personne ne voit pas une page grisée, elle ne voit pas la page du tout.

## Désactiver un compte

Passez le statut sur **Inactif** dans la fenêtre de modification. Conséquences immédiates :

- La personne ne peut plus se connecter ; sa tentative est refusée avec le message d'identifiants incorrects.
- Rien n'est supprimé : ses messages envoyés, ses campagnes et ses destinataires restent en place et restent attribués à son nom dans les [rapports](help:reporting).
- L'opération est réversible à tout moment en repassant le statut sur **Actif**.

C'est la bonne pratique lors d'un départ : la désactivation coupe l'accès sans effacer l'historique.

## Traçabilité

La création d'un utilisateur, le changement de rôle, la désactivation et les changements ou réinitialisations de mot de passe et les réinitialisations de double authentification sont enregistrés dans le [Journal d'audit](help:audit-log) avec l'auteur, la date et l'adresse IP. Les mots de passe ne sont jamais consignés.

## En cas de problème

- **« Cette adresse est déjà utilisée »** : un compte existe déjà avec cette adresse, peut-être désactivé. Recherchez-le dans la liste et réactivez-le plutôt que d'en créer un second.
- **Un collègue ne voit pas une page** : c'est presque toujours son rôle. Comparez avec le tableau ci-dessus, puis ajustez le rôle et demandez-lui de recharger la page.
- **Un utilisateur ne peut pas envoyer de campagne créée par quelqu'un d'autre** : c'est le comportement attendu du rôle Opérateur marketing, qui n'envoie que ses propres campagnes. Passez-le Responsable marketing si ce besoin est permanent.
- **Un compte affiche « Jamais » en dernière connexion** : le mot de passe initial n'a probablement jamais été transmis ou reçu. Modifiez le compte, définissez un nouveau mot de passe et communiquez de nouveau les accès.
