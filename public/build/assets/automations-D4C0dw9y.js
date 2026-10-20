const e=`# Automatisations

Une **automatisation** envoie automatiquement des e-mails à vos contacts lorsqu'un déclencheur se produit, sans que vous ayez à lancer chaque envoi. La page **Automatisations** liste vos scénarios avec leur statut et leurs résultats.

## Principe

Un scénario se compose de trois éléments :

| Élément | Exemple |
| --- | --- |
| Déclencheur | Un contact rejoint la liste « Nouveaux clients » |
| Étapes | Un e-mail de bienvenue, une attente de 3 jours, un second e-mail |
| Sortie | Le contact a terminé le scénario |

## Créer une automatisation

1. Cliquez sur **Créer une automatisation**, nommez-la et choisissez le **déclencheur** (ajout à une [liste](help:lists), formulaire envoyé, campagne ouverte ou cliquée, attribut modifié, anniversaire d'une date), puis **Créer et ouvrir l'éditeur**.
2. Dans l'[éditeur](help:automation-edit), complétez le déclencheur et, si besoin, filtrez les contacts concernés (par exemple selon un [segment](help:segments)).
3. Composez les **étapes** : enchaînez e-mails et délais d'attente. Chaque e-mail utilise un [modèle](help:templates) et, au choix, un [expéditeur](help:senders) vérifié.
4. **Activez** l'automatisation.

Tout le détail des déclencheurs, des étapes et des réglages se trouve dans la page [Éditeur d'automatisation](help:automation-edit).

## Suivre les résultats

Depuis la liste, suivez les contacts entrés, les envois réalisés et les résultats. Désactivez le scénario à tout moment : les contacts déjà en cours sont alors suspendus.

## Conseils

- Testez d'abord le scénario avec une petite liste interne avant de l'activer sur toute votre base.
- Espacez les messages pour ne pas saturer vos contacts.
- Les contacts en [liste noire](help:suppression) ne reçoivent jamais les e-mails d'une automatisation.
- Relisez chaque e-mail : une erreur se répète à chaque déclenchement.
`;export{e as default};
