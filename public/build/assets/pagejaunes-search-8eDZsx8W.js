const e=`# Annuaire PageJaunes

La page **Annuaire PageJaunes** interroge l'annuaire professionnel algérien depuis l'application. Elle sert à identifier des entreprises par métier, par nom ou par code d'activité, et à voir immédiatement lesquelles publient une adresse e-mail exploitable pour une campagne B2B.

![Recherche dans l'annuaire PageJaunes](/docs/screenshots/pagejaunes-search/01-search.png)

## Lancer une recherche

Saisissez un terme dans le champ « Rechercher une entreprise par nom ou code… » puis cliquez sur **Rechercher** (ou appuyez sur Entrée). Tant qu'aucune recherche n'a été lancée, la page affiche simplement « Saisissez un terme de recherche pour commencer. »

Exemples de recherches utiles :

| Vous cherchez | Saisissez |
| --- | --- |
| Un secteur d'activité | \`menuiserie\`, \`transport\`, \`agroalimentaire\` |
| Une entreprise précise | \`Menuiserie Bejaia\` |
| Un code d'activité | le code tel qu'il figure dans l'annuaire |

La recherche porte sur le nom et le code, pas sur la ville : pour cibler une wilaya, lisez la localisation affichée sur chaque fiche et écartez celles qui ne conviennent pas.

## Lire une fiche entreprise

Chaque résultat est présenté sous forme de carte contenant :

| Élément | Contenu |
| --- | --- |
| Titre | La raison sociale, ou l'abréviation si le nom complet n'est pas publié. |
| Localisation | Localité et wilaya, par exemple « Akbou, Bejaia ». |
| Adresse | L'adresse postale, quand elle est renseignée. |
| Étiquettes d'activité | Distributeur, Exportateur, Importateur, Producteur — une ou plusieurs, selon l'entreprise. |
| E-mails | Les adresses publiées, une par ligne. |

Si aucune adresse n'est publiée, la carte affiche une étiquette rouge **Pas d'email disponible**. Ces entreprises ne sont pas exploitables pour un envoi ; notez-les pour une prise de contact par téléphone.

## Utiliser les étiquettes d'activité

Les étiquettes affinent le ciblage sans requête supplémentaire. Si vous vendez des machines-outils, une entreprise marquée **Producteur** est un prospect plus pertinent qu'une entreprise marquée **Distributeur** seulement. Pour une offre de logistique, ce sont les **Importateur** et **Exportateur** qui comptent.

## Des résultats à la base de destinataires

Cette page est une page de consultation : elle affiche les entreprises et leurs adresses, sans les enregistrer. Pour constituer réellement votre base, relevez les adresses utiles, préparez un fichier et importez-le par [Importer (CSV/Excel)](help:recipients-import). Le pas-à-pas complet est décrit dans [Constituer une base depuis PageJaunes](help:flow-source-pagejaunes).

## Bonnes pratiques

- Travaillez par lots homogènes : un métier et une wilaya à la fois, par exemple « menuiserie » à Bejaia. Le message qui suivra sera bien plus pertinent qu'un envoi générique.
- Ne conservez que des adresses professionnelles publiées (\`contact@\`, \`commercial@\`, \`info@\`). Une prospection B2B ciblée obtient de bien meilleurs résultats qu'un envoi massif indifférencié.
- Vérifiez que l'entreprise ne figure pas déjà dans vos [Destinataires](help:recipients) avant de la ressaisir.

## En cas de problème

- **« Aucun résultat. »** : le terme est trop précis ou mal orthographié. Essayez un mot-clé plus court, \`menuis\` plutôt que \`menuiserie industrielle\`.
- **Le bouton Rechercher ne réagit pas** : le champ est vide, ou une recherche est déjà en cours (le bouton reste inactif pendant le chargement).
- **Beaucoup de fiches sans e-mail** : c'est fréquent dans certains secteurs. Élargissez la recherche pour obtenir un volume exploitable.
- **Une même entreprise apparaît plusieurs fois** : l'annuaire peut contenir plusieurs établissements. Choisissez celui dont la localisation correspond à votre cible.
`;export{e as default};
