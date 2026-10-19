# 46 — Editor Spike: GrapesJS + grapesjs-mjml in the Fluent shell (WP-60S)

Status: **decision memo** for WP-60 (EmailBuilder core), WP-61 (sections / Style tab) and WP-63
(rendering pipeline). Spike for doc 43 §43.10 item 3, de-risking §43.9 "Editor engine". The spike
page `resources/js/Pages/Lab/EditorSpike.tsx` (`GET /lab/editor-spike`, `lab.editor-spike`,
`can:templates.manage`, not in the nav) is throw-away and is deleted when WP-60 lands.

Inputs: doc 43 §43.4.4, §43.6.7, §43.9 · doc 45 §45.6.1 + `Lib/types/editor.ts` · doc 44 §44.10
item 7 · doc 07 §7.12.

The Lab page hardcodes its labels in French (it is not a product page and `fr.ts` is frozen for
this package). WP-60 moves every label into `fr.ts` (`editor.*`), see §7.

---

## 1. Go / No-go

**GO** — GrapesJS 0.23.6 + grapesjs-mjml 1.0.8 work inside the Fluent shell. The editor is a lazy
chunk of **680.71 kB gzip** (2,423.73 kB raw) + 13.14 kB gzip CSS, well under the **1.2 MB gzip**
budget, which applies to the main chunk anyway. The main chunk does not contain GrapesJS or MJML.
It grows only by the spike page and the Fluent components that page uses for the first time.

Measured with `npm run build` (Vite 7.3.6) in the WP-60S worktree. `npx tsc --noEmit` passes, and
`npm run build` passes with one warning: chunks are larger than `chunkSizeWarningLimit: 1000`.

| Chunk | Before (Wave 0 HEAD) raw / gzip | After (spike) raw / gzip | Notes |
|---|---|---|---|
| `app-*.js` (entry) | 271.37 kB / 78.90 kB | 287.30 kB / 83.96 kB | +5 kB gzip = spike page code (eagerly globbed) |
| `fluentui-*.js` (entry import) | 658.27 kB / 179.83 kB | 694.39 kB / 186.24 kB | +6.4 kB gzip: Toolbar, Dialog, TabList, Select first used |
| `vendor-*.js` (entry import) | 317.81 kB / 101.11 kB | 317.81 kB / 101.11 kB | unchanged |
| `app-*.css` | 15.00 kB / 3.74 kB | 18.93 kB / 4.26 kB | Tailwind picks up a few class-like words from the new sources |
| **Initial load total (JS)** | **359.84 kB gzip** | **371.31 kB gzip** | budget 1.2 MB → OK |
| `engine-*.js` (**lazy**) | — | **2,423.73 kB / 680.71 kB** | grapesjs + grapesjs-mjml (which bundles mjml-browser) |
| `engine-*.css` (lazy) | — | 64.25 kB / 13.14 kB | grapes.min.css + Fluent remap |
| `compile-*.js` (lazy, optional) | — | 1,199.35 kB / 360.63 kB | standalone `mjml-browser`, **redundant**, see below |
| Build time | 11–16 s | 25–28 s | |

How the parts add up (gzip -9 of the published dist files): `grapesjs/dist/grapes.min.js` 294 kB,
`grapesjs-mjml/dist/index.js` 348 kB (a webpack UMD bundle that **already contains mjml-browser**),
`mjml-browser/lib/index.js` 354 kB.

- **No duplicate MJML parser.** Importing `mjml-browser` on its own adds a second copy of the parser
  (+360 kB gzip). The npm dist of grapesjs-mjml cannot share its copy. Inside the editor, WP-60
  compiles with the plugin's own command:
  `editor.runCommand('mjml-code-to-html', { mjml })` → `{ html, errors }`. This took 11–20 ms for
  1.8 kB of MJML. The spike measured the standalone `mjml-browser` route as a separate lazy chunk
  (`compile.ts`: 22–45 ms for the same input) and both produce the same output. WP-60 should not
  import `mjml-browser` at all unless it needs to compile without the editor. The server compile
  already covers previews (§4).
- **Runtime checks.** These ran in headless Chromium against a Vite dev harness with Inertia mocked.
  The harness lives outside the repo and is not committed. Checked: the lazy module loads and
  initialises in 0.7–0.9 s (dev build, unminified); MJML loads; blocks drag from the Fluent palette
  into the canvas; the Fluent properties panel writes MJML attributes; undo and redo work; the
  desktop/mobile toggle sets the frame to 375 px; the merge-tag menu inserts a tag in a text block;
  a section saves as a block; save produces `design_json`, MJML and HTML with 0 MJML errors; after a
  reload from `design_json.project`, the MJML is byte-identical.
