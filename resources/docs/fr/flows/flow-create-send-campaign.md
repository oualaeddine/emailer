# Envoyer une campagne pas à pas

Ce guide suit une campagne de bout en bout : vérifier ce dont elle a besoin, la configurer, la tester, la programmer, puis lire ses résultats. Fil rouge : l'équipe commerciale de PageJaunes veut présenter son offre de référencement en ligne aux entreprises de la liste « Prospects BTP & Construction ». L'e-mail s'appellera « Guide — campagne exemple ».

## Vue d'ensemble

| Étape | Où | Résultat |
| --- | --- | --- |
| 1 | Prérequis | Expéditeur vérifié, contacts en liste, compte SMTP sain |
| 2 | Campagnes › **Créer une campagne** | Une campagne en **Brouillon** |
| 3 à 6 | Page de configuration | Cartes Expéditeur, Objet, Destinataires, Design complètes |
| 7 | **Aperçu & test** | Un e-mail de test relu |
| 8 | **Liste de vérification** | Plus aucun point « À corriger » |
| 9 | **Programmer** | Envoi immédiat, programmé, au meilleur moment ou par lots |
| 10 | Liste des campagnes | Suivi, pause, reprise |
| 11 | Rapport de campagne | Résultats |

## 1. Vérifier les prérequis

Trois éléments doivent exister avant de créer la campagne.

