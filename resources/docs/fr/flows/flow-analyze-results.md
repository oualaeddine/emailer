# Analyser les résultats d'une campagne

Une campagne envoyée n'est qu'une moitié du travail. Ce guide explique comment lire ses résultats, distinguer un vrai problème d'un chiffre normal, traiter les rebonds et nettoyer votre base pour que la campagne suivante parte mieux. Fil rouge : la campagne « Catalogue quincaillerie 2026 — menuisiers Bejaia », partie dimanche matin vers 340 contacts.

## 1. Attendre le bon moment

Ne jugez rien dans l'heure qui suit l'envoi. Les rebonds définitifs arrivent en général dans les minutes qui suivent, mais les ouvertures et les clics s'étalent sur plusieurs jours. Attendez **48 à 72 heures** avant de tirer des conclusions.

## 2. Regarder d'abord le détail de la campagne

Ouvrez [Campagnes](help:campaigns) et cliquez sur le nom de la campagne pour ouvrir le volet de détail.

![Volet de détail d'une campagne](/docs/screenshots/campaigns/02-detail-drawer.png)

L'onglet **Analyses** donne les chiffres essentiels : taille de l'audience, nombre d'ouvertures, nombre de clics, puis la répartition des messages par statut. L'onglet **Destinataires** permet de voir adresse par adresse ce qui s'est passé — c'est là que vous repérez concrètement qui a rebondi.

## 3. Passer aux Rapports pour la vue d'ensemble

Ouvrez [Rapports](help:reporting) dans le menu.

![Page Rapports](/docs/screenshots/reporting/01-reporting.png)

Réglez la période **Du / Au** pour couvrir l'envoi et les jours suivants, sélectionnez votre campagne dans le filtre **Campagne**, puis cliquez sur **Appliquer**. Les chiffres ne bougent pas tant que vous n'avez pas cliqué.

## 4. Lire les indicateurs dans le bon ordre

Analysez toujours de haut en bas : la livraison d'abord, l'engagement ensuite. Un mauvais taux d'ouverture sur une base qui ne se délivre pas ne veut rien dire.

| Indicateur | Bon | À surveiller | Alarmant |
| --- | --- | --- | --- |
| Taux de livraison | > 97 % | 90 – 97 % | < 90 % |
| Taux de rejet | < 2 % | 2 – 5 % | > 5 % |
| Taux d'ouverture | > 20 % | 10 – 20 % | < 10 % |
| Taux de clic | > 3 % | 1 – 3 % | < 1 % |

Rappel utile : les ouvertures sont mesurées par une image invisible. Un destinataire qui bloque les images lit votre message sans être compté. Le taux d'ouverture est donc toujours un plancher, jamais une mesure exacte — alors que le taux de clic, lui, est fiable.

## 5. Comprendre les rebonds

Le bloc **Répartition par statut** distingue deux familles très différentes.

| Statut | Ce qui s'est passé | Conséquence |
| --- | --- | --- |
| Rebond temporaire | Boîte pleine, serveur momentanément indisponible. | Le message est réessayé plus tard. Aucune action de votre part. |
| Rebond définitif | L'adresse n'existe pas ou le domaine est mort. | L'adresse est bloquée automatiquement et définitivement. |
| Signalement spam | Le destinataire a marqué votre message comme indésirable. | L'adresse est bloquée automatiquement. |
| Désabonné | Le destinataire a cliqué sur le lien de désabonnement. | L'adresse est bloquée automatiquement. |
| Échec | Le message n'a pas pu partir du tout. | Vérifiez l'état de votre compte SMTP. |

Un point rassurant : vous n'avez **rien à faire manuellement** pour ces cas. L'application ajoute elle-même l'adresse à la liste de suppression avec le bon motif. Votre travail consiste à comprendre la cause, pas à bloquer une par une.

## 6. Vérifier la liste de suppression

Ouvrez [Liste de suppression](help:suppression) et filtrez sur le motif **Rebond définitif**, puis triez mentalement par date d'ajout.

![Liste de suppression](/docs/screenshots/suppression/01-suppression.png)

Si une vingtaine d'adresses viennent d'être ajoutées le jour de votre envoi, votre fichier source contenait des adresses périmées. C'est fréquent quand on relève des adresses dans un annuaire sans les vérifier — voir [Constituer une base depuis PageJaunes](help:flow-source-pagejaunes).

Ne retirez pas ces adresses. Un rebond définitif signifie que la boîte n'existe pas : réessayer ne fera qu'aggraver votre réputation d'expéditeur.

## 7. Contrôler la santé de vos comptes SMTP

Toujours dans les Rapports, le bloc **Par compte SMTP** affiche pour chaque compte les envoyés, les délivrés, les rejetés et son **État**.

| État | Signification | Que faire |
| --- | --- | --- |
| Sain | Tout va bien. | Rien. |
| Dégradé | Le taux de rejet ou de plainte a dépassé le seuil. | Suspendez les gros envois, nettoyez la base. |
| Défaillant | Le compte est en très mauvaise posture. | Cessez de l'utiliser et faites intervenir un administrateur. |
| Désactivé | Le compte est hors service. | Voir [Comptes SMTP](help:smtp). |

Un compte qui se dégrade après une campagne est le signal le plus sérieux : il annonce que vos prochains envois, même vers de bonnes adresses, arriveront moins bien.

## 8. Bloquer manuellement ce qui doit l'être

Certaines demandes n'arrivent pas par e-mail. Si le gérant de « Menuiserie Bejaia » vous appelle pour demander l'arrêt des envois, ouvrez la [liste de suppression](help:suppression), cliquez sur **Ajouter une adresse**, choisissez le motif **Blocage manuel** et notez la raison dans le champ **Notes**.

![Fenêtre d'ajout à la liste de suppression](/docs/screenshots/suppression/02-dialog.png)

Le détail de cette fenêtre est décrit dans [Ajouter une adresse à la liste de suppression](help:dialog-suppression-entry-form).

## 9. Exporter et conclure

Le bouton **Exporter** de la page Rapports télécharge les données filtrées au format CSV, pour un compte rendu interne ou une comparaison dans un tableur. Il n'apparaît que si votre rôle dispose du droit d'export.

Concluez par une décision concrète pour la campagne suivante : réécrire l'objet si l'ouverture est faible, revoir l'offre ou le lien si les ouvertures sont bonnes mais les clics rares, nettoyer la base si les rebonds dépassent 2 %. Puis dupliquez la campagne depuis [Campagnes](help:campaigns) et relancez avec [Créer et envoyer une campagne](help:flow-create-send-campaign).

## En cas de problème

- **« Aucune donnée pour cette période »** : la plage de dates ne couvre pas l'envoi, ou le filtre campagne est trop restrictif. Repassez sur « Toutes les campagnes ».
- **Zéro ouverture alors que la livraison est bonne** : l'objet du message est probablement en cause, ou vos destinataires bloquent massivement les images. Fiez-vous au taux de clic.
- **Le taux de rejet dépasse 10 %** : arrêtez toute nouvelle campagne, la source de vos adresses est en cause. Reprenez un import propre avant de réessayer.
- **Les chiffres du volet de détail et ceux des Rapports diffèrent légèrement** : les rapports sont recalculés périodiquement, le détail de campagne est plus immédiat. L'écart se résorbe de lui-même.
