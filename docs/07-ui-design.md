# 07 — UI / UX Design System

## 7.1 Design Language

The application uses **Fluent UI v9** (`@fluentui/react-components`) as its component library, styled to evoke **Microsoft Outlook** specifically (not generic Fluent/Office): a persistent left nav rail, a three-pane mailbox layout, a ribbon-like command bar, and persona/avatar treatment for contacts. Tailwind CSS is used for layout utilities (spacing, flex/grid, responsive breakpoints) and app-specific one-off styles; Fluent tokens remain the source of truth for color/typography/elevation so the two systems don't fight — Tailwind's theme is configured to read from Fluent design tokens (see 7.6).

## 7.2 Design Tokens

| Token Category | Source | Notes |
|---|---|---|
| Color | Fluent `webLightTheme` / `webDarkTheme`, customized brand ramp | Brand primary mapped to organization's brand color (configurable in Settings → Branding) |
| Typography | Fluent type ramp (`caption1`…`title1`) | Base font: Segoe UI Variable stack with system fallback |
| Spacing | Fluent spacing scale (4px base unit) | Tailwind `spacing` config aliased to same scale |
| Elevation | Fluent shadow tokens (`shadow2`,`shadow4`,`shadow8`,`shadow16`,`shadow28`) | Used for panels, dialogs, flyouts |
| Radius | Fluent corner radius tokens (`small`,`medium`,`large`) | Consistent 4/6/8px radii |
| Motion | Fluent duration/easing tokens | Standard 150–250ms transitions, respects `prefers-reduced-motion` |

Both `webLightTheme` and `webDarkTheme` are extended via `createLightTheme`/`createDarkTheme` from a single brand ramp generator, so light/dark stay in sync from one brand color input.

## 7.3 Color System

- **Neutral palette**: Fluent neutral ramp for backgrounds, borders, text — ensures WCAG AA contrast by default.
- **Brand palette**: single configurable brand color (Settings → Branding) generates a 16-step ramp via Fluent's ramp generator; used for primary actions, active nav state, links.
- **Semantic colors**: Fluent status palette mapped to domain status meanings:
  - Success/green → `delivered`, `completed`, `healthy`
  - Warning/yellow → `soft_bounced`, `degraded`, `paused`, `waiting_for_quota`
  - Danger/red → `hard_bounced`, `failed`, `rejected`, `unhealthy`, `spam_complaint`
  - Informational/blue → `queued`, `sending`, `scheduled`
  - Neutral/grey → `draft`, `cancelled`, `disabled`

Status colors are centralized in a single `statusTokens.ts` map consumed everywhere (mailbox list, campaign badges, SMTP health, message timeline) so meaning stays consistent across the app.

## 7.4 Typography Scale

| Fluent Token | Use |
|---|---|
| `title1` (28px) | Page titles (Dashboard, Campaigns) |
| `title2`/`title3` | Section headers, dialog titles |
| `subtitle1`/`subtitle2` | Card headers, list group headers |
| `body1` | Default body text, list items |
| `body2`/`caption1` | Secondary/meta text (timestamps, counts) |
| `caption2` | Micro-labels (badges) |

## 7.5 Component Library (Fluent v9 primitives mapped to app usage)

| Fluent Component | App Usage |
|---|---|
| `NavDrawer` / `NavDrawerBody` | Left navigation rail (Mailbox, Campaigns, Recipients, SMTP, Reports, Settings) |
| `Toolbar` | Command bar above mailbox list and composer (New, Send, Delete, Move, Tag) |
| `DataGrid` | Recipient lists, SMTP accounts table, campaign list, audit log |
| `TabList` / `Tab` | Campaign detail tabs (Overview, Recipients, Content, Analytics), Settings sections |
| `Dialog` | Confirmations (delete, cancel campaign), quick edit forms |
| `Drawer` (overlay/inline) | Reading pane detail, filter panel, segment builder |
| `MessageBar` | Inline warnings (quota near limit, SMTP unhealthy, unsaved changes) |
| `Persona` | Recipient/contact avatar + name + secondary text (company) |
| `Badge` / `CounterBadge` | Status pills, unread counts, queue counts |
| `Field` + input primitives | All forms |
| `Combobox` / `TagPicker` | Recipient tag selection, segment rule value pickers |
| `Menu` | Row-level context actions (right-click / "..." menu) |
| `ProgressBar` | Import progress, campaign send progress, quota usage bars |
| `Tooltip` | Truncated text, icon-only buttons |
| `Skeleton` | Loading states for lists/panels |

## 7.6 Tailwind Integration

