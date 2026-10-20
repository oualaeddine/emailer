const e=`# Historique des imports

L'**historique** recense chaque import de contacts : fichier, date, utilisateur, statut et compteurs (importés, mis à jour, en échec). Il sert à vérifier qu'un import s'est bien déroulé et à récupérer les lignes rejetées.

## Lire le tableau

| Colonne | Contenu |
| --- | --- |
| Fichier | Nom du fichier importé |
| Date et utilisateur | Quand et par qui |
| Statut | En cours, Terminé ou Échec |
| Compteurs | Contacts importés, mis à jour, en échec |

## Suivre un import en cours

Le statut passe de **En cours** à **Terminé**. Actualisez la page pour voir les compteurs progresser : un gros fichier peut prendre quelques minutes.

## Corriger les erreurs

1. Repérez un import dont le compteur d'échecs est supérieur à zéro.
2. Téléchargez le fichier CSV des erreurs.
3. Corrigez les lignes (adresse invalide, champ obligatoire manquant, doublon).
4. Réimportez uniquement ce fichier corrigé.

## Lancer un nouvel import

Cliquez sur **Importer des contacts** pour ouvrir l'[assistant en cinq étapes](help:recipients-import). Le guide [Importer des contacts depuis un CSV](help:flow-import-recipients-csv) détaille tout le parcours.

## Conseils

- Depuis une ligne, ouvrez les [listes](help:lists) concernées pour contrôler le résultat.
- Les adresses déjà présentes sont mises à jour, pas dupliquées.
- Les adresses de la [liste de suppression](help:suppression) ne sont jamais réactivées par un import.
`;export{e as default};
