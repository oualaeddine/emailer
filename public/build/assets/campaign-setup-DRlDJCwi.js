const e=`# Configuration d'une campagne

La page de configuration empile des cartes à cocher : **cinq** (Expéditeur, Objet, Destinataires, Design et Paramètres supplémentaires), ou **six** quand la carte **Test A/B** est disponible. Chaque carte affiche une coche verte une fois complétée, et une liste de contrôle à droite indique ce qui manque avant l'envoi.

## L'en-tête

Le nom de la campagne se renomme en cliquant sur le crayon. **Enregistrer et quitter** ramène à la liste des [campagnes](help:campaigns), **Aperçu & test** ouvre l'aperçu et **Programmer** lance l'envoi.

## Pas à pas

1. **Expéditeur** : choisissez une identité vérifiée dans vos [expéditeurs](help:senders).
2. **Objet** : saisissez l'objet et le texte d'aperçu (préheader).
3. **Destinataires** : sélectionnez les [listes](help:lists) et [segments](help:segments) à cibler, puis ceux à exclure ; le nombre de destinataires se met à jour en direct.
4. **Design** : choisissez un [modèle](help:templates) puis ouvrez l'[éditeur](help:campaign-design).
5. **Paramètres supplémentaires** : page de [désinscription](help:unsubscribe-pages), tags, suivi UTM, pièce jointe, stratégie SMTP, plage horaire d'envoi et approbation (voir ci-dessous).

## Stratégie SMTP et plage horaire d'envoi

- **Stratégie SMTP** : **Rotation automatique** (par défaut) répartit les messages entre vos comptes SMTP disponibles. **Compte unique** impose un compte pour toute la campagne : si ce compte est indisponible (désactivé, quota atteint), l'envoi attend, il ne bascule jamais sur un autre compte. Le compte doit être choisi pour pouvoir programmer.
- **Plage horaire d'envoi** : cochez-la pour n'envoyer que certains jours, entre une heure de début et une heure de fin, dans le fuseau horaire de la campagne (Alger par défaut). En dehors de la plage, rien ne part : l'envoi reprend automatiquement à la prochaine ouverture (par exemple lundi 08:00). La campagne reste « Programmée » ou « En cours » pendant l'attente.

## Approbation avant l'envoi

Si **Approbation requise avant l'envoi** est activée dans Paramètres supplémentaires :

- un bandeau **En attente d'approbation** s'affiche en haut de la page, la liste de vérification contient un point **Approbation** à corriger, et **Programmer** reste grisé (l'info-bulle l'explique) ;
- un utilisateur disposant du droit **Approuver les campagnes** (Responsable marketing, Administrateur) voit le bouton **Approuver** dans le bandeau ; dans la liste des [campagnes](help:campaigns), le même droit donne ⋯ › **Approuver**. Les autres rôles voient le bandeau mais ne peuvent pas approuver : demandez à un responsable ;
- une fois approuvée, le bandeau indique qui a approuvé et quand, et **Programmer** se dégrise ;
- **modifier l'objet, le texte d'aperçu, l'expéditeur, le contenu, les destinataires ou le test A/B retire l'approbation** : la campagne doit être approuvée à nouveau. Une campagne déjà programmée repasse en Brouillon. Les autres réglages (nom, tags, suivi…) ne retirent rien ;
- chaque approbation, et chaque retrait d'approbation, est inscrit dans le [journal d'audit](help:audit-log) (\`campaign.approved\`, \`campaign.approval_reset\`).

Il n'existe pas de refus motivé : si la campagne ne convient pas, demandez des corrections à son auteur, qui la modifie puis la soumet à nouveau.

## Tester avant d'envoyer

**Aperçu & test** affiche le rendu bureau et mobile et envoie un test à cinq adresses au plus. Les balises de personnalisation de l'objet et du texte d'aperçu sont remplies avec le premier contact de l'audience (ou affichées entre crochets, par exemple « [SOCIETE] », si l'audience est vide). Vérifiez les balises de personnalisation et les liens. Si la campagne a un test A/B actif, le sélecteur **Version du test A/B** bascule l'aperçu et l'envoi de test entre la version A et la version B (objet, texte d'aperçu et, pour un test de contenu, le contenu). Les envois de test ne comptent pas dans les résultats du test A/B.

## Programmer

Quand la liste de contrôle est verte, **Programmer** ouvre la confirmation : envoyer maintenant, programmer plus tard, envoyer au meilleur moment ou par lots.

## Conseils

- Une campagne déjà envoyée n'est plus modifiable ; dupliquez-la pour repartir de la même base.
- Un expéditeur sur un [domaine authentifié](help:domains) améliore la délivrabilité.
- Après l'envoi, suivez les résultats dans le [rapport de campagne](help:campaign-report).
`;export{e as default};
