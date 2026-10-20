const e=`# Expéditeurs

Les **expéditeurs** sont les identités (nom et adresse) utilisables dans le champ « De » de vos campagnes. Le tableau indique pour chacun le domaine, l'état de vérification et l'éventuel statut d'expéditeur par défaut.

## Ajouter un expéditeur

1. Cliquez sur **Ajouter un expéditeur**.
2. Saisissez le nom affiché (par exemple « Équipe PageJaunes ») et l'adresse e-mail.
3. Validez : un e-mail de vérification est envoyé à cette adresse, sauf si le [domaine](help:domains) est déjà authentifié.

## Vérifier l'adresse

Ouvrez le message reçu et cliquez sur le lien de validation. Tant que l'expéditeur n'est pas vérifié, il ne peut pas être choisi dans une [campagne](help:campaign-setup).

## Expéditeur par défaut

Marquez l'expéditeur à proposer d'office dans les nouvelles campagnes pour gagner du temps.

## Authentifier le domaine

Pour de meilleurs résultats, authentifiez le domaine de l'adresse depuis la page [Domaines](help:domains). Les expéditeurs d'un domaine authentifié sont validés automatiquement.

## Conseils

- Utilisez une adresse sur votre propre domaine plutôt qu'une adresse de messagerie grand public : la délivrabilité est nettement meilleure.
- Choisissez un nom reconnaissable ; c'est ce que voit le destinataire avant d'ouvrir le message.
- Une adresse à laquelle vous répondez réellement évite de perdre les réponses de vos contacts.
`;export{e as default};
