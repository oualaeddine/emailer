const e=`# Nouvel e-mail (rédacteur)

La page **Nouvel e-mail** est l'atelier de rédaction de PageJaunes Mailer. C'est là que vous écrivez l'objet et le contenu d'un message, que vous y ajoutez votre signature et que vous vérifiez son rendu sur ordinateur, tablette et téléphone avant qu'il ne parte. Votre travail est enregistré tout seul, en continu, sous forme de brouillon.

![Rédacteur d'e-mail](/docs/screenshots/composer/01-composer.png)

## Écrire l'objet

Le champ **Objet** est la première chose que verra le destinataire dans sa liste de messages. Restez court et concret :

- Bon : \`Menuiserie Bejaia — nouveau catalogue portes coupe-feu 2026\`
- À éviter : \`PROMOTION EXCEPTIONNELLE !!! OFFRE LIMITÉE\`

Les objets tout en majuscules et couverts de points d'exclamation sont les premiers à finir dans les indésirables.

## Rédiger le contenu

Deux onglets sont proposés au-dessus de la zone de rédaction :

| Onglet | Pour qui | Ce que vous y faites |
| --- | --- | --- |
| **Texte enrichi** | Tout le monde | Vous tapez votre texte et vous le mettez en forme avec la barre d'outils |
| **Source HTML** | Utilisateur averti | Vous collez ou modifiez directement le code HTML du message |

Les deux onglets travaillent sur le **même** contenu : passer de l'un à l'autre ne perd rien. La barre d'outils du mode Texte enrichi propose : **Gras**, **Italique**, **Souligné**, **Liste à puces**, **Liste numérotée** et **Insérer un lien**. Pour un lien, sélectionnez d'abord le texte à rendre cliquable, cliquez sur l'icône de lien, puis saisissez l'adresse (par exemple \`https://menuiserie-bejaia.dz/catalogue\`) dans la petite fenêtre « URL du lien : ».

Si vous collez un contenu depuis Word, contrôlez toujours le résultat dans l'aperçu : Word ajoute une mise en forme invisible qui s'affiche mal dans certaines messageries.

## Choisir la signature

La liste déroulante **Signature** ajoute automatiquement votre bloc de coordonnées à la fin du message. Votre signature par défaut est présélectionnée à l'ouverture de la page. Choisissez **Aucune** pour un message sans signature.

La signature n'est pas visible dans la zone de rédaction : elle apparaît uniquement dans l'aperçu, exactement à l'endroit où le destinataire la verra.

## Vérifier l'aperçu

Sous l'éditeur, la section **Aperçu** affiche le message tel qu'il sera reçu, signature comprise. Trois boutons changent la largeur de la fenêtre simulée :

| Bouton | Largeur simulée | Ce qu'il faut vérifier |
| --- | --- | --- |
| Bureau | 640 px | La mise en page générale, les images |
| Tablette | 480 px | Les tableaux et les colonnes |
| Mobile | 375 px | La lisibilité du texte, les liens assez grands pour le doigt |

En Algérie, une grande partie des professionnels ouvrent leurs e-mails sur téléphone : le contrôle en mode **Mobile** n'est pas optionnel.

## Enregistrement automatique et versions

Trois secondes après votre dernière frappe, l'application enregistre le brouillon toute seule. Un petit texte l'indique en haut à droite : **Enregistrement…** pendant l'opération, puis **Enregistré à** suivi de l'heure. Vous pouvez donc fermer l'onglet sans perdre votre travail ; le brouillon se retrouve dans le dossier **Brouillons** de la [Boîte de réception](help:mailbox).

À côté, deux boutons gèrent l'historique :

- **Enregistrer une version** : fige l'état actuel du message comme point de repère. À faire avant toute réécriture importante.
- **Historique des versions** : ouvre un panneau latéral listant les versions (\`v1\`, \`v2\`, …) avec leur auteur et leur date. Le bouton **Restaurer** ramène le message à cette version.

Exemple : vous rédigez une relance pour Transport Oran SARL, vous cliquez sur **Enregistrer une version**, puis vous tentez une formulation plus directe. Si elle ne convient pas, **Restaurer** sur \`v1\` remet le texte d'origine.

## En cas de problème

- **« Enregistré à » ne s'affiche pas** : l'enregistrement démarre trois secondes après la dernière frappe. Attendez un instant sans taper ; si rien ne vient, vérifiez votre connexion avant de fermer l'onglet.
- **L'historique des versions est vide** : tant qu'aucune version n'a été enregistrée pour ce brouillon, la liste reste vide. Cliquez d'abord sur **Enregistrer une version**.
- **La mise en forme collée depuis Word est cassée** : repassez en **Source HTML** pour retirer le code superflu, ou retapez le paragraphe en Texte enrichi.
- **L'aperçu semble décalé sur Mobile** : évitez les tableaux à plusieurs colonnes et les images larges à taille fixe ; partez plutôt d'un [modèle](help:templates) déjà éprouvé.

> Pour l'enchaînement complet, du modèle jusqu'au suivi de l'envoi, voir [Rédiger et envoyer un e-mail](help:flow-compose-send-email).
`;export{e as default};
