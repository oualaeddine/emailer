# Journal d'audit

Le **Journal d'audit** conserve la trace de toutes les actions sensibles réalisées dans l'application : connexions, création et modification de comptes SMTP, changements de rôle, envois de campagnes, imports, exports et modifications de paramètres. Il sert à répondre à la question « qui a fait quoi, quand, et depuis quelle machine ». Le journal est en écriture seule : personne, pas même un administrateur, ne peut modifier ni effacer une entrée.

![Journal d'audit](/docs/screenshots/audit-log/01-audit-log.png)

## Lire le tableau

| Colonne | Contenu | Exemple |
| --- | --- | --- |
| Date | Date et heure de l'action | `12/03/2026 09:41:07` |
| Utilisateur | Auteur de l'action, ou **Système** pour une action automatique | `Nadia Cherif` |
| Action | Code de l'événement | `smtp_account.updated` |
| Cible | Type d'objet concerné et son numéro | `SmtpAccount #3` |
| Actions | L'œil **Voir les détails** | — |

La mention **Système** désigne une action déclenchée par l'application elle-même sans intervention humaine, par exemple l'ajout automatique d'une adresse à la [liste de suppression](help:suppression) après un rebond définitif.

## Filtrer

La barre de filtres au-dessus du tableau permet de restreindre l'affichage. Renseignez un ou plusieurs champs puis cliquez sur **Confirmer**.

| Filtre | Usage | Exemple |
| --- | --- | --- |
| ID utilisateur | Numéro interne de l'utilisateur dont vous suivez l'activité | `4` |
| Action | Code d'événement, même partiel | `auth.login_failed` |
| Type d'entité | Type d'objet concerné | `SmtpAccount`, `User`, `Campaign` |
| Date de début | Ne montre rien avant cette date | `01/03/2026` |
| Date de fin | Ne montre rien après cette date | `31/03/2026` |

**Réinitialiser les filtres** vide tous les champs et réaffiche l'ensemble du journal.

Le tableau se charge par tranches. Le bouton **Charger plus**, en bas, ajoute les entrées suivantes à la suite de celles déjà affichées.

## Consulter le détail d'un événement

L'icône en forme d'œil ouvre la fenêtre **Détails de l'événement**.

![Détail d'un événement](/docs/screenshots/audit-log/02-detail.png)

Elle affiche en en-tête l'action, la cible, l'auteur, l'adresse IP et l'horodatage, puis deux colonnes :

- **Anciennes valeurs** — l'état des champs avant l'action.
- **Nouvelles valeurs** — l'état après.

Seuls les champs réellement modifiés apparaissent, ce qui rend la comparaison lisible. Si l'action n'a pas de contenu comparable — une connexion, par exemple — la mention **Aucune valeur enregistrée** s'affiche.

Certaines valeurs sont volontairement masquées et remplacées par `[REDACTED]` : le mot de passe d'un compte SMTP, tout paramètre marqué comme secret. Les mots de passe des utilisateurs ne sont jamais consignés, même sous forme masquée ; seul l'événement de changement l'est.

## Exporter

Le bouton **Exporter**, en haut à droite, produit un fichier CSV. L'export reprend exactement les filtres actuellement appliqués : filtrez d'abord, exportez ensuite. Utile pour transmettre une période précise à un auditeur externe.

L'export est lui-même une action auditée (`export.audit_log`) : votre propre téléchargement apparaîtra dans le journal.

## Ce qui est enregistré

| Catégorie | Exemples d'événements |
| --- | --- |
| Authentification | Connexion réussie, connexion refusée, déconnexion, changement de mot de passe |
| Utilisateurs et rôles | Création d'un utilisateur, changement de rôle, désactivation |
| Comptes SMTP | Création, modification, test, désactivation, suppression |
| Campagnes | Création, envoi, pause, reprise, annulation, approbation |
| Modèles | Création, modification, archivage, suppression |
| Imports et exports | Import démarré, validé, échoué ; export de destinataires ou de rapports |
| Liste de suppression | Ajout manuel, retrait |
| Paramètres | Modification d'un paramètre, clé par clé |

Le suivi fin de chaque message envoyé — ouvertures, clics, distributions — ne figure pas ici : ce volume relève des [Rapports](help:reporting) et de la [Boîte de réception](help:mailbox).

## Accès et conservation

Le journal est réservé au rôle **Administrateur** : les autres rôles ne voient pas la rubrique dans le menu. Les entrées sont conservées indéfiniment, aucune purge automatique ne les supprime.

## En cas de problème

- **Aucun résultat** : les filtres sont trop restrictifs. Vérifiez notamment que l'intervalle de dates couvre bien la période cherchée, puis cliquez sur **Réinitialiser les filtres**.
- **Je ne trouve pas l'auteur d'une modification** : l'entrée indique peut-être **Système**, signe d'une action automatique et non d'un oubli d'enregistrement.
- **Une valeur affiche `[REDACTED]`** : c'est volontaire et définitif. Le journal atteste qu'un mot de passe a changé, jamais sa valeur.
- **Un collègue ne voit pas cette page** : seul le rôle Administrateur y a accès. Voir [Utilisateurs](help:admin-users).
