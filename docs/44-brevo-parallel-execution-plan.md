# 44 — Brevo Parity: Parallel Agent Execution Plan (Opus + Sonnet)

## 44.1 Purpose

[43-brevo-parity-plan.md](43-brevo-parity-plan.md) defines *what* to build to reach Brevo parity.
This document defines *how to build it with parallel agents*, reusing the execution model that
delivered Phases 3–5 ([42-parallel-execution-plan.md](42-parallel-execution-plan.md)): work
packages (WPs) with disjoint file ownership, closed read lists, one commit per WP, waves that run
concurrently, one verification gate per wave.

Two additions over doc 42:

1. **Model tiering is explicit.** Every WP is assigned to **Opus** or **Sonnet** using the
   criteria in §44.3, with an escalation rule.
2. **Contracts-first Wave 0.** UI and backend WPs for the same feature run in the *same* wave,
   building against TypeScript types and API contracts frozen in Wave 0 — not against each
   other's code.

Where this document and doc 43 disagree on behaviour, doc 43 wins. Where doc 43 and a base spec
(01–41) disagree, doc 43 wins (it is the newer product decision).

## 44.2 Execution Model (unchanged from doc 42, restated)

- **Waves 0–4.** All WPs in a wave run in parallel in their own git worktrees. A wave starts when
  the previous wave is merged and green.
- **One commit per WP**, touching only owned paths (§44.10). Merges are conflict-free by
  construction; the orchestrator resolves only the append-only shared files.
- **Orchestrator** = one coordinator session (Opus). It launches a wave's agents concurrently
  (`Agent` tool, `isolation: "worktree"`, `model: "opus" | "sonnet"`), merges in listed order,
  runs the gate (§44.9) once, and relaunches only failed WPs with the failure output appended.
- **One shot per WP.** Agents do not iterate interactively.

## 44.3 Model Assignment Rules

| Give it to **Opus** when the WP… | Give it to **Sonnet** when the WP… |
|---|---|
| defines or changes a state machine, dispatcher, job orchestration or anything on the send path | mirrors an existing module/page 1:1 (Model → Service → Request → Controller → Resource → routes → test) |
| compiles user rules into SQL (segments, audience resolution, stats rollups) | is additive CRUD, a settings page, a list page, a form dialog |
| builds a canvas/editor, a multi-tab page with cross-tab state, or a condition builder | writes migrations + resources from a frozen schema |
| must decide an architecture (library choice, storage format) | regroups navigation, adds redirects, i18n keys, docs, help topics |
| integrates a third-party engine (GrapesJS/MJML, GeoIP, UA parser) | re-skins an existing flow (import wizard over the existing pipeline) |

Effort: Opus WPs run at **high** effort; Sonnet WPs at **medium** (low for pure wiring).

**Escalation.** A Sonnet WP that fails the gate once is relaunched on Sonnet with the failure
output. If it fails a second time it is relaunched on **Opus** with both failure outputs. An Opus
WP that fails twice stops the wave and the orchestrator reports to the user.

**Review.** After each wave's gate passes, one **Opus review agent** runs `/code-review` on the
wave's merged diff (high effort) before the next wave starts; findings it marks blocking are fixed
by the orchestrator or by relaunching the owning WP.

## 44.4 Token Economy Protocol

Identical to doc 42 §42.4, plus:

7. **Types before pages.** UI agents read `resources/js/Lib/types/<module>.ts` (frozen in
   Wave 0) and the API contract appendix; they never read backend PHP.
8. **One Brevo reference per WP.** Each WP's prompt names the doc 43 section(s) to mirror and at
   most one Brevo help-center URL from doc 43 §43.1; agents do not browse further.
9. **Spike before engine.** WP-60S (Wave 1) is research-only and returns a decision memo; WP-60
   (Wave 3) builds on that memo, not on fresh exploration.

Estimated per-agent context: ≤ 400 lines of spec + ≤ 800 lines of template source.
Total: **36 agents across 5 waves** (plus 4 review agents). Opus: 17 · Sonnet: 19.

## 44.5 Wave 0 — Contracts & Enablers (2 agents)

Must complete before anything else: it removes every shared-file hazard and freezes the
contracts the parallel waves build against.

