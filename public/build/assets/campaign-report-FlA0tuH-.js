const e=`# Rapport de campagne

Le **rapport** détaille les résultats d'une campagne envoyée, avec une comparaison à la moyenne de votre compte. Il se compose de cinq onglets. Pour une campagne qui n'est pas encore partie, la page vous renvoie vers la [configuration](help:campaign-setup).

## Les onglets

| Onglet | Contenu |
| --- | --- |
| Vue d'ensemble | Envoyés, Délivrés, Ouvertures uniques, Clics uniques, Désinscriptions, Plaintes, comparés à votre moyenne ; graphique par heure puis par jour |
| Délivrabilité | Messages distribués, rebonds et leurs causes |
| Ouvertures | Domaines, appareils et pays |
| Clics | Liens classés par nombre de clics |
| Désinscriptions | Qui s'est désabonné, et quand |

## Lire l'en-tête et « Envoyés »

- Dans l'en-tête, les balises de personnalisation de l'objet s'affichent en pastilles (par exemple « SOCIETE ») : c'est le modèle d'objet, chaque destinataire a reçu sa propre valeur.
- Sous **Envoyés**, **Restants** compte les messages pas encore partis ; sur une campagne terminée, le même chiffre s'intitule **En file d'attente**. Les contacts écartés au moment de l'envoi (liste noire) sont comptés à part, sous **Ignorés (liste noire)**, et ne figurent plus dans « Restants ».

## Pas à pas

1. Commencez par la **Vue d'ensemble** : repérez les écarts à la moyenne.
2. Passez à **Clics** pour savoir quels liens ont intéressé vos contacts.
3. Consultez **Délivrabilité** si le taux de livraison est faible.
4. Cliquez sur **Voir les contacts** pour créer un [segment](help:segments) (par exemple « a ouvert la campagne X ») à recibler.
5. **Exporter** télécharge l'onglet en CSV ; **Comparer** met plusieurs campagnes côte à côte.

## Conseils

- Les ouvertures sont indicatives : certains clients de messagerie préchargent les images.
- Un taux de plaintes ou de rebonds élevé appelle un nettoyage de la liste et une vérification du [domaine](help:domains).
- Pour une vue multi-campagnes ou multi-comptes, utilisez les [Rapports](help:reporting).

Retour : [Campagnes](help:campaigns).
`;export{e as default};
