# Tableau de bord

Le **Tableau de bord** est la page d'accueil qui s'affiche juste après la [connexion](help:login). Il répond en un coup d'œil à trois questions : combien d'e-mails sont partis ces derniers jours, est-ce qu'ils arrivent bien, et où en sont les campagnes en cours. C'est un écran de consultation : rien ne s'y modifie.

![Tableau de bord](/docs/screenshots/dashboard/01-dashboard.png)

## Le bandeau de bienvenue

En haut, l'application vous accueille par votre nom (« Bienvenue, Karim Belkacem »). Il confirme surtout avec quel compte vous travaillez — utile sur un poste partagé, avant d'envoyer un message au nom de la mauvaise personne.

## Volume d'envoi

Cette carte affiche le **nombre total de messages envoyés** sur les derniers jours, puis le détail jour par jour.

Concrètement, si votre équipe a diffusé une offre à 300 menuisiers de Béjaïa le lundi, la ligne du lundi affichera 300. Une journée à zéro alors que vous attendiez un envoi est un signal : allez vérifier la **Boîte d'envoi** dans la [Boîte de réception](help:mailbox).

## Taux de délivrabilité

Le grand pourcentage indique la part des messages réellement acceptés par les serveurs de messagerie des destinataires, avec deux chiffres de contrôle :

| Ligne | Signification |
| --- | --- |
| Envoyés | Nombre de messages remis au serveur d'envoi |
| Délivrés | Nombre de messages acceptés par la boîte du destinataire |

Un taux durablement inférieur à 90 % mérite attention : adresses vieillissantes, base achetée, ou objet trop promotionnel. Les [Rapports](help:reporting) permettent de creuser campagne par campagne.

## Utilisation des quotas SMTP

Chaque compte d'envoi (voir [Comptes SMTP](help:smtp)) a une limite quotidienne. La carte liste les comptes actifs avec la consommation du jour sous la forme `utilisé / quota` et une barre de progression.

- `340 / 500` : il reste 160 envois aujourd'hui sur ce compte.
- **Sans limite** : aucun quota quotidien n'a été défini pour ce compte.
- **Aucun compte SMTP actif.** : aucun serveur d'envoi n'est configuré, donc aucun e-mail ne peut partir.

Une barre proche de la saturation en fin de matinée annonce qu'une grosse campagne prévue l'après-midi restera en attente. Mieux vaut la planifier au lendemain.

## Campagnes récentes

La dernière carte liste les campagnes les plus récentes avec leur statut et leur date : **Brouillon**, **Planifiée**, **Envoi en cours**, **Envoyée**, **En pause**, **Annulée**. Une campagne bloquée sur « Envoi en cours » depuis longtemps se diagnostique depuis la page [Campagnes](help:campaigns).

## Cartes visibles selon votre rôle

Les cartes **Utilisation des quotas SMTP** et **Campagnes récentes** ne s'affichent que si votre rôle vous donne accès aux comptes SMTP et aux campagnes. Leur absence n'est donc pas une anomalie : c'est le comportement normal pour un Opérateur marketing ou un Lecteur.

## En cas de problème

- **La page reste sur « Chargement… »** : les chiffres proviennent du serveur. Actualisez la page ; si l'attente se prolonge, signalez-le à votre administrateur.
- **Le volume d'envoi paraît trop faible** : seuls les derniers jours sont comptabilisés. Pour un historique long, utilisez les [Rapports](help:reporting).
- **« Aucune campagne pour le moment. »** alors que vous en avez créé une : vérifiez qu'elle a bien été enregistrée depuis l'[assistant de campagne](help:dialog-campaign-wizard).
- **Un quota est déjà au maximum le matin** : un envoi de la veille a probablement débordé sur la journée. Répartissez la charge sur plusieurs comptes SMTP.
