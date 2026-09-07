const e=`# Import de destinataires

La page **Importer (CSV/Excel)** ajoute des destinataires en masse à partir d'un fichier. L'import se déroule en quatre étapes : envoi du fichier, association des colonnes, vérification, puis validation définitive. Tant que vous n'avez pas validé la dernière étape, rien n'est enregistré : vous pouvez abandonner à tout moment sans conséquence.

![Étape 1 — envoi du fichier](/docs/screenshots/recipients-import/01-upload.png)

## Étape 1 — Envoyer le fichier

Cliquez sur la zone d'envoi et choisissez un fichier \`.csv\`, \`.xlsx\` ou \`.xls\`. La première ligne du fichier doit contenir les intitulés de colonnes.

Exemple de fichier accepté :

\`\`\`
Email;Prénom;Nom;Société
contact@menuiserie-bejaia.dz;Karim;Belkacem;Menuiserie Bejaia
info@transport-oran.dz;Nadia;Cherif;Transport Oran SARL
\`\`\`

Le séparateur peut être un point-virgule ou une virgule. Seule la colonne **e-mail** est obligatoire ; toutes les autres sont facultatives.

## Étape 2 — Associer les colonnes

![Étape 2 — association des colonnes](/docs/screenshots/recipients-import/02-mapping.png)

Indiquez, pour chaque champ de l'application, la colonne de votre fichier qui le contient. Ouvrez la liste déroulante « Sélectionner une colonne… » en face de **Adresse e-mail** et choisissez \`Email\`, puis faites de même pour les autres champs. Les listes proposent les intitulés lus dans la première ligne de votre fichier.

| Champ de l'application | Obligatoire | Exemple |
| --- | --- | --- |
| Adresse e-mail | Oui | \`contact@menuiserie-bejaia.dz\` |
| Prénom | Non | \`Karim\` |
| Nom | Non | \`Belkacem\` |
| Société | Non | \`Menuiserie Bejaia\` |

Les colonnes que vous laissez sur « Ignorer » ne sont pas importées.

## Étape 3 — Vérifier

Un aperçu récapitule le nombre de lignes valides, les doublons et les lignes rejetées. Les motifs de rejet les plus fréquents sont une adresse mal formée et une adresse déjà présente dans la [liste de suppression](help:suppression) — dans ce dernier cas, l'exclusion est volontaire et ne doit pas être contournée.

## Étape 4 — Terminer

La validation crée les destinataires. Ils apparaissent immédiatement dans la page [Destinataires](help:recipients) avec la source « Import CSV » ou « Import Excel », ce qui permet de les retrouver plus tard.

## En cas de problème

- **Le fichier est refusé** : vérifiez l'extension (\`.csv\`, \`.xlsx\`, \`.xls\`) et la présence d'une ligne d'intitulés.
- **Les accents s'affichent mal** : enregistrez votre fichier CSV en UTF-8 depuis Excel (« CSV UTF-8 (délimité par des virgules) »).
- **Beaucoup de doublons** : c'est normal si le fichier recoupe une base existante ; les doublons sont ignorés, pas dupliqués.

> Pour un pas-à-pas complet depuis la préparation du fichier, voir le guide [Importer des destinataires depuis un fichier CSV/Excel](help:flow-import-recipients-csv).
`;export{e as default};
