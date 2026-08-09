# Destinataires

La page **Destinataires** est le carnet d'adresses de l'application : toutes les entreprises et tous les contacts à qui vous pouvez écrire, quelle que soit leur provenance (saisie manuelle, import de fichier ou annuaire PageJaunes). C'est ici que vous cherchez un contact, que vous vérifiez son statut et que vous en ajoutez un nouveau.

![Liste des destinataires](/docs/screenshots/recipients/01-recipients.png)

## Rechercher un contact

Le champ de recherche, juste sous le titre, porte le libellé « Rechercher par nom, e-mail ou entreprise… ». La liste se met à jour au fur et à mesure de la frappe : inutile de valider.

Quelques exemples :

- `bejaia` retrouve « Menuiserie Bejaia » et tout contact dont le nom contient ce mot ;
- `transport-oran.dz` retrouve toutes les adresses de ce domaine ;
- `Cherif` retrouve le contact par son nom de famille.

Videz le champ pour revenir à la liste complète.

## Lire le tableau

| Colonne | Contenu |
| --- | --- |
| Prénom / Nom | Les deux champs assemblés, ou `—` si aucun n'est renseigné. |
| Adresse e-mail | L'adresse utilisée pour l'envoi, par exemple `contact@transport-oran.dz`. |
| Entreprise | La raison sociale, par exemple « Transport Oran SARL ». |
| Statut | Actif, Supprimé ou Invalide (voir ci-dessous). |
| Étiquettes | Les étiquettes associées, séparées par des virgules, ou `—`. |

## Les statuts

| Statut | Signification | Reçoit-il vos envois ? |
| --- | --- | --- |
| Actif | Contact utilisable normalement. | Oui |
| Supprimé | L'adresse figure dans la liste de suppression (rebond définitif, plainte, désabonnement…). | Non, jamais |
| Invalide | L'adresse a été jugée non délivrable lors d'une vérification. | Non |

Un contact « Supprimé » reste visible dans la liste : il n'est pas effacé, il est simplement écarté de tous les envois. Le motif exact se consulte dans la [liste de suppression](help:suppression).

## Ajouter un destinataire

Le bouton **Nouveau destinataire**, en haut à droite, ouvre une fenêtre de saisie. Utilisez-la pour un ajout ponctuel — le contact rencontré sur un salon, le client qui vous laisse sa carte. Le détail des champs est décrit dans [Créer un destinataire](help:dialog-recipient-form).

![Fenêtre de création d'un destinataire](/docs/screenshots/recipients/02-dialog.png)

Pour ajouter plusieurs dizaines ou centaines de contacts d'un coup, passez plutôt par [Importer (CSV/Excel)](help:recipients-import).

## D'où viennent les contacts

Chaque destinataire conserve la trace de son origine :

| Source | Comment elle est créée |
| --- | --- |
| Manuel | Saisie dans la fenêtre **Nouveau destinataire**. |
| Contact interne | Contact de votre organisation, saisi manuellement lui aussi. |
| Import CSV / Import Excel | Ajouté par l'assistant d'import de fichier. |
| PageJaunes | Issu d'une recherche dans l'[annuaire PageJaunes](help:pagejaunes-search). |

Les sources d'import ne se choisissent pas à la main : elles sont posées automatiquement par le processus qui a créé le contact.

## En cas de problème

- **« Aucun destinataire trouvé »** : votre recherche ne correspond à rien. Effacez le champ pour vérifier que la base n'est pas simplement vide, puis importez un fichier.
- **« Un destinataire avec cette adresse e-mail existe déjà »** : l'adresse est déjà présente. Recherchez-la pour retrouver la fiche existante plutôt que d'en créer une seconde.
- **Un contact est en statut Supprimé et vous voulez le réactiver** : c'est possible uniquement en retirant l'adresse de la [liste de suppression](help:suppression), et seulement si vous savez pourquoi elle y figure.
- **Une entreprise apparaît en double avec deux adresses différentes** : ce sont bien deux contacts distincts. Conservez celui qui répond et bloquez l'autre si ses messages rebondissent.

> Guides complets : [Importer des destinataires depuis un fichier CSV/Excel](help:flow-import-recipients-csv) et [Constituer une base depuis PageJaunes](help:flow-source-pagejaunes).
