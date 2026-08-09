# Créer et envoyer une campagne

Ce guide suit une campagne de bout en bout : préparer ce dont elle a besoin, la créer dans l'assistant, l'envoyer ou la planifier, puis surveiller son déroulement jusqu'à la fin. L'exemple servira de fil rouge : la SARL Bâti-Sud veut annoncer son nouveau catalogue de quincaillerie à 340 menuisiers de la région de Bejaia.

## 1. Vérifier les prérequis

Avant d'ouvrir l'assistant, assurez-vous d'avoir les trois éléments suivants.

| Élément | Où le préparer |
| --- | --- |
| Un modèle d'e-mail prêt | [Modèles](help:templates) |
| Une liste de destinataires constituée | [Destinataires](help:recipients), alimentée par [import](help:recipients-import) |
| Au moins un compte SMTP en état « Sain » | [Comptes SMTP](help:smtp) |

Si le modèle n'existe pas encore, créez-le maintenant : l'assistant ne permet pas d'écrire le contenu, il le reprend du modèle choisi.

## 2. Ouvrir la page Campagnes

Dans le menu de navigation, groupe **Campagnes**, cliquez sur **Campagnes**. Le tableau liste vos campagnes existantes avec leur statut.

![Liste des campagnes](/docs/screenshots/campaigns/01-campaigns.png)

Cliquez sur **Nouvelle campagne**, en haut à droite.

## 3. Étape « Informations »

![Assistant de campagne](/docs/screenshots/campaigns/03-wizard.png)

Renseignez :

- **Nom de la campagne** : `Catalogue quincaillerie 2026 — menuisiers Bejaia`. Un nom explicite vous fera gagner du temps quand vous chercherez cette campagne dans les rapports, six mois plus tard.
- **Modèle** : choisissez `Annonce catalogue 2026` dans la liste déroulante.

Cliquez sur **Confirmer**.

## 4. Étape « Destinataires »

Choisissez **Liste de destinataires** (l'autre option, **Segment**, s'utilise quand l'audience est définie par des critères plutôt que par une sélection fixe).

Collez ensuite l'identifiant de la liste dans le champ **Identifiant de la liste/du segment**. Vous le récupérez sur la page de la liste concernée.

Vous n'avez rien à filtrer : les adresses de la [liste de suppression](help:suppression) seront écartées automatiquement au moment de l'envoi.

Cliquez sur **Confirmer**.

## 5. Étape « Envoi »

Deux possibilités :

| Choix | Effet |
| --- | --- |
| Envoyer immédiatement | L'envoi démarre à la validation finale. |
| Planifier | Un champ de date et d'heure apparaît ; l'envoi partira tout seul à ce moment-là. |

Pour Bâti-Sud, choisissez **Planifier** et fixez le dimanche à 09:00 : en B2B algérien, un envoi en début de matinée de jour ouvré est lu bien plus souvent qu'un envoi du jeudi soir.

Cliquez sur **Confirmer**.

## 6. Étape « Vérification »

Le récapitulatif reprend le nom, le modèle, l'audience et le mode d'envoi. Relisez-le attentivement : c'est le dernier point de contrôle.

- **Enregistrer comme brouillon** si vous voulez d'abord faire relire le contenu à un collègue.
- **Planifier** (ou **Envoyer maintenant**) pour valider définitivement.

L'assistant se ferme et la campagne apparaît dans le tableau avec le statut correspondant. Le détail complet de la fenêtre est décrit dans [l'assistant de campagne](help:dialog-campaign-wizard).

## 7. Relire le contenu avant le départ

Si vous avez enregistré un brouillon, ouvrez la campagne en cliquant sur son nom : un volet s'ouvre à droite.

![Volet de détail d'une campagne](/docs/screenshots/campaigns/02-detail-drawer.png)

L'onglet **Contenu** montre le message tel qu'il sera envoyé. Vérifiez l'objet, les liens et la présence du lien de désabonnement. L'onglet **Aperçu** confirme le modèle et l'audience.

## 8. Surveiller l'envoi

Quand l'envoi démarre, le statut passe à **Envoi en cours**. Les messages ne partent pas tous d'un coup : ils sont volontairement étalés pour protéger la réputation de vos adresses d'expédition. Une audience de 340 contacts peut prendre plusieurs dizaines de minutes.

Dans le volet de détail :

- l'onglet **Destinataires** liste chaque adresse avec le statut de son message ;
- l'onglet **Analyses** donne la taille de l'audience, les ouvertures, les clics et la répartition par statut.

Si vous repérez une erreur pendant l'envoi — mauvais lien, erreur de prix — utilisez le menu `…` de la ligne et choisissez **Mettre en pause**. Les messages déjà confiés au serveur partiront, mais les suivants seront retenus. Après correction, **Reprendre** relance l'envoi là où il s'était arrêté ; **Annuler la campagne** l'arrête définitivement.

## 9. Après l'envoi

Le statut passe à **Envoyée** quand tous les destinataires ont été traités. Laissez ensuite 48 à 72 heures aux ouvertures et aux clics pour s'accumuler avant de conclure quoi que ce soit.

Passez alors au guide [Analyser les résultats d'une campagne](help:flow-analyze-results), qui explique comment lire les [Rapports](help:reporting) et quoi faire des rebonds.

Pour relancer l'opération plus tard, ne repartez pas de zéro : utilisez **Dupliquer** dans le menu `…`, qui recrée un brouillon identique sans date planifiée.

## En cas de problème

- **Le bouton final de l'assistant reste grisé** : un champ obligatoire est vide — nom, modèle, identifiant d'audience, ou date en mode planifié.
- **La campagne planifiée ne part pas à l'heure prévue** : vérifiez d'abord son statut ; si elle est en **Annulée**, quelqu'un l'a arrêtée. Sinon, contrôlez l'état de vos comptes SMTP dans les [Rapports](help:reporting).
- **Beaucoup de rejets dès les premières minutes** : mettez la campagne en pause immédiatement et vérifiez la qualité de la liste. Un envoi massif vers des adresses invalides dégrade durablement votre délivrabilité.
- **Le contenu affiché n'est pas le bon** : le modèle choisi n'était pas celui attendu. Annulez le brouillon, dupliquez-le et corrigez le modèle depuis l'assistant.
