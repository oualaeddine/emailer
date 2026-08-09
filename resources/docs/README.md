# Documentation utilisateur intégrée

Ce dossier contient le **contenu** de l'aide affichée dans l'application : le
centre de documentation (`/help`), le menu « Aide » de la barre supérieure et
le bouton `?` présent dans les fenêtres.

Ce n'est pas de la documentation technique — les spécifications de
développement restent dans `docs/` à la racine du dépôt.

## Organisation

```
resources/docs/
  fr/pages/<slug>.md      ar/pages/<slug>.md      une page de l'application
  fr/dialogs/<slug>.md    ar/dialogs/<slug>.md    une fenêtre / un assistant
  fr/flows/<slug>.md      ar/flows/<slug>.md      un guide pas à pas multi-pages
```

L'interface de l'application est en français uniquement
(`docs/07-ui-design.md` §7.12) ; seule la **documentation** est bilingue
français / arabe. Si un fichier arabe manque, le texte français est affiché à
sa place avec un avertissement — rien ne casse.

## Ajouter une rubrique

1. Créer `fr/<kind>/<slug>.md` et `ar/<kind>/<slug>.md`.
2. Ajouter une entrée dans `resources/js/Lib/docs/registry.ts` (titre FR/AR,
   groupe, permissions, route documentée, mots-clés, rubriques liées).

C'est tout : le sommaire du centre de documentation, la recherche, le filtrage
par permissions et l'aide contextuelle se mettent à jour automatiquement.
Chaque fichier `.md` est chargé à la demande dans son propre morceau de bundle.

## Conventions d'écriture

- Le HTML brut n'est **pas** interprété. Markdown GFM uniquement (tableaux,
  listes, blocs de code).
- Liens internes : `[texte](help:<slug>)` ouvre une autre rubrique sur place.
- Liens vers l'application : `[texte](/campaigns)` navigue dans l'application.
- Captures d'écran : `![Légende](/docs/screenshots/<slug>/<nn>-<nom>.png)`.
  Un clic ouvre la capture en grand. Une capture absente s'affiche comme un
  encadré « Capture d'écran à venir » — la page reste lisible.

## Captures d'écran

Elles sont produites automatiquement, jamais à la main :

```
npm run docs:screenshots         # migre + peuple une base dédiée, compile, capture
npm run docs:screenshots:fast    # idem sans recompiler les assets
```

Le script (`scripts/capture-screenshots.ts`) utilise une base MySQL jetable
`pagejaunes_mailer_docs` et le jeu de démonstration `AlgeriaB2bDemoSeeder`,
afin que les captures montrent toujours les mêmes données. Les fichiers sont
écrits dans `public/docs/screenshots/`. Après une modification visuelle de
l'interface, relancer la commande suffit à remettre toutes les captures à jour.