- **Not exercised:** the Laravel route and the `can:templates.manage` middleware, because no PHP was
  run in this package.

## 2. Storage format

Keep the frozen contract `EmailDesign` (doc 45 §45.6.1) as it is. The spike confirmed its shape:

```jsonc
// design_json (campaigns.design_json, templates.design_json, A/B variants)
{
  "format": "grapesjs-mjml",
  "version": 1,
  "mjml": "<mjml lang=\"fr\"><mj-head><mj-attributes><mj-all font-family=\"Arial, sans-serif\"></mj-all><mj-text color=\"#242424\" font-size=\"14px\" line-height=\"1.5\"></mj-text><mj-section background-color=\"#FFFFFF\"></mj-section><mj-button background-color=\"#0F6CBD\" color=\"#FFFFFF\" border-radius=\"4px\" font-weight=\"600\"></mj-button></mj-attributes><mj-style>a { color: #0F6CBD; text-decoration: underline; }</mj-style></mj-head><mj-body background-color=\"#F5F5F5\" width=\"600px\"><mj-section>…</mj-section></mj-body></mjml>",
  "project": {                       // editor.getProjectData() — abridged
    "assets": [], "styles": [], "symbols": [], "dataSources": [],
    "pages": [{ "frames": [{ "component": { "type": "wrapper", "components": [
      { "type": "mjml", "tagName": "mjml", "components": [
        { "type": "mj-body", "tagName": "mj-body", "attributes": { "background-color": "#F5F5F5", "width": "600px" },
          "components": [{ "type": "mj-section", "tagName": "mj-section", "components": ["…"] }] }
      ] } ] } }], "id": "…" }]
  },
  "global_styles": { "font_family": "Arial, sans-serif", "text_color": "#242424", "link_color": "#0F6CBD",
    "link_underline": true, "background_color": "#F5F5F5", "content_background_color": "#FFFFFF",
    "content_width": 600, "button": { "background_color": "#0F6CBD", "text_color": "#FFFFFF",
    "border_radius": 4, "font_weight": "600" }, "mobile": { "font_scale": 1, "hide_on_mobile_padding": false } }
}
```

Measured on the sample design: MJML 1.8 kB, project JSON 13.5 kB (about 7× the MJML), whole
`design_json` 15.8 kB, compiled HTML 17–19 kB. These sizes fit a JSON column.

Rules found in the spike, which WP-60 must keep:

1. **`mjml` is the source of truth for the server. `project` exists only so the editor can reopen
   the design faithfully.** Opening an existing design uses `editor.loadProjectData(project)`.
   Re-importing the MJML would also work, but it loses GrapesJS-only metadata such as dynamic-content
   rules, locks and saved-section ids.
2. **The `<mj-head>` is never inside the editor.** grapesjs-mjml 1.0.8 has no `mj-attributes`
   component type, so the children of `mj-attributes` (`mj-button`, `mj-section`…) show up as visible
   empty components on the canvas. The canvas also renders each component through
   `<mjml><mj-body>…` without the head, so head defaults are not visible there either. The fix:
   - The editor holds only `<mjml><mj-body>…</mj-body></mjml>`.
   - `global_styles` is turned into the head (`mj-attributes` + `mj-style`) at save time
     (`composeMjml` in `Lab/editorSpike/design.ts`).
   - An incoming `<mj-head>` is removed before import (`splitMjml`).
   - `<mjml lang="fr">` is set, which makes the HTML `lang="fr"` instead of `und`.
3. **Expand self-closing MJML tags before import.** The default GrapesJS HTML parser treats
   `<mj-image … />` as an opening tag and nests the following siblings inside it.
   `expandSelfClosingMjml()` rewrites these tags to `<mj-image …></mj-image>`. The plugin's
   `useXmlParser` option also fixed it, but it is marked experimental and expects well-formed XML
   inside `mj-raw` and `mj-text`, so it was not adopted.

## 3. Block list feasibility (doc 43 §43.4.4, `EmailBlockType`)