| WP | Scope | Read list | Owned paths | Model |
|---|---|---|---|---|
| **WP-40** Contracts freeze | (a) Write `docs/45-brevo-api-contracts.md`: every new/changed endpoint of doc 43 §43.4 with request/response JSON (field names per doc 43 §43.1 "Brevo facts"). (b) Write/extend all TypeScript types in `resources/js/Lib/types/{campaigns,senders,contacts,attributes,segments,lists,imports,forms,reports,statistics,transactional,automations}.ts`. (c) Add all new `PermissionName` cases (doc 43 §43.8) + seeder rows. (d) Add all new i18n keys for every page in doc 43 §43.4 to `fr.ts` (one pass, Brevo's French wording). (e) Allocate migration slots (§44.11) and publish the schema addendum as a table-by-table column list in the contracts doc. | doc 43 all; doc 29 §29.1–29.3; `fr.ts`; `PermissionName.php`; `Lib/types/*.ts` | `docs/45-brevo-api-contracts.md`, `resources/js/Lib/types/**`, `fr.ts`, `app/Domain/Enums/PermissionName.php`, `database/seeders/RolePermissionSeeder.php` | **Opus** |
| **WP-40N** Nav & shell regroup | New IA from doc 43 §43.3: nav groups Contacts / Marketing / Automatisations / Transactionnel, account-menu Paramètres, top-bar **Créer une campagne** button, redirects `/recipients→/contacts`, `/suppression→/contacts/blocklist`, `/reporting→/statistics`. Keep every existing page reachable at its new URL (route rename only, pages untouched). | doc 43 §43.3; `navItems.ts`, `NavRail.tsx`, `TopBar.tsx`, `AppShell.tsx`, `routes/web/*.php` | `resources/js/Components/Shell/**`, `routes/web/**` (rename/redirect only), `tests/Feature/WebRouteAuthorizationTest.php` | **Sonnet** |

DoD: gate passes; existing feature tests pass; `tsc` passes with the new (still unused) types.

## 44.6 Wave 1 — Campaign Flow (Phase A) — 7 agents

| WP | Scope | Read list (beyond doc 45 contracts) | Owned paths | Template | Model |
|---|---|---|---|---|---|
| **WP-41** Senders & domains backend | `senders`, `sending_domains` migrations (slot A), models, `SenderService` (verification email with signed link, default sender), `DomainAuthService` + `VerifyDomainJob` (DNS TXT/CNAME lookups for SPF/DKIM/DMARC, status per record), controllers/resources/policies, default sender seeded from `MAIL_FROM_*`, feature tests with a fake DNS resolver. | doc 43 §43.4.14 | `app/Modules/Senders/**`, `routes/api/senders.php`, migrations slot A, `tests/Feature/Modules/Senders/**`, `tests/Support/FakeDnsResolver.php` | `app/Modules/Suppression` | **Sonnet** |
| **WP-41U** Senders & domains UI | `Settings/Senders.tsx`, `Settings/Domains.tsx` (records to copy, *Vérifier* button, status badges), reusable `SenderPicker` component. | doc 43 §43.4.14 | `resources/js/Pages/Settings/{Senders,Domains}.tsx`, `resources/js/Components/Senders/**`, `Lib/api/senders.ts` | `Pages/Smtp/Index.tsx` | **Sonnet** |
| **WP-42** Campaign model v2 + audience | New `campaigns` columns, `campaign_folders`, `campaign_audiences`, `campaign_recipients` (slot B); `AudienceResolver` (union/exclude/dedup/suppression → materialised rows); `estimate`, `test-send`, `archive`, `unarchive`, `duplicate`, `send-to-new-contacts`, folders endpoints; `suspended` status + `suspended_reason`; batch send (`batch_size`/`batch_interval_minutes`) wired into `DispatchCampaignJob` via the existing throttle; backfill migration from `recipient_list_id`/`segment_id`; feature tests incl. state-machine table test. | doc 43 §43.4.1–43.4.2, §43.6.1; doc 15 §15.1; `CampaignService.php`, `DispatchCampaignJob.php`, `CampaignStatus.php` | `app/Modules/Campaigns/**`, `routes/api/campaigns.php`, migrations slot B, `tests/Feature/Modules/Campaigns/**` | — (extends own module) | **Opus** |
| **WP-43** Campaign list + Setup page | `Campaigns/Index.tsx` rebuilt (status tabs, folders, row actions, report icon), create-campaign dialog (type + internal name), `Campaigns/Setup.tsx` with the five cards, *Aperçu & test* panel, checklist, *Confirmer l'envoi* modal with the four delivery options; recipients picker dialog (Listes/Segments tabs, Exclure); all against doc 45 types. Delete `CampaignWizardDialog.tsx`. | doc 43 §43.4.1–43.4.2 | `resources/js/Pages/Campaigns/**`, `resources/js/Components/Campaigns/**`, `Lib/api/campaigns.ts` | `Pages/Smtp/SmtpAccountFormDialog.tsx` (card/field patterns) | **Opus** |
| **WP-44** Template picker + code path | `TemplatePicker` modal (Galerie / Mes modèles / Campagnes précédentes / Coder / Texte enrichi), HTML paste, import-from-URL (server-side fetch endpoint with SSRF guard), ZIP upload; `templates` columns `editor_type`, `design_json`, `folder_id`, `scope`, `is_gallery` (slot C); `duplicate` and `test-send` template endpoints. | doc 43 §43.4.3, §43.4.5 | `resources/js/Components/Editor/TemplatePicker*`, `app/Modules/Templates/**` (additive), `routes/api/templates.php`, migrations slot C, tests | `Pages/Templates/TemplateFormDialog.tsx` | **Sonnet** |
| **WP-45** Pre-send checklist service | `CampaignChecklistService` returning the checklist rows (sender verified, recipients > 0, subject, unsubscribe link present, test sent, links reachable, size) consumed by WP-43; link checker job with timeout; feature tests. Pure backend, additive. | doc 43 §43.4.2 item 6; doc 39 §39.2 | `app/Modules/Campaigns/Services/Checklist/**`, `app/Modules/Campaigns/Http/Controllers/CampaignChecklistController.php`, one route line, tests | `SegmentEvaluationService` (service shape) | **Sonnet** |
| **WP-60S** Editor spike (research) | Prototype GrapesJS + `grapesjs-mjml` inside the Fluent shell in an isolated page `Pages/Lab/EditorSpike.tsx` (not linked from nav): load/save MJML + JSON, compile in browser with `mjml-browser`, restyle panels with Fluent tokens, measure bundle size. Return a **decision memo** (`docs/46-editor-spike.md`): go/no-go, storage format, block list feasibility, server-side compile choice (`spatie/mjml-php` vs Node sidecar). | doc 43 §43.4.4, §43.6.7 | `resources/js/Pages/Lab/**`, `package.json` (add deps only), `docs/46-editor-spike.md` | — | **Opus** |

Exit criteria (doc 43 Phase A): create → test send → schedule → send a campaign to several lists
with exclusions, from a verified sender, from the new Setup page.

## 44.7 Wave 2 — Contacts Parity (Phase B) — 9 agents

| WP | Scope | Read list (beyond doc 45) | Owned paths | Template | Model |
|---|---|---|---|---|---|
| **WP-50** Contact attributes backend | `contact_attributes` (slot D), 7 types + options, system attributes seeded (EMAIL, PRENOM, NOM, SOCIETE), validation/casting of `recipients.custom_fields` against definitions, `GET/POST/PATCH/DELETE contact-attributes`, `MergeVariableResolver` reads definitions, feature tests. | doc 43 §43.4.10, §43.6.6; `MergeVariableResolver.php` | `app/Modules/Recipients/Attributes/**`, `routes/api/attributes.php`, migrations slot D, tests | `app/Modules/Recipients/Services/RecipientService.php` | **Sonnet** |
| **WP-51** Contacts table v2 (UI) | `Contacts/Index.tsx`: Views tabs, *Charger une liste ou un segment*, *Ajouter un filtre* (uses the shared `ConditionBuilder` from WP-54U — build against its props contract in doc 45), *Enregistrer comme vue/segment*, column chooser, bulk-action bar, export trigger. | doc 43 §43.4.6 | `resources/js/Pages/Contacts/Index.tsx`, `resources/js/Components/Contacts/**` (except `ConditionBuilder`), `Lib/api/contacts.ts` | `Pages/Admin/AuditLog.tsx` (filters + table) | **Opus** |
| **WP-52** Contact detail page | `Contacts/Show.tsx`: identity card, blocklist toggle, tabs Activité (timeline endpoint), Attributs (form generated from definitions), Listes, Notes. Backend: `GET recipients/{uuid}/timeline` merging `messages` + `message_events` + imports + list changes; per-contact counters on `recipients` (slot E). | doc 43 §43.4.7 | `resources/js/Pages/Contacts/Show.tsx`, `app/Modules/Recipients/Http/Controllers/RecipientTimelineController.php`, migrations slot E, tests | `Pages/Mailbox/Index.tsx` (reading pane) | **Sonnet** |
| **WP-53** Lists & folders | `list_folders` (slot F), `contact_count` cache, `DELETE recipient-lists/{id}`, batch add/remove members, export job; pages `Contacts/Lists.tsx`, `Contacts/ListShow.tsx`. | doc 43 §43.4.8 | `app/Modules/Recipients/Lists/**`, `routes/api/lists.php`, migrations slot F, `resources/js/Pages/Contacts/{Lists,ListShow}.tsx`, tests | `RecipientListService.php` / `Pages/Templates/Index.tsx` | **Sonnet** |
| **WP-54** Segments v2 backend | Standalone `segments` (name, description, folder; `recipient_list_id` nullable, slot G) + conversion migration for `dynamic_segment` lists; extend `SegmentEvaluationService` with `tags`, typed `custom_fields.*`, list membership (in/not in), engagement sub-queries (opened/clicked/not opened campaign X or any, last N days; bounced; unsubscribed), date fields; CRUD + `count` + `members` endpoints; property-based tests on rule → SQL. | doc 43 §43.4.9; doc 13 §13.7; `SegmentEvaluationService.php` | `app/Modules/Recipients/Segments/**`, `SegmentEvaluationService.php`, `routes/api/segments.php`, migrations slot G, tests | — | **Opus** |
| **WP-54U** Segment builder UI | Shared `ConditionBuilder` component (groups AND/OR, condition rows typed by field metadata from `GET contact-attributes` + static engagement/list/date fields, live count), `Contacts/Segments.tsx`, `Contacts/SegmentEdit.tsx`. Props contract fixed in doc 45 so WP-51 can embed it. | doc 43 §43.4.9 | `resources/js/Components/Contacts/ConditionBuilder/**`, `resources/js/Pages/Contacts/{Segments,SegmentEdit}.tsx`, `Lib/api/segments.ts` | — | **Opus** |
| **WP-55** Import wizard + history | `import_jobs` columns `mapping`, `list_ids`, `update_existing`, `summary` (slot H); mapping applied in the existing row pipeline; `Contacts/ImportWizard.tsx` five steps (Fichier incl. PagesJaunes tab reusing `PageJaunesSearch`, Correspondance with inline *Créer un attribut*, Listes, Paramètres, Lancer); `Contacts/Imports.tsx` history with errors CSV. | doc 43 §43.4.12; `app/Modules/Importing/**` | `app/Modules/Importing/**` (additive), migrations slot H, `resources/js/Pages/Contacts/{ImportWizard,Imports}.tsx`, tests | `Pages/Recipients/Import.tsx` | **Sonnet** |
| **WP-56** Blocklist move | `Contacts/Blocklist.tsx` with tabs *Contacts en liste noire* / *Rebonds*, *Réactiver* action; reuses suppression API; `recipients.blocklisted_at` mirror kept in sync by suppression events (slot I). | doc 43 §43.4.13 | `resources/js/Pages/Contacts/Blocklist.tsx`, `app/Modules/Suppression/Listeners/**`, migrations slot I, tests | `Pages/Suppression/Index.tsx` | **Sonnet** |
| **WP-57** Views + export backend | `contact_views` (slot J) CRUD; `GET recipients` gains `filters` (same rule tree as segments, compiled by WP-54's service — read-only dependency on its *contract*, stubbed in tests), `columns`, `sort`, cursor pagination; `ExportContactsJob` → CSV/XLSX via openspout with signed download link; bulk endpoints (add/remove list, blocklist, update attribute, delete). | doc 43 §43.4.6 | `app/Modules/Recipients/Views/**`, `app/Modules/Recipients/Http/Controllers/{RecipientBulkController,ContactViewController,ContactExportController}.php`, `RecipientController@index` (owned this wave), migrations slot J, tests | `AuditLogController` (export pattern) | **Sonnet** |

Exit criteria (doc 43 Phase B): a user imports a file mapped to typed attributes into a list,
filters contacts with the builder, saves a segment, opens a contact's timeline.

## 44.8 Wave 3 — Design & Reports (Phases C + D) — 8 agents

| WP | Scope | Read list (beyond doc 45) | Owned paths | Model |
|---|---|---|---|---|
| **WP-60** EmailBuilder core | Per `docs/46-editor-spike.md`: `Components/Editor/EmailBuilder/**` (GrapesJS+MJML), block set (title, text, image, button, divider, spacer, social, columns 1–4, HTML, video, logo, nav), properties panel, desktop/mobile preview, undo/redo, save → `{design_json, html}`; `Templates/Edit.tsx` and `Campaigns/Design.tsx` host pages; server-side MJML validation endpoint. | doc 43 §43.4.4, §43.6.7; doc 46 | `resources/js/Components/Editor/EmailBuilder/**`, `resources/js/Pages/Templates/Edit.tsx`, `resources/js/Pages/Campaigns/Design.tsx`, `app/Modules/Templates/Services/MjmlCompiler.php`, one route line, tests | **Opus** |
| **WP-62** Templates v2 | `template_folders` (slot K), gallery seeder with 12 original MJML designs, thumbnail job (Playwright already in repo), `Templates/Index.tsx` tabs *Mes modèles* / *Galerie*, folders, kebab actions, versions drawer. | doc 43 §43.4.5 | `app/Modules/Templates/**` (additive: folders, gallery seeder, `RenderThumbnailJob`), migrations slot K, `resources/js/Pages/Templates/Index.tsx`, `database/seeders/TemplateGallerySeeder.php`, `resources/gallery/**`, tests | **Sonnet** |
| **WP-63** Rendering pipeline update | Pipeline stages per doc 38 order: merge tags (with `default` filter, `{{ mirror }}`, `{{ unsubscribe }}`, `{{ update_profile }}`), UTM rewrite, `data-link-id` injection, mirror page route (`/m/{message}` signed), inline images, `to_field` rendering; golden-file tests. | doc 43 §43.6.3, §43.6.6; doc 38; `app/Modules/DeliveryEngine/**` rendering classes | `app/Modules/DeliveryEngine/Rendering/**`, `MergeVariableResolver.php` (owned this wave), `routes/web/mirror.php`, tests | **Opus** |
| **WP-70** Tracking enrichment | `campaign_links` + `message_events` columns + `campaign_stats_hourly` + `campaign_stats_by_list` (slot L); UA parsing (`matomo/device-detector`), GeoLite2 country (`geoip2/geoip2`, monthly update command), Apple MPP proxy-open flag, per-URL upsert in click tracker, `RollupCampaignStatsJob` (hourly + on completion), `recipients.last_opened_at/last_clicked_at/engagement_score` updates. | doc 43 §43.4.15 backend, §43.6.3, §43.6.5; `app/Modules/Tracking/**` | `app/Modules/Tracking/**` (owned this wave), `app/Modules/Reporting/Services/CampaignStatsService.php`, migrations slot L, `composer.json` (add deps only), tests | **Opus** |
| **WP-71** Campaign report page | `Campaigns/Report.tsx` with tabs Vue d'ensemble / Délivrabilité / Ouvertures / Clics / Désinscriptions, KPI tiles vs account average, time series, audience section, timeline, per-link table, click map overlay on `HtmlPreview`, device/domain/country breakdowns, *Voir les contacts*, export per tab, compare drawer. Charts follow the repo `dataviz` guidance. Against doc 45 report contract (`GET campaigns/{id}/report/{tab}`). | doc 43 §43.4.15 | `resources/js/Pages/Campaigns/Report.tsx`, `resources/js/Components/Reports/**`, `Lib/api/reports.ts` | **Opus** |
| **WP-72** Report + statistics API | Controllers over WP-70's rollups (contract in doc 45; built in parallel against the schema): `report/{tab}`, `report/segment` (metric → segment via WP-54 service), `compare`, `report/export`; `GET statistics/*` for the global page; account averages cache. | doc 43 §43.4.15–43.4.16 | `app/Modules/Reporting/Http/**`, `app/Modules/Reporting/Services/{ReportQueryService,StatisticsService,ReportExportService}.php`, `routes/api/reporting.php`, tests | **Sonnet** |
| **WP-73** Statistics page | `Statistics/Index.tsx`: period picker, KPI tiles, time series, campaign performance table, per-list performance; retire `Reporting/Index.tsx` (SMTP performance table moves into `Smtp/Index.tsx` as a tab). | doc 43 §43.4.16 | `resources/js/Pages/Statistics/**`, `resources/js/Pages/Reporting/**` (delete), `resources/js/Pages/Smtp/Index.tsx` (owned this wave), `Lib/api/statistics.ts` | **Sonnet** |
| **WP-74** Unsubscribe & preference pages | `unsubscribe_pages`, `update_profile_forms` (slot M); settings pages; public `preferences/{token}` route with per-list opt-out; campaign *Paramètres supplémentaires* picks them; double-opt-in confirmation email template. | doc 43 §43.4.18 | `app/Modules/Suppression/Preferences/**`, `routes/web/preferences.php`, migrations slot M, `resources/js/Pages/Settings/{UnsubscribePages,UpdateProfileForms}.tsx`, tests | **Sonnet** |

Exit criteria: a campaign designed in the builder is sent and its report shows per-link clicks,
devices and domains; the statistics page replaces the old reporting page.

## 44.9 Wave 4 — Advanced Sending, Forms, Automation, Transactional (Phases E–G) — 10 agents

| WP | Scope | Read list (beyond doc 45) | Owned paths | Model |
|---|---|---|---|---|
| **WP-61** Editor: sections, style tab, dynamic content | Sections (pre-made + *Enregistrés*), *Style* global settings, personalisation menu bound to `GET contact-attributes`, dynamic-content block (condition on attribute/segment), restore previous version. | doc 43 §43.4.4; WP-60 code | `resources/js/Components/Editor/EmailBuilder/**` (owned this wave), `app/Modules/Templates/Sections/**`, migrations slot N (`saved_sections`), tests | **Opus** |
| **WP-80** A/B testing | `ab_test_variants` (slot O), config validation, split dispatch in `DispatchCampaignJob`, `PickAbWinnerJob`, report card; Setup-page A/B card (subject A/B or content A/B, sample %, winner metric, delay). | doc 43 §43.6.2 | `app/Modules/Campaigns/AbTesting/**`, `DispatchCampaignJob.php` (owned this wave), migrations slot O, `resources/js/Components/Campaigns/AbTest*`, tests | **Opus** |
| **WP-81** Send at best time | `BestSendTimeService` (per-recipient hour-of-week from last 10 campaigns, fallbacks), `messages.scheduled_for` honoured by the throttle, option wired in the confirm modal, explanation tooltip. | doc 43 §43.6.4; `QuotaManager.php`, `RotationEngine.php` | `app/Modules/DeliveryEngine/Scheduling/**`, migrations slot P, `resources/js/Components/Campaigns/SendModeOptions.tsx` (owned this wave), tests | **Opus** |
| **WP-90** Forms backend | `forms`, `form_submissions` (slot Q); public hosted form `/f/{uuid}` (Blade, no Inertia), embed script, double opt-in token flow writing `recipients.opt_in_at`, captcha hook, rate limiting, feature tests. | doc 43 §43.4.11 | `app/Modules/Forms/**`, `routes/web/forms.php`, `routes/api/forms.php`, `resources/views/forms/**`, migrations slot Q, tests | **Sonnet** |
| **WP-90U** Forms builder UI | `Marketing/Forms.tsx`, `Marketing/FormEdit.tsx` with tabs Champs / Design / Paramètres / Partager, live preview iframe. | doc 43 §43.4.11 | `resources/js/Pages/Marketing/**`, `Lib/api/forms.ts` | **Opus** |
| **WP-91** Automations backend | `automations`, `automation_steps`, `automation_runs` (slot R); triggers (list added, form submitted, campaign opened/clicked, attribute changed, date anniversary) as listeners; `AdvanceAutomationRunsJob` tick; step types (send template, wait, condition yes/no, add/remove list, update attribute, end); re-entry setting; feature tests with time travel. | doc 43 §43.4.19; doc 31 | `app/Modules/Automations/**`, `routes/api/automations.php`, migrations slot R, tests | **Opus** |
| **WP-91U** Automations editor UI | `Automations/Index.tsx`, `Automations/Edit.tsx` (linear step list with branch for condition), *Activité* tab (started/finished, per-email open/click rates). | doc 43 §43.4.19 | `resources/js/Pages/Automations/**`, `resources/js/Components/Automations/**`, `Lib/api/automations.ts` | **Opus** |
| **WP-100** API keys + transactional send | Sanctum token management with scopes (`Transactional/Settings.tsx` → *Clés API* tab), `POST /api/v1/transactional/send` (to, template or html, params, tags), rate limits, audit events. | doc 43 §43.4.17 | `app/Modules/Transactional/**`, `routes/api/transactional.php`, `resources/js/Pages/Transactional/Settings.tsx`, tests | **Sonnet** |
| **WP-101** Logs + webhooks | `api_webhooks`, `api_webhook_deliveries` (slot S); dispatcher on `message_events` with Brevo-shaped payloads, HMAC signature, retry with backoff, delivery log; `Transactional/Logs.tsx` (event filter, search, expandable timeline) and *Webhooks* tab in `Transactional/Settings.tsx` (owned by WP-100 — WP-101 contributes `WebhooksPanel.tsx` component only). | doc 43 §43.4.17; doc 31 | `app/Modules/Webhooks/**`, `routes/api/webhooks.php`, migrations slot S, `resources/js/Pages/Transactional/Logs.tsx`, `resources/js/Components/Transactional/WebhooksPanel.tsx`, tests | **Sonnet** |
| **WP-102** Help center & screenshots | `PageHelp` topics + French guides for every new page (doc 43 §43.4), screenshot script entries, docs gallery regeneration; README/overview docs updated to the new IA. | doc 43 §43.4; `resources/js/Lib/docs/**`, `scripts/capture-screenshots.ts` | `resources/js/Lib/docs/**`, `scripts/**`, `docs/08-navigation.md` (rewrite §8.2–8.3), `docs/README.md` | **Sonnet** |

## 44.10 Verification Gate (per wave, once)

As doc 42 §42.9, plus:

5. `php artisan route:list --json` diffed against `docs/45-brevo-api-contracts.md` — every
   contract endpoint exists, no undocumented endpoint added.
6. Playwright smoke (CI only): login → create campaign → pick sender → add recipients → choose
   template → test send (log mailer) → schedule; from Wave 2 also import wizard end-to-end; from
   Wave 3 also builder save + report open.
7. Bundle budget: `npm run build` main chunk ≤ 1.2 MB gzip; the editor must be a lazy chunk.
8. Opus review agent on the merged wave diff (§44.3); blocking findings fixed before the next
   wave.

## 44.11 Migration Timestamp Allocation

| Slot | WP | Prefix |
|---|---|---|
| A | WP-41 Senders | `2026_10_20_1000xx` |
| B | WP-42 Campaigns v2 | `2026_10_20_1100xx` |
| C | WP-44 Templates columns | `2026_10_20_1200xx` |
| D | WP-50 Attributes | `2026_10_21_1000xx` |
| E | WP-52 Recipient counters | `2026_10_21_1100xx` |
| F | WP-53 Lists | `2026_10_21_1200xx` |
| G | WP-54 Segments v2 | `2026_10_21_1300xx` |
| H | WP-55 Imports | `2026_10_21_1400xx` |
| I | WP-56 Blocklist mirror | `2026_10_21_1500xx` |
| J | WP-57 Views | `2026_10_21_1600xx` |
| K | WP-62 Template folders | `2026_10_22_1000xx` |
| L | WP-70 Tracking/stats | `2026_10_22_1100xx` |
| M | WP-74 Preferences | `2026_10_22_1200xx` |
| N | WP-61 Saved sections | `2026_10_23_1000xx` |
| O | WP-80 A/B | `2026_10_23_1100xx` |
| P | WP-81 Best time | `2026_10_23_1200xx` |
| Q | WP-90 Forms | `2026_10_23_1300xx` |
| R | WP-91 Automations | `2026_10_23_1400xx` |
| S | WP-101 Webhooks | `2026_10_23_1500xx` |

Cross-WP foreign keys go in a separate `add_foreign_key_*` migration in the *later* slot
(existing pattern). Column lists per table are frozen by WP-40 in doc 45, so two WPs never add the
same column.

## 44.12 Shared-File Rules

| File | Rule |
|---|---|
| `routes/api.php`, `routes/web.php` | Append one `require` line only |
| `resources/js/Components/Shell/navItems.ts` | Frozen after WP-40N (all entries pre-added with `hidden: true` until the page exists). **WPs never edit it**: the orchestrator removes `hidden` for every page merged at the wave gate |
| `resources/js/Lib/i18n/fr.ts` | Frozen after WP-40; missing key = defect reported in the structured return |
| `resources/js/Lib/types/**` | Frozen after WP-40; a needed change is reported, applied by the orchestrator between waves |
| `docs/45-brevo-api-contracts.md` | Frozen after WP-40; same rule |
| `app/Domain/Enums/PermissionName.php`, seeder | Frozen after WP-40 |
| `package.json` / `composer.json` | Add-dependency lines only (WP-60S, WP-60, WP-70); orchestrator runs the lockfile update at the gate |
| `app/Providers/AppServiceProvider.php` | Contract bindings, append-only |
| `DispatchCampaignJob.php` | Owned by WP-42 in Wave 1, by WP-80 in Wave 4; untouched otherwise |
| `MergeVariableResolver.php` | Owned by WP-50 in Wave 2, by WP-63 in Wave 3 |

## 44.13 Agent Prompt Template

Every WP agent is launched with this prompt skeleton (orchestrator fills the brackets):

```
You are implementing work package [WP-ID] "[title]" of docs/44-brevo-parallel-execution-plan.md
in a dedicated git worktree on branch [branch].

READ (closed list — do not explore beyond it):
- docs/43-brevo-parity-plan.md §[sections]
- docs/45-brevo-api-contracts.md §[sections]
- [template module/page paths]
- [existing files you may modify]

OWN (you may create/modify only these paths): [owned paths]
APPEND-ONLY shared files you may touch: [list or "none"]
MIGRATION SLOT: [prefix] (use only this prefix)

BEHAVIOUR: mirror the Brevo page/flow described in the doc 43 sections above. UI strings only
via fr.ts keys (already present). Status enums stay English; labels French.

DO NOT: run composer/npm install, run the test suite, read other modules, edit frozen files,
change contracts. Write feature tests next to the module as the template does.

RETURN (structured, no prose): commit SHA; files created/modified; routes/events/jobs added;
fr.ts keys you needed but did not find; open questions.
```

Model and effort are passed on the `Agent` call (`model: "opus" | "sonnet"`, `effort` per
§44.3), never inside the prompt.

## 44.14 Orchestrator Runbook

1. **Wave 0**: launch WP-40 (Opus) and WP-40N (Sonnet) in parallel; merge WP-40 first; gate.
2. **Wave 1**: launch 7 agents (§44.6) in one call; merge order 41 → 42 → 44 → 45 → 41U → 43 →
   60S; gate; Opus review; triage `docs/46-editor-spike.md` decision with the user (go/no-go on
   GrapesJS) before Wave 3.
3. **Wave 2**: launch 9 agents; merge order 50 → 54 → 53 → 57 → 55 → 56 → 52 → 54U → 51; gate;
   review.
4. **Wave 3**: launch 8 agents; merge order 70 → 72 → 63 → 60 → 62 → 74 → 73 → 71; gate; review.
5. **Wave 4**: launch 10 agents; merge order 91 → 90 → 100 → 101 → 80 → 81 → 61 → 90U → 91U →
   102; gate; review.
6. After each wave: update doc 42-style status table in this document's §44.15, open one PR per
   wave (draft until green), ask the user for the go on the next wave.

Failed WP: relaunch per §44.3 escalation with the gate output appended to the prompt. Never
merge a wave partially unless the failed WP has no dependants in the same wave (then merge the
rest, relaunch the failure, gate again).

If the `Workflow` tool is available and the user has opted in, each wave maps to one
`parallel([...agent(...)])` call followed by a `merge+gate` agent; otherwise the `Agent` tool with
`isolation: "worktree"` and `run_in_background: true` per WP.

## 44.15 Status

| Wave | WPs | Status |
|---|---|---|
| 0 | 40, 40N | ✅ merged (brevo/wave-0 → brevo/main); gate green locally (CI unavailable: GitHub billing lock); review findings fixed — see [47](47-orchestration-log.md) |
| 1 | 41, 41U, 42, 43, 44, 45, 60S | ⬜ |
| 2 | 50, 51, 52, 53, 54, 54U, 55, 56, 57 | ⬜ |
| 3 | 60, 62, 63, 70, 71, 72, 73, 74 | ⬜ |
| 4 | 61, 80, 81, 90, 90U, 91, 91U, 100, 101, 102 | ⬜ |

## 44.16 Orchestrator Session Prompt

Paste the block below as the first message of a fresh Claude Code session (Opus) on this
repository. It runs the whole programme **without asking the user or stopping**: every failure
mode has a built-in fallback (escalate, split, repair, defer) and the only artefact left for a
human is the final `brevo/main → main` pull request.

```
You are the ORCHESTRATOR for the Brevo-parity programme of the `emailer` repository
(PageJaunes Mailer: Laravel 12 + Inertia/React + Fluent UI v9, French-only UI).
Your job is to coordinate parallel agents until every work package in
docs/44-brevo-parallel-execution-plan.md is merged and green. You do not write
feature code yourself; you plan, launch, merge, verify, review and report.

YOU RUN FULLY AUTONOMOUSLY. The user has pre-authorised every action below and will
not answer questions. Never ask the user anything, never wait for confirmation,
never end your turn while any wave is unfinished. If you are unsure, pick the
option that keeps the programme moving and record the decision in the log.
If a context compaction happens, re-read docs/47-orchestration-log.md and continue
from its last entry. If a tool call fails, retry with backoff (2s, 4s, 8s, 16s),
then take the fallback named below. Every notification or wake that reaches you
is handled, then you continue the programme.

READ FIRST, in this order, before any action:
1. docs/44-brevo-parallel-execution-plan.md (waves, WPs, owned paths, model per
   WP, gate, migration slots, shared-file rules, prompt template, runbook).
2. docs/43-brevo-parity-plan.md (the product spec every WP implements).
3. docs/42-parallel-execution-plan.md §42.3–42.4 and §42.9–42.11.
4. docs/README.md, docs/03-folder-structure.md, docs/07-ui-design.md §7.12–7.13.
Do not explore the codebase beyond what these documents point you to; the WP agents
do the reading of source files.

GROUND RULES
- Base branch: main. Programme branch: brevo/main (create from main if missing).
  Every wave gets its own branch brevo/wave-N cut from brevo/main; every WP agent
  gets its own worktree/branch brevo/wave-N/wp-XX cut from brevo/wave-N.
- Launch WP agents with the Agent tool: subagent_type "general-purpose",
  isolation "worktree", run_in_background true, model exactly as the WP table says
  ("opus" or "sonnet"), effort "high" for Opus WPs and "medium" for Sonnet WPs.
  Launch all WPs of a wave in ONE message so they run concurrently.
- Build each agent prompt from §44.13 of doc 44, filling: WP id and title, closed
  read list, owned paths, append-only shared files allowed, migration slot, the
  doc 43 sections to mirror, and the branch name. Paste a doc 43 section into the
  prompt only if it is under ~150 lines; otherwise give path and section numbers.
  Never give an agent more than its own WP.
- Agents must return the structured result of §44.13. Reject and relaunch an agent
  that returns code dumps or touched paths it does not own. An agent silent for
  more than 90 minutes is stopped and relaunched with the same prompt.
- Merge WPs into brevo/wave-N in the runbook order (§44.14). Resolve only the
  append-only shared files (routes/api.php, routes/web.php,
  AppServiceProvider.php, package.json/composer.json dependency lines). Any other
  conflict means an agent broke ownership: revert that WP and relaunch it with
  the violation named.
- Never rewrite history on a branch an agent produced; merge commits only.
  Never push to main directly.

VERIFICATION GATE (once per wave, after all merges) — §44.10
1. Try locally first: composer install, php artisan test, npm ci, npx tsc --noEmit,
   npm run build, php artisan migrate:fresh --seed. If the container cannot
   install dependencies, push brevo/wave-N and use CI. If the repository has no
   GitHub Actions workflow, create .github/workflows/ci.yml in Wave 0 (the five
   steps above on push and PR) as an extra owned path of WP-40N. If CI is also
   unavailable, run what you can locally, record the gap in the log, and continue.
2. Run the grep gates (no hardcoded French outside fr.ts; no routes outside
   routes/api/*.php and routes/web/*.php) and the route-list vs
   docs/45-brevo-api-contracts.md diff from Wave 1 onward.
3. Launch ONE Opus review agent on the merged wave diff with the code-review skill
   at high effort. Fix blocking findings via the owning WP (relaunch with the
   finding) or, when trivial (≤ 10 lines), directly on brevo/wave-N with a commit
   naming the finding. A security finding is fixed with the safer option, never
   deferred, and noted in the PR body.
4. A WP that fails the gate is relaunched per §44.3 escalation: Sonnet → Sonnet
   with output → Opus with both outputs. An Opus WP that fails twice is NOT a
   stop: split it into two or three smaller WPs with narrower owned paths (write
   them into doc 44 as WP-XXa/b/c), launch them on Opus, and continue. If a split
   WP still fails, launch a dedicated Opus "repair" agent with the full failure
   history and the instruction to make the gate pass with the smallest change;
   if that fails too, mark the WP "deferred" in §44.15 with the failing output
   attached in the log, keep the rest of the wave, and move on. Nothing blocks
   the programme.
5. If a contract in docs/45 must change in a way that affects an already-merged
   wave, change it, launch a Sonnet "contract-fix" agent whose owned paths are the
   affected files from the earlier wave, gate again, log the change. Do not ask.

PULL REQUESTS
- When a wave is green: open a PR brevo/wave-N → brevo/main titled
  "Brevo parity — Wave N: <scope>", body listing WPs, agents/models used, gate
  results, review findings fixed, retries and anything deferred. Subscribe to it
  with subscribe_pr_activity, mark it ready for review and merge it into
  brevo/main yourself as soon as CI is green (pre-authorised). If CI never runs,
  merge after the local gate and say so in the body.
- Update the status table in docs/44 §44.15 (and the roadmap note in docs/34
  §34.1) in the same PR.
- Start the next wave immediately after the merge. Do not wait for the user.

DECISIONS YOU MAKE YOURSELF (never ask)
- Merge order, naming, which failing WP to relaunch first, trivial review fixes,
  adding or fixing the CI workflow, bumping dependencies a WP needs.
- GrapesJS go/no-go after WP-60S: GO if docs/46-editor-spike.md reports working
  load/save/compile inside the Fluent shell and a lazy chunk under 1.2 MB gzip;
  otherwise relaunch WP-60S once with the alternative in doc 43 §43.9 ("own block
  editor over MJML components") and decide on the second memo. If both memos are
  negative, GO with GrapesJS anyway and raise the bundle budget to 2 MB; record
  the trade-off in docs/46.
- Scope cuts: if a WP cannot be made green, cut it to the subset that is green
  and list the remainder under "deferred" in §44.15. Never cut Wave 0–2 scope
  without first trying the split-and-repair path above.

REPORTING
- Keep a running log in docs/47-orchestration-log.md on brevo/main: one entry per
  launch, merge, gate run, retry and decision (timestamp, WP, model, outcome,
  SHA). Commit it at every wave boundary and after every decision; it is how
  you recover after context compaction.
- At each wave boundary post a short progress message (wave done, PR link, gate
  result, retries, next wave launched) and keep going without waiting for a
  reply.
- Only when Wave 4 is merged into brevo/main: open a PR brevo/main → main, ready
  for review (not draft), whose body is the parity checklist against doc 43 §43.2
  (each Brevo area: done / partial / deferred), the list of deferred WPs with
  reasons, and the three things to test by hand first. Subscribe to it, then end
  with a final summary message. That PR is the only thing left for the user; you
  do not merge it.

START NOW: read the four documents, create brevo/main and brevo/wave-0, confirm
the CI situation, then launch Wave 0 (WP-40 on opus, WP-40N on sonnet) in one
message.
```

Return to [README.md](README.md) for the full index.
