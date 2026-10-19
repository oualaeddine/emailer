# 43 — Brevo Parity Plan ("my own Brevo")

## 43.1 Goal

Turn PageJaunes Mailer into a self-hosted equivalent of **Brevo's email-marketing product**: the same
information architecture, the same user pages, the same campaign-building flow and the same reports,
while keeping what already makes this app different (French-only UI, Fluent UI v9 design system,
multi-SMTP rotation/throttling, PageJaunes directory as a recipient source, mandatory 2FA, RBAC, audit).

"Same user experience" is interpreted as: a Brevo user who opens this app should recognise where
everything is, how a campaign is created (Setup page → Design → Recipients → Schedule → Review), how
contacts are organised (Contacts / Lists / Segments / Attributes / Forms / Import) and how a campaign
report reads (Overview / Deliverability / Opens / Clicks / Unsubscribes).

### In scope (Brevo *email* feature set)

Email campaigns, templates and drag-and-drop editor, contacts (lists, segments, attributes, forms,
import, blocklist), senders & domain authentication, campaign reports and global statistics,
A/B testing, send-time optimisation, transactional email (SMTP/API, templates, logs), subscription
and preference pages, a first version of marketing automation.

### Out of scope (Brevo products we do not clone)

SMS, WhatsApp, push, chat/Conversations, CRM Deals/Tasks/Meetings, Payments, landing-page builder,
Facebook ads, the Brevo template *gallery licensing* (we ship our own starter gallery).
Multi-tenancy stays out ([34-roadmap.md §34.3](34-roadmap.md#343-explicitly-deferred-not-in-v1)).

### Sources used for the Brevo reference

Brevo's help center (`help.brevo.com`) blocks scripted access, so its articles were used through
search excerpts; the developer reference (`developers.brevo.com`) is reachable and gives the exact
field and statistics model the product exposes. Page flows were cross-checked against two
step-by-step tutorials with screenshots.

| Topic | Source |
|---|---|
| Campaign object: fields, statuses, recipients, UTM, A/B, statistics breakdowns | https://developers.brevo.com/reference/get-email-campaign |
| Contact object: attributes, blocklist flags, list ids, update/merge rules | https://developers.brevo.com/reference/createcontact |
| Webhook event catalogue (transactional + marketing) | https://developers.brevo.com/docs/how-to-use-webhooks |
| Campaign creation flow (setup cards, Preview & Test, schedule options) | https://help.brevo.com/hc/en-us/articles/4413566705298, https://www.glitter.io/guides/create-contacts-send-email-campaigns-brevo |
| Full product walkthrough (menus, forms, automations, statistics) | https://www.emailtooltester.com/en/reviews/brevo/tutorial/ |
| A/B test campaign | https://help.brevo.com/hc/en-us/articles/4523165348626 |
| Campaign report tabs and export | https://help.brevo.com/hc/en-us/articles/19764406559506 |
| Campaign list actions (resume, duplicate, archive, send to new contacts) | https://help.brevo.com/hc/en-us/articles/4411394977810 |
| Drag & Drop editor (Content › Blocks / Sections / Saved, Design) | https://help.brevo.com/hc/en-us/articles/360016831820, https://help.brevo.com/hc/en-us/articles/360016873319 |
| Contacts page (views, columns, filters, load list/segment) | https://help.brevo.com/hc/en-us/articles/4408399178898, https://help.brevo.com/hc/en-us/articles/25889576395922 |
| Segments and available conditions | https://help.brevo.com/hc/en-us/articles/4411804163090, https://help.brevo.com/hc/en-us/articles/14902945335954 |
| Contact attributes (7 types, limits) | https://help.brevo.com/hc/en-us/articles/10617359589906 |
| Unsubscribe page customisation | https://help.brevo.com/hc/en-us/articles/208772629 |

Before each work package starts, the implementer should open the matching Brevo page side by side
and adjust labels/ordering; the plan fixes the structure, not pixel-level copy.

### Brevo facts that shape this plan (from the sources above)

- **Campaign statuses**: `draft`, `queued`, `in_review`, `in_process`, `sent`, `suspended`,
  `cancelling`, `cancelled`, `archive`. The list page filters on Draft / Scheduled / Running / Sent
  / Suspended / Archived; archived campaigns are hidden from "All"; only Sent, Suspended or
  Cancelled campaigns can be archived; Suspended ones get *Resume*; Sent ones get *Send to new
  contacts* (resend to contacts added to the lists since the send).
- **Recipients**: a campaign has `lists`, `exclusionLists`, `segments`, `excludedSegments`, plus
  an optional `toField` (how the To header is rendered, e.g. `{FNAME} {LNAME}`).
- **Setup fields**: `sender {name,email}`, `replyTo`, `subject`, `previewText`, `tag`/`tags`,
  `mirrorActive`, `inlineImageActivation`, `header`/`footer`, `attachmentUrl`, UTM
  (`utmSource`, `utmMedium`, `utmCampaignValue`, `utmContent`, `utmTerm`, `utmId`),
  `emailExpirationDate {duration, unit}`, `unsubscriptionPageId`, `updateFormId`,
  `testSent`, `shareLink` (public mirror).
- **A/B**: `abTesting`, `subjectA`/`subjectB`, `splitRule` (sample %), `winnerCriteria`
  (`open`|`click`), `winnerDelay` (hours).
- **Scheduling**: Send now · Schedule for later · Send at best time · Send in batches
  (`sendAtBestTime`, `scheduledAt`; batches spread delivery over time).
- **Statistics**: `globalStats` and per-list `campaignStats` with `sent`, `delivered`,
  `hardBounces`, `softBounces`, `complaints`, `unsubscriptions`, `viewed`, `uniqueViews`,
  `trackableViews`, `estimatedViews`, `appleMppOpens`, `uniqueClicks`, `clickers`, `deferred`,
  `returnBounce`, `opensRate`; plus `mirrorClick`, `remaining`, `linksStats` (per URL),
  `statsByDomain`, `statsByDevice` (desktop/mobile/tablet/unknown), `statsByBrowser`. Report tabs:
  Overview (with *Campaign audience* and *Timeline*), Deliverability, Opens, Clicks,
  Conversions, Revenue, Unsubscribes. We implement all but Conversions/Revenue.
- **Contacts**: attributes are UPPERCASE names of 7 types (text, number, date, boolean,
  category, multiple-choice, user), max 200 per account; contacts carry `emailBlacklisted`,
  `ext_id`, `listIds`; imports can *update existing* or *force merge*. The Contacts page offers
  saved **Views** as tabs, *Load a list or a segment*, *Add filter* → *Save as segment*
  (up to 100 conditions), and attribute columns of your choice.
- **Webhook events**: sent, delivered, opened, clicked, softBounce, hardBounce, invalid,
  deferred, spam, unsubscribed, blocked, error (transactional); marketing adds list addition and
  contact-update events.

## 43.2 Gap Analysis — Brevo vs. current `main`

Current state is read from the code (routes, pages, migrations), not from the design docs.

| Brevo area | Brevo UX (target) | What exists today | Gap |
|---|---|---|---|
| **Campaign list** | `Campaigns > Email`: table with status tabs (Drafts / Scheduled / Sending / Sent / Suspended / Archived), folders, search, sort, per-row Edit / Report / Duplicate / Archive / Send test, columns incl. recipients, open rate, click rate | `Pages/Campaigns/Index.tsx` list + Drawer detail, status badges, pause/resume/cancel | No folders, no archive, no per-row rates, no test send, detail is a drawer not a page |
| **Campaign builder** | Full-page **Setup** screen with cards: *From* (sender), *To* (lists/segments + exclusions), *Subject* (subject + preview text), *Design*, *Advanced* (reply-to, UTM, tags, attachment, mirror link, unsubscribe). Header: inline name, Preview, Send test, Schedule button | `CampaignWizardDialog.tsx`: a 480px dialog with Basics/Target/Schedule/Review tabs; target entered by typing a UUID | Rebuild as a page; add sender, preview text, multi-list + exclusions, advanced options, test send, checklist |
| **Design step** | Template picker modal (Gallery / My templates / Previous campaigns / Code your own / Rich text) → **Drag & Drop editor** (Content blocks, Sections, Design/global styles, Settings; desktop/mobile preview; personalisation menu; undo/redo; Save & quit) | `RichTextEditor.tsx` = contentEditable with 6 toolbar buttons; templates are raw HTML pasted in `TemplateFormDialog.tsx` | Entire block editor, design JSON storage, template gallery, HTML import/code editor |
| **Recipients step** | Pick several lists and/or segments, *exclude* lists/segments, live count, "unengaged" warning | Campaign targets exactly one `recipient_list_id` **or** one `segment_id` | Pivot tables for include/exclude, audience resolver that unions/dedups/excludes |
| **Schedule & send** | Send now / Schedule (date, time, timezone) / Send at best time; "Review and send" confirmation modal with summary | immediate or scheduled; business-hours windows; approval gate | Send-time optimisation, review modal, Brevo-style confirmation; keep business hours as an *Advanced* option |
| **A/B test** | Test subject or content, sample size %, winner by opens or clicks, test duration, auto-send winner | none (roadmap item) | Variants table, dispatcher split, winner job |
| **Campaign report** | Tabs Overview / Deliverability / Opens / Clicks / Unsubscribes; KPIs vs. averages; performance-over-time chart; clicks per link + click map; opens by domain, device, country, hour; "View contacts" per metric; Export CSV; Compare | `CampaignDetail` Analytics tab = counts from `/campaigns/{id}/analytics`; `Reporting/Index.tsx` aggregate tables | Per-link stats, device/geo/domain breakdowns, time series, metric→segment, click map, compare view |
| **Templates** | `Campaigns > Templates`: My templates (grid, folders, search, edit/duplicate/send test/delete), Template gallery, "Create a template" with same picker | `Templates/Index.tsx` grid, archive/delete, versions API | Editor, folders, gallery, duplicate, test send, thumbnail generation |
| **Contacts — All** | Table with configurable columns (attributes), filter builder, saved filter → segment, bulk actions (add to list, remove, blocklist, export, delete, update attribute), import/export buttons | `Recipients/Index.tsx` basic table (name/email/company/status/tags) + search + create | Column chooser, filter builder, bulk actions, export |
| **Contact detail** | Header (email, blocklist toggle), attribute panel, tabs Activity timeline / Lists / Notes | API `GET recipients/{uuid}` + notes exist; no page | Build page; reuse `messages`/`message_events` for timeline |
| **Lists** | Folders, list table (name, contacts, created), create, import into, export, delete; list detail with its contacts | `recipient-lists` index/store API only; no page | Pages, membership management, folders, counts |
| **Segments** | Visual condition builder (AND/OR groups; attributes, list membership, email engagement "opened/clicked campaign X in last N days", dates), live count, save | `SegmentEvaluationService` with rule tree + `segments/preview`; no page | Builder UI, engagement conditions, standalone segment CRUD |
| **Attributes** | Typed custom attributes: text, number, date, boolean, category, multiple-choice, user; used as columns, filters, merge tags | `recipients.custom_fields` untyped JSON | `contact_attributes` definitions + validation + merge-tag exposure |
| **Forms** | Subscription form builder (fields, design, double opt-in email, redirect, hosted link + embed code) | none | New module |
| **Import** | Wizard: Upload (CSV/XLSX/paste) → Map columns (create attribute inline) → Select lists → Settings (update existing / blocklist) → Launch; Import history with counts + error file | `Recipients/Import.tsx` + import_jobs/rows/errors pipeline | Re-skin as wizard, column mapping to attributes, list selection, history page |
| **Blocklist / Unsubscribes** | Blocklisted contacts view, per-contact toggle, unsubscribe page & update-profile page customisation | Suppression module + signed unsubscribe route | Rename/merge UX into Contacts; preference-page customisation |
| **Senders & Domains** | Senders list (name, email, verified, default); Domains with SPF/DKIM/DMARC records to copy and "Authenticate" check | SMTP accounts only (`Smtp/Index.tsx`) | `senders` + `sending_domains` tables, DNS check job, picker in campaign *From* card |
| **Statistics** | `Campaigns > Statistics`: global email stats over period with chart, campaign comparison table | `Reporting/Index.tsx` partially covers | Time-series + comparison; keep SMTP-performance report as our extra |
| **Transactional** | Templates, real-time logs (events per message), statistics, SMTP & API keys, webhooks | Composer/Mailbox/History cover single sends; Sanctum API exists; no API keys UI, no webhooks | API-key management page, transactional send endpoint, logs page, outgoing webhooks |
| **Automation** | Workflow builder (trigger → wait → condition → send email) | none | Phase F: minimal linear workflows |
| **Dashboard / Home** | Home with quick actions, recent campaigns, contacts growth | `Dashboard.tsx` with widgets | Minor restyle; add "Create campaign" quick action |

## 43.3 Target Information Architecture

Replace the current nav groups with Brevo's top-level sections. French labels in parentheses are
the ones the UI will display ([07-ui-design.md §7.12](07-ui-design.md#712-langue--français-uniquement)).

Brevo's left menu today is: **Contacts** (CRM: Contacts, Lists, Segments, Companies),
**Marketing** (Emails/Campaigns, Forms, Templates, Statistics), **Automations**,
**Transactional**, then the non-email products (Conversations, Deals, Meetings), with
*Settings* (Senders, Domains & IPs; Contact attributes; Users; API keys; …) behind the account
menu. We mirror that, dropping the non-email products and surfacing our extras where Brevo would
put them.

```
Accueil                          /dashboard
Contacts
  ├─ Contacts (vues)             /contacts            tabs: Tous les contacts · Mes contacts · vues enregistrées
  ├─ Listes                      /contacts/lists, /contacts/lists/{list}
  ├─ Segments                    /contacts/segments, /contacts/segments/{segment}
  ├─ Entreprises PagesJaunes     /contacts/pagejaunes (our "Companies" equivalent)
  ├─ Attributs                   /contacts/attributes
  ├─ Imports                     /contacts/imports (history), /contacts/imports/new (wizard)
  └─ Liste noire                 /contacts/blocklist   (today: /suppression)
Marketing
  ├─ E-mails (campagnes)         /campaigns, /campaigns/{campaign}/setup|design|report
  ├─ Formulaires                 /marketing/forms, /marketing/forms/{form}
  ├─ Modèles                     /templates, /templates/{template}/edit
  └─ Statistiques                /statistics
Automatisations (phase F)        /automations
Transactionnel
  ├─ Nouveau message             /compose           (existing Composer)
  ├─ Messagerie                  /mailbox           (existing Mailbox)
  ├─ Journaux                    /transactional/logs
  ├─ Modèles transactionnels     /templates?scope=transactional
  └─ SMTP & API                  /transactional/settings
Paramètres (account menu, top-right)
  ├─ Expéditeurs, domaines & IP  /settings/senders, /settings/domains
  ├─ Comptes SMTP & rotation     /smtp              (existing; our equivalent of "IPs")
  ├─ Pages de désinscription     /settings/unsubscribe-pages
  ├─ Formulaires de mise à jour  /settings/update-profile-forms
  ├─ Suivi (tracking)            /settings/tracking
  ├─ Identité visuelle           /settings/branding (existing)
  ├─ Utilisateurs & rôles        /admin/users       (existing)
  ├─ Clés API & webhooks         /settings/api
  ├─ Journal d'audit             /admin/audit-log   (existing)
  └─ Files d'attente             /horizon           (existing)
Aide                             /help              (existing)
```

Shell changes: Brevo uses a collapsible left sidebar with section headers and a top bar holding
search, "Créer une campagne" primary button, notifications and the account menu. `AppShell.tsx` /
`NavRail.tsx` / `navItems.ts` already support groups; this is a regroup plus new entries, not a
rewrite. Old URLs (`/recipients`, `/suppression`, `/reporting`) keep working as redirects for one
release.

## 43.4 Page-by-Page Specification

Each page lists the Brevo layout to reproduce and the backend it needs. Pages are Inertia pages
under `resources/js/Pages/**`, data via `/api/v1/**` as today.

### 43.4.1 Campaigns list — `Campaigns/Index`

- Header: title, folder breadcrumb, primary button **Créer une campagne** → type chooser
  (E-mail · Test A/B) → *Nom interne de la campagne* → **Créer la campagne** routes to
  `/campaigns/{uuid}/setup` (Brevo asks only for the internal name up front).
- Status tabs with counts: Toutes / Brouillons / Programmées / En cours / Envoyées / Suspendues /
  Archivées (archived hidden from *Toutes*). Search, sort (modified, name, sent date), filter by
  folder and tag.
- Table columns: name (+ A/B badge, tags; a **report icon** next to the name once Sent), status,
  recipients, open rate, click rate, last modified / sent at, owner. Row **Plus d'actions**:
  Modifier, Rapport, Dupliquer, Envoyer un test, Envoyer aux nouveaux contacts (Sent only),
  Reprendre (Suspended only), Déplacer dans un dossier, Archiver (Sent/Suspended/Cancelled
  only), Supprimer. Bulk: archive, move, delete.
- Status mapping: keep our `CampaignStatus` enum and add `suspended` (paused by quota/SMTP
  failure, distinct from a user *pause*) and `archived_at`; UI labels follow Brevo's wording
  (Brouillon, Programmée, En cours, Envoyée, Suspendue, Annulée, Archivée).
- Backend: `campaigns` gets `folder_id`, `archived_at`, `tags` (json), `preview_text`,
  `sender_id`, `reply_to`, `to_field`, `utm_*`, `editor_type`, `design_json`, `stats` cache
  columns (`delivered_count`, `unique_opens`, `unique_clicks`, `unsubscribes`), `ab_test`
  fields. New `campaign_folders`. `CampaignResource` exposes rates.
  `POST campaigns/{id}/send-to-new-contacts` creates a child campaign targeting the audience
  delta.

### 43.4.2 Campaign Setup — `Campaigns/Setup` (replaces `CampaignWizardDialog`)

Brevo's setup screen ("campaign dashboard") is a vertical stack of cards, each with a completion
check mark and an Add/Edit action, in this order: Sender → Subject → Recipients → Design →
Additional settings. Sticky header: editable internal name, status chip, **Aperçu & test**
(preview desktop/mobile + send test to up to 5 addresses), **Programmer** (primary, disabled
until the required cards are complete).

1. **Expéditeur** — *Sélectionner l'expéditeur*: dropdown of verified `senders` (name + email),
   *Gérer les expéditeurs* link, reply-to override.
2. **Objet** — *Ajouter un objet*: subject with personalisation menu (`{{ contact.PRENOM }}`
   etc.), **texte d'aperçu** (preview text), emoji picker, character counter and an inbox
   preview line.
3. **Destinataires** — *Ajouter des destinataires*: dialog with tabs **Listes** (folder tree →
   checkboxes) and **Segments**, each list/segment showing its count; a second section
   **Exclure** with the same picker; live audience count (union − exclusions − suppression) via
   `POST /campaigns/{id}/audience/estimate`; warning when > N% unengaged (no open in 90 days);
   optional *Champ À* (`to_field`, e.g. `{{ contact.PRENOM }} {{ contact.NOM }}`).
4. **Design** — Thumbnail of the current design or **Commencer la conception** → template picker
   modal (43.4.3). Actions: Modifier le design, Changer de modèle, Aperçu.
5. **Paramètres supplémentaires** → *Modifier les paramètres*: page de désinscription
   (par défaut / personnalisée), formulaire de mise à jour du profil, tags, dossier, suivi UTM
   (source, medium, campaign, content, term, id), pièce jointe (uses `attachments`), lien miroir
   ("voir dans le navigateur"), images intégrées, date d'expiration de l'e-mail, suivi des
   ouvertures/clics (`tracking_enabled`), and our own: stratégie SMTP, plage horaire
   d'envoi, approbation requise.
6. **Checklist** panel: sender verified, recipients chosen, subject filled, design has an
   unsubscribe link, test sent (recommended), links valid, spam-score ok (hook for
   [39-deliverability-analyzer.md](39-deliverability-analyzer.md)).

**Programmer** opens the *Confirmer l'envoi* modal: summary (from, to with count, subject,
scheduled time) and the four Brevo delivery options as radios:
*Envoyer maintenant* · *Programmer pour plus tard* (date, time, timezone) · *Envoyer au meilleur
moment* (43.6.4) · *Envoyer par lots* (batch size + interval; maps onto our throttling engine as
a per-campaign rate cap). Confirm → `POST schedule|send` as today.

Backend: `PATCH campaigns/{id}` accepts the new fields; `StoreCampaignRequest` drops the
one-list-or-one-segment rule in favour of `campaign_audiences` (pivot: campaign_id, target_type
list|segment, target_id, mode include|exclude). `CampaignService::resolveAudience()` replaces the
current list/segment branch in `DispatchCampaignJob`. `POST campaigns/{id}/test-send` sends the
rendered email to up to 5 addresses through the normal pipeline with `is_test = true`.

### 43.4.3 Template picker modal

Tabs exactly as Brevo: **Galerie** (our seeded starter designs by category), **Mes modèles**,
**Campagnes précédentes**, **Coder** (paste HTML / import from URL / upload ZIP), **Éditeur de
texte enrichi** (current `RichTextEditor` kept for simple mails). Choosing one copies
`design_json` + `html_content` into the campaign and opens the editor.

### 43.4.4 Drag & Drop editor — `Components/Editor/EmailBuilder`

Three-pane layout like Brevo: left panel with top-level tabs **Contenu** and **Style** (Brevo's
current editor); *Contenu* has sub-tabs **Blocs**, **Sections** (pre-made row layouts) and
**Enregistrés** (saved sections, only whole sections can be saved); *Style* holds the global
design settings (fonts, colours, background, link style, button defaults, mobile settings) set
once for the whole email. Centre canvas with desktop/mobile toggle; right properties panel for
the selected block. Top bar: undo/redo, restore previous version, **Aperçu & test**,
**Enregistrer et quitter**.

Block set: title, text, image, button, divider, spacer, social links, columns (1–4), HTML block,
video thumbnail, logo, navigation bar, dynamic content (conditional block on attribute/segment),
product card (static), saved section. Personalisation menu in every text block inserting merge
tags resolved by `MergeVariableResolver`.

Technical choice: build on **GrapesJS + grapesjs-mjml** (MIT) so output is MJML → responsive HTML,
with blocks restyled to Fluent tokens; store MJML/JSON in `design_json` and compiled HTML in
`html_body` / `templates.html_content`. Alternative is `react-email-editor` (Unlayer, hosted,
licence cost) — rejected for a self-hosted product. Rendering pipeline order stays
[38-rendering-pipeline.md](38-rendering-pipeline.md): merge tags → tracking rewrite → signature.

### 43.4.5 Templates — `Templates/Index`, `Templates/Edit`

- Tabs **Mes modèles** / **Galerie**. Grid cards with real thumbnails (generated server-side with
  Playwright/Chromium already present in the repo) and kebab: Modifier, Dupliquer, Envoyer un
  test, Déplacer, Archiver, Supprimer. Folders and search as for campaigns. "Créer un modèle"
  opens the same picker.
- Edit page = the same EmailBuilder; `template_versions` keep history (restore button).
- `templates` gets `design_json`, `editor_type`, `folder_id`, `scope` (marketing|transactional),
  `is_gallery`; API gains `duplicate`, `test-send`, `thumbnail`.

### 43.4.6 Contacts — `Contacts/Index`

- **Vues** as tabs across the top: *Tous les contacts*, *Mes contacts* (created by me), then
  saved views; a view = filters + visible columns + sort, saved with **Enregistrer comme vue**
  (the two defaults cannot be overwritten, only saved as a new view).
- Toolbar: search box, **Charger une liste ou un segment** (dropdown with Listes / Segments
  tabs that scopes the table), **Ajouter un filtre** (condition builder shared with segments,
  with **Enregistrer comme segment**), **Créer un contact**, **Importer des contacts**,
  Exporter.
- Bulk-action bar on select: Ajouter à une liste, Retirer d'une liste, Mettre en liste noire /
  Retirer de la liste noire, Mettre à jour un attribut, Exporter, Supprimer.
- **Personnaliser les colonnes**: any contact attribute becomes a column; order and width are
  part of the view.
- Row click → contact detail. Pagination server-side (`GET recipients` with `filters`, `columns`,
  `sort`, `cursor`). Backend adds `contact_views` (user_id, name, filters json, columns json,
  sort, is_shared).

### 43.4.7 Contact detail — `Contacts/Show`

Left: identity card (email, name, company, PageJaunes link, *Liste noire* toggle, subscription
status, created/modified). Centre tabs: **Activité** (timeline from `messages` +
`message_events`: envoyé, délivré, ouvert, cliqué, rebond, désinscrit, import, ajout à une
liste), **Attributs** (editable form generated from attribute definitions), **Listes**
(membership with add/remove), **Notes** (existing `recipient_notes`).

### 43.4.8 Lists — `Contacts/Lists`, `Contacts/ListShow`

Folder tree + table (name, contacts, created, last import). Create list dialog (name, folder).
List detail = Contacts table scoped to the list, with Importer dans cette liste, Exporter,
Supprimer la liste. Backend: `list_folders`, counts cached on `recipient_lists.contact_count`,
`DELETE recipient-lists/{id}`, `POST recipient-lists/{id}/contacts` (add/remove batch).

### 43.4.9 Segments — `Contacts/Segments`, `Contacts/SegmentEdit`

Condition builder with groups (AND/OR), each condition = field · operator · value. Condition
categories as in Brevo: *Attributs du contact* (typed operators per attribute type), *Listes*
(est dans / n'est pas dans), *Engagement e-mail* (a ouvert / a cliqué / n'a pas ouvert une
campagne X ou n'importe quelle campagne, dans les N derniers jours; a rebondi; s'est désinscrit),
*Dates* (ajouté le, modifié le, dernier contact), *PagesJaunes* (secteur, wilaya, etc.). Live
count button, save with name/folder. Segment detail shows members and "Utiliser dans une
campagne". Backend: make `segments` standalone (name, description, folder, no 1:1 list), extend
`SegmentEvaluationService` with `tags`, `custom attributes`, list membership and engagement
sub-queries against `messages`/`message_events`.

### 43.4.10 Attributes — `Contacts/Attributes`

Table of attribute definitions (name, type, used in N contacts, created). **Créer un attribut**:
name (A–Z, 0–9, `_`, max 50), type: Texte, Nombre, Date, Booléen, Catégorie (single choice with
options), Choix multiple, Utilisateur. System attributes (EMAIL, PRENOM, NOM, SOCIETE,
SMS placeholder) are read-only. Backend: `contact_attributes` table; values stay in
`recipients.custom_fields` JSON but are validated/cast against the definition; merge-tag list and
segment field list are generated from it; import mapping and column chooser read it.

### 43.4.11 Forms — `Marketing/Forms`, `Marketing/FormEdit`

Form list (name, list(s), submissions, status) under *Marketing › Formulaires* as in Brevo.
**Créer un formulaire d'inscription** → builder with tabs **Champs** (pick attributes,
required, GDPR checkbox), **Design** (colours, button text, logo, font), **Paramètres** (target
lists, double opt-in on/off with confirmation email template, redirect/thank-you message,
captcha), **Partager** (hosted "Quick share" URL `/f/{uuid}`, iframe + HTML embed). Backend: `forms`, `form_submissions`,
public signed routes, double-opt-in token flow writing `recipients.opt_in_at`.

### 43.4.12 Import — `Contacts/ImportWizard`, `Contacts/Imports`

Five-step wizard as Brevo: **Fichier** (CSV/XLSX/TXT upload, paste, or PagesJaunes search = our
extra) → **Correspondance** (preview first rows, map each column to an attribute or *Créer un
attribut*, ignore column) → **Listes** (add to existing lists / create new) → **Paramètres**
(update existing contacts, treat as blocklisted, mark opt-in) → **Lancer**. History page lists
import jobs (file, date, user, status, imported/updated/failed, download errors CSV). Reuses the
existing `import_jobs/rows/errors` pipeline; adds `mapping` and `list_ids` to `import_jobs`.

### 43.4.13 Blocklist — `Contacts/Blocklist`

The existing Suppression page relabelled and moved under Contacts, with Brevo's two tabs:
**Contacts en liste noire** (unsubscribed / complaint / manual, reason, date, source campaign,
réactiver) and **Rebonds** (hard/soft bounce entries). Same API.

### 43.4.14 Senders & Domains — `Settings/Senders`, `Settings/Domains`

Senders table (name, email, domain status, default, verified). Add sender → verification email
with signed link (or auto-verified when domain is authenticated). Domains table with
**Authentifier**: shows the DKIM selector/TXT, SPF include and DMARC records to add, **Vérifier**
runs a DNS lookup job and stores status per record. Backend: `senders`, `sending_domains`,
`VerifyDomainJob`; campaigns and composer pick a `sender_id`; the delivery engine sets From/
Reply-To from it and DKIM-signs when a private key is stored for the domain.

### 43.4.15 Campaign report — `Campaigns/Report`

Header: campaign name, sent date, sender, subject, **Exporter** (CSV per tab), **Comparer**,
**Voir l'e-mail**. Tabs:

- **Vue d'ensemble** — KPI tiles: Envoyés, Délivrés (%), Ouvertures uniques (%), Clics uniques
  (%), Désinscriptions (%), Plaintes, Rebonds; each with *vs moyenne du compte*. Chart: ouvertures
  et clics par heure sur 72 h, puis par jour. **Audience de la campagne** section (lists and
  segments targeted/excluded with per-list sent/delivered/opens/clicks, i.e. Brevo's
  `campaignStats`). **Chronologie** of actions (created, scheduled, sent, paused, resumed,
  A/B winner picked, archived — from the audit log). Top links table. Device split. Apple Mail
  privacy opens shown separately (`appleMppOpens`) with the same explanatory note Brevo shows.
- **Délivrabilité** — délivrés, rebonds soft/hard (reason table, export), bloqués par suppression,
  différés, en file; par compte SMTP (our extra).
- **Ouvertures** — uniques vs totales, by hour/day chart, by domain (gmail.com…), by device/
  client (parsed UA), by country (GeoIP), **Voir les contacts** → creates a segment "a ouvert
  campagne X".
- **Clics** — per-URL table (clics uniques, totaux, %), **Carte des clics** (email rendered with
  overlay badges per link), by device, **Voir les contacts**.
- **Désinscriptions** — count and rate, spam complaints, by list, list of contacts.

Backend: `campaign_links` (url, label, unique/total clicks) filled by the click tracker;
`message_events` gains `user_agent`, `device_type`, `email_client`, `ip_country`; a
`CampaignStatsService` with hourly rollups stored in `campaign_stats_hourly` and per-list
rollups in `campaign_stats_by_list`; the stats payload mirrors Brevo's field names
(`sent`, `delivered`, `hardBounces`, `softBounces`, `complaints`, `unsubscriptions`, `viewed`,
`uniqueViews`, `trackableViews`, `appleMppOpens`, `uniqueClicks`, `clickers`, `deferred`,
`mirrorClick`, `remaining`, `linksStats`, `statsByDomain`, `statsByDevice`, `statsByBrowser`)
so any Brevo-reporting integration or dashboard template maps 1:1.
`GET campaigns/{id}/report/{tab}`; `POST campaigns/{id}/report/segment` (metric → segment);
`GET campaigns/compare?ids=`.

### 43.4.16 Statistics — `Statistics/Index`

Period picker, KPI tiles (sent, delivered, opens, clicks, unsubscribes, bounces, complaints),
time-series chart, "Performance des campagnes" table (all sent campaigns with rates, sortable),
by list performance. Replaces `Reporting/Index`; SMTP-account performance moves to `Smtp`.

### 43.4.17 Transactional — `Transactional/Logs`, `Transactional/Settings`

Logs: real-time table of messages with event filter using Brevo's event names (envoyé, délivré,
ouvert, cliqué, rebond soft/hard, invalide, différé, plainte, désinscrit, bloqué, erreur), search
by recipient/subject/message id, row expands to the event timeline (reuses the Mailbox history
API). Settings: SMTP relay instructions for our own inbound relay (phase G), **Clés API**
(create/revoke Sanctum personal access tokens with scopes), **Webhooks** (URL, events, secret,
delivery log) emitting the same event identifiers and payload shape as Brevo
(`event`, `email`, `message-id`, `date`, `ts`, `ts_event`, `subject`, `tag`/`tags`, `link` for
clicks, `reason` for bounces) so existing Brevo webhook consumers can be pointed at this app.
`POST /api/v1/transactional/send` (to, template id or html, params) for external systems.

### 43.4.18 Unsubscribe & preference pages — `Settings/UnsubscribePages`

Customise the public pages: logo/colours from branding, texts, "update your preferences" page
listing the lists the contact can opt out of individually, confirmation message. Backed by
`settings` keys; public routes already exist (`unsubscribe.show/confirm`); add
`preferences/{token}`.

### 43.4.19 Automations (phase F) — `Automations/Index`, `Automations/Edit`

Linear workflow editor: trigger (contact added to list, form submitted, campaign opened/clicked,
attribute changed, date anniversary) → steps (send email template, wait N days/hours, condition
on attribute/engagement with yes/no branch, add/remove list, update attribute, end). Backend:
`automations`, `automation_steps`, `automation_runs`, a scheduler tick job; emails go through
the transactional path. Scoped deliberately to what a welcome/follow-up series needs.

## 43.5 Data Model Changes (addendum to [04-database-design.md](04-database-design.md))

New tables: `campaign_folders`, `campaign_audiences`, `campaign_recipients`, `campaign_links`,
`campaign_stats_hourly`, `campaign_stats_by_list`, `ab_test_variants`, `senders`,
`sending_domains`, `contact_attributes`, `contact_views`, `list_folders`, `forms`,
`form_submissions`, `unsubscribe_pages`, `update_profile_forms`, `api_webhooks`,
`api_webhook_deliveries`, `automations`, `automation_steps`, `automation_runs`,
`template_folders`.

Altered tables:
- `campaigns`: `preview_text`, `sender_id`, `reply_to`, `to_field`, `folder_id`, `tags` json,
  `utm_enabled` + `utm_source/medium/campaign/content/term/id`, `mirror_link_enabled`,
  `inline_images`, `attachment_id`, `email_expiration_duration` + `_unit`,
  `unsubscribe_page_id`, `update_profile_form_id`, `editor_type`, `design_json`, `archived_at`,
  `suspended_reason`, `send_time_optimized`, `batch_size` + `batch_interval_minutes`,
  `ab_test_enabled`, `ab_test_config` json, `winner_variant_id`, `test_sent_at`,
  `parent_campaign_id` (for *send to new contacts*), stats cache counters. Drop reliance on
  `recipient_list_id`/`segment_id` (kept nullable for backfill, removed one release later).
- `templates`: `design_json`, `editor_type`, `folder_id`, `scope`, `is_gallery`, `thumbnail_path`
  generated.
- `segments`: `name`, `description`, `folder_id`, make `recipient_list_id` nullable (standalone
  segments); `recipient_lists.type = dynamic_segment` becomes legacy.
- `recipients`: `ext_id` (customer's own id, unique nullable), `opt_in_at`, `opt_in_source`,
  `blocklisted_at` (mirror of suppression for fast filters), `last_opened_at`,
  `last_clicked_at`, `engagement_score`, per-contact counters shown on the detail page
  (`messages_sent`, `delivered`, `opened`, `clicked`, `hard_bounces`, `soft_bounces`,
  `complaints`, `unsubscriptions`).
- `recipient_lists`: `folder_id`, `contact_count`.
- `messages`: `variant_id`, `is_test`; `message_events`: `url`, `user_agent`, `device_type`,
  `email_client`, `ip_country`, `ip_city`.
- `import_jobs`: `mapping` json, `list_ids` json, `update_existing`, `summary` json.
- `users`: `column_preferences` json (per-page column chooser).

All migrations stay driver-portable per the existing conventions note.

## 43.6 Key Technical Designs

### 43.6.1 Audience resolution
`AudienceResolver::resolve(Campaign)` = union of included lists' members and included segments'
evaluated members, minus excluded lists/segments, minus suppression, deduplicated by recipient id,
materialised into `campaign_recipients` (campaign_id, recipient_id, variant_id) before dispatch so
counts, A/B sampling and resumption are deterministic. `DispatchCampaignJob` iterates this table
instead of the list/segment branch.

### 43.6.2 A/B testing
`ab_test_config`: `{ "type": "subject|content", "sample_percent": 20, "winner_metric":
"opens|clicks", "test_duration_minutes": 240 }`. Dispatcher sends variant A/B to the sample split
evenly, schedules `PickAbWinnerJob` at `sent_at + duration`, which computes unique opens/clicks per
variant from `messages`, sets `winner_variant_id`, and dispatches the remainder. Report shows a
*Test A/B* card with both variants' rates.

### 43.6.3 Per-link click tracking and click map
`TrackClickController` already redirects signed URLs; add the original URL to `message_events`
and upsert `campaign_links` counters. The click map renders the campaign HTML in the sandboxed
`HtmlPreview` iframe and overlays a badge per anchor with its share of clicks (anchors matched by
`data-link-id` injected during the tracking rewrite step).

### 43.6.4 Send at best time
Per recipient, compute the hour-of-week with most opens over the last 10 campaigns
(fallback: account-wide best hour, then the scheduled time). Dispatcher releases each message
with a delay to the next occurrence of that hour within a 24 h window. Stored as
`messages.scheduled_for` and honoured by the existing throttling engine.

### 43.6.5 Device / client / geo enrichment
Parse `User-Agent` on open/click with a PHP UA parser (e.g. `matomo/device-detector`), resolve IP
country with a local MaxMind GeoLite2 DB (`geoip2/geoip2`), store on `message_events`; nothing
leaves the server. Apple Mail privacy-proxy opens flagged `is_proxy_open` so the report can show
the Brevo-style note.

### 43.6.6 Typed attributes & merge tags
`contact_attributes` defines name/type/options. `MergeVariableResolver` exposes
`{{ contact.<NAME> }}` plus `| default:"…"` filter, `{{ mirror }}`, `{{ unsubscribe }}`,
`{{ update_profile }}`. The editor's personalisation menu, the segment builder's field list and
the import mapper all read the same definitions endpoint `GET contact-attributes`.

### 43.6.7 Editor storage and rendering
MJML source + GrapesJS component JSON in `design_json`; compiled HTML in `html_body`. Compilation
runs client-side on save (mjml-browser) and is re-validated server-side (`mjml` via Node sidecar
or `spatie/mjml-php`). Thumbnails rendered by a queued Playwright job.

## 43.7 Delivery Phases and Work Packages

Follows the WP conventions of [42-parallel-execution-plan.md](42-parallel-execution-plan.md)
(file ownership, one `require` per route file, migrations slots). Effort is in engineer-weeks for
one developer; packages inside a phase can run in parallel.

### Phase A — Brevo shell & campaign flow (highest user-visible value) — ~5 weeks

| WP | Scope | Owns | Effort |
|---|---|---|---|
| WP-40 Nav & shell | New IA (43.3), top-bar *Créer une campagne*, settings gear menu, redirects from old URLs, help topics renamed | `navItems.ts`, `AppShell.tsx`, `TopBar.tsx`, `routes/web/*` | 0.5 |
| WP-41 Senders & domains | `senders`, `sending_domains`, verification email, DNS check job, settings pages, picker component | `app/Modules/Senders/**`, `Pages/Settings/{Senders,Domains}.tsx` | 1 |
| WP-42 Campaign model v2 | New columns, `campaign_audiences`, `campaign_folders`, `AudienceResolver`, estimate endpoint, test-send endpoint, archive/duplicate/folders API, backfill migration | `app/Modules/Campaigns/**` | 1 |
| WP-43 Campaign list + Setup page | `Campaigns/Index` rebuilt, `Campaigns/Setup` cards, review-and-send modal, checklist, folder UI | `Pages/Campaigns/**` | 1.5 |
| WP-44 Template picker + rich-text path | Picker modal with Galerie/Mes modèles/Campagnes précédentes/Coder/Texte enrichi, HTML import | `Components/Editor/TemplatePicker*` | 1 |

Exit criteria: a user creates, tests, schedules and sends a campaign to several lists with
exclusions from a page that mirrors Brevo's setup screen, with a verified sender.

### Phase B — Contacts parity — ~5 weeks

| WP | Scope | Effort |
|---|---|---|
| WP-50 Contact attributes | `contact_attributes`, validation/casting, attributes page, merge-tag & segment field generation | 1 |
| WP-51 Contacts table v2 | Column chooser, filter builder component, bulk actions, export job | 1.5 |
| WP-52 Contact detail page | Activity timeline, attributes, lists, notes, blocklist toggle | 1 |
| WP-53 Lists & folders | Lists pages, list detail, membership API, counts | 0.5 |
| WP-54 Segments v2 | Standalone segments, builder UI, engagement/list conditions in `SegmentEvaluationService` | 1 |
| WP-55 Import wizard + history | Five-step wizard, mapping to attributes, lists step, history page | 1 |
| WP-56 Blocklist move | Relabel/move Suppression under Contacts with Brevo tabs | 0.25 |

### Phase C — Design & templates — ~5 weeks

| WP | Scope | Effort |
|---|---|---|
| WP-60 EmailBuilder core | GrapesJS+MJML integration, Fluent restyle, block set, properties panel, desktop/mobile preview, save/compile pipeline | 2.5 |
| WP-61 Sections, Design tab, personalisation menu, dynamic content block | 1 |
| WP-62 Templates v2 | Folders, gallery seed (10–15 starter designs), duplicate, thumbnails job, edit page, versions UI | 1 |
| WP-63 Rendering pipeline update | MJML server validation, mirror link, UTM rewrite, `data-link-id` injection | 0.5 |

### Phase D — Reports & statistics — ~4 weeks

| WP | Scope | Effort |
|---|---|---|
| WP-70 Tracking enrichment | `campaign_links`, UA/device/geo on events, proxy-open flag, hourly rollups | 1 |
| WP-71 Campaign report page | Five tabs, charts (follow the `dataviz` guidance), click map, export, view-contacts → segment | 2 |
| WP-72 Statistics page + compare | Global time series, campaign performance table, compare view; retire `Reporting/Index` | 1 |

### Phase E — Advanced sending — ~3 weeks

| WP | Scope | Effort |
|---|---|---|
| WP-80 A/B test | Variants, split dispatch, winner job, setup UI, report card | 1.5 |
| WP-81 Send at best time | Per-recipient best-hour computation, `scheduled_for` release, UI option | 1 |
| WP-82 Unsubscribe & preference pages | Customisation settings, per-list preferences page, double opt-in email template | 0.5 |

### Phase F — Forms & automation — ~4 weeks

| WP | Scope | Effort |
|---|---|---|
| WP-90 Forms | Builder, public hosted form, embed code, double opt-in flow, submissions | 1.5 |
| WP-91 Automations (linear) | Models, scheduler tick, editor, run log, 5 triggers / 6 step types | 2.5 |

### Phase G — Transactional surface — ~2 weeks

| WP | Scope | Effort |
|---|---|---|
| WP-100 API keys & transactional send | Token management UI with scopes, `POST transactional/send`, rate limits | 1 |
| WP-101 Logs & webhooks | Logs page, outgoing webhooks with signed payloads and retry, delivery log | 1 |

Total ≈ 28 engineer-weeks sequentially; ≈ 12–14 calendar weeks with two developers working
phases in parallel (A∥B, then C∥D, then E∥F, then G).

## 43.8 Cross-cutting Requirements

- **French-only UI**: every new label goes through `Lib/i18n/fr.ts`; the Brevo French labels are
  the reference wording (Brevo ships a French locale, use its terms: *Campagnes*, *Contacts*,
  *Listes*, *Segments*, *Attributs*, *Expéditeurs*, *Programmer*, *Envoyer un test*, *Rapport*).
- **Permissions**: add `senders.manage`, `attributes.manage`, `forms.manage`, `automations.manage`,
  `api_keys.manage`, `contacts.export`, `campaigns.archive` to `PermissionName` and the role
  seeder; nav items filter on them as today.
- **Audit**: new sensitive actions (sender verification, domain keys, API key creation, exports,
  attribute deletion) emit audit events ([27-audit-logs.md](27-audit-logs.md)).
- **Help center**: each new page gets a `PageHelp` topic and a screenshot in the docs gallery
  (`npm run docs:screenshots`).
- **Testing**: feature tests per WP as in `tests/Feature/Modules/**`; Playwright smoke for the
  campaign flow (create → design → recipients → test send → schedule) and for the import wizard.
- **Performance**: audience materialisation and stats rollups run in queued jobs; report tabs read
  rollups, never raw `message_events`, above 100k messages.
- **Migration of existing data**: backfill `campaign_audiences` from `recipient_list_id`/
  `segment_id`; create a default sender from `MAIL_FROM_*`; convert `dynamic_segment` lists to
  standalone segments; existing HTML templates become `editor_type = html`.

## 43.9 Risks and Decisions to Confirm

| Risk / decision | Recommendation |
|---|---|
| Editor engine (GrapesJS-MJML vs. writing our own) | GrapesJS-MJML: mature, MIT, MJML gives responsive output for free; budget 1 week for Fluent restyling |
| Brevo "gallery" templates are copyrighted | Ship 10–15 original MJML designs; never copy Brevo's |
| GeoIP database licence | GeoLite2 (free with attribution), updated monthly by a scheduled job |
| DKIM signing in-app vs. at the SMTP relay | Support both: sign in-app when a domain key exists, else rely on the relay; document per SMTP account |
| Dropping single-list campaign fields | Keep nullable for one release, remove after backfill is verified in production |
| Scope creep into CRM/SMS | Explicitly out (43.1); revisit only after Phase G |

## 43.10 Immediate Next Steps

1. Confirm the IA in 43.3 and the phase order (A and B first).
2. Open WP-40/41/42 as the first wave; WP-43 starts as soon as WP-42's resource contract is
   frozen (same pattern as WP-20/WP-23).
3. Spike (2 days) GrapesJS-MJML inside the Fluent shell to de-risk Phase C before committing to
   its estimate.

Return to [README.md](README.md) for the full index.
