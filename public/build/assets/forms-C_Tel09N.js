const e=`# Formulaires d'inscription

La page **Formulaires** liste vos formulaires d'inscription avec leurs listes cibles, le nombre d'inscriptions reçues et leur statut. Chaque formulaire dispose d'un **lien public** (de la forme \`/f/…\`) et d'un **code d'intégration** pour votre site.

## Créer un formulaire

1. Cliquez sur **Créer un formulaire d'inscription**.
2. Dans le constructeur, parcourez les quatre volets : **Champs**, **Design**, **Paramètres**, **Partager** (détail dans l'[Éditeur de formulaire](help:form-edit)).

| Volet | Ce que vous y réglez |
| --- | --- |
| Champs | Les [attributs](help:attributes) demandés, les champs obligatoires, la case de consentement RGPD |
| Design | Couleurs, textes et bouton |
| Paramètres | Listes cibles, double confirmation, message de remerciement ou redirection |
| Partager | Lien public, code iframe ou HTML |

## Double confirmation

Quand elle est activée, le visiteur reçoit un e-mail de confirmation et n'est ajouté à la [liste](help:lists) qu'après avoir cliqué. C'est la meilleure protection contre les fausses adresses et les inscriptions non consenties. Le choix de l'e-mail de confirmation et de la redirection se fait dans l'onglet **Paramètres** de l'[éditeur](help:form-edit).

## Captcha des formulaires

Un administrateur voit le bouton **Captcha des formulaires** en haut de la page : il choisit le fournisseur (**hCaptcha** ou **Cloudflare Turnstile**), saisit la **Clé de site** et la **Clé secrète**, puis enregistre. La clé secrète est conservée chiffrée et n'est jamais réaffichée (« Clé secrète enregistrée » ; saisissez une nouvelle clé pour la remplacer). Ce réglage ne s'applique qu'aux formulaires dont **Activer le captcha** est coché dans l'onglet **Paramètres**.

## Partager le formulaire

Copiez le lien public pour l'envoyer par message ou le placer sur les réseaux sociaux, ou collez le code d'intégration dans la page de votre site.

## Suivre les inscriptions

Le compteur de la liste des formulaires indique les inscriptions reçues. Les nouveaux contacts apparaissent dans la liste cible et dans le tableau [Contacts](help:recipients).

## Conseils

- Demandez le minimum de champs : adresse e-mail et prénom suffisent souvent.
- Gardez la case RGPD obligatoire pour conserver une preuve de consentement.
- Pour laisser un contact corriger ses données plus tard, voir les [formulaires de mise à jour](help:update-profile-forms).
`;export{e as default};
