const e=`# Formulaires de mise à jour

Les **formulaires de mise à jour du profil** permettent à un contact de corriger lui-même ses informations (société, téléphone, wilaya, centres d'intérêt…) via un lien placé dans vos e-mails. Vos données restent à jour sans effort de saisie de votre part.

## Créer un formulaire

1. Cliquez sur **Créer un formulaire**.
2. Donnez-lui un nom interne.
3. Choisissez les [attributs](help:attributes) que le contact pourra modifier. Les attributs système en lecture seule ne sont pas proposés.
4. Personnalisez les textes, le bouton et le message de remerciement.
5. Ouvrez l'**aperçu** pour contrôler l'affichage avant publication, puis enregistrez.

## Associer le formulaire à une campagne

Dans les paramètres supplémentaires d'une [campagne](help:campaign-setup), sélectionnez ce formulaire : un lien « Mettre à jour mon profil » est alors disponible pour le contact.

## Ce qui est enregistré

Les valeurs saisies remplacent celles de la [fiche contact](help:contact-detail). Les changements apparaissent dans l'onglet Activité.

## Conseils

- Limitez le formulaire à quelques champs utiles ; plus il est court, plus il sera rempli.
- Pour les champs à valeurs fixes, utilisez des attributs de type Catégorie afin d'obtenir des données propres pour vos [segments](help:segments).
- Complétez avec une [page de désinscription](help:unsubscribe-pages) proposant les préférences, pour offrir une alternative au départ.
`;export{e as default};
