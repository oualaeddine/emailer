# Campagnes

La page **Campagnes** regroupe tous vos envois de masse : les brouillons en préparation, les envois planifiés, ceux en cours et ceux déjà terminés. C'est depuis cette page que vous créez une campagne, que vous suivez son avancement et que vous la mettez en pause ou l'annulez si nécessaire.

![Liste des campagnes](/docs/screenshots/campaigns/01-campaigns.png)

## Lire le tableau

Chaque ligne représente une campagne. Les colonnes affichées sont :

| Colonne | Contenu |
| --- | --- |
| Nom de la campagne | Le nom que vous avez donné, par exemple « Promo rentrée — menuisiers Bejaia ». Cliquez dessus pour ouvrir le détail. |
| Statut | L'état actuel de la campagne (voir le tableau ci-dessous). |
| Modèle | Le modèle d'e-mail utilisé, par exemple « Offre commerciale 2026 ». |
| Planifiée pour | La date et l'heure d'envoi prévues, ou `—` si l'envoi est immédiat. |
| Envoyée le | La date à laquelle l'envoi a réellement démarré, ou `—` si rien n'a encore été envoyé. |
| Actions | Le menu `…` qui propose les actions autorisées par le statut. |

## Les statuts et ce qu'ils permettent

Une campagne suit un cycle de vie strict. Les actions proposées dans le menu `…` dépendent directement du statut.

| Statut | Signification | Actions disponibles |
| --- | --- | --- |
| Brouillon | Créée mais jamais envoyée ni planifiée. | Annuler la campagne, Dupliquer |
| Planifiée | Une date d'envoi future est enregistrée. | Annuler la campagne, Dupliquer |
| Envoi en cours | Les messages partent en ce moment. | Mettre en pause, Dupliquer |
| En pause | L'envoi est interrompu, les messages restants attendent. | Reprendre, Annuler la campagne, Dupliquer |
| Envoyée | Tous les destinataires ont été traités. | Dupliquer |
| Annulée | L'envoi a été arrêté définitivement. | Dupliquer |

Deux points importants :

- **Mettre en pause** n'annule rien : les messages déjà confiés au serveur d'envoi partent normalement, seuls les suivants sont retenus. **Reprendre** repart exactement là où l'envoi s'était arrêté.
- **Annuler la campagne** n'annule jamais un envoi déjà parti. Les destinataires non encore servis sont simplement écartés. Une confirmation vous est demandée.

## Créer une campagne

Le bouton **Nouvelle campagne**, en haut à droite, ouvre l'assistant en quatre étapes : Informations, Destinataires, Envoi, Vérification. Le détail de chaque étape est décrit dans [l'assistant de campagne](help:dialog-campaign-wizard).

![Assistant de campagne](/docs/screenshots/campaigns/03-wizard.png)

## Dupliquer une campagne

**Dupliquer** crée une copie en Brouillon avec le même modèle, le même contenu et la même audience, mais sans date planifiée. C'est la façon la plus rapide de relancer une opération qui a bien fonctionné : dupliquez « Promo rentrée — menuisiers Bejaia », renommez-la, changez la liste et planifiez-la.

## Le volet de détail

Un clic sur le nom d'une campagne ouvre un volet sur la droite avec quatre onglets.

![Volet de détail d'une campagne](/docs/screenshots/campaigns/02-detail-drawer.png)

| Onglet | Ce que vous y trouvez |
| --- | --- |
| Aperçu | Modèle utilisé, audience visée, date planifiée, date d'envoi. |
| Destinataires | La liste des adresses touchées, avec le statut de chaque message (distribué, ouvert, rejeté…). |
| Contenu | L'aperçu du message tel qu'il est envoyé. |
| Analyses | Taille de l'audience, nombre d'ouvertures et de clics, puis la répartition des messages par statut. |

Pour une analyse plus large — plusieurs campagnes, plusieurs comptes SMTP, une période donnée — passez plutôt par les [Rapports](help:reporting).

## En cas de problème

- **La campagne reste en « Envoi en cours » très longtemps** : c'est normal sur une grosse audience, l'envoi est volontairement étalé pour protéger la réputation de vos adresses d'expédition. Suivez la progression dans l'onglet **Destinataires**.
- **Le nombre de destinataires est inférieur à celui de la liste** : les adresses présentes dans la [liste de suppression](help:suppression) sont écartées avant l'envoi, systématiquement et sans exception.
- **Impossible de mettre en pause** : l'action n'apparaît que pour une campagne en « Envoi en cours ». Une campagne « Planifiée » s'annule, elle ne se met pas en pause.
- **La colonne Modèle affiche un code** : le modèle a probablement été supprimé. Dupliquez la campagne et choisissez un modèle valide dans les [Modèles](help:templates).

> Pas-à-pas complet, de la création au suivi des résultats : [Créer et envoyer une campagne](help:flow-create-send-campaign).