grapesjs-mjml 1.0.8 provides these component types: `mj-body`, `mj-wrapper`, `mj-section`,
`mj-group`, `mj-column`, `mj-text`, `mj-button`, `mj-image`, `mj-divider`, `mj-spacer`, `mj-social`,
`mj-social-element`, `mj-navbar`, `mj-navbar-link`, `mj-hero`, `mj-raw`, plus head types (`mj-head`,
`mj-style`, `mj-font`, `mj-title`, `mj-preview`). There is no `mj-table`, `mj-accordion`,
`mj-carousel` or `mj-attributes`. The plugin's own blocks were turned off (`blocks: []`) and
every block was registered under our keys (`Lab/editorSpike/blocks.ts`).

| Key | Status | Built from | Prototyped | Notes for WP-60/61 |
|---|---|---|---|---|
| `title` | composed | `mj-text` with font-size/weight | yes | presets H1/H2/H3 in the properties panel |
| `text` | native | `mj-text` | yes | RTE + **Personnaliser** menu (RTE action `<select>`) works |
| `image` | native | `mj-image` | yes | asset manager → WP-60 upload endpoint |
| `button` | native | `mj-button` | yes | |
| `divider` | native | `mj-divider` | yes | |
| `spacer` | native | `mj-spacer` | yes | |
| `social` | native | `mj-social` + `mj-social-element` | yes | MJML loads its icons from `www.mailjet.com/images/theme/v1/icons/ico-social/`. WP-61 sets `icon-src` to icons we host ourselves |
| `columns` 1–3 | native | `mj-section` + n × `mj-column` | yes | |
| `columns` 4 | composed | `mj-section` + 4 × `mj-column` | yes | MJML stacks columns on mobile, nothing to add |
| `html` | native | `mj-raw` | yes | the server sanitises the content (WP-63) |
| `video` | composed | `mj-image` + `href` + play-thumbnail | yes | the thumbnail (oEmbed, play overlay) is server work for WP-61 |
| `logo` | composed | `mj-image` width 120 px | yes | default `src` = Settings/Branding logo |
| `navigation` | native | `mj-navbar` + `mj-navbar-link` | yes | the hamburger menu only works in some email clients; default `hamburger` off |
| `product` | composed | `mj-section` (image column + text/price/button column) | yes | static card, as §43.4.4 requires |
| `saved_section` | composed | section MJML (`component.toHTML()`) + `component.toJSON()` | yes ("Enregistrer la section") | maps to `SavedSection.mjml` / `.component`; reinserting from the MJML is enough |
| `dynamic_content` | **custom** | new GrapesJS type extending `mj-wrapper` | no | see below |

`dynamic_content` needs a GrapesJS component type `pj-dynamic` that extends `mj-wrapper`. Its
variants are child wrappers, and the `RuleTree` condition is stored as a component property. On
export, each variant is wrapped in `mj-raw` comment markers (`<!--pj-dc:{id}:{n}-->`). WP-63 then
keeps one segment per recipient. This requires the **server compile to keep comments**
(`keepComments: true`, no minifier that strips them). The contract does not say where the
`DynamicContentBlock` rules live inside `design_json` (see the open questions). Estimate: 3–4 days
across WP-61 and WP-63.

## 4. Server-side compile

| Option | Pros | Cons |
|---|---|---|
| **A. `spatie/mjml-php` + npm `mjml` (Node on the app server)** | One PHP call (`Mjml::new()->convert($mjml)` → html + errors), runs in-process from the queue/web worker. Node is already a server dependency because thumbnails use Playwright/Chromium (§43.6.7). Same MJML version as the client, so the output is identical. | Spawns a Node process per compile (~100–200 ms, fine for save/validate). `node` must be on PATH in prod/CI. |
| B. Node sidecar (HTTP service wrapping `mjml`) | Warm process, fastest, isolated | Another service to deploy, monitor and secure. Too much for v1. |
| C. Trust the client-compiled HTML + server sanitisation | No Node on the server | Anyone can POST arbitrary HTML as `html_body`. Breaks the contract ("server re-validates the MJML and stores **its** HTML"). Imports and the API have no client compiler. Rejected. |

**Recommendation: A.** `MjmlCompiler` wraps `spatie/mjml-php`:

- `validationLevel` is `strict` for `/templates/mjml/validate`, and the save path returns its errors
  as 422 `design_json.mjml`.