Tailwind config extends its theme from exported Fluent tokens (`tailwind.config.ts` imports a small token-bridge module that reads the active Fluent theme object and republishes spacing/colors as Tailwind theme values) so utility classes (`p-4`, `gap-2`, `text-neutral-secondary`) stay pixel/color-consistent with Fluent components. Tailwind is used only for layout/composition; it must not redefine colors that already exist as Fluent semantic tokens.

## 7.7 Responsive Behavior

| Breakpoint | Layout Change |
|---|---|
| `< 640px` (mobile) | Nav rail collapses to bottom/hamburger drawer; mailbox becomes single-pane (list OR reading pane, not both) with back navigation |
| `640–1024px` (tablet) | Two-pane mailbox (list + reading pane), nav rail collapses to icon-only rail |
| `> 1024px` (desktop) | Full three-pane where applicable (nav rail + list + reading pane), composer opens as a full overlay or docked panel |

Composer specifically supports desktop/tablet/mobile/dark/print preview modes as first-class states — see [11-email-composer.md](11-email-composer.md#117-preview-modes).

## 7.8 Accessibility

- WCAG 2.1 AA target across the app.
- All interactive elements keyboard-reachable in logical tab order; Fluent components provide this by default — custom components (drag-and-drop block editor) must implement keyboard-equivalent reordering (see [11-email-composer.md](11-email-composer.md)).
- Color is never the sole indicator of status — every status badge pairs color with an icon and text label.
- Focus states visible (Fluent's default focus outline retained, never suppressed).
- `aria-live` regions for async updates (import progress, send progress, toast notifications).
- Minimum contrast ratio 4.5:1 for text enforced by using only Fluent semantic tokens (no ad hoc hex colors).
- Dark mode is a fully supported theme, not just an inverted filter — verified against the same contrast requirements.

## 7.9 Keyboard Shortcuts (Outlook-inspired)

| Shortcut | Action |
|---|---|
| `Ctrl/Cmd + N` | New email |
| `Ctrl/Cmd + Enter` | Send |
| `Ctrl/Cmd + S` | Save draft |
| `Delete` | Delete selected message(s) |
| `Ctrl/Cmd + F` | Search |
| `J` / `K` | Next/previous message in list (Outlook web parity) |
| `Ctrl/Cmd + Shift + U` | Toggle unread |
| `Esc` | Close reading pane / dialog / composer overlay |
| `Ctrl/Cmd + K` | Insert link (in composer) |
| `?` | Show shortcut cheat sheet |

Shortcut map is centralized in a single `useKeyboardShortcuts` hook so it can be introspected for the `?` help dialog and remains consistent across pages.

## 7.10 Iconography

Fluent System Icons (`@fluentui/react-icons`) exclusively, using the `Regular` icon set at rest and `Filled` variant for active/selected nav states — matches Outlook's own icon behavior. No mixing of icon libraries.

## 7.11 Theming & Branding (Settings-driven)

Administrators can configure (Settings → Branding, see [09-dashboard.md](09-dashboard.md) is dashboard-only; branding lives in Settings, referenced across app):
- Organization name/logo (used in nav header and default email footer branding)
- Brand color (drives the Fluent brand ramp)
- Default theme (light/dark/system) as the organization default, with per-user override persisted to `users` preference (a `preferences jsonb` column can be added to `users` if per-user theme override is required — noted as a minor schema extension point, not in v1 base schema unless requested).

## 7.12 Langue — Français uniquement

**L'application entière est monolingue française.** Il n'y a ni sélecteur de langue, ni infrastructure i18n multi-locale dans la v1 — c'est une exigence produit explicite, pas une simplification technique à généraliser plus tard sans qu'on le demande.

- **Toute l'interface** — libellés, boutons, menus, messages d'erreur/validation, notifications, e-mails système (confirmation de désabonnement, alertes internes), contenu des pages vides, info-bulles, raccourcis clavier ( §7.9, ex. "Nouvel e-mail" au lieu de "New Email") — est rédigée en français. Aucun texte d'interface en anglais ne doit apparaître dans l'application livrée.
- Les valeurs techniques des colonnes `status`/enum (ex. `queued`, `hard_bounced`, `draft` — voir [04-database-design.md](04-database-design.md)) restent des **identifiants internes en anglais** (code, base de données, API) mais ne sont **jamais affichées telles quelles** à l'utilisateur : chaque module maintient une table de correspondance identifiant → libellé français, centralisée comme les jetons de couleur de statut (§7.3). Exemples de correspondances à respecter partout où un statut est affiché :

| Identifiant interne | Libellé français affiché |
|---|---|
| `draft` | Brouillon |
| `scheduled` | Programmé |
| `running` | En cours |
| `paused` | En pause |
| `completed` | Terminé |
| `cancelled` | Annulé |
| `queued` | En file d'attente |
| `sending` | Envoi en cours |
| `delivered` | Distribué |
| `opened` | Ouvert |
| `clicked` | Cliqué |
| `soft_bounced` | Rebond temporaire |
| `hard_bounced` | Rebond définitif |
| `failed` | Échec |
| `rejected` | Rejeté |
| `spam_complaint` | Signalement spam |
| `unsubscribed` | Désabonné |
| `healthy` / `degraded` / `unhealthy` | Sain / Dégradé / Défaillant |

- Bien qu'il n'y ait qu'une seule langue active, Laravel reste configuré avec `app.locale = fr` (et `fallback_locale = fr`, pas `en`) et tous les textes traduisibles passent par les fichiers de langue standards (`lang/fr/*.php`) plutôt que d'être codés en dur dispersés dans les vues — non pas pour préparer une future traduction, mais parce que c'est le mécanisme natif de Laravel pour centraliser et faire relire les textes, ce qui facilite la cohérence terminologique (voir aussi le glossaire ci-dessous).
- Idem côté React : les chaînes d'interface vivent dans un unique dictionnaire de textes français (`resources/js/Lib/i18n/fr.ts`), consommé par un hook simple (`useText()`/`t()`), sans bibliothèque de bascule de langue (pas de `react-i18next` avec sélecteur — un unique dictionnaire statique suffit et évite toute dérive vers d'autres langues).
- Formats de date/heure, nombres et devise suivent la convention française (`jj/mm/aaaa`, séparateur décimal virgule) via les locales `Intl`/Fluent configurées en `fr-FR` (ou `fr-DZ` si les conventions algériennes locales — pertinent vu l'origine du référentiel PageJaunes, voir [06-pagejaunes-integration.md](06-pagejaunes-integration.md) — doivent primer ; à confirmer avec l'organisation).
- Les données sources (ex. `wilaya_label`, `company_name` importés de PageJaunes) sont affichées telles quelles (ce sont des données, pas de l'interface) même si elles contiennent occasionnellement de l'arabe/anglais — cette règle de monolinguisme ne s'applique qu'au texte d'interface généré par l'application, pas aux données métier importées.

## 7.13 Outillage de développement — Serveur MCP Fluent UI

L'équipe d'implémentation doit connecter et utiliser un **serveur MCP Fluent UI** pendant toute la phase de développement du frontend, plutôt que de s'appuyer sur la mémoire du modèle ou une documentation Fluent potentiellement obsolète.

**Précision importante** : il n'existe pas (à la date de rédaction) de serveur MCP officiel publié par Microsoft. Microsoft a indiqué développer sa propre version interne, sans date de publication publique connue ([discussion GitHub microsoft/fluentui#35732](https://github.com/microsoft/fluentui/discussions/35732)). En attendant, l'outil communautaire de référence est :

- **`fluentui-mcp`** (auteur : blendsdk), publié sur npm — [npmjs.com/package/fluentui-mcp](https://www.npmjs.com/package/fluentui-mcp), documentation à [blendsdk.github.io/fluentui-mcp](https://blendsdk.github.io/fluentui-mcp/). Ce serveur analyse le code source de Fluent UI pour extraire documentation, types, exemples et patterns, puis les sert à la demande à l'agent IA — exactement l'usage recherché ici.
- Installation type (à valider par l'équipe DevOps/outillage au moment de la mise en place, sans dépendance figée dans ce document d'architecture) : `npm install -g fluentui-mcp` puis déclaration du serveur dans la configuration MCP de l'environnement de développement (Claude Code, Cline, etc.).
- Si Microsoft publie une version officielle avant l'implémentation, celle-ci doit être préférée à l'outil communautaire — cette section documente l'**intention** (toujours vérifier l'API Fluent UI v9 réelle via un serveur MCP plutôt que deviner), pas un outil figé dans le temps.

Concrètement :
- Avant d'implémenter ou de modifier un composant Fluent (Toolbar, DataGrid, NavDrawer, Dialog, Persona, etc., voir §7.5), interroger le serveur MCP Fluent UI pour obtenir la signature de props exacte, les variantes disponibles et un exemple d'utilisation à jour, plutôt que de deviner l'API.
- Ceci garantit que l'implémentation reste alignée avec la version de Fluent UI v9 réellement installée (les props/variantes évoluent entre versions mineures) et réduit le risque d'utiliser une API dépréciée.
- Cette exigence est un **processus d'implémentation**, pas une contrainte d'architecture : elle ne change aucune décision de conception documentée dans ce dossier, elle conditionne uniquement la façon dont les développeurs/IA doivent produire le code Fluent UI correspondant.

Continue to [08-navigation.md](08-navigation.md).
