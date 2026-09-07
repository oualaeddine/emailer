const e=`# Rédiger et envoyer un e-mail

Ce guide suit un cas réel de bout en bout : Menuiserie Bejaia veut présenter sa nouvelle gamme de portes coupe-feu à ses clients transporteurs et entreprises de bâtiment. Vous partirez d'un modèle réutilisable, vous rédigerez le message, vous contrôlerez son rendu, puis vous suivrez son acheminement jusqu'à la boîte du destinataire.

## Étape 1 — Vérifier qu'un compte d'envoi est prêt

![Comptes SMTP](/docs/screenshots/smtp/01-smtp.png)

Aucun e-mail ne peut partir sans serveur d'envoi. Ouvrez la page [Comptes SMTP](help:smtp) et contrôlez deux colonnes :

- **État** doit afficher **Sain**. Une valeur **Dégradé** ou **Défaillant** annonce des échecs d'envoi.
- **Quota quotidien** doit laisser de la marge pour la diffusion prévue.

Le bouton **Tester** confirme en direct que la connexion fonctionne. Si aucun compte n'existe encore, demandez à un administrateur de le créer via [Créer un compte SMTP](help:dialog-smtp-account-form).

## Étape 2 — Préparer ou choisir un modèle

![Bibliothèque de modèles](/docs/screenshots/templates/01-templates.png)

Rendez-vous sur la page [Modèles](help:templates). Si un modèle « Offre commerciale — menuiserie » existe déjà, réutilisez-le. Sinon, cliquez sur **Nouveau modèle**.

Dans la fenêtre [Créer un modèle](help:dialog-template-form), renseignez :

| Champ | Valeur de l'exemple |
| --- | --- |
| Nom | \`Offre commerciale — menuiserie\` |
| Catégorie | \`Prospection\` |
| Contenu | En-tête, présentation de la gamme, coordonnées |

Cliquez sur **Enregistrer**. La carte apparaît aussitôt dans la bibliothèque.

## Étape 3 — Ouvrir le rédacteur

![Rédacteur d'e-mail](/docs/screenshots/composer/01-composer.png)

Depuis la [Boîte de réception](help:mailbox), cliquez sur **Nouvel e-mail** en haut à droite. Le rédacteur s'ouvre sur un brouillon vierge.

Remplissez d'abord l'**Objet**. Il détermine à lui seul une grande part du taux d'ouverture :

- \`Menuiserie Bejaia — nouvelle gamme portes coupe-feu, livraison 15 jours\`

Évitez les majuscules intégrales et les points d'exclamation en série, qui déclenchent les filtres anti-spam.

## Étape 4 — Rédiger le contenu

Restez sur l'onglet **Texte enrichi** et composez votre message avec la barre d'outils (**Gras**, **Italique**, **Souligné**, **Liste à puces**, **Liste numérotée**, **Insérer un lien**). Si vous partez d'un modèle, collez son contenu et adaptez les passages variables.

Pour un lien vers votre catalogue, sélectionnez le texte « consulter le catalogue », cliquez sur l'icône de lien et saisissez \`https://menuiserie-bejaia.dz/catalogue\`.

L'onglet **Source HTML** permet de travailler directement dans le code : les deux onglets modifient le même contenu, vous pouvez passer de l'un à l'autre sans rien perdre.

## Étape 5 — Ajouter la signature

Dans la liste **Signature**, choisissez votre bloc de coordonnées ; il est présélectionné s'il est défini par défaut. Sélectionnez **Aucune** pour un message sans signature. La signature n'apparaît pas dans la zone de rédaction, mais bien dans l'aperçu.

## Étape 6 — Contrôler l'aperçu, surtout en mobile

Sous l'éditeur, la section **Aperçu** montre le message tel qu'il sera reçu. Cliquez successivement sur les trois boutons :

1. **Bureau** (640 px) — vérifiez la mise en page générale.
2. **Tablette** (480 px) — vérifiez les tableaux et les colonnes.
3. **Mobile** (375 px) — vérifiez la lisibilité et la taille des liens.

Une bonne partie de vos interlocuteurs professionnels liront le message sur téléphone : ne sautez jamais cette dernière vérification.

## Étape 7 — Sécuriser votre travail avec les versions

Le brouillon s'enregistre automatiquement trois secondes après votre dernière frappe : la mention **Enregistrement…** puis **Enregistré à** suivi de l'heure apparaît en haut à droite.

Avant toute réécriture importante, cliquez sur **Enregistrer une version** pour figer un point de repère. Le bouton **Historique des versions** ouvre le panneau latéral listant \`v1\`, \`v2\`, … avec leur auteur et leur date ; **Restaurer** ramène le message à la version choisie.

## Étape 8 — Retrouver le brouillon et déclencher la diffusion

![Boîte de réception](/docs/screenshots/mailbox/01-mailbox.png)

Votre brouillon est désormais dans le dossier **Brouillons** de la [Boîte de réception](help:mailbox). Cliquer dessus le rouvre exactement là où vous l'aviez laissé.

Pour l'adresser à une liste de destinataires, passez par une campagne : l'assistant vous fait choisir le contenu, l'audience et le moment de l'envoi. Le pas-à-pas correspondant est [Créer et envoyer une campagne](help:flow-create-send-campaign) ; les destinataires se préparent au préalable dans [Destinataires](help:recipients), par [import de fichier](help:flow-import-recipients-csv) ou depuis l'[Annuaire PageJaunes](help:flow-source-pagejaunes).

## Étape 9 — Suivre l'acheminement

Toujours dans la [Boîte de réception](help:mailbox), les dossiers racontent la suite du parcours :

| Dossier | Ce que cela signifie |
| --- | --- |
| **Planifiés** | L'envoi est programmé pour plus tard |
| **Boîte d'envoi** | Le message part maintenant ou attend son tour |
| **Envoyés** | Le message est parti |

Sélectionnez un message pour afficher sa chronologie : **Mis en file d'attente**, **Envoyé**, **Distribué**, **Ouvert**, **Cliqué**, **Rejeté**, **Échoué**, ainsi que le **Compte SMTP** utilisé et la **Dernière réponse du serveur**.

Tant que le message est dans la **Boîte d'envoi**, deux boutons restent disponibles : **Relancer maintenant** et **Annuler**. Une fois dans **Envoyés**, l'envoi est définitif.

## Étape 10 — Tirer les conclusions

Le lendemain, le [Tableau de bord](help:dashboard) affiche le **Volume d'envoi**, le **Taux de délivrabilité** et la consommation des quotas SMTP. Pour une analyse détaillée, ouvrez les [Rapports](help:reporting) ou suivez le guide [Analyser les résultats](help:flow-analyze-results).

Les adresses en **Rebond définitif** ou ayant émis un **Signalement spam** basculent dans la [liste de suppression](help:suppression) : ne les recontactez pas, c'est ce qui protège la réputation de votre domaine.

## En cas de problème

- **Rien ne part** : contrôlez d'abord l'**État** et le quota du compte SMTP sur le [Tableau de bord](help:dashboard) ; un quota atteint met les messages en attente jusqu'au lendemain.
- **Le brouillon semble perdu** : il est dans **Brouillons**, enregistré automatiquement, même si vous avez fermé l'onglet brutalement.
- **Le message s'affiche mal chez le destinataire** : reprenez l'aperçu **Mobile** et simplifiez la mise en page ; les tableaux complexes et les images larges sont les causes les plus fréquentes.
- **Beaucoup de rebonds définitifs** : la base est obsolète. Nettoyez-la depuis [Destinataires](help:recipients) avant l'envoi suivant.
`;export{e as default};