- `keepComments` stays on (needed for dynamic content, §3).
- The 1 MB input limit from the contract applies.
- The server sanitises the content of `mj-raw` / HTML blocks after compiling.
- The client-compiled HTML is used only for the instant preview and is never stored.
- B stays the fallback if process spawning is forbidden in production.

Dependency lines for WP-60 (WP-63 owns `MjmlCompiler` if the orchestrator prefers):

```jsonc
// composer.json  "require"
"spatie/mjml-php": "^1.3"          // 1.3.0 (2026-07-23) requires php ^8.2, symfony/process ^6.3.2|^7|^8 — confirm against composer.json
// package.json  "dependencies"   (server-side compiler used by spatie/mjml-php, must match the client parser)
"mjml": "4.18.0"
// package.json  "dependencies"   (already added by WP-60S)
"grapesjs": "0.23.6",
"grapesjs-mjml": "1.0.8",
"mjml-browser": "4.18.0"           // drop in WP-60 unless an editor-less browser compile is needed (§1)
```

Keep MJML on **4.18.x**. grapesjs-mjml 1.0.8 depends on `mjml-browser ^4.18.0` and bundles it.
MJML 5.x (latest 5.4.1) changes the compile API and must not be mixed in until the plugin moves to
it.

## 5. Fluent restyle approach

What worked:

- **Fluent chrome, GrapesJS for the canvas only.** The editor is created with
  `panels: { defaults: [] }`, so GrapesJS draws no panels. The toolbar (undo/redo, device toggle,
  save…), the block palette and the properties panel are React + Fluent components. GrapesJS renders
  only the canvas, plus the style and layer managers, each placed in a Fluent tab container.
- **Block palette in React.** With `blockManager: { custom: true }`, a Fluent card with `draggable`
  calls `editor.Blocks.startDrag(block, nativeEvent)` on `dragstart` and `editor.Blocks.endDrag()` on
  `dragend`. Native HTML5 drag-and-drop into the canvas iframe works, including in a headless
  Chromium mouse drag.
- **Theme by remapping CSS variables, not by forking grapes.min.css.** FluentProvider exposes every
  token as a CSS custom property, so `.pj-gjs { --gjs-primary-color: var(--colorNeutralBackground1);
  --gjs-tertiary-color: var(--colorBrandBackground); … }` themes all GrapesJS chrome, about 30
  variables plus a few rules for fields, sectors, layers, the RTE toolbar and modals
  (`Lab/editorSpike/fluent-gjs.css`). The selection toolbar and badge use brand yellow with dark
  text. Light and dark would follow FluentProvider automatically.
- **A Fluent "Propriétés" panel per component type** (`Lab/editorSpike/properties.ts`): Fluent
  `Field`/`Input`/`Select` read `component.getAttributes()` and write `component.addAttributes()`.
  grapesjs-mjml keeps attributes and styles mirrored, so undo, export and the style manager stay
  consistent. This should be the primary UI. The GrapesJS style manager should be at most an
  "advanced" tab.
- **French UI strings.** The `grapesjs/locale/fr.mjs` locale is loaded with
  `i18n: { locale: 'fr', detectLocale: false }`, and French component names are passed to the
  plugin's `i18n` option.

What did not work, or needed a workaround:

- **The canvas is an iframe and does not inherit CSS variables.** Selection and hover outlines must
  be literal colours, passed through `canvasCss`. A dark theme would mean re-injecting them on theme
  change.
- **The plugin's own theme fights the remap.** grapesjs-mjml's `useCustomTheme` must be `false`.
  `resetDevices: false` plus our own `deviceManager.devices` (desktop, mobile 375 px) avoids its
  device panel.
- **Global styles on the canvas.** Values set in the head are not shown on the canvas (§2.2). The
  fix that worked: write the global styles into each type's `style-default`
  (`applyGlobalStyleDefaults`: `mj-text`, `mj-button`, `mj-section`) **before** components load.
  The canvas then shows the brand button, and grapesjs-mjml strips attributes equal to
  `style-default` on export, so the MJML stays clean and the head applies the values. The catch:
  when the Style tab changes a value later, WP-61 must move existing components whose attribute
  still equals the old default to the new value.
- **Some labels are hardcoded in English.** Style-manager sector names and the
  "Top/Right/Bottom/Left" sub-labels must be relabelled or translated through `fr.ts` messages.
  grapesjs-mjml ships only `en` messages.
