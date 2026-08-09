# Comptes SMTP

La page **Comptes SMTP** regroupe les serveurs d'envoi utilisés par l'application. Chaque e-mail envoyé depuis le [Composeur](help:composer) ou par une [campagne](help:campaigns) sort par l'un de ces comptes. C'est ici que l'administrateur déclare les serveurs, contrôle leur état de santé et fixe le nombre maximal de messages autorisés par jour.

![Liste des comptes SMTP](/docs/screenshots/smtp/01-smtp.png)

## Ce que contient la liste

Le tableau affiche une ligne par compte configuré.

| Colonne | Contenu | Exemple |
| --- | --- | --- |
| Nom | Le libellé que vous avez donné au compte | `Envoi principal PageJaunes` |
| Hôte | Le serveur et le port, séparés par deux-points | `smtp.pagejaunes.dz:587` |
| État | Pastille de santé calculée automatiquement | Sain, Dégradé, Défaillant, Désactivé |
| Quota quotidien | Nombre maximal de messages par jour, ou `—` si aucun quota | `2000` |
| Actions | Le bouton **Tester** | — |

## Comprendre l'état de santé

L'application sonde régulièrement chaque compte et combine le résultat du dernier test de connexion avec les taux de rebond et de plainte observés récemment. Vous ne modifiez pas cet état à la main : il reflète le comportement réel du serveur.

| État | Signification | Conséquence sur les envois |
| --- | --- | --- |
| **Sain** | Connexion correcte, taux de rebond et de plainte normaux | Le compte est utilisé normalement |
| **Dégradé** | Le taux de rebond ou de plainte dépasse le seuil d'alerte | Le compte reste utilisable, mais il faut surveiller la qualité des adresses |
| **Défaillant** | Connexion impossible ou taux de rebond très élevé | Le compte est écarté des envois ; les messages partent par les autres comptes |
| **Désactivé** | Le compte a été mis hors service par un administrateur | Aucun envoi ne passe par ce compte |

Un passage en Dégradé ou Défaillant déclenche une notification aux administrateurs. Si tous vos comptes sont Défaillants, plus aucun message ne peut partir : les envois restent en attente jusqu'à ce qu'un compte redevienne opérationnel.

## Ajouter un compte

Cliquez sur **Nouveau compte SMTP** en haut à droite. La fenêtre de saisie s'ouvre ; les champs sont détaillés dans [Fenêtre — Compte SMTP](help:dialog-smtp-account-form).

![Fenêtre de création d'un compte SMTP](/docs/screenshots/smtp/02-dialog.png)

Après **Enregistrer**, le compte apparaît immédiatement dans la liste. Testez-le avant de lancer une campagne.

## Tester un compte

Le bouton **Tester** de la colonne Actions ouvre une connexion réelle vers le serveur : l'application se connecte à l'hôte et au port indiqués, négocie le chiffrement demandé puis présente le nom d'utilisateur et le mot de passe.

- Une coche verte signifie que le serveur a accepté la connexion et l'authentification.
- Une croix rouge signifie un échec ; la réponse brute renvoyée par le serveur est conservée pour le diagnostic.

Le test n'envoie pas de message commercial et ne consomme pas votre quota quotidien. Testez systématiquement après avoir changé un mot de passe ou une adresse de serveur.

## À propos du quota quotidien

Le quota quotidien est le garde-fou qui empêche votre fournisseur de bloquer votre domaine pour cause d'envoi massif. Il se remet à zéro chaque jour.

- Un quota trop bas fait s'étaler une campagne sur plusieurs jours : les messages restants sont mis en file d'attente et repris le lendemain.
- Un quota vide (`—`) signifie « illimité » du côté de l'application. Le fournisseur, lui, applique toujours ses propres limites : un blocage côté fournisseur fera basculer le compte en Défaillant.
- Alignez toujours le quota sur ce que votre hébergeur autorise réellement. Pour un compte mutualisé algérien, une valeur de 500 à 2 000 messages par jour est courante.

## Qui peut faire quoi

Seul un **Administrateur** peut créer un compte, modifier des identifiants, changer un quota ou lancer un test. Les rôles Responsable marketing et Opérateur marketing voient l'état de santé en lecture seule, ce qui leur permet de comprendre la capacité d'envoi disponible sans toucher aux réglages. Le rôle Lecteur ne voit pas cette page. Voir [Utilisateurs](help:admin-users).

Toute création ou modification de compte SMTP est enregistrée dans le [Journal d'audit](help:audit-log). Les mots de passe n'y figurent jamais : ils sont remplacés par `[REDACTED]`.

## En cas de problème

- **Le test échoue avec une erreur d'authentification** : le nom d'utilisateur ou le mot de passe est incorrect. Chez la plupart des fournisseurs, le nom d'utilisateur est l'adresse complète (`envoi@pagejaunes.dz`) et non la partie avant l'arobase. Si le compte est protégé par une double authentification, il faut créer un mot de passe d'application dédié.
- **Le test échoue sans réponse du serveur** : le port ou le chiffrement ne correspond pas. Essayez `587` avec `tls`, ou `465` avec `ssl`. Un pare-feu sortant côté hébergement peut aussi bloquer ces ports.
- **Le compte est passé en Dégradé après une campagne** : trop d'adresses invalides. Nettoyez vos listes et vérifiez que les rebonds alimentent bien la [Liste de suppression](help:suppression).
- **Une campagne semble figée** : vérifiez que le quota quotidien n'est pas atteint. Les messages ne sont pas perdus, ils reprennent au prochain cycle quotidien. Le détail se lit dans les [Rapports](help:reporting).
