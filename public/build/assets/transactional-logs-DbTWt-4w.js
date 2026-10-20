const e=`# Journaux transactionnels

Les **journaux** affichent en temps réel les messages transactionnels (confirmations de commande, réinitialisations de mot de passe, alertes…) et tous les événements qui les concernent. Ils répondent à la question « ce message est-il bien parti, et qu'est-il devenu ? ».

## Les événements

Envoyé, délivré, ouvert, cliqué, rebond, invalide, différé, plainte, désinscrit, bloqué, erreur.

## Pas à pas

1. Laissez l'interrupteur **Temps réel** activé pour voir les nouveaux messages arriver ; désactivez-le pour figer la liste pendant une analyse.
2. Filtrez par **type d'événement** pour isoler, par exemple, les rebonds ou les erreurs.
3. Utilisez la recherche pour retrouver un destinataire, un objet ou un identifiant de message.
4. Dépliez une ligne pour afficher la chronologie complète des événements de ce message.

## Diagnostiquer un problème

| Symptôme | Piste |
| --- | --- |
| Beaucoup de rebonds | Adresses invalides : nettoyez la source |
| Statut « bloqué » | Le destinataire est dans la [liste de suppression](help:suppression) |
| Statut « différé » | Le serveur du destinataire est temporairement indisponible, un nouvel essai a lieu |
| Statut « erreur » | Vérifiez la [configuration SMTP & API](help:transactional-settings) |

## Conseils

- Croisez les journaux avec vos [domaines](help:domains) : un domaine non authentifié provoque souvent des rejets.
- Pour les envois de masse, consultez plutôt le [rapport de campagne](help:campaign-report).
`;export{e as default};
