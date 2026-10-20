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
- L'écran ne propose ni bouton de copie ni téléchargement : recopiez-les ou enregistrez-les vous-même dans un endroit sûr (gestionnaire de mots de passe, papier rangé sous clé), jamais dans un e-mail ni sur un poste partagé.
- Cliquez ensuite sur **J'ai enregistré mes codes — continuer** pour arriver sur le [Tableau de bord](help:dashboard).

## À chaque connexion

Après votre adresse et votre mot de passe sur la page [Connexion](help:login), l'écran **Vérification en deux étapes** demande « le code à 6 chiffres généré par votre application d'authentification ».

1. Ouvrez l'application d'authentification et lisez le code actuel du compte.
2. Saisissez-le dans **Code de vérification**, puis cliquez sur **Vérifier**.

Votre choix **Se souvenir de moi** de la page de connexion est conservé.

## Utiliser un code de récupération

Si votre téléphone est indisponible, cliquez sur **Utiliser un code de récupération**. Le champ devient **Code de récupération** : saisissez l'un de vos codes tel qu'il a été affiché (majuscules et tiret compris), puis **Vérifier**. Chaque code ne sert **qu'une fois** et est détruit après usage ; **Utiliser l'application d'authentification** ramène au champ du code à 6 chiffres.

## En cas de problème

- **« Le code de vérification est invalide. »** : le code a expiré ou a été mal recopié. Attendez le prochain code de l'application et ressaisissez-le. Si l'erreur persiste, vérifiez que l'heure du téléphone est réglée sur l'heure automatique.
- **« Trop de tentatives de connexion. Réessayez dans … secondes. »** : après 5 essais infructueux, l'accès est bloqué quelques minutes. Patientez le délai indiqué.
- **Téléphone perdu ou changé** : connectez-vous avec un code de récupération (section ci-dessus). Si vous n'en avez plus, contactez votre administrateur : l'application ne propose pas de réinitialisation de la double authentification, ni en libre-service ni depuis la page [Utilisateurs](help:admin-users).
- **Retour à la page de connexion** : si la session de vérification a expiré, vous êtes renvoyé vers [Connexion](help:login) ; recommencez avec votre mot de passe.
