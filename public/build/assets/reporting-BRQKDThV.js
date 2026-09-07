const e=`# Rapports

La page **Rapports** donne une vue d'ensemble de vos envois sur une période : combien de messages sont partis, combien ont été distribués, ouverts, cliqués ou rejetés. Elle permet aussi de comparer vos campagnes entre elles et de vérifier la santé de chaque compte SMTP.

![Page Rapports](/docs/screenshots/reporting/01-reporting.png)

## Choisir la période et les filtres

La barre de filtres en haut de page comporte quatre réglages, puis un bouton **Appliquer**.

| Filtre | Rôle | Valeur par défaut |
| --- | --- | --- |
| Du | Début de la période analysée | Il y a 30 jours |
| Au | Fin de la période analysée | Aujourd'hui |
| Campagne | Restreint à une seule campagne | Toutes les campagnes |
| Compte SMTP | Restreint à un seul compte d'expédition | Tous les comptes SMTP |

Les chiffres ne se rafraîchissent pas pendant que vous tapez : il faut cliquer sur **Appliquer**. Pour analyser la campagne « Promo rentrée — menuisiers Bejaia », sélectionnez-la dans **Campagne** et élargissez la période à la semaine qui a suivi l'envoi, le temps que les ouvertures s'accumulent.

## Rapports de livraison

Le premier bloc affiche six compteurs bruts.

| Indicateur | Ce qu'il compte |
| --- | --- |
| Envoyés | Messages remis au serveur d'expédition. |
| Délivrés | Messages acceptés par le serveur du destinataire. |
| Ouverts (uniques) | Nombre de destinataires distincts ayant ouvert le message. |
| Cliqués (uniques) | Nombre de destinataires distincts ayant cliqué un lien. |
| Rejetés | Messages refusés par le destinataire (rebonds). |
| Échoués | Messages qui n'ont pas pu être envoyés du tout. |

## Les taux

Le deuxième bloc convertit ces compteurs en pourcentages, et ajoute les volumes totaux.

| Indicateur | Lecture |
| --- | --- |
| Taux de livraison | Part des messages effectivement acceptés. En dessous de 95 %, la qualité de votre base est en cause. |
| Taux d'ouverture | Part des destinataires ayant ouvert. En B2B algérien, 15 à 30 % est un résultat courant. |
| Taux de clic | Part des destinataires ayant cliqué un lien. |
| Taux de rejet | Part des rebonds. Au-delà de 2 %, nettoyez votre base sans attendre. |
| Ouvertures (total) | Toutes les ouvertures, y compris les relectures d'un même destinataire. |
| Clics (total) | Tous les clics, y compris répétés. |

Les ouvertures sont mesurées par une image invisible insérée dans le message : un destinataire qui bloque les images ne sera pas compté, même s'il a bien lu votre e-mail. Le taux d'ouverture est donc toujours un minimum, jamais un chiffre exact.

## Répartition par statut

Ce bloc détaille combien de messages se trouvent dans chaque état de livraison : distribué, ouvert, cliqué, rebond temporaire, rebond définitif, échec, signalement spam… C'est ici que vous repérez une anomalie précise, par exemple un pic de rebonds définitifs après un import de fichier douteux.

## Par campagne

Chaque ligne donne le nom de la campagne et le triplet **Envoyés / Délivrés / Rejetés**. En comparant deux campagnes de taille voisine, vous voyez immédiatement laquelle a touché une base plus propre.

## Par compte SMTP

Même principe, avec en plus la colonne **État** du compte : Sain, Dégradé, Défaillant ou Désactivé. Un compte qui passe en Dégradé alors que ses rejets augmentent doit être mis de côté le temps d'assainir la base ; voir [Comptes SMTP](help:smtp).

## Exporter

Le bouton **Exporter**, en haut à droite, télécharge les données de la période et des filtres en cours au format CSV. Il n'apparaît que si votre rôle dispose du droit d'export ; les comptes en lecture seule consultent les rapports mais ne les exportent pas.

## En cas de problème

- **« Aucune donnée pour cette période »** : la plage de dates ne couvre aucun envoi, ou le filtre campagne est trop restrictif. Repassez sur « Toutes les campagnes » et élargissez la période.
- **Les chiffres n'ont pas bougé** : vous avez modifié les filtres sans cliquer sur **Appliquer**.
- **Les ouvertures paraissent trop basses juste après l'envoi** : les données s'accumulent pendant plusieurs jours ; ne jugez pas une campagne avant 48 à 72 heures.
- **Le bouton Exporter est absent** : votre rôle n'autorise pas l'export. Demandez le droit à un administrateur depuis [Utilisateurs](help:admin-users).

> Pour interpréter les résultats et agir sur les rebonds : [Analyser les résultats d'une campagne](help:flow-analyze-results).
`;export{e as default};
