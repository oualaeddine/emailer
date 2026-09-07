const e=`# Constituer une base depuis PageJaunes

Ce guide part d'une page blanche : vous n'avez encore aucun contact et vous voulez prospecter un secteur précis. Il montre comment identifier des entreprises dans l'annuaire PageJaunes, en tirer un fichier propre, l'importer, puis vérifier que la base obtenue est exploitable. Fil rouge : la SARL Bâti-Sud cherche des menuisiers dans la wilaya de Bejaia pour son catalogue de quincaillerie.

## 1. Définir la cible avant de chercher

Avant d'ouvrir l'annuaire, écrivez en une phrase qui vous voulez toucher : *« les entreprises de menuiserie situées dans la wilaya de Bejaia »*. Une cible étroite donne toujours de meilleurs résultats qu'une liste large et floue : le message qui suivra pourra parler concrètement au métier du destinataire.

## 2. Rechercher dans l'annuaire

Dans le menu de navigation, groupe **Contacts**, ouvrez **Annuaire PageJaunes**.

![Recherche dans l'annuaire PageJaunes](/docs/screenshots/pagejaunes-search/01-search.png)

Saisissez \`menuiserie\` dans le champ de recherche et cliquez sur **Rechercher**. La recherche porte sur le nom et le code d'activité, pas sur la ville : le filtrage géographique se fera à l'œil, sur la localisation affichée dans chaque fiche.

Si vous obtenez trop peu de résultats, raccourcissez le terme (\`menuis\` plutôt que \`menuiserie industrielle\`). Si vous en obtenez trop, ce n'est pas grave : vous allez trier à l'étape suivante.

## 3. Trier les fiches

Chaque résultat est une carte contenant la raison sociale, la localité et la wilaya, l'adresse postale, des étiquettes d'activité (Distributeur, Exportateur, Importateur, Producteur) et les e-mails publiés.

Trois critères de tri, dans cet ordre :

| Critère | Décision |
| --- | --- |
| Une adresse e-mail est publiée | Sans adresse, la fiche affiche **Pas d'email disponible** : écartez-la de l'import et notez-la pour un appel téléphonique. |
| La wilaya correspond | Gardez les fiches d'Akbou, Bejaia, Amizour… écartez celles d'Alger ou d'Oran. |
| L'activité correspond | Pour un catalogue de quincaillerie, un **Producteur** ou un **Distributeur** est plus pertinent qu'un **Exportateur** seul. |

## 4. Constituer le fichier

Cette page est une page de consultation : elle affiche les entreprises, elle ne les enregistre pas. Reportez donc les fiches retenues dans un classeur Excel, avec exactement ces quatre colonnes :

\`\`\`
Email;Prénom;Nom;Société
contact@menuiserie-bejaia.dz;;;Menuiserie Bejaia
info@boiserie-akbou.dz;;;Boiserie Akbou SARL
commercial@atelier-amizour.dz;;;Atelier Amizour
\`\`\`

Les colonnes Prénom et Nom restent souvent vides quand l'annuaire ne publie qu'une adresse générique de type \`contact@\`. Ce n'est pas gênant : seule l'adresse e-mail est obligatoire. En revanche, renseignez toujours **Société** — sans elle, une liste de 200 adresses \`contact@…\` devient illisible.

Travaillez par lots homogènes : un métier et une wilaya par fichier. Vous pourrez ainsi envoyer un message adapté à chaque lot.

## 5. Enregistrer au bon format

**Fichier → Enregistrer sous → CSV UTF-8 (délimité par des virgules)**. C'est ce format qui préserve les accents des raisons sociales. Un enregistrement en CSV non-UTF-8 transformera « Boiserie Akbou » en caractères illisibles, et cela ne se rattrape pas après l'import.

## 6. Importer le fichier

Ouvrez **Importer (CSV/Excel)** dans le groupe **Contacts**.

![Étape 1 — envoi du fichier](/docs/screenshots/recipients-import/01-upload.png)

Cliquez sur **Choisir un fichier** et sélectionnez votre CSV.

## 7. Associer les colonnes

![Étape 2 — association des colonnes](/docs/screenshots/recipients-import/02-mapping.png)

Associez \`Email\` à **Adresse e-mail** et \`Société\` à **Entreprise**. Laissez Prénom et Nom non associés si vos colonnes sont vides. Cliquez sur **Continuer**.

Le détail complet de l'assistant, étape par étape, est dans [Importer des destinataires depuis un fichier CSV/Excel](help:flow-import-recipients-csv).

## 8. Vérifier puis valider

L'écran de vérification affiche les lignes totales, valides, invalides et les doublons. Sur des adresses relevées dans un annuaire, quelques invalides sont normales — une faute de frappe lors de la recopie suffit. Si vous en voyez beaucoup, revenez à votre fichier avant de valider.

Cliquez ensuite sur **Importer les lignes valides**.

## 9. Contrôler la base obtenue

Ouvrez [Destinataires](help:recipients).

![Liste des destinataires](/docs/screenshots/recipients/01-recipients.png)

Recherchez \`bejaia\` : vos nouvelles entreprises doivent apparaître, en statut **Actif**. Celles qui apparaissent en **Supprimé** figuraient déjà dans la [liste de suppression](help:suppression) — un contact qui vous avait déjà demandé de ne plus l'écrire, par exemple. Laissez-les ainsi.

Pour ajouter après coup une entreprise oubliée, inutile de refaire un fichier : utilisez **Nouveau destinataire** et la fenêtre décrite dans [Créer un destinataire](help:dialog-recipient-form).

## 10. Envoyer, puis mesurer la qualité de la source

Créez maintenant votre campagne avec [Créer et envoyer une campagne](help:flow-create-send-campaign). Le premier envoi est le véritable test de la source.

Quarante-huit heures plus tard, ouvrez [Rapports](help:reporting) et regardez le taux de rejet :

| Taux de rejet | Ce que cela dit de votre collecte |
| --- | --- |
| < 2 % | Source de bonne qualité, poursuivez la même méthode. |
| 2 – 5 % | Acceptable, mais quelques fiches de l'annuaire sont périmées. |
| > 5 % | Vous avez repris trop d'adresses anciennes ou mal recopiées. Ralentissez et vérifiez avant d'importer. |

Les adresses ayant rebondi définitivement sont bloquées automatiquement : ne les réimportez pas, elles resteraient inactives.

## En cas de problème

- **« Aucun résultat. »** dans l'annuaire : le terme est trop précis. Essayez un mot-clé plus court ou une variante orthographique.
- **La majorité des fiches n'ont pas d'e-mail** : c'est courant dans certains secteurs. Élargissez la recherche pour obtenir un volume exploitable, ou prévoyez une prise de contact téléphonique.
- **Une entreprise apparaît plusieurs fois dans l'annuaire** : il s'agit souvent de plusieurs établissements. Ne gardez que celui dont la localisation correspond à votre cible.
- **Beaucoup de doublons à l'import** : ces entreprises sont déjà dans votre base, probablement issues d'une recherche précédente. Aucun contact n'est dupliqué, l'import n'a simplement rien à ajouter.
`;export{e as default};
