# Fenêtre — Créer un compte SMTP

Cette fenêtre déclare un serveur d'envoi. Les informations demandées sont exactement celles que votre hébergeur ou votre fournisseur de messagerie vous a communiquées. Tant que vous n'avez pas cliqué sur **Enregistrer**, rien n'est créé.

![Fenêtre de création d'un compte SMTP](/docs/screenshots/smtp/02-dialog.png)

## Les champs

| Champ | Obligatoire | À quoi ça sert | Exemple |
| --- | --- | --- | --- |
| Nom | Oui | Libellé interne, visible uniquement par votre équipe dans la liste | `Envoi principal PageJaunes` |
| Fournisseur | Non | Nom du service utilisé ; sert au classement et aux valeurs suggérées | `custom`, `sendgrid`, `mailgun` |
| Hôte | Oui | Adresse du serveur SMTP | `smtp.pagejaunes.dz` |
| Port | Oui | Port de connexion, prérempli à `587` | `587` |
| Chiffrement | Non | `none`, `ssl` ou `tls` ; préréglé sur `tls` | `tls` |
| Nom d'utilisateur | Oui | Identifiant de connexion au serveur | `envoi@pagejaunes.dz` |
| Mot de passe | Oui | Mot de passe du compte d'envoi | — |
| Adresse d'expédition | Oui | Adresse qui apparaîtra dans le champ « De » des messages | `contact@pagejaunes.dz` |
| Quota quotidien | Non | Nombre maximal de messages par jour ; laisser vide = illimité côté application | `2000` |

## Choisir le port et le chiffrement

Ces deux champs vont ensemble et une combinaison erronée est la cause la plus fréquente d'échec.

| Port | Chiffrement | Usage |
| --- | --- | --- |
| `587` | `tls` | Combinaison recommandée, valable chez la quasi-totalité des fournisseurs |
| `465` | `ssl` | Ancienne norme, encore proposée par certains hébergeurs algériens |
| `25` | `none` | À éviter : souvent bloqué et non chiffré |

## Adresse d'expédition et délivrabilité

L'adresse d'expédition doit appartenir au même domaine que le compte d'authentification. Déclarer `contact@pagejaunes.dz` alors que le serveur authentifie `envoi@autre-domaine.dz` fera classer vos messages en indésirables, voire refuser l'envoi. Si votre entreprise utilise plusieurs domaines, créez un compte SMTP par domaine.

## Sécurité du mot de passe

Le mot de passe est enregistré chiffré et n'est jamais réaffiché après l'enregistrement : pour le changer, il faut le ressaisir en entier. Il n'apparaît pas non plus dans le [Journal d'audit](help:audit-log), où il est remplacé par `[REDACTED]`.

## Après l'enregistrement

Le compte apparaît immédiatement dans la page [Comptes SMTP](help:smtp) avec l'état de santé initial. Lancez tout de suite le bouton **Tester** de sa ligne : c'est le seul moyen de vérifier que les identifiants sont bons avant qu'une campagne ne le découvre à votre place.

## En cas de problème

- **Erreur d'authentification au test** : vérifiez que le nom d'utilisateur est bien l'adresse complète et que le mot de passe ne contient pas d'espace copié par erreur.
- **Aucune réponse du serveur** : le couple port/chiffrement est probablement inadapté ; essayez `587`/`tls` puis `465`/`ssl`.
- **Enregistrement refusé** : un champ obligatoire est vide, ou l'adresse d'expédition n'est pas une adresse e-mail valide.
- **Quota laissé vide** : l'application n'appliquera aucune limite, mais votre fournisseur en applique une. Renseignez une valeur réaliste pour éviter un blocage brutal en pleine [campagne](help:campaigns).
