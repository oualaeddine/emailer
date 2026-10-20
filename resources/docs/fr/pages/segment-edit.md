# Constructeur de segment

Le constructeur assemble des **conditions** en groupes ET / OU pour définir une audience précise. Chaque condition associe un champ, un opérateur et une valeur. Il sert à créer ou modifier un [segment](help:segments).

## Les catégories de conditions

| Catégorie | Exemples |
| --- | --- |
| Attributs du contact | Wilaya = Alger, SOCIETE contient « Meuble » |
| Listes | Appartient à la liste X, n'appartient pas à la liste Y |
| Engagement e-mail | A ouvert, a cliqué, n'a pas ouvert dans les N derniers jours |
| Dates | Créé après le… , dernier import avant le… |
| PagesJaunes | Catégorie ou localité issue d'une [recherche PagesJaunes](help:pagejaunes-search) |

## Pas à pas

1. Cliquez sur **Ajouter une condition** et choisissez la catégorie.
2. Sélectionnez le champ, l'opérateur et la valeur.
3. Regroupez les conditions et basculez **ET / OU** : avec ET, toutes doivent être vraies ; avec OU, une seule suffit.
4. Cliquez sur le bouton de calcul pour voir en direct le nombre de contacts correspondants.
5. Nommez le segment, choisissez un dossier et cliquez sur **Enregistrer**.

## Conseils

- Commencez par une condition large, puis affinez en surveillant l'effectif.
- Les conditions d'engagement dépendent des envois passés : un segment « a ouvert » est vide tant qu'aucune [campagne](help:campaigns) n'est partie.
- Un indicateur signale les modifications non enregistrées ; enregistrez avant de quitter.
- Un segment hérité d'une ancienne liste porte un badge et peut être en lecture seule.

Une fois enregistré, **Utiliser dans une campagne** crée directement l'envoi ciblé.
