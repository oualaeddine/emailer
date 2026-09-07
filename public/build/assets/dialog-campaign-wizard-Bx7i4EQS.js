const e=`# Assistant « Nouvelle campagne »

Cette fenêtre sert à créer une campagne en quatre étapes courtes : lui donner un nom et un modèle, choisir qui la recevra, décider quand elle part, puis vérifier le tout. Rien n'est créé tant que vous n'avez pas cliqué sur le bouton final.

![Assistant de campagne](/docs/screenshots/campaigns/03-wizard.png)

## Étape 1 — Informations

| Champ | Obligatoire | Exemple |
| --- | --- | --- |
| Nom de la campagne | Oui | \`Promo rentrée — menuisiers Bejaia\` |
| Modèle | Oui | \`Offre commerciale 2026\` |

Le nom n'est visible que par votre équipe : choisissez-le explicite, il servira à retrouver la campagne dans les [Rapports](help:reporting). Le **Modèle** fournit l'objet et le contenu du message ; s'il manque, créez-le d'abord dans [Modèles](help:templates).

Cliquez sur **Confirmer** pour passer à l'étape suivante. Vous pouvez aussi cliquer directement sur les onglets pour revenir en arrière.

## Étape 2 — Destinataires

Choisissez d'abord le type d'audience :

- **Liste de destinataires** — un ensemble fixe de contacts que vous avez constitué, par exemple les 340 entreprises importées depuis un fichier.
- **Segment** — un ensemble défini par des critères, par exemple « toutes les entreprises de la wilaya d'Oran ».

Renseignez ensuite **Identifiant de la liste/du segment**, qui est obligatoire. Copiez cet identifiant depuis la page de la liste ou du segment concerné et collez-le ici.

Dans tous les cas, les adresses figurant dans la [liste de suppression](help:suppression) sont exclues automatiquement au moment de l'envoi : vous n'avez rien à filtrer vous-même.

## Étape 3 — Envoi

| Option | Effet |
| --- | --- |
| Envoyer immédiatement | L'envoi démarre dès la validation, la campagne passe en « Envoi en cours ». |
| Planifier | Un champ **Planifiée pour** apparaît ; renseignez la date et l'heure. La campagne passe en « Planifiée ». |

Pour de la prospection B2B en Algérie, une planification en matinée de jour ouvré (dimanche à jeudi) donne généralement de meilleurs taux d'ouverture qu'un envoi de fin de semaine.

## Étape 4 — Vérification

Un récapitulatif reprend le nom, le modèle, l'audience et le mode d'envoi. Trois boutons sont alors proposés :

| Bouton | Résultat |
| --- | --- |
| Annuler | Ferme la fenêtre, rien n'est créé. |
| Enregistrer comme brouillon | Crée la campagne en **Brouillon**, sans aucun envoi. |
| Envoyer maintenant / Planifier | Crée la campagne et déclenche l'envoi immédiat ou la planification, selon l'étape 3. |

Le bouton principal reste inactif tant que le nom, le modèle et l'identifiant d'audience ne sont pas renseignés — et, en mode planifié, tant que la date est vide.

## En cas de problème

- **Le bouton final est grisé** : revenez sur les onglets **Informations** et **Destinataires**, l'un des trois champs obligatoires est vide.
- **La liste des modèles est vide** : aucun modèle n'existe encore, ou tous sont archivés. Créez-en un dans [Modèles](help:templates).
- **Vous fermez la fenêtre par erreur** : la saisie est perdue, l'assistant repart de zéro à la prochaine ouverture. En cas de doute, terminez avec **Enregistrer comme brouillon**.
- **Vous voulez retoucher le texte avant l'envoi** : enregistrez d'abord en brouillon, puis relisez le message dans l'onglet **Contenu** du détail de la campagne.

> Voir aussi : [Campagnes](help:campaigns) et le guide [Créer et envoyer une campagne](help:flow-create-send-campaign).
`;export{e as default};