- **The GrapesJS style manager looks generic and CSS-like**, even after restyling. This is another
  reason to keep the per-block Fluent panel as the primary UI.

## 6. Risks and fallback

| Risk | Likelihood / impact | Mitigation |
|---|---|---|
| grapesjs-mjml 1.0.8 is tested against grapesjs 0.21 (devDependency) while we run 0.23.6 | M / M | All spike flows passed on 0.23.6. Pin exact versions and add a Playwright smoke test of the builder (§44.10 item 6, Wave 3) |
| Plugin gaps: no `mj-attributes`; head not used on the canvas; HTML parser and self-closing tags | known / M | Workarounds in §2 and §5, already coded in the spike |
| Editor chunk is 681 kB gzip, so first open is slow on slow links | M / L | Lazy-loaded already. Prefetch `import()` when the user reaches the campaign "Conception" step; add a `manualChunks` split for `grapesjs` / `grapesjs-mjml` (only loaded lazily) |
| Licence: grapesjs and grapesjs-mjml are **BSD-3-Clause** (doc 43 says MIT); mjml is MIT | L / L | Both are permissive. Add attribution to the licences / about notice |
| `backbone-undo@0.2.6` (transitive) is deprecated on npm | L / L | Watch upstream. No runtime issue seen |
| Dynamic content needs a custom type plus server segment handling | M / M | §3 design, 3–4 days |
| Merge-tag syntax in the spike (`{{ contact.first_name }}`) is a placeholder | — | Align with `MergeVariableResolver` (open question) |

**Fallback: our own block editor over MJML components** (doc 43 §43.9). The React block tree would
map 1:1 to MJML (section → columns → blocks) and include:

- drag-and-drop with a sortable library;
- Fluent properties panels (reused from WP-60);
- a rich-text editor for text blocks;
- our own undo stack;
- a canvas preview made by compiling the tree with `mjml-browser` into an iframe on each change;
- serialisation of the tree as `project` and of the MJML as `mjml`, so the storage contract is
  unchanged.

**Estimate: 5–7 engineer-weeks**, against about 1 week of Fluent restyling and integration on top of
the GrapesJS path. Not needed given the GO. Revisit only if the plugin blocks a must-have, for
example if dynamic content proves impossible.

## 7. Instructions for WP-60

**File layout** (`resources/js/Components/Editor/EmailBuilder/**`):

```
EmailBuilder/
  index.ts                 // export { EmailBuilderLazy } + types only — the ONLY thing pages import
  EmailBuilderLazy.tsx     // React.lazy(() => import('./EmailBuilder')) + <Suspense> with Fluent <Spinner>
  EmailBuilder.tsx         // 3-pane layout (§43.4.4): left Contenu/Style tabs, canvas, right properties
  engine/
    createEditor.ts        // grapesjs.init + grapesjs-mjml (as in Lab/editorSpike/engine.ts)
    blocks.ts              // EmailBlockType → block definitions (from Lab/editorSpike/blocks.ts)
    globalStyles.ts        // applyGlobalStyleDefaults, composeMjml/splitMjml, expandSelfClosingMjml
    mergeTags.ts           // RTE "Personnaliser" action
    dynamicContent.ts      // pj-dynamic component type (WP-61 may own)
    fluent-gjs.css         // CSS-variable remap (from Lab/editorSpike/fluent-gjs.css)
    ambient.d.ts           // module typings for grapesjs/locale/fr.mjs (+ mjml-browser if kept)
  panels/
    BlockPalette.tsx       // Blocs / Sections / Enregistrés, draggable Fluent cards
    PropertiesPanel.tsx    // per-type Fluent fields (from Lab/editorSpike/properties.ts)
    properties.ts
  useEmailBuilder.ts       // owns the editor handle: select/change subscriptions, save, device, undo
```

**Lazy-load rule.** `app.tsx` eagerly globs `./Pages/**/*.tsx`, so everything a page imports
statically ends up in the main chunk. Follow these rules:

- Pages import only `EmailBuilderLazy` (or `import type`).
- `grapesjs`, `grapesjs-mjml` and the CSS are imported only from files that are reached through
  `import()`.
- Never put engine helpers in a `.tsx` file under `Pages/`.
- Check after every build that `public/build/manifest.json` marks `EmailBuilder.tsx` as
  `isDynamicEntry`, and that neither `app-*.js` nor `fluentui-*.js` contains engine-only strings
  such as `UndoManager`, `keepComments` or `mjml-core`. In the spike build these strings appear
  27, 3 and 2 times in `engine-*.js` and 0 times in `app-*.js`. Do not use `grapesjs` or
  `mj-column` as markers: the page legitimately contains the `format: 'grapesjs-mjml'` literal and
  sample MJML.

