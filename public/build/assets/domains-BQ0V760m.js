const e=`# Domaines

La page **Domaines** affiche vos domaines d'envoi et l'état de leurs enregistrements DNS : **DKIM**, **SPF** et **DMARC**. Un domaine authentifié prouve aux messageries que vos e-mails sont légitimes et protège votre réputation d'expéditeur.

## À quoi servent les trois enregistrements

| Enregistrement | Rôle |
| --- | --- |
| SPF | Liste les serveurs autorisés à envoyer pour votre domaine |
| DKIM | Signe chaque message pour garantir qu'il n'a pas été altéré |
| DMARC | Indique quoi faire des messages qui échouent aux contrôles |

## Authentifier un domaine

1. Cliquez sur **Ajouter un domaine** et saisissez-le, par exemple \`monentreprise.dz\`.
2. L'application génère les enregistrements à publier.
3. Copiez les valeurs DKIM, SPF et DMARC dans la zone DNS de votre hébergeur ou registrar.
4. Revenez et cliquez sur **Vérifier** : chaque enregistrement affiche son statut.

La propagation DNS peut prendre de quelques minutes à plusieurs heures ; si la vérification échoue, patientez puis réessayez.

## Associer les expéditeurs

Une fois le domaine authentifié, les [expéditeurs](help:senders) qui l'utilisent sont validés automatiquement.

## Conseils

- Ne supprimez pas un enregistrement SPF existant : fusionnez les nouvelles valeurs avec les anciennes.
- Commencez DMARC avec une politique souple, puis durcissez-la une fois les envois stabilisés.
- Un domaine non authentifié est une cause fréquente de rejets visibles dans les [journaux transactionnels](help:transactional-logs).
`;export{e as default};
