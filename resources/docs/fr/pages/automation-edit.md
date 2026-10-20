# Éditeur d'automatisation

L'éditeur sert à construire le scénario d'une [automatisation](help:automations) : quel événement fait entrer un contact, puis quelles étapes il parcourt (e-mails, attentes, conditions…). On l'ouvre en cliquant sur une automatisation dans la liste, ou juste après **Créer et ouvrir l'éditeur**.

## L'en-tête

À côté du nom, un badge indique le statut : **Brouillon**, **Active** ou **En pause**. À droite :

| Bouton | Effet |
| --- | --- |
| Enregistrer | Enregistre le nom, le déclencheur, les paramètres et les étapes ; grisé tant que rien n'a changé (« Vous avez des modifications non enregistrées. » s'affiche sinon) |
| Activer | Enregistre si besoin, puis démarre le scénario (« Automatisation activée. ») |
| Mettre en pause | Remplace **Activer** quand l'automatisation est active (« Automatisation mise en pause. ») |

**Activer** reste inactif tant qu'il manque un déclencheur configuré et au moins une étape : « Ajoutez un déclencheur et au moins une étape pour activer l'automatisation. » Trois onglets organisent la page : **Flux**, **Paramètres** et **Activité** (cet onglet n'est disponible qu'une fois l'automatisation enregistrée).

## Onglet Flux : le déclencheur

Cliquez sur la carte **Déclencheur** pour ouvrir le panneau latéral et choisir l'événement.

| Déclencheur | À régler |
| --- | --- |
| Contact ajouté à une liste | **Listes** (une ou plusieurs, obligatoire) |
| Formulaire envoyé | **Formulaires** (obligatoire), voir [Éditeur de formulaire](help:form-edit) |
| Campagne ouverte | **N'importe quelle campagne envoyée** ou **Des campagnes précises** |
| Lien de campagne cliqué | Comme ci-dessus, plus **URL du lien** (facultatif, préfixe accepté avec `*`) |
| Attribut modifié | **Attribut**, puis **Toute modification** ou **Uniquement vers une valeur précise** |
| Anniversaire d'une date | **Attribut de type date**, **Décalage (jours)** de -365 à 365 (négatif = avant la date, positif = après), **Heure d'envoi** |

L'interrupteur **Uniquement les contacts qui remplissent ces conditions** ajoute un filtre sur les [attributs](help:attributes) du contact. Pour « Anniversaire d'une date », créez d'abord un attribut de type date dans Contacts › Attributs. Une fois l'automatisation active, le déclencheur est verrouillé : « Le déclencheur ne peut pas être modifié tant que l'automatisation est active. »

## Onglet Flux : les étapes

Chaque bouton « + » entre deux cartes (**Insérer une étape ici**) propose les types d'étapes ; l'étape s'ouvre aussitôt dans le panneau latéral. Le menu de la carte offre **Modifier l'étape** et **Supprimer l'étape**.

| Étape | Réglages |
| --- | --- |
| Envoyer un e-mail | **Modèle d'e-mail** (obligatoire, voir [Modèles](help:templates)), **Expéditeur**, **Objet** (vide = objet du modèle), **Texte d'aperçu** |
| Attendre | **Durée** et **Unité** (minute(s), heure(s), jour(s)), de 1 minute à 365 jours |
| Condition (oui / non) | Conditions sur le contact : ceux qui les remplissent suivent la branche **Oui**, les autres la branche **Non** |
| Ajouter à une liste / Retirer d'une liste | **Liste** (obligatoire) |
| Mettre à jour un attribut | **Attribut** et **Valeur** (les attributs EMAIL et EXT_ID ne sont pas modifiables) |
| Fin | Le contact sort de l'automatisation ; uniquement en dernière position d'une branche |

Limites : 50 étapes au plus et 3 conditions imbriquées. Si les deux branches d'une condition se terminent, les étapes qui suivent ne seront jamais atteintes (un avertissement s'affiche). Après des envois, chaque carte indique « N entrés · N terminés ».

## Expéditeur et adresse de réponse

Dans une étape **Envoyer un e-mail**, **Expéditeur (facultatif)** liste vos [expéditeurs](help:senders). Laissé vide, l'e-mail part avec l'adresse du compte SMTP ; **Retirer l'expéditeur** revient à ce cas. Un expéditeur choisi doit être vérifié pour activer (« L'expéditeur doit être vérifié avant l'activation. »). L'étape n'a pas de champ d'adresse de réponse : c'est l'**Adresse de réponse** de l'expéditeur choisi, réglée sur la page Expéditeurs, qui est utilisée.

## Onglet Paramètres

- **Nom** (obligatoire) et **Description** (facultatif).
- **Réentrée des contacts** : **Jamais** (un contact ne parcourt le scénario qu'une fois), **Après un délai** (**Délai avant une nouvelle entrée (jours)**, de 1 à 3650) ou **Toujours, à chaque déclenchement**.

## Activer et mettre en pause

Quand l'automatisation est active, l'onglet **Flux** est en lecture seule : « Mettez l'automatisation en pause pour modifier son workflow. » En pause, plus aucun contact n'entre et ceux qui sont en cours restent gelés jusqu'à la réactivation. Les contacts en [liste noire](help:suppression) ne reçoivent pas les e-mails.

## Onglet Activité

Le menu **Période** propose **Depuis la création**, ou les 7, 30 et 90 derniers jours. Quatre indicateurs résument le scénario : **Entrés**, **En cours**, **Terminés** (avec le taux) et **Sortis**. **Performance des e-mails** détaille par e-mail les envoyés, délivrés, taux d'ouverture, de clic et de désinscription. **Contacts dans l'automatisation** liste chaque contact avec son statut (En cours, En attente, Terminé, Sorti, Échec), son étape en cours et ses dates ; **Faire sortir le contact** l'arrête après confirmation.

## En cas de problème

- **« Donnez un nom à l'automatisation. »** : le **Nom** de l'onglet **Paramètres** est vide.
- **« Déclencheur à configurer »** ou **« Étape à compléter »** : une carte est incomplète (liste, modèle, condition ou valeur manquante). Ouvrez-la et renseignez-la.
- **« Complétez les étapes signalées avant d'enregistrer. »** / **« Corrigez les étapes signalées avant d'activer. »** : repérez les cartes marquées en rouge ou en avertissement.
- **« Entre 1 minute et 365 jours. »** : la durée de l'étape **Attendre** est hors limites.
- **« Impossible de charger l'automatisation. »** : actualisez la page ; si elle a été supprimée, le message « Élément introuvable. » s'affiche.
