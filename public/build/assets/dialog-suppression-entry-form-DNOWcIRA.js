const e=`# Ajouter une adresse à la liste de suppression

Cette fenêtre bloque manuellement une adresse e-mail : à partir de son enregistrement, plus aucun message ne lui sera envoyé, ni par campagne ni individuellement. Elle sert aux demandes reçues hors de l'application — un appel téléphonique, un courrier, un client qui vous dit simplement « ne m'écrivez plus ».

![Fenêtre d'ajout à la liste de suppression](/docs/screenshots/suppression/02-dialog.png)

## Les champs

| Champ | Obligatoire | Exemple |
| --- | --- | --- |
| Adresse e-mail | Oui | \`direction@menuiserie-bejaia.dz\` |
| Motif | Oui | \`Blocage manuel\` |
| Notes | Non, et visible uniquement si le motif est « Blocage manuel » | \`demande de retrait via le ticket support #1234\` |

## Choisir le bon motif

| Motif | Quand l'utiliser vous-même |
| --- | --- |
| Blocage manuel | Le cas normal pour une saisie manuelle : demande reçue par téléphone, par courrier ou en personne. |
| Rebond définitif | Vous savez de source sûre que l'adresse n'existe plus, par exemple après retour d'un serveur externe. |
| Plainte pour spam | Un destinataire vous a signalé formellement. |
| Désabonnement manuel | Vous enregistrez à sa place une demande de désabonnement. |
| Désabonnement global (tout) | Le contact refuse tout courrier de votre organisation, sans exception. |
| Adresse invalide | L'adresse est manifestement erronée ou non délivrable. |

Dans la grande majorité des cas, **Blocage manuel** est le bon choix : les autres motifs sont normalement enregistrés automatiquement par l'application quand l'événement correspondant se produit.

## Le champ Notes

Il n'apparaît que pour le motif **Blocage manuel**, et il est fortement recommandé de le remplir. Dans six mois, personne ne se souviendra pourquoi \`direction@menuiserie-bejaia.dz\` a été bloquée. Une note comme « refus explicite lors de l'appel du 12/03, contact M. Belkacem » évite un retrait hasardeux plus tard.

## Enregistrer

**Enregistrer** crée l'entrée et referme la fenêtre ; l'adresse apparaît immédiatement dans la [liste de suppression](help:suppression). **Annuler** ferme sans rien bloquer.

## En cas de problème

- **« Cette adresse est déjà présente dans la liste de suppression. »** : le blocage existe déjà, avec peut-être un autre motif. Fermez la fenêtre et recherchez l'adresse dans la liste.
- **Le champ Notes a disparu** : vous avez changé de motif. Il ne s'affiche que pour « Blocage manuel ».
- **Une erreur rouge s'affiche sous l'adresse** : le format est refusé. Retapez l'adresse complète, sans espace.
- **Vous vouliez bloquer toute une entreprise** : ce n'est pas possible en une fois. Chaque adresse se bloque individuellement ; ajoutez-les une par une.
`;export{e as default};
