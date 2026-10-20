const e=`# Éditeur de formulaire d'inscription

L'éditeur s'ouvre depuis la page [Formulaires](help:forms). À gauche, quatre onglets (**Champs**, **Design**, **Paramètres**, **Partager**) ; à droite, un **Aperçu** qui se met à jour au fur et à mesure, même avant l'enregistrement.

## L'en-tête

Le nom du formulaire s'accompagne d'un badge de statut (**Brouillon**, **Actif** ou **Inactif**) et de deux compteurs : **Inscriptions** et **Confirmées** (ces dernières concernent la double confirmation).

| Bouton | Effet |
| --- | --- |
| Enregistrer | Enregistre les modifications ; grisé tant que rien n'a changé (« Vous avez des modifications non enregistrées. » s'affiche sinon) |
| Publier | Enregistre si besoin, active le formulaire (« Formulaire publié. ») et ouvre l'onglet **Partager** |
| Désactiver | Remplace **Publier** quand le formulaire est actif (« Formulaire désactivé. ») |

Quitter la page avec des modifications non enregistrées déclenche un message de confirmation du navigateur.

## Onglet Champs

Le champ **E-mail** est toujours présent et obligatoire. Pour chaque champ, réglez le **Libellé** (obligatoire), le **Texte indicatif** (absent pour les champs oui/non et à choix), le **Texte d'aide** et la case **Obligatoire**. Les flèches **Monter** / **Descendre** changent l'ordre, **Retirer le champ** supprime un champ. Le menu **Ajouter un champ** propose les [attributs](help:attributes) pas encore utilisés (« Tous les attributs sont déjà dans le formulaire. » quand il n'en reste plus).

L'interrupteur **Case de consentement RGPD** ajoute une case à cocher : saisissez le **Texte du consentement** (obligatoire une fois activée) et décidez avec « Le consentement est obligatoire pour s'inscrire » si elle doit être cochée.

## Onglet Design

| Champ | Détail |
| --- | --- |
| Titre du formulaire, Description | Textes affichés au-dessus des champs |
| Texte du bouton | Obligatoire |
| Couleur principale, Couleur de fond, Couleur du texte, Couleur du texte du bouton | Codes hexadécimaux, par ex. \`#0F6CBD\` |
| Logo | Adresse (URL) d'une image déjà en ligne ; pas de téléversement |
| Arrondi des angles (px) | De 0 à 40 |
| Police, Disposition | Disposition **Verticale** ou **Horizontale** |

## Onglet Paramètres

- **Nom du formulaire** (obligatoire).
- **Listes de destination** : une ou plusieurs [listes](help:lists) qui recevront les inscrits. Obligatoire pour publier : « Sélectionnez au moins une liste avant de publier. »
- **Double opt-in** : « Le contact doit confirmer son inscription par e-mail avant d'être ajouté. » Deux réglages apparaissent : **E-mail de confirmation** (**Par défaut** ou l'un de vos [modèles](help:templates)) et **Redirection après confirmation** (facultatif, URL en \`http://\` ou \`https://\`). Placez dans le modèle choisi la balise \`{{ doubleoptin }}\` (ou \`{{ doi_link }}\`) à l'endroit du lien de confirmation ; sans elle, un lien est ajouté à la fin du message. Le contact n'entre dans les listes qu'après avoir cliqué ; au plus un e-mail de confirmation est envoyé par adresse toutes les 10 minutes.
- **Après l'inscription** : **Afficher un message de remerciement** (**Message de remerciement**, obligatoire) ou **Rediriger vers une URL** (URL complète, obligatoire). Avec la double confirmation, le message de remerciement et la redirection ci-dessus sont **remplacés** par « Consultez votre boîte de réception pour confirmer votre inscription. » (un encadré le rappelle dans l'onglet) : seule la **Redirection après confirmation** s'applique, une fois le lien cliqué.
- **Activer le captcha** : ajoute une vérification anti-robot au formulaire public. Le fournisseur (hCaptcha ou Cloudflare Turnstile) et ses clés se règlent une fois pour tous les formulaires, par un administrateur, avec le bouton **Captcha des formulaires** de la page [Formulaires](help:forms) ; tant qu'aucun fournisseur n'est configuré, la case n'a aucun effet.
- **Mettre à jour les contacts existants** : désactivé, un contact déjà connu ne voit pas ses attributs écrasés par le formulaire (avec la double confirmation, seules ses valeurs vides sont complétées).
- **Prévenir ces adresses à chaque inscription** (facultatif, une adresse par ligne, 10 au plus) : chacune reçoit un court e-mail à chaque nouvelle inscription : nom du formulaire, valeurs saisies, état du contact (**Contact créé**, **Contact mis à jour** ou **En attente de confirmation**) et lien vers la fiche du contact. Avec la double confirmation, l'e-mail part dès l'inscription et indique que le contact attend encore sa confirmation. L'envoi se fait en arrière-plan et n'empêche jamais une inscription d'aboutir ; les tentatives bloquées (liste de blocage, anti-spam) ne déclenchent rien. Suivez aussi les inscriptions dans les compteurs et dans [Contacts](help:recipients).

## Onglet Partager

Il propose le **Lien de partage rapide** (de la forme \`/f/…\`, avec **Copier** et **Ouvrir**), le **Code iframe** et le **Code HTML intégrable** à coller dans votre site. Tant que le formulaire n'est pas publié, la page affiche « Publiez le formulaire pour que ce lien et ces codes fonctionnent. » ; après **Désactiver**, le lien public ne répond plus.

## En cas de problème

- **« obligatoire »** sous un champ : renseignez le **Libellé**, le **Texte du bouton**, le **Texte du consentement**, le **Message de remerciement** ou l'URL de redirection selon l'onglet signalé.
- **« Saisissez une URL complète commençant par http:// ou https://. »** : corrigez la redirection (après l'inscription ou après la confirmation).
- **« Une ou plusieurs adresses e-mail sont invalides. »** : vérifiez la liste des adresses à prévenir.
- **Les inscrits n'apparaissent pas dans la liste** : avec la double confirmation, ils n'y entrent qu'après avoir cliqué sur le lien reçu ; comparez **Inscriptions** et **Confirmées**.
`;export{e as default};
