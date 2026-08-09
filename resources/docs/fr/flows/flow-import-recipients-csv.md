# Importer des destinataires depuis un fichier CSV/Excel

Ce guide couvre tout le trajet d'un fichier de contacts : le préparer proprement dans Excel, le charger dans l'application, associer les colonnes, vérifier le résultat, puis utiliser les contacts importés dans une campagne. Fil rouge : un fichier de 340 menuisiers de la région de Bejaia, collecté par le commercial de la SARL Bâti-Sud.

## 1. Préparer le fichier dans Excel

Une ligne = un contact. La première ligne contient les intitulés de colonnes. Une seule colonne est réellement obligatoire : l'adresse e-mail.

| Colonne | Obligatoire | Exemple |
| --- | --- | --- |
| Email | Oui | `contact@menuiserie-bejaia.dz` |
| Prénom | Non | `Karim` |
| Nom | Non | `Belkacem` |
| Société | Non | `Menuiserie Bejaia` |

Trois règles à respecter avant tout :

- **Une seule adresse par cellule.** Si votre source contient `contact@x.dz ; commercial@x.dz`, créez deux lignes.
- **Pas de cellules fusionnées, pas de lignes de titre décoratives** au-dessus des intitulés.
- **Pas de colonne vide au milieu** du tableau.

Nettoyez aussi les espaces parasites : dans Excel, la formule `=SUPPRESPACE(A2)` retire les espaces avant et après une adresse copiée d'un site web.

## 2. Enregistrer au bon format

Utilisez **Fichier → Enregistrer sous** et choisissez :

- `CSV UTF-8 (délimité par des virgules)` — le choix recommandé, il conserve les accents ;
- ou bien `Classeur Excel (*.xlsx)`, également accepté.

Si vous enregistrez en CSV classique (non UTF-8), les accents des noms d'entreprise risquent d'apparaître comme `MenuisÃ¨rie`. C'est le problème le plus fréquent, et il se corrige uniquement à cette étape.

## 3. Ouvrir l'assistant d'import

Dans le menu de navigation, groupe **Contacts**, cliquez sur **Importer (CSV/Excel)**.

![Étape 1 — envoi du fichier](/docs/screenshots/recipients-import/01-upload.png)

Cliquez sur **Choisir un fichier** et sélectionnez votre fichier. Les formats acceptés sont rappelés sous le bouton : CSV, TXT, XLSX. Le nom du fichier s'affiche à côté du bouton, puis l'analyse démarre.

Rien n'est enregistré à ce stade : vous pouvez quitter la page à tout moment sans conséquence.

## 4. Associer les colonnes

![Étape 2 — association des colonnes](/docs/screenshots/recipients-import/02-mapping.png)

L'application a lu vos intitulés. Pour chacun des quatre champs qu'elle sait remplir, choisissez la colonne correspondante de votre fichier :

| Champ de l'application | Colonne de votre fichier |
| --- | --- |
| Adresse e-mail | `Email` |
| Prénom | `Prénom` |
| Nom | `Nom` |
| Entreprise | `Société` |

Seule l'association de l'**Adresse e-mail** est indispensable : le bouton **Continuer** reste inactif tant qu'elle n'est pas définie. Les colonnes de votre fichier que vous n'associez à rien sont simplement ignorées — un fichier contenant aussi un numéro de téléphone ou une wilaya s'importe sans problème, ces colonnes ne seront juste pas reprises.

Cliquez sur **Continuer**.

## 5. Vérifier avant de valider

L'écran **Vérification** affiche quatre compteurs :

| Compteur | Signification |
| --- | --- |
| Lignes totales | Le nombre de lignes lues dans le fichier. |
| Valides | Les lignes qui seront importées. |
| Invalides | Les lignes rejetées, adresse mal formée le plus souvent. |
| Doublons | Les adresses déjà présentes dans votre base. |

C'est le moment de vous poser la bonne question. Sur 340 lignes, voir 15 doublons et 5 invalides est parfaitement normal. Voir 200 invalides ne l'est pas : c'est presque toujours le signe que la colonne e-mail a été mal associée à l'étape précédente. Dans ce cas, rechargez la page et recommencez l'import.

Quand les chiffres sont cohérents, cliquez sur **Importer les lignes valides**. Seules les lignes valides sont créées ; les invalides et les doublons sont écartés, jamais dupliqués.

## 6. Lire le résumé

L'écran final récapitule le nombre de destinataires importés, de doublons et de lignes invalides. Notez ce chiffre : il vous servira à vérifier la taille de l'audience de votre future campagne.

## 7. Retrouver les contacts importés

Ouvrez [Destinataires](help:recipients).

![Liste des destinataires](/docs/screenshots/recipients/01-recipients.png)

Les nouveaux contacts y figurent avec la source « Import CSV » ou « Import Excel ». Utilisez le champ de recherche pour vérifier un cas précis : tapez `menuiserie-bejaia` pour confirmer que `contact@menuiserie-bejaia.dz` est bien arrivé.

Certains contacts peuvent apparaître directement en statut **Supprimé** : leur adresse figurait déjà dans la [liste de suppression](help:suppression). C'est voulu, l'import ne débloque jamais une adresse bloquée.

## 8. Utiliser les contacts dans une campagne

Regroupez les contacts importés dans une liste de destinataires, puis ouvrez [Campagnes](help:campaigns) et cliquez sur **Nouvelle campagne**. Dans l'étape **Destinataires** de l'assistant, choisissez **Liste de destinataires** et indiquez l'identifiant de cette liste.

Le pas-à-pas complet de l'envoi est décrit dans [Créer et envoyer une campagne](help:flow-create-send-campaign).

## 9. Vérifier après le premier envoi

Le premier envoi vers une base fraîchement importée est aussi le test de sa qualité. Ouvrez [Rapports](help:reporting) 48 heures après : si le taux de rejet dépasse 5 %, le fichier source contenait beaucoup d'adresses périmées. Ajustez votre méthode de collecte avant le prochain import.

## En cas de problème

- **Le fichier est refusé au chargement** : vérifiez l'extension (`.csv`, `.txt`, `.xlsx`) et la présence d'une vraie ligne d'intitulés en première position.
- **Les accents sont remplacés par des caractères bizarres** : le fichier n'a pas été enregistré en UTF-8. Reprenez à l'étape 2, l'import ne peut pas corriger cela après coup.
- **Le bouton Continuer reste inactif** : l'association du champ **Adresse e-mail** n'a pas été faite.
- **Presque tout est en doublon** : votre fichier recoupe une base déjà importée. Ce n'est pas une erreur, aucun contact n'est dupliqué ; l'import n'a simplement plus rien à ajouter.

> Version courte, limitée à la page d'import : [Import de destinataires](help:recipients-import).
