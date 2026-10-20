const e=`# Campagnes

La page **Campagnes e-mail** (menu **Marketing** › **E-mails**) regroupe tous vos envois de masse : brouillons en préparation, envois programmés, en cours et terminés. C'est depuis cette page que vous créez une campagne, que vous suivez son avancement, que vous la mettez en pause, la dupliquez ou ouvrez son rapport.

![Liste des campagnes e-mail](/docs/screenshots/campaign-flow/01-campaigns-list.png)

## Lire le tableau

Chaque ligne est une campagne : son nom, son objet en dessous, puis les colonnes ci-dessous. Un clic sur le nom ouvre la page de configuration si la campagne est encore modifiable, sinon son rapport.

| Colonne | Contenu |
| --- | --- |
| Campagne | Nom et objet ; un badge **Test A/B** signale ce type de campagne. |
| Statut | État actuel de la campagne (voir ci-dessous). |
| Destinataires | Nombre de destinataires. |
| Taux d'ouverture, Taux de clic | Résultats, une fois la campagne envoyée. |
| Dernière modification | Date de modification, ou **Envoyée le** / **Programmée le** selon l'état. |
| Propriétaire | La personne qui a créé la campagne. |
| Actions | Le menu ⋯ (**Plus d'actions**), qui propose les actions autorisées par le statut. |

Au-dessus du tableau : les onglets de statut avec leur compteur (**Toutes**, **Brouillons**, **Programmées**, **En cours**, **Envoyées**, **Suspendues**, **Archivées**), la recherche, le tri (**Trier par**), les filtres par **Tags** et par **Type** (E-mail ou Test A/B), et, à gauche, l'arborescence des **Dossiers** pour ranger vos campagnes. En cochant plusieurs lignes, vous pouvez les **Archiver**, les **Déplacer** ou les **Supprimer** en une fois.

## Les statuts et ce qu'ils permettent

Une campagne suit un cycle de vie strict. Les actions du menu ⋯ dépendent du statut.

| Statut | Signification | Actions disponibles |
| --- | --- | --- |
| Brouillon | Créée, jamais envoyée ni programmée. | Modifier, Dupliquer, Envoyer un test, Programmer, Envoyer maintenant, Supprimer |
| Programmée | Une date d'envoi future est enregistrée. | Modifier, Dupliquer, Envoyer un test, Déprogrammer, Envoyer maintenant, Annuler l'envoi |
| En cours | Les messages partent en ce moment. | Rapport, Dupliquer, Mettre en pause, Annuler l'envoi |
| En pause | Aucun nouveau message n'est libéré. | Rapport, Dupliquer, Reprendre, Annuler l'envoi |
| Suspendue | Envoi interrompu ; l'info-bulle du statut donne la raison. | Rapport, Modifier, Dupliquer, Reprendre, Annuler l'envoi, Archiver |
| Envoyée | Tous les destinataires ont été traités. | Rapport, Dupliquer, Envoyer aux nouveaux contacts, Archiver |
| Annulée | Envoi arrêté définitivement. | Dupliquer, Archiver, Supprimer |

Quand **Approbation requise avant l'envoi** est activée sur une campagne, la colonne Statut ajoute le badge **En attente d'approbation** (ou **Approuvée**) sous le statut pour les brouillons, programmées et suspendues. Un utilisateur disposant du droit **Approuver les campagnes** (Responsable marketing, Administrateur) trouve ⋯ › **Approuver** sur les campagnes en attente (brouillon ou suspendue) ; les autres rôles ne voient pas cette action. Tant qu'une campagne n'est pas approuvée, **Programmer** et **Envoyer maintenant** échouent avec « Cette campagne doit être approuvée avant de pouvoir être envoyée. », et **Reprendre** une campagne suspendue aussi. Modifier une campagne approuvée (objet, contenu, expéditeur, destinataires) retire son approbation.

Deux points importants :

- **Mettre en pause** n'annule rien : les messages déjà confiés au moteur d'envoi partent normalement, seuls les suivants sont retenus. **Reprendre** repart là où l'envoi s'était arrêté.
- **Annuler l'envoi** n'annule jamais un message déjà parti. Les destinataires non encore servis sont simplement écartés. Une confirmation vous est demandée. Un **Brouillon** ne s'annule pas : on le **Supprime**.

## Créer une campagne

Le bouton **Créer une campagne**, en haut à droite, ouvre une courte fenêtre : choix du type (**E-mail** ou **Test A/B**), puis **Nom interne de la campagne**. **Créer la campagne** vous conduit à la [page de configuration](help:campaign-setup), où vous complétez l'expéditeur, l'objet, les destinataires et le design, puis programmez l'envoi.

![Fenêtre « Créer une campagne »](/docs/screenshots/campaign-flow/02-create-dialog.png)

Le parcours complet est décrit dans [Envoyer une campagne pas à pas](help:flow-create-send-campaign).

## Dupliquer une campagne

**Dupliquer** crée un Brouillon « Copie de … » avec la même configuration et le même design, sans date programmée. C'est la façon la plus rapide de relancer une opération réussie : dupliquez la campagne, renommez-la, changez la liste, puis programmez-la.

## Consulter les résultats

Pour une campagne envoyée, **Rapport** (ou un clic sur son nom) ouvre le [rapport de campagne](help:campaign-report) : vue d'ensemble, délivrabilité, ouvertures, clics et désinscriptions. Pour une analyse plus large (plusieurs campagnes, plusieurs comptes SMTP, une période donnée), passez par les [Rapports](help:reporting).

## En cas de problème

- **La campagne reste en « En cours » très longtemps** : l'envoi passe par vos comptes SMTP, dans le respect de leurs quotas. Si rien ne bouge, vérifiez qu'au moins un compte est disponible dans les [comptes SMTP](help:smtp).
- **Le nombre de destinataires est inférieur à celui de la liste** : les adresses de la [liste de suppression](help:suppression) et les exclusions sont écartées avant l'envoi.
- **« Cette campagne doit être approuvée… »** : voir ⋯ › **Approuver** (réservé au droit « Approuver les campagnes »), ou demandez à un Responsable marketing.
- **Impossible de mettre en pause** : l'action n'apparaît que pour une campagne « En cours ». Une campagne « Programmée » se déprogramme ou s'annule.
- **Impossible de modifier une campagne** : seules les campagnes Brouillon, Programmée et Suspendue sont modifiables. Dupliquez-la pour repartir de la même base.
- **Une campagne a disparu de la liste** : elle est peut-être archivée ; ouvrez l'onglet **Archivées** ou réinitialisez les filtres.

> Pas-à-pas complet, de la création au suivi des résultats : [Envoyer une campagne pas à pas](help:flow-create-send-campaign).
`;export{e as default};
