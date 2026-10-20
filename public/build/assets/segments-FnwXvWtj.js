const e=`# Segments

Un **segment** est une audience dynamique définie par des conditions : il se recalcule à chaque utilisation, contrairement à une [liste](help:lists) qui reste figée. La page **Segments** liste vos segments avec leur effectif, leur dossier et leur date de modification.

## Créer un segment

1. Cliquez sur **Créer un segment** : le [constructeur](help:segment-edit) s'ouvre.
2. Ajoutez vos conditions (attributs, listes, engagement e-mail, dates, PagesJaunes).
3. Donnez un nom, puis enregistrez.

## Consulter et utiliser

- **Ouvrir** un segment affiche les contacts qui correspondent aujourd'hui à ses conditions.
- **Utiliser dans une campagne** crée une [campagne](help:campaign-setup) ciblant ce segment ; vous pouvez aussi le choisir à l'étape Destinataires.
- **Dupliquer** permet de tester une variante sans toucher à l'original.
- **Supprimer** retire le segment, jamais les contacts.

## Exemples d'usage

| Objectif | Condition |
| --- | --- |
| Relancer les non-ouvreurs | N'a pas ouvert dans les 30 derniers jours |
| Cibler une région | Attribut Wilaya = Béjaïa |
| Récompenser les engagés | A cliqué dans la campagne X |

## Conseils

- Filtrez la page par dossier ou par nom pour retrouver rapidement un segment.
- L'effectif affiché est un instantané : relancez le calcul avant un gros envoi.
- Les contacts en [liste noire](help:suppression) sont écartés à l'envoi, même s'ils correspondent aux conditions.
`;export{e as default};
