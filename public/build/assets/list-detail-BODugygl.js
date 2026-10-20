const e=`# Détail d'une liste

Cette page affiche les **contacts d'une liste** dans un tableau, avec les mêmes outils de recherche et de filtre que la page [Contacts](help:recipients), plus les actions propres à la liste. Un bandeau de statistiques rappelle le nombre de contacts, la date de création et le dernier import.

## Parcourir les membres

Utilisez la recherche et les filtres pour retrouver un contact ; cliquez sur une ligne pour ouvrir sa [fiche](help:contact-detail). Si certains membres sont en liste noire, leur nombre est indiqué dans le bandeau : ils restent dans la liste mais ne reçoivent aucun envoi.

## Les actions

| Action | Effet |
| --- | --- |
| Importer dans cette liste | Lance l'[assistant d'import](help:recipients-import) avec la liste déjà choisie. |
| Exporter | Télécharge un fichier des membres. |
| Supprimer la liste | Supprime la liste uniquement, jamais les contacts. |
| Retirer d'une liste | Après sélection de lignes : les contacts quittent la liste mais restent dans votre base. |

## Pas à pas : nettoyer une liste

1. Filtrez les contacts à retirer (par exemple sans activité depuis longtemps).
2. Cochez les lignes concernées.
3. Cliquez sur **Retirer d'une liste** et confirmez.

## Conseils

- Exportez la liste avant une suppression : l'opération est définitive pour la liste.
- Pour cibler les membres selon leur comportement (ouvreurs, cliqueurs), créez un [segment](help:segments) plutôt que de trier à la main.

Retour : [Listes](help:lists).
`;export{e as default};
