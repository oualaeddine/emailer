# SMTP & API

Cette page rassemble la **configuration technique** des envois transactionnels : instructions du relais SMTP, clés API et webhooks sortants. Elle s'adresse à la personne qui connecte vos applications (site web, boutique, CRM) à la plateforme.

## Relais SMTP

L'onglet SMTP indique le serveur, le port et les identifiants à saisir dans votre application pour qu'elle envoie ses messages par ce relais.

## Clés API

1. Cliquez sur **Créer une clé** et donnez-lui un nom (par exemple « Boutique en ligne »).
2. Choisissez ses autorisations.
3. **Copiez la clé immédiatement** : elle ne sera plus jamais affichée.
4. Révoquez toute clé compromise ou devenue inutile.

## Webhooks

Un webhook notifie votre application des événements d'un message (livré, rebond, plainte…).

1. Indiquez l'**URL** de réception.
2. Cochez les **événements** à recevoir.
3. Conservez le **secret de signature** pour vérifier l'authenticité des appels.
4. Consultez le **journal de livraison** pour voir chaque tentative et son résultat.

## Envoyer par API

Utilisez la clé pour appeler le point d'envoi transactionnel depuis vos applications, puis suivez le résultat dans les [journaux transactionnels](help:transactional-logs).

## Conseils

- Une clé par application : vous pourrez en révoquer une sans interrompre les autres.
- N'enregistrez jamais une clé dans un dépôt de code public.
- Authentifiez d'abord votre [domaine](help:domains) et vos [expéditeurs](help:senders) pour une bonne délivrabilité.
