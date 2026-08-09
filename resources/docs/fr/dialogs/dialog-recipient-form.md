# Créer un destinataire

Cette fenêtre ajoute un contact unique à votre carnet d'adresses. Elle est faite pour la saisie ponctuelle : le prospect rencontré sur un salon, le client qui vous laisse sa carte de visite. Pour plusieurs dizaines de contacts, utilisez plutôt l'[import de fichier](help:recipients-import).

![Fenêtre de création d'un destinataire](/docs/screenshots/recipients/02-dialog.png)

## Les champs

| Champ | Obligatoire | Exemple | Remarque |
| --- | --- | --- | --- |
| Adresse e-mail | Oui | `contact@menuiserie-bejaia.dz` | Doit être une adresse valide et unique dans la base. |
| Prénom | Non | `Karim` | Utilisable comme variable de personnalisation dans vos modèles. |
| Nom | Non | `Belkacem` | — |
| Entreprise | Non | `Menuiserie Bejaia` | Facilite la recherche et le regroupement par société. |
| Source | Non | `Manuel` | Trace l'origine du contact. |

Même si un seul champ est obligatoire, renseignez au minimum l'entreprise : sans elle, une liste de plusieurs centaines d'adresses `contact@…` devient très vite illisible.

## Le champ Source

Deux valeurs seulement sont proposées ici :

| Valeur | Quand la choisir |
| --- | --- |
| Manuel | Contact externe saisi à la main — le cas le plus courant. |
| Contact interne | Collaborateur ou service de votre propre organisation. |

Les autres sources (Import CSV, Import Excel, PageJaunes) n'apparaissent pas dans cette liste : elles sont posées automatiquement par les processus d'import et ne se choisissent jamais à la main.

## Enregistrer

**Enregistrer** crée le destinataire et referme la fenêtre ; il apparaît aussitôt dans la liste des [Destinataires](help:recipients) avec le statut Actif. **Annuler** ferme sans rien créer.

## En cas de problème

- **« Un destinataire avec cette adresse e-mail existe déjà »** : le message s'affiche sous le champ e-mail. Fermez la fenêtre et recherchez l'adresse dans la liste, la fiche existe déjà.
- **Un message d'erreur rouge apparaît sous l'adresse** : le format est refusé. Vérifiez qu'il n'y a ni espace ni accent, et que le domaine est complet (`contact@transport-oran.dz` et non `contact@transport-oran`).
- **Le bouton Enregistrer ne réagit pas** : l'enregistrement est en cours ; le bouton se réactive dès la réponse du serveur.
- **Le contact créé est immédiatement en statut Supprimé** : l'adresse figure déjà dans la [liste de suppression](help:suppression). Elle ne recevra rien tant qu'elle n'en est pas retirée.
