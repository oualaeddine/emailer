# Attributs

Les **attributs** sont les champs personnalisés de vos contacts : société, wilaya, secteur, date de dernier devis… Le tableau liste leur nom, leur type, le nombre de contacts concernés et leur date de création. Ils alimentent les colonnes, les filtres, les [segments](help:segments) et les balises de personnalisation des e-mails.

## Créer un attribut

1. Cliquez sur **Créer un attribut**.
2. Saisissez le nom : majuscules, chiffres et tirets bas uniquement, 50 caractères au maximum (par exemple `WILAYA`).
3. Choisissez le type, puis validez.

## Les types disponibles

| Type | Usage |
| --- | --- |
| Texte | Valeur libre |
| Nombre | Montant, effectif… |
| Date | Échéance, dernier contact |
| Booléen | Oui / non |
| Catégorie | Un choix dans une liste fixe |
| Choix multiple | Plusieurs choix dans une liste fixe |
| Utilisateur | Commercial responsable |

Pour une Catégorie ou un Choix multiple, saisissez la liste des valeurs autorisées.

## Attributs système

`EMAIL`, `PRENOM`, `NOM` et `SOCIETE` sont en **lecture seule** : ils ne peuvent être ni modifiés ni supprimés.

## Où les retrouver

- Dans la correspondance des colonnes de l'[import](help:recipients-import).
- Dans **Personnaliser les colonnes** du tableau [Contacts](help:recipients).
- Dans l'onglet Attributs de la [fiche contact](help:contact-detail).
- Dans le menu de personnalisation de l'éditeur d'e-mail.

## Conseils

- Choisissez le bon type dès le départ : changer de type après coup est limité.
- Préférez une Catégorie à un Texte libre pour obtenir des filtres fiables.
