const e=`# Modèles

La page **Modèles** est votre bibliothèque de mises en page réutilisables. Au lieu de réécrire à chaque fois l'en-tête, la présentation de l'offre et le pied de page, vous préparez une fois un modèle « Offre commerciale » ou « Relance impayé » et vous le réutilisez pour tous vos envois. Résultat : des messages homogènes et beaucoup de temps gagné.

![Bibliothèque de modèles](/docs/screenshots/templates/01-templates.png)

## Lire la bibliothèque

Les modèles s'affichent sous forme de vignettes. Chaque carte présente :

| Élément | Ce qu'il indique |
| --- | --- |
| Nom | Le titre du modèle, ex. « Offre commerciale — menuiserie » |
| Catégorie | Le classement libre que vous avez choisi, ex. « Prospection » |
| Aperçu | Une miniature du rendu réel du modèle |
| Utilisé dans n brouillon(s) | Le nombre de brouillons qui s'appuient sur ce modèle |
| Archivé | Une étiquette présente uniquement sur les modèles mis de côté |

Si la bibliothèque est vide, le message **Aucun modèle** vous invite à créer le premier.

## Créer un modèle

Cliquez sur **Nouveau modèle** en haut à droite : la fenêtre de création s'ouvre. Vous y saisissez un nom, une catégorie facultative et le contenu, avec le même éditeur que le [rédacteur d'e-mail](help:composer). Le détail des champs est décrit dans [Créer un modèle](help:dialog-template-form).

Quelques modèles utiles pour une activité B2B en Algérie :

- **Présentation d'entreprise** — premier contact avec un prospect rencontré sur un salon.
- **Offre commerciale** — envoi d'un devis type avec grille tarifaire.
- **Relance** — deuxième message après une offre restée sans réponse.
- **Vœux et fermetures** — annonce des congés annuels ou des jours fériés.

## Archiver un modèle

Le bouton **…** de chaque carte ouvre un menu contenant deux actions.

**Archiver** met le modèle de côté sans le détruire : il reste consultable, marqué de l'étiquette **Archivé**, et les campagnes déjà créées avec ce modèle ne sont pas affectées. C'est l'action à privilégier pour une offre saisonnière ou une ancienne charte graphique.

## Supprimer un modèle

**Supprimer** efface définitivement le modèle. L'opération n'est possible que si aucun brouillon ni aucune campagne ne l'utilise. Sinon, un message vous l'indique :

> Ce modèle est utilisé et ne peut pas être supprimé ; archivez-le à la place.

Ce garde-fou existe pour protéger votre historique. Dans le doute, archivez : c'est réversible, la suppression ne l'est pas.

## Modifier un modèle sans casser l'existant

Un point important : quand une campagne est créée à partir d'un modèle, elle en conserve une **copie** de son contenu. Modifier le modèle plus tard ne change donc jamais le contenu d'une campagne déjà créée ou envoyée. Vous pouvez faire évoluer vos modèles en toute sérénité — mais si vous voulez que la nouveauté s'applique à un envoi, il faut recréer le brouillon ou la campagne à partir du modèle mis à jour.

## En cas de problème

- **La suppression est refusée** : le modèle sert encore à un brouillon ou à une campagne. Utilisez **Archiver**.
- **La miniature s'affiche mal** : elle reprend le HTML réel du modèle en tout petit ; un contenu très large peut apparaître tronqué. Fiez-vous à l'aperçu du [rédacteur](help:composer) pour juger du rendu.
- **Le bouton Nouveau modèle est absent** : la création et la modification de modèles sont réservées aux rôles Responsable marketing et Administrateur. Les opérateurs peuvent utiliser les modèles sans les modifier.
- **Deux modèles portent presque le même nom** : ajoutez une catégorie et une année dans le nom, par exemple « Offre commerciale 2026 — Oran ».

> Voir aussi le pas-à-pas complet [Rédiger et envoyer un e-mail](help:flow-compose-send-email).
`;export{e as default};
