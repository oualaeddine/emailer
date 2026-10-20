# Double authentification

La **double authentification** ajoute une seconde preuve d'identité après le mot de passe : un code à 6 chiffres généré par une application installée sur votre téléphone. Elle est **obligatoire pour tous les comptes** : même avec un mot de passe volé, personne ne peut entrer sans votre téléphone. Deux écrans en dépendent : **Configurer la double authentification** (une seule fois, à la première connexion) et **Vérification en deux étapes** (à chaque connexion).

## Première connexion : activer la protection

Tant que la double authentification n'est pas activée, toute page de l'application vous ramène à l'écran **Configurer la double authentification**, sans menu : il faut la terminer pour continuer.

1. Installez sur votre téléphone une application d'authentification, par exemple Google Authenticator, Microsoft Authenticator ou Authy.
2. Dans l'application, ajoutez un compte et **scannez le QR code** affiché à l'écran.
3. Si vous ne pouvez pas scanner, saisissez à la main la clé affichée sous **Ou saisissez cette clé manuellement :**.
4. Saisissez dans **Code de vérification** le code à 6 chiffres que l'application affiche pour ce compte, puis cliquez sur **Activer**.

| Champ | Obligatoire | Détail |
| --- | --- | --- |
| Code de vérification | Oui | Les 6 chiffres affichés par l'application ; ils changent régulièrement, saisissez le code en cours |

Rechargez l'écran sans crainte : le même QR code reste valable tant que vous n'avez pas validé de code.

## Les codes de récupération

Dès que le code est accepté, l'écran **Codes de récupération** affiche **8 codes à usage unique**, de la forme `ABCDE-FGH12`. Ils vous permettent de vous connecter si vous perdez l'accès à votre application d'authentification.

- Ils ne sont affichés **qu'une seule fois** : si vous quittez ou actualisez l'écran, ils ne reviennent pas.
- Deux boutons vous aident à les conserver : **Copier les codes** (copie les 8 codes dans le presse-papiers ; si le navigateur refuse, les codes sont simplement sélectionnés et un message vous invite à les copier avec Ctrl+C) et **Télécharger (.txt)** (enregistre le fichier `pagejaunes-mailer-codes-recuperation.txt`).
- Placez-les dans un endroit sûr (gestionnaire de mots de passe, papier rangé sous clé), jamais dans un e-mail ni sur un poste partagé.
- Cochez **J'ai conservé mes codes en lieu sûr** : le bouton **Continuer** reste grisé tant que la case n'est pas cochée. Il vous amène sur le [Tableau de bord](help:dashboard).

## Réinitialiser la double authentification d'un collègue

Un administrateur (droit de gérer les utilisateurs) peut réinitialiser la double authentification d'un autre utilisateur qui a perdu son téléphone et ses codes. Dans la page [Utilisateurs](help:admin-users), cliquez sur le bouton **Réinitialiser la double authentification** (bouclier avec une clé) de la ligne, puis confirmez dans la fenêtre. Conséquences immédiates :

- le secret, les codes de récupération et la confirmation de la personne sont supprimés ;
- toutes ses sessions sont fermées et ses clés API sont révoquées ;
- à sa prochaine connexion, avec son seul mot de passe, elle est renvoyée vers **Configurer la double authentification** et doit tout recommencer, codes de récupération compris ;
- l'action est consignée dans le [Journal d'audit](help:audit-log) (`user.two_factor_reset`), sans jamais enregistrer de secret.

Vous ne pouvez pas réinitialiser votre propre double authentification : le bouton n'apparaît pas sur votre ligne, et l'application refuse l'opération si elle est demandée quand même. Demandez à un autre administrateur.

## À chaque connexion

Après votre adresse et votre mot de passe sur la page [Connexion](help:login), l'écran **Vérification en deux étapes** demande « le code à 6 chiffres généré par votre application d'authentification ».

1. Ouvrez l'application d'authentification et lisez le code actuel du compte.
2. Saisissez-le dans **Code de vérification**, puis cliquez sur **Vérifier**.

Votre choix **Se souvenir de moi** de la page de connexion est conservé. Le lien **Retour à la connexion**, sous le formulaire, annule la vérification en cours et vous ramène à la page [Connexion](help:login) (mauvais compte, changement d'avis).

## Utiliser un code de récupération

Si votre téléphone est indisponible, cliquez sur **Utiliser un code de récupération**. Le champ devient **Code de récupération** : saisissez l'un de vos codes tel qu'il a été affiché (majuscules et tiret compris), puis **Vérifier**. Chaque code ne sert **qu'une fois** et est détruit après usage ; **Utiliser l'application d'authentification** ramène au champ du code à 6 chiffres.

## En cas de problème

- **« Le code de vérification est invalide. »** : le code a expiré ou a été mal recopié. Attendez le prochain code de l'application et ressaisissez-le. Si l'erreur persiste, vérifiez que l'heure du téléphone est réglée sur l'heure automatique.
- **« Trop de tentatives de vérification. Réessayez dans … secondes. »** : après 5 essais infructueux, la vérification est bloquée quelques minutes. Le message s'affiche sous le champ du code (ou du code de récupération) ; patientez le délai indiqué, même si votre code est correct.
- **Téléphone perdu ou changé** : connectez-vous avec un code de récupération (section ci-dessus). Si vous n'en avez plus, demandez à un administrateur de **réinitialiser votre double authentification** (voir plus haut) : à votre prochaine connexion, l'écran de configuration réapparaît et vous enrôlez votre nouveau téléphone.
- **Administrateur unique bloqué** : si personne d'autre ne peut réinitialiser votre double authentification (téléphone perdu, plus de codes, aucun autre administrateur), l'équipe technique vous rétablit l'accès en ligne de commande avec `php artisan users:reset-two-factor adresse@exemple.dz`. Cette commande agit comme la réinitialisation par un administrateur et laisse une trace dans le [Journal d'audit](help:audit-log) sans auteur (action système).
- **Se déconnecter pendant la configuration** : le lien **Se déconnecter** de l'écran **Configurer la double authentification** ferme votre session sans terminer l'enrôlement.
- **Retour à la page de connexion** : si la session de vérification a expiré, vous êtes renvoyé vers [Connexion](help:login) ; recommencez avec votre mot de passe.
