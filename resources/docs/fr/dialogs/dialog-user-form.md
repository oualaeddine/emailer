# Fenêtre — Créer / Modifier un utilisateur

Cette fenêtre sert à la fois à créer un compte et à modifier un compte existant. Le titre indique le mode en cours : **Créer un utilisateur** ou **Modifier un utilisateur**. Les champs proposés diffèrent selon le mode.

![Fenêtre utilisateur](/docs/screenshots/admin-users/02-dialog.png)

## Les champs

| Champ | Création | Modification | Rôle du champ |
| --- | --- | --- | --- |
| Nom | Obligatoire | Modifiable | Nom affiché dans la liste, les rapports et le journal d'audit. Exemple : `Nadia Cherif` |
| Adresse e-mail | Obligatoire | Non affiché | Identifiant de connexion, définitif. Exemple : `nadia.cherif@pagejaunes.dz` |
| Mot de passe | Obligatoire | — | Mot de passe initial, à communiquer à la personne |
| Nouveau mot de passe | — | Facultatif (absent sur votre propre compte) | Réinitialise le mot de passe de la personne. Laissez vide pour ne rien changer |
| Confirmer le nouveau mot de passe | — | Obligatoire si un nouveau mot de passe est saisi | Même valeur que le champ précédent |
| Rôle | Obligatoire | Modifiable | Détermine l'ensemble des droits |
| Statut | Non affiché | Interrupteur Actif / Inactif | Autorise ou coupe la connexion |

En modification, l'adresse e-mail disparaît volontairement : c'est l'identifiant permanent du compte. Le mot de passe enregistré n'est jamais réaffiché, mais vous pouvez en définir un nouveau (personne qui l'a oublié, départ d'un poste partagé…). La réinitialisation déconnecte la personne de toutes ses sessions et révoque ses clés API personnelles ; elle est consignée dans le [journal d'audit](help:audit-log).

Pour votre propre compte, ces champs n'apparaissent pas : utilisez **Changer le mot de passe** dans le menu du compte (en haut à droite), qui demande votre mot de passe actuel.

## Choisir le rôle

La liste déroulante **Rôle** propose les quatre rôles de l'application. Choisissez le plus restreint qui permette à la personne de travailler.

| Rôle | À qui l'attribuer |
| --- | --- |
| Administrateur | Responsable informatique qui configure les [comptes SMTP](help:smtp), les utilisateurs et consulte le [journal d'audit](help:audit-log) |
| Responsable marketing | Chef de service qui valide les campagnes et gère la bibliothèque de [modèles](help:templates) |
| Opérateur marketing | Chargé de prospection qui compose, importe des contacts et envoie ses propres campagnes |
| Lecteur | Direction ou client interne qui consulte uniquement les [rapports](help:reporting) |

Le détail complet des droits par rôle figure dans la page [Utilisateurs](help:admin-users).

## L'interrupteur Statut

Visible seulement en modification. Le basculer sur **Inactif** bloque immédiatement la connexion de la personne, sans supprimer ses données ni ses envois passés. L'opération est réversible.

## Enregistrer

Le bouton **Enregistrer** valide la saisie. Si le serveur refuse une valeur, le message d'erreur s'affiche sous le champ concerné et la fenêtre reste ouverte : corrigez et réessayez. **Annuler** ferme la fenêtre sans rien conserver.

## En cas de problème

- **Erreur sous le champ Adresse e-mail** : l'adresse est mal formée ou déjà attribuée à un autre compte, éventuellement désactivé.
- **Erreur sous le champ Mot de passe** : le mot de passe est trop court ou trop simple ; utilisez au moins douze caractères avec des majuscules, des minuscules et des chiffres.
- **« La confirmation du mot de passe ne correspond pas. »** : les deux champs de nouveau mot de passe diffèrent ; ressaisissez-les.
- **Le champ Rôle reste vide** : la liste ne s'est pas chargée. Fermez la fenêtre, rechargez la page [Utilisateurs](help:admin-users) et recommencez.
- **La personne se plaint de ne plus voir une rubrique après modification** : le rôle a changé. Vérifiez le rôle choisi et demandez-lui de recharger la page.
