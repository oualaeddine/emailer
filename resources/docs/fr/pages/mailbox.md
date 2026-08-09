# Boîte de réception

La **Boîte de réception** rassemble tout ce qui concerne vos messages individuels : les brouillons en cours, ce qui attend de partir, ce qui est planifié et ce qui a déjà été envoyé. C'est ici que vous vérifiez si un e-mail est bien arrivé chez `contact@menuiserie-bejaia.dz`, et que vous relancez un envoi bloqué.

![Boîte de réception](/docs/screenshots/mailbox/01-mailbox.png)

## L'organisation en trois volets

L'écran se lit de gauche à droite :

1. **La colonne des dossiers**, à gauche.
2. **La liste des messages** du dossier sélectionné, au centre.
3. **Le volet de lecture**, à droite, qui détaille le message choisi.

Le bouton **Nouvel e-mail**, en haut à droite, ouvre directement le [rédacteur](help:composer).

## Les cinq dossiers

| Dossier | Ce qu'il contient |
| --- | --- |
| **Boîte de réception** | Les notifications système uniquement (rebonds, alertes de vérification, imports terminés). L'application ne reçoit pas de courrier entrant réel. |
| **Brouillons** | Les messages en cours de rédaction, enregistrés automatiquement par le rédacteur |
| **Boîte d'envoi** | Les messages en attente de départ ou en cours d'envoi |
| **Planifiés** | Les messages dont le départ est programmé à une date future |
| **Envoyés** | L'historique des messages effectivement partis |

Cliquer sur une ligne des **Brouillons** rouvre le message dans le rédacteur, exactement là où vous l'aviez laissé.

## Chercher un message

Dans les dossiers **Boîte d'envoi**, **Planifiés** et **Envoyés**, un champ de recherche filtre la liste : tapez une adresse ou un fragment d'objet, par exemple `transport-oran`, et la liste se met à jour immédiatement.

Si votre rôle le permet, l'interrupteur **Voir toute l'équipe** élargit la liste aux messages de vos collègues, et non plus seulement aux vôtres. Pratique pour vérifier si un client a déjà été contacté par quelqu'un d'autre.

Quand la liste est longue, le bouton **Charger plus**, en bas, affiche la suite.

## Lire le détail d'un message

Sélectionnez une ligne : le volet de droite affiche l'objet, l'adresse du destinataire, une étiquette de statut colorée, puis une chronologie complète.

| Ligne | Signification |
| --- | --- |
| Compte SMTP | Le serveur d'envoi utilisé (voir [Comptes SMTP](help:smtp)) |
| Mis en file d'attente | Le moment où le message a été pris en charge |
| Envoyé | Le départ effectif |
| Distribué | L'acceptation par la messagerie du destinataire |
| Ouvert (Ouvertures : n) | La première ouverture et le nombre total |
| Cliqué (Clics : n) | Le premier clic sur un lien et le nombre total |
| Rejeté | Un refus du serveur destinataire |
| Échoué | Un échec technique de l'envoi |

Un tiret `—` signifie simplement que l'événement ne s'est pas produit. **Dernière réponse du serveur** affiche, le cas échéant, le message technique renvoyé par le serveur destinataire : c'est l'information à transmettre en cas de réclamation.

## Comprendre les statuts

Les étiquettes vertes sont bonnes (**Distribué**, **Ouvert**, **Cliqué**, **Accepté**), les oranges signalent une opération en cours (**En file d'attente**, **Envoi en cours**), les rouges demandent une action :

- **Rebond temporaire** : boîte pleine ou serveur indisponible ; l'envoi peut réussir plus tard.
- **Rebond définitif** : l'adresse n'existe pas. Elle rejoint la [liste de suppression](help:suppression).
- **Signalement spam** / **Désabonné** : ne recontactez plus cette adresse.
- **Échec** / **Rejeté** : problème d'envoi ; lisez la dernière réponse du serveur.

## Relancer ou annuler un envoi

Dans le dossier **Boîte d'envoi** uniquement, deux boutons apparaissent au bas du volet de lecture :

- **Relancer maintenant** : remet le message en file d'attente immédiatement, sans attendre la prochaine tentative automatique.
- **Annuler** : abandonne définitivement l'envoi de ce message.

Une fois le message dans **Envoyés**, il est parti : ni relance ni annulation ne sont possibles.

## En cas de problème

- **La Boîte de réception est toujours vide** : c'est normal. Elle n'affiche que des notifications système, jamais de courrier entrant.
- **Un message reste en « En file d'attente »** : le compte SMTP a peut-être atteint son quota quotidien. Vérifiez la carte des quotas sur le [Tableau de bord](help:dashboard).
- **Beaucoup de rebonds définitifs sur un même import** : la base est obsolète. Nettoyez-la avant l'envoi suivant depuis la page [Destinataires](help:recipients).
- **Vous ne voyez pas les messages d'un collègue** : l'interrupteur **Voir toute l'équipe** n'apparaît que pour les rôles qui y ont droit.
