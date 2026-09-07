const e=`# Identité visuelle

La page **Identité visuelle** adapte l'apparence de l'application aux couleurs de votre entreprise. Vous y réglez trois choses : le nom de l'organisation affiché dans l'interface, la couleur de marque qui teinte les boutons et les liens, et le thème clair ou sombre appliqué par défaut à toute l'équipe.

![Identité visuelle](/docs/screenshots/settings-branding/01-branding.png)

## Nom de l'organisation

Ce nom apparaît dans l'en-tête de navigation et sert de signature par défaut au bas des messages sortants. Saisissez la raison sociale telle que vos destinataires la reconnaissent, par exemple \`PageJaunes Algérie\` ou \`SARL Bla Communication\`.

Évitez les abréviations internes : le nom est vu par vos correspondants, pas seulement par votre équipe.

## Couleur de marque

Le champ **Couleur de marque** accepte un code hexadécimal à six caractères précédé du dièse, par exemple \`#FFD400\` (le jaune officiel Pages Jaunes DZ). Le petit carré à droite du champ ouvre le sélecteur de couleur de votre système si vous préférez choisir visuellement.

Cette unique couleur suffit : l'application en dérive automatiquement toute une gamme de nuances, utilisée pour les boutons principaux, la rubrique active du menu et les liens, en version claire comme en version sombre.

Quelques précautions :

- Choisissez une couleur suffisamment contrastée par rapport au blanc et au noir. Un jaune très clair rend les libellés de boutons illisibles.
- Reprenez la couleur exacte de votre charte graphique plutôt qu'une approximation à l'œil : votre imprimeur ou votre agence vous fournira le code hexadécimal.
- Le changement est global : il s'applique à tous les utilisateurs, pas seulement à vous.

## Thème par défaut

La liste déroulante propose trois valeurs.

| Valeur | Effet |
| --- | --- |
| **Clair** | Fond blanc, adapté aux bureaux bien éclairés et aux captures d'écran |
| **Sombre** | Fond foncé, plus confortable en soirée ou en éclairage faible |
| **Système** | Suit le réglage du système d'exploitation de chaque poste, qui bascule souvent seul entre jour et nuit |

Il s'agit du thème **par défaut de l'organisation**, appliqué à toute personne qui n'a pas exprimé de préférence. Le mode **Système** est le choix le plus souple pour une équipe aux habitudes variées.

## Enregistrer

Cliquez sur **Enregistrer**. Le bandeau vert « Paramètres enregistrés. » confirme la prise en compte. La couleur et le thème s'appliquent immédiatement ; si un collègue a l'application déjà ouverte, il verra le changement après un rechargement de page.

Chaque modification est consignée dans le [Journal d'audit](help:audit-log), paramètre par paramètre, avec l'ancienne et la nouvelle valeur.

## Qui peut modifier cette page

Les rôles **Administrateur** et **Responsable marketing** ont accès à l'identité visuelle. Les rôles Opérateur marketing et Lecteur ne voient pas cette rubrique. Le détail des droits est décrit dans [Utilisateurs](help:admin-users).

## En cas de problème

- **Le bandeau de confirmation n'apparaît pas** : l'enregistrement a échoué, souvent parce que la couleur n'est pas un code hexadécimal valide. Elle doit s'écrire \`#FFD400\` — un dièse suivi de six caractères.
- **La couleur a changé mais l'interface reste identique** : rechargez la page. Les onglets déjà ouverts conservent l'ancienne palette jusqu'à leur rafraîchissement.
- **Le texte des boutons devient illisible** : la couleur choisie est trop claire. Prenez une teinte plus soutenue de la même famille.
- **Un collègue ne voit pas le thème que j'ai défini** : il a probablement choisi lui-même un thème sur son poste, ou son système est réglé différemment si vous avez retenu le mode Système.
`;export{e as default};