| Élément | Où le préparer |
| --- | --- |
| Un **expéditeur vérifié** (nom et adresse du champ « De ») | [Expéditeurs](help:senders), idéalement sur un [domaine authentifié](help:domains) |
| Des contacts rassemblés dans une **liste** ou un **segment** | [Listes](help:lists), [Segments](help:segments), alimentés par [l'import de contacts](help:recipients-import) |
| Au moins un compte **SMTP** en bon état | [Comptes SMTP](help:smtp) |

Sans expéditeur vérifié, la carte Expéditeur affiche « Aucun expéditeur vérifié. Ajoutez-en un dans Paramètres › Expéditeurs. ». Sans compte SMTP disponible, la campagne peut être programmée mais aucun message ne partira (voir « En cas de problème »).

Le contenu n'est pas un prérequis : vous le créerez, ou le choisirez parmi les [modèles](help:templates), pendant la configuration.

## 2. Créer la campagne

Dans le menu, groupe **Marketing**, cliquez sur **E-mails**. La page **Campagnes e-mail** liste vos campagnes, avec des onglets de statut (Toutes, Brouillons, Programmées, En cours, Envoyées, Suspendues, Archivées).

![Liste des campagnes e-mail](/docs/screenshots/campaign-flow/01-campaigns-list.png)

1. Cliquez sur **Créer une campagne** (en haut à droite).
2. Choisissez le type : **E-mail** (envoi classique) ou **Test A/B** (deux objets ou deux contenus, la meilleure version est envoyée automatiquement). Pour ce guide : **E-mail**, puis **Continuer**.
3. Saisissez le **Nom interne de la campagne** (visible seulement par vous et votre équipe), par exemple `Guide — campagne exemple`.
4. Cliquez sur **Créer la campagne**.

![Fenêtre « Créer une campagne »](/docs/screenshots/campaign-flow/02-create-dialog.png)

Vous arrivez sur la page de configuration. La campagne existe déjà en **Brouillon** : vous pouvez la quitter à tout moment avec **Enregistrer et quitter** ou la flèche de retour, puis la rouvrir en cliquant sur son nom dans la liste.

## 3. Comprendre la page de configuration

![Page de configuration d'une campagne](/docs/screenshots/campaign-flow/03-setup.png)

L'en-tête affiche le nom (crayon pour le renommer), le statut et trois actions : **Enregistrer et quitter**, **Aperçu & test** et **Programmer**. Ce dernier bouton reste grisé tant que les étapes obligatoires ne sont pas complètes (info-bulle : « Complétez les étapes obligatoires pour programmer la campagne. »).

Dessous, des cartes à compléter dans l'ordre : **Expéditeur**, **Objet**, **Destinataires**, **Design**, **Test A/B** (facultatif) et **Paramètres supplémentaires**. Chaque carte affiche une coche verte une fois terminée. À droite, la **Liste de vérification** se met à jour après chaque enregistrement.

Chaque carte s'ouvre avec son bouton (« Sélectionner l'expéditeur », « Ajouter un objet »…) et s'enregistre avec son propre bouton **Enregistrer**.

## 4. Expéditeur et objet

**Expéditeur**

1. Cliquez sur **Sélectionner l'expéditeur** et choisissez une identité dans la liste (seuls les expéditeurs vérifiés sont proposés ; le lien **Gérer les expéditeurs** mène à leur page).
2. Facultatif : **Adresse de réponse**. Laissée vide, c'est l'adresse de l'expéditeur qui reçoit les réponses.
3. **Enregistrer**.

**Objet**

1. Cliquez sur **Ajouter un objet**.
2. Saisissez l'**Objet** : `Boostez la visibilité de {{ contact.SOCIETE }} avec PageJaunes`. Un compteur indique le nombre de caractères ; au-delà de 60, un avertissement signale que l'objet risque d'être tronqué (maximum 255).
3. Facultatif : **Texte d'aperçu**, la ligne grise affichée après l'objet dans la boîte de réception. Le bloc « Aperçu dans la boîte de réception » montre le résultat.
4. **Enregistrer**.

### Personnaliser avec des balises

Une balise est remplacée, pour chaque destinataire, par une donnée de sa fiche. Les icônes à droite des champs insèrent une balise (icône **Personnaliser**) ou un emoji (**Insérer un emoji**). Syntaxe :

| Balise | Résultat |
| --- | --- |
| `{{ contact.PRENOM }}`, `{{ contact.NOM }}`, `{{ contact.SOCIETE }}`, `{{ contact.EMAIL }}` | Données de la fiche contact |
| `{{ contact.NOM_DE_L_ATTRIBUT }}` | Tout [attribut](help:attributes) personnalisé (par exemple `{{ contact.wilaya }}`) |
| `{{ contact.PRENOM \| default:"cher client" }}` | Valeur de remplacement quand le champ est vide |
| `{{ unsubscribe }}` | Lien de désinscription |
| `{{ mirror }}`, `{{ update_profile }}` | Version en ligne, mise à jour du profil |
| `{{ campaign.NAME }}`, `{{ sender.NAME }}`… | Nom de la campagne, de l'expéditeur… |

Les balises fonctionnent dans l'objet, le texte d'aperçu, le contenu et le champ « À ». Une balise inconnue (faute de frappe, `{{ prenom }}` au lieu de `{{ contact.PRENOM }}`) bloque la programmation : voir la liste de vérification.

## 5. Destinataires

1. Cliquez sur **Ajouter des destinataires**. La fenêtre **Choisir les destinataires** s'ouvre.
2. Dans **Envoyer à**, cochez des [listes](help:lists) (onglet **Listes**) et/ou des [segments](help:segments) (onglet **Segments**). Un champ de recherche aide à retrouver un nom. Maximum : 50 listes et segments.
3. Facultatif : dans **Exclure**, cochez ce qui ne doit pas être touché (par exemple vos clients actifs).
4. Les éléments cochés restent visibles sous forme de pastilles au-dessus de la liste (cliquez sur la croix pour en retirer un). Le bloc **Audience estimée** se met à jour en direct : nombre de destinataires, d'exclus et d'adresses « en liste noire » (un contact présent dans plusieurs listes n'est compté qu'une fois ; un client déjà dans une liste exclue est retiré même s'il figure aussi dans la liste choisie). Ces dernières, celles de la [liste de suppression](help:suppression), sont toujours écartées. Un avertissement apparaît si une grande part de l'audience n'a rien ouvert depuis 90 jours.
5. Facultatif : **Champ « À »** pour personnaliser le nom affiché du destinataire (par exemple `{{ contact.PRENOM }} {{ contact.NOM }}`).
6. **Enregistrer**.

![Choisir les destinataires](/docs/screenshots/campaign-flow/04-recipients.png)

Dans l'exemple : liste « Prospects BTP & Construction », 130 contacts, dont 13 en liste noire, soit 117 destinataires.

## 6. Design

1. Dans la carte **Design**, cliquez sur **Commencer la conception**. La fenêtre **Choisir un modèle** propose cinq onglets : **Galerie** (modèles prêts à l'emploi, dont **Modèle vierge**), **Mes modèles**, **Campagnes précédentes**, **Coder** (HTML) et **Éditeur de texte enrichi**.
2. Cliquez sur un modèle, par exemple **Lancement de produit** : l'éditeur glisser-déposer s'ouvre.
3. Modifiez le contenu : glissez des blocs depuis **Contenu › Blocs** (Titre, Texte, Image, Bouton, Séparateur, colonnes…) ou des sections prêtes à l'emploi (**Sections**), et réglez le bloc sélectionné dans le panneau **Propriétés**. Dans un bloc texte, le menu **Personnaliser…** insère une balise (Prénom, Société, Lien de désinscription…) et place le curseur juste après elle ; le correcteur orthographique du navigateur est désactivé dans le canevas. Dans la barre du haut, **Restaurer une version précédente** est une icône (infobulle au survol).
4. Cliquez sur **Enregistrer et quitter** pour revenir à la configuration (**Enregistrer** garde l'éditeur ouvert).

![Éditeur de design](/docs/screenshots/campaign-flow/05-design-editor.png)

La carte affiche alors une miniature et trois actions : **Modifier le design**, **Changer de modèle** et **Aperçu**. Les détails de l'éditeur sont dans [Éditeur de design](help:campaign-design).

**Lien de désinscription obligatoire.** Le design doit contenir un lien de désinscription : soit la balise `{{ unsubscribe }}` (menu **Personnaliser…** › « Lien de désinscription »), soit un lien vers une adresse `/unsubscribe/…`. Les modèles de la galerie en contiennent déjà un. Sans lui, la programmation est bloquée.

## 7. Paramètres supplémentaires et test A/B (facultatifs)

La carte **Paramètres supplémentaires** (bouton **Modifier les paramètres**) regroupe notamment :

- **Page de désinscription** : **Page par défaut** ou **Page personnalisée** (voir [Pages de désinscription](help:unsubscribe-pages)) ;
- **Tags** et **Dossier**, pour retrouver la campagne dans la liste ;
- **Suivi Google Analytics (UTM)** : **Activer le suivi UTM** ajoute les paramètres `utm_*` aux liens ;
- **Suivi des ouvertures et des clics** (activé par défaut), **Lien miroir** (« voir dans le navigateur ») ;
- **Stratégie SMTP** : **Rotation automatique** (par défaut) ou **Compte unique** (tous les messages par le compte choisi ; s'il est indisponible, l'envoi attend au lieu de basculer sur un autre compte) ;
- **Plage horaire d'envoi** : jours et heures (fuseau horaire de la campagne) pendant lesquels les messages peuvent partir ; hors plage, l'envoi est reporté à la prochaine ouverture ;
- **Approbation requise avant l'envoi** : voir « Approbation » ci-dessous.

La carte **Test A/B** (bouton **Activer le test A/B**) sert à comparer deux versions : élément testé (**Objet** ou **Contenu**), taille de l'échantillon (de 5 à 50 % des destinataires), critère du gagnant (taux d'ouverture ou de clic), durée du test et envoi automatique de la version gagnante au reste de l'audience. Les objets (ou contenus) des versions A et B doivent être différents.

Pour un premier envoi, vous pouvez laisser les autres réglages tels quels.

**Approbation.** Avec **Approbation requise avant l'envoi**, la page affiche le bandeau **En attente d'approbation**, la liste de vérification ajoute le point **Approbation** et **Programmer** reste grisé. Un utilisateur qui détient le droit **Approuver les campagnes** (Responsable marketing, Administrateur) clique sur **Approuver** dans le bandeau, ou ⋯ › **Approuver** dans la liste des campagnes ; **Programmer** devient alors disponible. Modifier ensuite l'objet, le contenu, l'expéditeur ou les destinataires retire l'approbation (une campagne programmée repasse en Brouillon) : il faut l'approuver à nouveau. Approbations et retraits sont inscrits dans le journal d'audit.

## 8. Tester avant d'envoyer

Cliquez sur **Aperçu & test** dans l'en-tête : un panneau s'ouvre à droite.

![Aperçu et test](/docs/screenshots/campaign-flow/06-preview-test.png)

1. Relisez l'**Aperçu dans la boîte de réception**, puis le rendu en mode **Ordinateur** et **Mobile**.
2. Dans **Adresses de test**, saisissez de 1 à 5 adresses séparées par des virgules, puis cliquez sur **Envoyer un test**. Le message « E-mail de test envoyé. » s'affiche, avec la mention « Dernier test envoyé le … ».
3. Ouvrez le message reçu : vérifiez l'objet, les images, les liens et la version mobile.
4. Avec un test A/B actif, le sélecteur **Version du test A/B** (Version A, Version B) bascule l'aperçu et l'envoi de test entre les deux versions.

À savoir :

- **Limite** : 5 adresses par envoi et 10 envois de test par heure et par campagne.
- Les balises sont remplies avec la fiche du contact **si l'adresse de test correspond à un contact existant**. Sinon elles restent vides, ou prennent leur valeur `default:` : utilisez l'adresse d'un contact de test pour vérifier la personnalisation.
- Un test ne compte pas dans les statistiques et ne comporte pas les en-têtes de désinscription d'un vrai envoi. Une adresse de test inconnue n'est jamais utilisée comme destinataire de vos campagnes.
- Le test part par vos comptes SMTP, comme un vrai message.

## 9. Lire la liste de vérification

La **Liste de vérification**, à droite de la page, contrôle la campagne avant l'envoi.

![Liste de vérification](/docs/screenshots/campaign-flow/07-checklist.png)

| Point | Bloquant ? | Ce qui est vérifié |
| --- | --- | --- |
| Expéditeur vérifié | Oui | Un expéditeur vérifié est choisi |
| Destinataires sélectionnés | Oui | Au moins un destinataire une fois exclusions et liste noire déduites |
| Objet renseigné | Oui | L'objet n'est pas vide |
| Design prêt | Oui | Le design n'est pas vide |
| Balises de personnalisation | Oui | Aucune balise inconnue (message : « Balises inconnues : … ») |
| Lien de désinscription présent | Oui | Voir l'étape 6 |
| E-mail de test envoyé | Non | Au moins un test envoyé |
| Liens valides | Non | Résultat du bouton **Vérifier les liens** |
| Taille de l'e-mail | Non | Avertissement au-delà de 102 Ko (risque de coupure par Gmail) |
| Score de spam | Non | « Analyse anti-spam non disponible » |

Chaque point porte un badge : **OK**, **Recommandé** (conseil, non bloquant), **À corriger**, **En cours** (vérification en cours) ou **Non vérifié**. Le badge global en haut indique :

- **Bloquée** : au moins un point obligatoire est « À corriger ». **Programmer** est inactif.
- **Incomplète** : rien ne bloque, mais un conseil reste à traiter (test non envoyé, liens non vérifiés…). Le message sous le titre devient « Vous pouvez programmer l'envoi ; quelques vérifications recommandées restent à faire. » et **Programmer** reste actif.
- **Prête** : tout est au vert (« Votre campagne est prête à être envoyée. »).

Cliquez sur **Vérifier les liens** pour contrôler que chaque lien du design répond ; un lien défaillant est signalé (« n lien(s) inaccessible(s) »). Ce contrôle est un conseil : il ne bloque pas l'envoi.

## 10. Programmer ou envoyer

Cliquez sur **Programmer**. La fenêtre **Confirmer l'envoi** s'ouvre : le **Récapitulatif** reprend **De**, **À** (avec le nombre de destinataires), **Objet** et **Envoi prévu**. Relisez-le, puis choisissez parmi les **Options d'envoi**.

![Confirmer l'envoi](/docs/screenshots/campaign-flow/08-schedule.png)

| Option | Fonctionnement | Bouton final |
| --- | --- | --- |
| **Envoyer maintenant** | La campagne part immédiatement. | **Envoyer maintenant** |
| **Programmer pour plus tard** | **Date**, **Heure** et **Fuseau horaire** (Africa/Algiers par défaut) obligatoires ; la date doit être dans le futur. Par défaut : demain 9 h. | **Programmer** |
| **Envoyer au meilleur moment** | Chaque contact reçoit l'e-mail à l'heure où il ouvre habituellement ses messages, dans les 24 heures. L'analyse porte sur ses 10 dernières campagnes ; sans historique, l'heure la plus performante du compte est utilisée. Un résumé indique combien de contacts ont un historique. Date et heure facultatives. | **Programmer au meilleur moment** |
| **Envoyer par lots** | L'envoi est réparti dans le temps : **Nombre d'e-mails par lot** (50 à 100 000, 500 par défaut) toutes les **Intervalle entre les lots (minutes)** (5 à 1 440, 30 par défaut). Date et heure facultatives. | **Programmer l'envoi par lots** |

Règles à connaître :

- Pour « meilleur moment » et « par lots », une date et une heure vides signifient « démarrer tout de suite » ; avec une date, la campagne attend ce moment. Chaque option ne garde que ses propres champs : en passant de « Programmer pour plus tard » à « meilleur moment » ou « par lots », la date proposée par défaut est effacée, et la ligne **Envoi prévu** n'affiche une date que si vous en saisissez une. La fenêtre défile à l'intérieur, titre et boutons restant visibles.
- **Une campagne avec test A/B ne propose que « Envoyer maintenant » et « Programmer pour plus tard »** : l'échantillon doit partir en une fois pour que le gagnant soit choisi sur des données complètes.
- Si une date est dans le passé, les champs Date et Heure passent en erreur ; si le lot ou l'intervalle sort des bornes, le champ concerné est signalé.
- Le serveur revérifie la liste de vérification : si un point bloquant a changé entre-temps, la fenêtre affiche « La campagne ne peut pas être programmée : … » avec les points en cause.

Une fois validé, un message s'affiche (« Campagne programmée. » ou « Envoi de la campagne lancé. ») et vous revenez à la liste, sur l'onglet **Programmées** ou **En cours**. La campagne n'est plus modifiable tant qu'elle est en cours d'envoi.

## 11. Suivre l'envoi

### Les statuts

| Statut | Signification |
| --- | --- |
| **Brouillon** | En préparation, jamais envoyée. |
| **Programmée** | Une date d'envoi future est enregistrée. À l'heure dite, elle passe à « En cours ». |
| **En cours** | Les messages sont confiés au moteur d'envoi, par paquets. « Meilleur moment » et « par lots » démarrent directement en « En cours » quand aucune date n'est fixée. |
| **En pause** | Plus aucun nouveau message n'est libéré. |
| **Suspendue** | Envoi interrompu ; l'info-bulle du statut donne la raison (quota d'envoi atteint, aucun compte SMTP disponible…). **Reprendre** relance l'envoi. |
| **Envoyée** | Tous les destinataires ont été traités. |
| **Annulée** | Envoi arrêté définitivement. |
| **Archivée** | Campagne rangée hors de l'onglet « Toutes ». |

L'envoi n'est pas instantané : les messages passent par vos comptes SMTP, dans le respect de leurs quotas et de leurs limites par domaine. Une audience de plusieurs centaines de contacts peut demander un certain temps.

### Les actions du menu « Plus d'actions » (icône ⋯)

| Statut | Actions proposées |
| --- | --- |
| Brouillon | Modifier, Dupliquer, Envoyer un test, Programmer, Envoyer maintenant, Supprimer |
| Programmée | Modifier, Dupliquer, Envoyer un test, Déprogrammer, Envoyer maintenant, Annuler l'envoi |
| En cours | Rapport, Dupliquer, Mettre en pause, Annuler l'envoi |
| En pause | Rapport, Dupliquer, Reprendre, Annuler l'envoi |
| Suspendue | Rapport, Modifier, Dupliquer, Reprendre, Annuler l'envoi, Archiver |
| Envoyée | Rapport, Dupliquer, Envoyer aux nouveaux contacts, Archiver |
| Annulée | Dupliquer, Archiver, Supprimer (et Rapport si des messages sont partis) |

- **Mettre en pause** : aucune confirmation ; les messages déjà confiés au moteur d'envoi partent, les suivants sont retenus. **Reprendre** repart de là où l'envoi s'était arrêté.
- **Annuler l'envoi** demande confirmation (« Annuler cette campagne ? ») et n'annule jamais un message déjà parti ; les destinataires non servis sont simplement écartés.
- **Déprogrammer** ramène la campagne en Brouillon pour la modifier, puis la reprogrammer. Une campagne « Programmée » modifiée doit toujours satisfaire la liste de vérification.
- **Dupliquer** crée un nouveau Brouillon « Copie de … » avec la même configuration et le même design, sans date : le moyen le plus rapide de relancer une opération réussie.
- **Envoyer aux nouveaux contacts** (campagne « Envoyée ») crée une nouvelle campagne destinée aux contacts ajoutés aux listes depuis l'envoi.

### Désinscription

Chaque message d'une campagne réelle porte les en-têtes `List-Unsubscribe` et `List-Unsubscribe-Post` (désinscription en un clic proposée par Gmail, Outlook…), en plus du lien du design. Le destinataire arrive sur la page de désinscription (par défaut ou personnalisée) ; son adresse est alors ajoutée à la [liste de suppression](help:suppression) (motif « Désabonnement manuel », ou « Désabonnement global (tout) » s'il choisit de tout quitter) et ne recevra plus aucune campagne.

## 12. Lire les résultats

Dans l'onglet **Envoyées**, cliquez sur le nom de la campagne (ou ⋯ › **Rapport**) pour ouvrir son rapport.

![Rapport de campagne](/docs/screenshots/campaign-flow/09-report.png)

Cinq onglets : **Vue d'ensemble** (Envoyés, Délivrés, Ouvertures uniques, Clics uniques, Désinscriptions, Plaintes, Rebonds, comparés à la moyenne de votre compte), **Délivrabilité**, **Ouvertures**, **Clics** et **Désinscriptions**. Les boutons **Exporter**, **Comparer** et **Voir l'e-mail** complètent la page.

Attendez 48 à 72 heures avant de conclure : ouvertures et clics s'étalent sur plusieurs jours. La lecture détaillée est décrite dans [Analyser les résultats](help:flow-analyze-results) et [Rapport de campagne](help:campaign-report).

## En cas de problème

- **Le bouton Programmer est grisé** : une carte obligatoire est incomplète, ou la liste de vérification est « Bloquée ». Passez la souris sur le bouton, puis traitez les points « À corriger » de la liste.
- **« Aucun expéditeur sélectionné » / « L'expéditeur n'est pas vérifié »** : choisissez un expéditeur vérifié ; sinon vérifiez-le dans [Expéditeurs](help:senders).
- **« Aucun destinataire »** : les listes sont vides, ou toutes les adresses sont exclues ou en liste noire. Vérifiez l'**Audience estimée**.
- **« Balises inconnues : {{ prenom }} »** : corrigez la balise (`{{ contact.PRENOM }}`) dans l'objet, le texte d'aperçu, le « À » ou le design. La balise `{{ first_name }}` n'existe pas non plus.
- **« Le design ne contient pas de lien de désinscription »** : insérez `{{ unsubscribe }}` (menu **Personnaliser…**) ou reprenez un modèle de la galerie.
- **« La campagne doit avoir un objet et un contenu avant l'envoi d'un test. »** : complétez l'objet et le design avant de tester.
- **« Vous pouvez saisir au maximum 5 adresses. »** / **« Limite de 10 envois de test par heure atteinte pour cette campagne. »** : réduisez la liste, ou attendez l'heure suivante.
- **« La campagne ne peut pas être programmée : … »** : un point bloquant est apparu entre l'ouverture de la fenêtre et la validation ; fermez la fenêtre et relisez la liste de vérification.
- **« Cette campagne doit être approuvée avant de pouvoir être envoyée. »** / **« En attente d'approbation »** : l'option **Approbation requise avant l'envoi** est activée dans **Paramètres supplémentaires** et personne n'a encore approuvé la campagne (ou l'approbation a été retirée par une modification). Faites-la approuver par un Responsable marketing ou un Administrateur (bouton **Approuver**), ou décochez l'option si vous n'avez pas de circuit d'approbation.
- **L'envoi ne part que le matin / en semaine** : une **Plage horaire d'envoi** est active. Hors plage, les messages sont retenus jusqu'à la prochaine ouverture ; modifiez la plage dans Paramètres supplémentaires (campagne en brouillon, programmée ou suspendue) si besoin.
- **« Choisissez le compte SMTP unique à utiliser… »** : la stratégie **Compte unique** est choisie sans compte. Sélectionnez un compte, ou repassez en **Rotation automatique**.
- **« Seules les campagnes en brouillon peuvent être programmées. »** : la campagne a déjà été lancée ou programmée (par exemple depuis un autre onglet). Rechargez la liste.
- **Programmer reste grisé alors que tout est au vert** : le test A/B est activé mais incomplet. Ouvrez la carte **Test A/B** et corrigez-la (« Les objets des versions A et B doivent être différents. », « Les versions A et B du test A/B sont obligatoires. »), ou désactivez le test.
- **La campagne reste « En cours » sans que rien ne parte** : aucun compte SMTP n'est disponible (désactivé, quota atteint). Les messages sont retentés toutes les 5 minutes ; vérifiez les [comptes SMTP](help:smtp).
- **Beaucoup de rebonds dès les premières minutes** : mettez la campagne en pause, puis vérifiez la qualité de la liste. Un envoi massif vers des adresses invalides abîme durablement la délivrabilité.
- **Le contenu envoyé n'est pas le bon** : une campagne en cours n'est plus modifiable. Annulez l'envoi, dupliquez la campagne, corrigez le design, puis relancez.
- **Le nombre de destinataires est inférieur à celui de la liste** : les adresses de la [liste de suppression](help:suppression), les doublons et les exclusions sont retirés avant l'envoi.