**Init options**, as validated in the spike:

```ts
grapesjs.init({
  container, height: '100%', width: 'auto', fromElement: false,
  storageManager: false,                       // save is explicit: PUT /campaigns/{id}/design | PATCH /templates/{id}
  panels: { defaults: [] },                    // Fluent chrome lives in React
  blockManager: { custom: true },              // React palette → Blocks.startDrag / endDrag
  styleManager: { appendTo: advancedTabEl }, layerManager: { appendTo: layersTabEl },
  selectorManager: { componentFirst: true },
  canvasCss: '.gjs-selected{outline:2px solid #E6BF00!important}…',
  deviceManager: { default: 'desktop', devices: [{ id: 'desktop', name: 'Bureau', width: '' },
                                                 { id: 'mobile', name: 'Mobile', width: '375px', widthMedia: '480px' }] },
  i18n: { locale: 'fr', localeFallback: 'fr', detectLocale: false, messages: { fr: grapesFr } },
  plugins: [(ed) => grapesjsMjml(ed, { blocks: [], resetBlocks: true, resetDevices: false,
            useCustomTheme: false, hideSelector: true, i18n: { fr: { 'grapesjs-mjml': { components: { names } } } } })],
});
applyGlobalStyleDefaults(editor, design.global_styles);         // BEFORE loading components
design ? editor.loadProjectData(design.project) : editor.setComponents(expandSelfClosingMjml(splitMjml(mjml).body));
editor.UndoManager.clear();
```

**Save flow** (doc 45 §45.6.1):

1. `mjml = composeMjml(editor.runCommand('mjml-code'), globalStyles)`.
2. `{ html, errors } = editor.runCommand('mjml-code-to-html', { mjml })`. Use it for the preview
   and the client error list only.
3. `PUT /campaigns/{id}/design` with `editor_type: 'dnd'`,
   `design_json: { format: 'grapesjs-mjml', version: 1, mjml, project: editor.getProjectData(), global_styles }`
   and `html_body: html`. The server re-compiles and stores **its** HTML. Show 422
   `design_json.mjml` errors in a MessageBar.

**Mapping blocks to the Fluent properties panel.** Listen to `component:toggled` and
`component:update`, then read `cmp.get('type')` and `cmp.getAttributes()`, leaving out `id` and
`style`. Look up `FIELDS_BY_TYPE[type]`, which holds the MJML attribute name, French label (from
`fr.ts`) and kind (`text` / `color` / `select` / `number+unit`). Commit on blur or Enter with
`cmp.addAttributes({ [name]: value })`, or `removeAttributes` when the field is empty, so that each
field change is one undo step. Composed blocks such as `title`, `logo` and `video` are identified by
a component property set by the block (`attributes: { 'data-pj-block': 'title' }` is **not**
allowed in MJML; use a GrapesJS component prop such as `pjBlock: 'title'` instead). This lets the
panel show block-specific presets. The **Style** tab (WP-61) edits `global_styles` and calls
`applyGlobalStyleDefaults` plus the migration described in §5.

**Out of scope for WP-60, carried from the spike:**

- Asset upload: wire the asset manager to the upload endpoint.
- Dynamic content (§3).
- Self-hosted social icons.
- Strings: every hardcoded French label in `Pages/Lab/**` goes into `fr.ts` `editor.*`.
- Deleting `Pages/Lab/**`, `routes/web/lab.php` and its `require` line once WP-60 lands.

### Open questions

1. Where should the `dynamic_content` rules live in `EmailDesign`? Inside `project` only (the server
   would then have to parse GrapesJS JSON), or in a new `design_json.dynamic_blocks:
   DynamicContentBlock[]`? The second is a contract change for the orchestrator (doc 45 is frozen).
2. What merge-tag syntax does `MergeVariableResolver` expect? The spike uses `{{ contact.first_name }}`.
3. Should `mjml-browser` stay a direct dependency? It is only needed for an editor-less browser
   compile, and the server compile covers previews.
4. Is a Node binary allowed on the production app server for `spatie/mjml-php`? Option A assumes it
   is, as Playwright already is.
