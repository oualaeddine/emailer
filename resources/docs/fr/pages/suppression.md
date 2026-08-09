# Liste de suppression

La **liste de suppression** rassemble toutes les adresses e-mail auxquelles l'application n'enverra plus jamais rien. C'est le garde-fou de votre réputation d'expéditeur : chaque envoi, campagne ou message individuel, est contrôlé contre cette liste avant de partir.

![Liste de suppression](/docs/screenshots/suppression/01-suppression.png)

## Ce que garantit cette liste

Une adresse présente ici est écartée à coup sûr, quelle que soit la liste ou le segment qui la contient encore. Aucune règle de ciblage ne peut la réintroduire : c'est une sécurité non contournable. Le contact correspondant passe automatiquement en statut « Supprimé » dans les [Destinataires](help:recipients).

## Rechercher et filtrer

Deux contrôles au-dessus du tableau :

- le champ **Rechercher par adresse e-mail…**, qui filtre au fur et à mesure de la frappe ;
- la liste déroulante des motifs, réglée par défaut sur **Tous les motifs**.

Pour vérifier si `contact@transport-oran.dz` est bloquée, tapez simplement l'adresse ou même seulement `transport-oran`.

## Les motifs

| Motif | Origine | Peut-on raisonnablement retirer ? |
| --- | --- | --- |
| Rebond définitif | Le serveur du destinataire a déclaré l'adresse inexistante. | Non, sauf certitude absolue |
| Plainte pour spam | Le destinataire a signalé votre message comme indésirable. | Non |
| Désabonnement manuel | Le destinataire a cliqué sur le lien de désabonnement. | Non, c'est sa demande |
| Désabonnement global (tout) | Le destinataire refuse tout courrier de votre organisation. | Non |
| Adresse invalide | Une vérification a jugé l'adresse non délivrable. | Oui, si l'adresse a été corrigée depuis |
| Blocage manuel | Ajout volontaire par un membre de votre équipe. | Oui |

Un point important : un **rebond temporaire** (boîte pleine, serveur momentanément indisponible) n'entraîne jamais de suppression. Le message est simplement réessayé plus tard. Seuls les rebonds définitifs bloquent l'adresse.

## Le tableau

| Colonne | Contenu |
| --- | --- |
| Adresse e-mail | L'adresse bloquée. |
| Motif | L'étiquette colorée correspondant au tableau ci-dessus. |
| Date d'ajout | Le jour où le blocage a été enregistré. |
| Actions | Le bouton **Retirer**. |

## Ajouter une adresse

Le bouton **Ajouter une adresse** ouvre une fenêtre de saisie décrite dans [Ajouter une adresse à la liste de suppression](help:dialog-suppression-entry-form). Cas typique : un dirigeant de « Menuiserie Bejaia » vous demande par téléphone de ne plus le contacter — bloquez son adresse immédiatement, avant même la prochaine campagne.

![Fenêtre d'ajout à la liste de suppression](/docs/screenshots/suppression/02-dialog.png)

## Retirer une adresse

Le bouton **Retirer** ouvre une confirmation. Si le motif est un rebond définitif ou une plainte pour spam, le texte est plus insistant et rappelle le risque important pour la délivrabilité : réenvoyer vers une adresse qui a rebondi ou qui vous a signalé dégrade la réputation de tout votre domaine, donc la distribution de toutes vos campagnes.

Ne retirez une adresse que si vous savez précisément pourquoi elle a été bloquée et ce qui a changé depuis — typiquement une adresse mal saisie lors d'un import, corrigée entre-temps.

## En cas de problème

- **« Aucune adresse dans la liste de suppression. »** : soit la liste est réellement vide, soit votre recherche ou votre filtre de motif est trop restrictif. Repassez sur **Tous les motifs** et videz la recherche.
- **« Cette adresse est déjà présente dans la liste de suppression. »** : le blocage existe déjà, rien de plus à faire.
- **Une campagne touche moins de contacts que prévu** : c'est normal, les adresses bloquées ont été écartées. Comparez avec les chiffres des [Rapports](help:reporting).
- **Une adresse revient après un import** : le contact est bien recréé, mais reste en statut « Supprimé » tant qu'il figure ici. L'import ne débloque jamais une adresse.

> Voir aussi le guide [Analyser les résultats d'une campagne](help:flow-analyze-results), qui explique quoi faire des rebonds constatés après un envoi.
