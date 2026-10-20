# 45 — Brevo Parity API Contracts (frozen by WP-40)

Status: **frozen** after WP-40 (doc 44 §44.12). A change needed by a later WP is reported in the
WP's structured return and applied by the orchestrator between waves — never edited in place by a
WP agent.

This document is the single contract between backend and UI agents for doc 43 (Brevo parity).
A backend agent implements an endpoint exactly as written here; a UI agent builds against the
TypeScript types in `resources/js/Lib/types/**`, which mirror this document 1:1 (the type name is
given next to every payload). Neither needs to read the other's code.

Contents: 45.1 Conventions · 45.2 Permissions · 45.3 Shared shapes · 45.4 Wave 1 (senders,
campaigns v2, templates v2/picker, checklist) · 45.5 Wave 2 (attributes, segments + rule grammar,
ConditionBuilder props, contacts index/views/bulk/export, contact detail, lists, imports,
blocklist) · 45.6 Wave 3 (editor/MJML, templates folders/gallery, rendering/mirror, tracking,
reports, statistics, preference pages, tracking settings) · 45.7 Wave 4 (saved sections, A/B,
best time, forms, automations, API keys, transactional, webhooks) · 45.8 Schema addendum ·
45.9 Domain events, jobs and service contracts · 45.10 Legacy compatibility · 45.11 i18n map ·
45.12 Open questions.

## 45.1 Conventions

These extend doc 29 §29.1 and never contradict it.

| Topic | Rule |
|---|---|
| Base path | `/api/v1`. Every path below is relative to it unless it starts with `web:` (a web route, no `/api/v1` prefix). |
| Auth | Sanctum session cookie + `X-XSRF-TOKEN` (SPA). From Wave 4 the endpoints marked *API-key* also accept an API key (`api-key: <token>` header, Brevo-style, or `Authorization: Bearer <token>`), checked against the key's scopes (§45.7.6). |
| IDs | Every `id` / `*_id` in URLs and payloads is the row's `uuid` (string). Internal bigint ids never leave the server. Exceptions kept from existing code: `Tag.id`, `RecipientNote.id`, `AuditLog.id`, `ImportRow.id` (numbers). |
| Casing | snake_case everywhere, **except** objects that reproduce Brevo's statistics model, which keep Brevo's camelCase keys verbatim: `globalStats`, `campaignStats`, `linksStats`, `statsByDomain`, `statsByDevice`, `statsByBrowser`, `statsByCountry` and every key inside them (`sent`, `delivered`, `hardBounces`, `softBounces`, `complaints`, `unsubscriptions`, `viewed`, `uniqueViews`, `trackableViews`, `estimatedViews`, `appleMppOpens`, `uniqueClicks`, `clickers`, `deferred`, `returnBounce`, `opensRate`, `mirrorClick`, `remaining`). Webhook payloads (§45.7.7) reproduce Brevo's payload keys verbatim (`message-id`, `ts_event`, `campaign name`…). |
| Rates | snake_case rates (`open_rate`, `click_rate`, `delivery_rate`, `bounce_rate`, `unsubscribe_rate`, `complaint_rate`, `click_to_open_rate`) are **ratios 0–1** (same as the existing `ReportingSummary`). The single Brevo rate key `opensRate` is a **percentage 0–100 with 2 decimals**, as in Brevo. Denominator for every rate except `delivery_rate` is `delivered` (`delivery_rate` = delivered / sent; `click_to_open_rate` = uniqueClicks / uniqueViews). |
| Dates | ISO-8601 UTC strings (`2026-10-19T09:30:00Z`). Date-only values (attribute type `date`) are `YYYY-MM-DD`. Request `scheduled_at` accepts an offset; `timezone` (IANA, default `Africa/Algiers`) is stored for display. |
| Envelope | Single resource `{ "data": {…} }`. Page-paginated list = `PaginatedResponse<T>` (`Lib/types/identity.ts`). Cursor-paginated list = `CursorPaginatedResponse<T>` (`Lib/types/audit.ts`). Extra list metadata is added under `meta` (e.g. `meta.status_counts`). Small non-paginated lists = `{ "data": [ … ] }` (`DataList<T>`). |
| Pagination choice | Cursor: recipients index (`pagination=cursor`), segment members, timeline, report contacts, transactional logs, webhook deliveries, form submissions, automation runs. Page: everything else (`page`, `per_page` ≤ 100, default 25). |
| Errors | doc 29 §29.1 envelope. `409` = state conflict (message explains, e.g. "Seules les campagnes envoyées, suspendues ou annulées peuvent être archivées."). `422` = validation; nested keys use dot paths (`rules.children.0.operator`). Error *messages* are French (shown to users). |
| Async work | Long work returns `202` with `{ "data": AsyncOperation }` (§45.3), polled at `status_url`. |
| Sorting | `sort=field` ascending, `sort=-field` descending. Allowed fields are listed per endpoint; anything else → 422. |
| Structured filters in GET | Travel as URL-encoded JSON in one query parameter (`filters=%7B…%7D`). When the encoded string would exceed 6 KB, use the `POST …/search` twin, which accepts the same keys in a JSON body and returns the same response. |
| Route files | Each WP appends **one** `require` line to `routes/api.php` / `routes/web.php` for its own route file (doc 44 §44.12). Literal sub-paths (`/campaigns/compare`, `/campaigns/bulk`, `/templates/gallery`, `/templates/import-from-url`, `/recipients/search`, `/recipients/bulk`, `/recipients/export`, `/segments/count`) are registered **before** the `{uuid}` routes of the same prefix. |
| Status enums | Values stay English; every value has a French label in `fr.ts` (namespace given per type, usually `<namespace>.statusLabels.<value>`). |
| Legacy | Existing endpoints and their current response fields keep working unchanged unless §45.10 says otherwise; v2 fields are **added**. |

TypeScript mapping: `Lib/types/common.ts` (shared shapes), `campaigns.ts`, `senders.ts`,
`templates.ts`, `editor.ts`, `attributes.ts`, `contacts.ts`, `lists.ts`, `segments.ts`,
`imports.ts`, `suppression.ts`, `reports.ts`, `statistics.ts`, `preferences.ts`, `forms.ts`,
`automations.ts`, `transactional.ts`, `webhooks.ts`.

## 45.2 Permissions

New `App\Domain\Enums\PermissionName` cases (seeded by `RolePermissionSeeder`):

| Value | Case | Guards | Administrator | Marketing manager | Marketing operator | Viewer |
|---|---|---|---|---|---|---|
| `senders.manage` | `SendersManage` | senders & domains write; domains read | ✔ | ✔ | — | — |
| `attributes.manage` | `AttributesManage` | contact-attributes write | ✔ | ✔ | — | — |
| `forms.manage` | `FormsManage` | forms CRUD + submissions | ✔ | ✔ | — | — |
| `automations.manage` | `AutomationsManage` | automations CRUD, activate/pause, runs | ✔ | ✔ | — | — |
| `api_keys.manage` | `ApiKeysManage` | API keys create/revoke | ✔ | — | — | — |
| `webhooks.manage` | `WebhooksManage` | outgoing webhooks + delivery log | ✔ | — | — | — |
| `contacts.export` | `ContactsExport` | contact / list / segment exports | ✔ | ✔ | ✔ | — |
| `campaigns.archive` | `CampaignsArchive` | archive / unarchive / bulk archive | ✔ | ✔ | ✔ | — |
| `transactional.view` | `TransactionalView` | transactional logs + *SMTP & API* page | ✔ | ✔ | — | — |
| `transactional.send` | `TransactionalSend` | `POST transactional/send` for session callers (API keys use scope `transactional:send`) | ✔ | ✔ | — | — |

Existing permissions reused (no new case): lists → write `recipients.manage`, read
`recipients.view`; segments → write `segments.manage`, read `recipients.view`; statistics and
campaign reports → `reporting.view` / `reporting.export` (report read also allowed with
`campaigns.view`); templates → `templates.view` / `templates.manage`; blocklist →
`suppression.view` / `suppression.manage`; unsubscribe pages, update-profile forms, tracking
settings → `settings.manage`; campaigns → existing `campaigns.*`. Wherever this document writes
`A | B` the caller needs **any one** of the listed permissions. The nav (WP-40N) filters on the
same names.

## 45.3 Shared shapes (`Lib/types/common.ts`, `Lib/types/reports.ts`)

```jsonc
// Folder — campaign_folders, template_folders, list_folders (kind list|segment)
{ "id": "uuid", "name": "Newsletters", "kind": "campaign", "items_count": 12,
  "created_at": "2026-10-19T09:00:00Z", "updated_at": "2026-10-19T09:00:00Z" }
// kind: "campaign" | "template" | "list" | "segment"
// FolderInput (POST/PATCH): { "name": "Newsletters" }  (name 1–100 chars, unique per kind)

// UserRef
{ "id": "uuid", "name": "Amina B." }

// AsyncOperation — every 202 response that is not domain-specific
{ "id": "uuid", "type": "contacts_export", "status": "queued", "progress": 0,
  "total": 15000, "processed": 0, "failed": 0, "result": null, "error": null,
  "status_url": "/api/v1/operations/uuid", "created_at": "…", "completed_at": null }
// status: "queued" | "running" | "completed" | "failed"
// type:   "contacts_export" | "contacts_bulk" | "list_delete" | "segment_count"
//       | "report_export" | "template_thumbnail"
// result: free object per type; exports: { "download_url": "…", "expires_at": "…", "row_count": 15000 }

// BulkResult — synchronous bulk responses
{ "processed": 120, "skipped": [ { "id": "uuid", "reason": "Statut incompatible" } ], "failed": 0 }
```

`GET /operations/{id}` → `{ "data": AsyncOperation }` (owner WP-57; table `async_operations`,
slot J). Permission: the user who created it, or `settings.manage`. Backend WPs from Wave 2 on
create rows through the `AsyncOperations` contract (§45.9). Wave-1 WPs that need async behaviour
(WP-41 domain check, WP-42 audience materialisation, WP-45 link check) expose a status field on
their own resource instead and do **not** depend on `/operations`.

### Brevo statistics objects

```jsonc
// BrevoStats — globalStats, one campaignStats entry, one statistics period
{ "sent": 1000, "delivered": 980, "hardBounces": 8, "softBounces": 12, "complaints": 1,
  "unsubscriptions": 4, "viewed": 610, "uniqueViews": 402, "trackableViews": 350,
  "estimatedViews": 402, "appleMppOpens": 52, "uniqueClicks": 95, "clickers": 95,
  "deferred": 3, "returnBounce": 0, "opensRate": 41.02 }
// viewed = total opens; uniqueViews = unique openers incl. proxy opens;
// trackableViews = uniqueViews - appleMppOpens; estimatedViews = uniqueViews (kept for 1:1 mapping);
// clickers = uniqueClicks (contacts who clicked); returnBounce = bounces after "delivered".

// BrevoCampaignStatsEntry — one item of "campaignStats" (per targeted list or segment)
{ "listId": "uuid|null", "segmentId": "uuid|null", "name": "Clients 2025", …every BrevoStats key… }

// BrevoEngagementBreakdown — value inside statsByDevice[*][*], statsByBrowser[*], statsByCountry[*]
{ "clickers": 10, "uniqueClicks": 10, "viewed": 40, "uniqueViews": 33 }

// BrevoDomainStats — value of statsByDomain["gmail.com"]
{ "sent": 500, "delivered": 495, "hardBounces": 2, "softBounces": 3, "complaints": 0,
  "unsubscriptions": 1, "uniqueViews": 210, "uniqueClicks": 40 }

// statsByDevice:  { "desktop": { "<os or client>": Breakdown }, "mobile": {…}, "tablet": {…}, "unknown": {…} }
// statsByBrowser: { "<browser or mail client>": Breakdown }
// statsByCountry: { "<ISO-3166 alpha-2 or 'unknown'>": Breakdown }   (our addition, same casing)
// linksStats:     { "<url>": <total clicks> }   (Brevo shape; per-link rows are in "links")

// RateSet — snake_case ratios computed from a BrevoStats
{ "delivery_rate": 0.98, "open_rate": 0.41, "click_rate": 0.097, "click_to_open_rate": 0.236,
  "unsubscribe_rate": 0.004, "bounce_rate": 0.02, "complaint_rate": 0.001 }
```

### ContactSelection (`Lib/types/contacts.ts`)

Designates contacts for bulk actions and exports without sending 100k ids:

```jsonc
{ "ids": ["uuid", "uuid"] }
// or
{ "all_matching": { "q": "acme", "list_id": "uuid|null", "segment_id": "uuid|null",
                    "view_id": "uuid|null", "filters": RuleTree|null, "scope": "all|mine" },
  "exclude_ids": ["uuid"] }
```
Exactly one of `ids` (1–1000) / `all_matching` is required (422 otherwise).

## 45.4 Wave 1 — Campaign flow

### 45.4.1 WP-41 Senders & domains (`routes/api/senders.php`, `Lib/types/senders.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/senders` | `senders.manage` \| `campaigns.create` \| `composer.compose` | query `verified=1`, `q` | `200 DataList<Sender>` |
| POST | `/senders` | `senders.manage` | `SenderInput` | `201 {data: Sender}` |
| GET | `/senders/{id}` | `senders.manage` | — | `200 {data: Sender}` |
| PATCH | `/senders/{id}` | `senders.manage` | `SenderUpdateInput` | `200 {data: Sender}` |
| DELETE | `/senders/{id}` | `senders.manage` | — | `204`; `409` if default or used by a scheduled/running campaign |
| POST | `/senders/{id}/default` | `senders.manage` | — | `200 {data: Sender}`; `409` if not verified |
| POST | `/senders/{id}/resend-verification` | `senders.manage` | — | `202 {data: Sender}`; `409` if already verified; rate limit 3/h per sender |
| GET | `/senders/{id}/verify` | none (signed URL, 48 h) | `expires`, `signature` | `302` → `/settings/senders?verified={id}`; `403` bad signature |
| GET | `/sending-domains` | `senders.manage` | — | `200 DataList<SendingDomain>` |
| POST | `/sending-domains` | `senders.manage` | `{ "domain": "exemple.dz" }` | `201 {data: SendingDomain}` (DKIM key pair generated, records computed) |
| GET | `/sending-domains/{id}` | `senders.manage` | — | `200 {data: SendingDomain}` |
| POST | `/sending-domains/{id}/verify` | `senders.manage` | — | `202 {data: SendingDomain}` with `status: "checking"`; dispatches `VerifyDomainJob` |
| DELETE | `/sending-domains/{id}` | `senders.manage` | — | `204`; `409` if verified senders use it |

```jsonc
// Sender
{ "id": "uuid", "name": "PageJaunes Algérie", "email": "news@pagesjaunes.dz",
  "domain": "pagesjaunes.dz", "domain_id": "uuid|null",
  "domain_status": "verified",          // SendingDomainStatus | null (domain not registered)
  "is_default": true, "is_verified": true,
  "verification_method": "domain",      // "email_link" | "domain" | "system" (seeded from MAIL_FROM_*) | null while unverified
  "verified_at": "…|null", "verification_sent_at": "…|null",
  "reply_to": "contact@pagesjaunes.dz|null",
  "created_at": "…", "updated_at": "…" }
// SenderInput:       { "name": "…", "email": "…", "reply_to": "…|null", "is_default": false }
// SenderUpdateInput: { "name"?: "…", "reply_to"?: "…|null" }   (email immutable → 422)
// Creating a sender whose domain is "verified" auto-verifies it (verification_method "domain");
// otherwise a French verification e-mail with the signed link is queued.

// SendingDomain
{ "id": "uuid", "domain": "pagesjaunes.dz",
  "status": "verified",                 // "pending" | "checking" | "verified" | "partially_verified" | "failed"
  "is_authenticated": true,             // spf && dkim valid
  "dkim_selector": "pjm1", "has_private_key": true,
  "records": [
    { "kind": "spf",   "host": "pagesjaunes.dz",                 "type": "TXT",
      "value": "v=spf1 include:_spf.pagesjaunes.dz ~all", "status": "valid",
      "found_value": "v=spf1 …|null", "checked_at": "…|null" },
    { "kind": "dkim",  "host": "pjm1._domainkey.pagesjaunes.dz", "type": "TXT",
      "value": "v=DKIM1; k=rsa; p=MIIB…", "status": "missing", "found_value": null, "checked_at": "…" },
    { "kind": "dmarc", "host": "_dmarc.pagesjaunes.dz",          "type": "TXT",
      "value": "v=DMARC1; p=none; rua=mailto:dmarc@pagesjaunes.dz", "status": "invalid",
      "found_value": "v=DMARC1; p=reject", "checked_at": "…" }
  ],                                    // kind: "spf" | "dkim" | "dmarc"; status: "pending" | "valid" | "invalid" | "missing"
  "senders_count": 3, "last_checked_at": "…|null", "created_at": "…" }
```

The private DKIM key is stored encrypted and never returned. The SPF `include:` target and the
DMARC `rua` address come from settings `senders.spf_include` / `senders.dmarc_rua`.

### 45.4.2 WP-42 Campaigns v2 (`routes/api/campaigns.php`, `Lib/types/campaigns.ts`)

#### Statuses

`CampaignStatus` (DB/enum, English): `draft`, `scheduled`, `running`, `paused`, `suspended`
(new — paused by the system: quota exhausted / every SMTP account failing / approval revoked;
`suspended_reason` explains), `completed`, `cancelled` (TS: `CampaignStatusV2`; the legacy TS
`CampaignStatus` union stays unchanged for the current pages). Archive is **not** a status: it is
`archived_at` (+ `is_archived`) and can only be set when status ∈ {`completed`, `suspended`,
`cancelled`}.

Brevo mapping exposed read-only as `brevo_status` (`CampaignBrevoStatus`):

| `status` | archived? | `brevo_status` | List tab (`status_tab`) | UI label (`fr.campaigns.statusLabels`) |
|---|---|---|---|---|
| draft | no | `draft` | `draft` | Brouillon |
| scheduled | no | `queued` | `scheduled` | Programmée |
| running | no | `in_process` | `running` | En cours |
| paused | no | `suspended` | `running` | En pause |
| suspended | no | `suspended` | `suspended` | Suspendue |
| completed | no | `sent` | `sent` | Envoyée |
| cancelled | no | `cancelled` | (only `all`) | Annulée |
| any | yes | `archive` | `archived` (excluded from `all`) | Archivée |

(`in_review` and `cancelling` exist in the union for Brevo parity: `in_review` while an approval
is pending, `cancelling` while a cancel is draining the queue.)

`available_actions` (`CampaignAction[]`, computed server-side from the state machine so the UI
never re-implements it): `edit`, `report`, `duplicate`, `test_send`, `schedule`, `unschedule`,
`send_now`, `pause`, `resume`, `cancel`, `send_to_new_contacts`, `archive`, `unarchive`, `move`,
`delete`.

| status | actions (besides `duplicate`, `move`) |
|---|---|
| draft | edit, test_send, schedule, send_now, delete |
| scheduled | edit, test_send, unschedule, send_now, cancel |
| running | report, pause, cancel |
| paused | report, resume, cancel |
| suspended | report, edit, resume, cancel, archive |
| completed | report, send_to_new_contacts, archive |
| cancelled | report (if any message sent), archive, delete |
| archived | report, unarchive, delete |

#### Endpoints

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/campaigns` | `campaigns.view` | `CampaignListQuery` | `200 CampaignListResponse` |
| POST | `/campaigns` | `campaigns.create` | `CampaignCreateInput` | `201 {data: CampaignV2Detail}` |
| GET | `/campaigns/{id}` | `campaigns.view` | — | `200 {data: CampaignV2Detail}` |
| PATCH | `/campaigns/{id}` | `campaigns.create` (own) \| `campaigns.send_any` | `CampaignUpdateInput` | `200 {data: CampaignV2Detail}`; `409` if status ∉ {draft, scheduled, suspended} |
| DELETE | `/campaigns/{id}` | `campaigns.create` (own) \| `campaigns.send_any` | — | `204`; `409` unless `delete` ∈ available_actions |
| PUT | `/campaigns/{id}/design` | same as PATCH | `CampaignDesignInput` | `200 {data: CampaignV2Detail}` |
| POST | `/campaigns/{id}/design/copy` | same as PATCH | `CampaignDesignCopyInput` | `200 {data: CampaignV2Detail}` |
| GET | `/campaigns/{id}/preview` | `campaigns.view` | query `contact_id?`, `variant?` (`A`/`B`) | `200 {data: CampaignPreview}` |
| POST | `/campaigns/{id}/audience/estimate` | `campaigns.create` | `{ "audience"?: CampaignAudienceInput }` (omitted = saved audience) | `200 {data: AudienceEstimate}` |
| POST | `/campaigns/{id}/test-send` | `campaigns.create` | `CampaignTestSendInput` | `202 {data: TestSendResult}`; rate limit 10/h per campaign |
| POST | `/campaigns/{id}/schedule` | `campaigns.send` (own) \| `campaigns.send_any` | `CampaignScheduleInput` | `200 {data: CampaignV2Detail}`; `422` with `errors.checklist` if a blocking checklist item fails; `409` on state |
| POST | `/campaigns/{id}/unschedule` | same as schedule | — | `200 {data: CampaignV2Detail}` (back to `draft`) |
| POST | `/campaigns/{id}/send` | existing | — | existing behaviour = `schedule` with `mode: "now"` |
| POST | `/campaigns/{id}/pause` · `/cancel` | existing (`campaigns.cancel_pause`) | — | `200 {data: CampaignV2Detail}` |
| POST | `/campaigns/{id}/resume` | `campaigns.cancel_pause` | — | `200`; now valid from `paused` **and** `suspended` (clears `suspended_reason`) |
| POST | `/campaigns/{id}/duplicate` | `campaigns.create` | `{ "name"?: "…", "folder_id"?: "uuid\|null" }` | `201 {data: CampaignV2Detail}` (name default "Copie de {name}", status draft, audience + design + settings copied, stats reset) |
| POST | `/campaigns/{id}/clone` | existing | — | alias of `duplicate` (kept) |
| POST | `/campaigns/{id}/send-to-new-contacts` | `campaigns.send` (own) \| `campaigns.send_any` | `{ "name"?: "…" }` | `201 {data: CampaignV2Detail}` child draft with `parent_campaign_id`; audience = parent audience minus everyone already in parent's `campaign_recipients`; `409` unless status `completed` |
| POST | `/campaigns/{id}/archive` | `campaigns.archive` | — | `200 {data: CampaignV2Detail}`; `409` unless status ∈ {completed, suspended, cancelled} |
| POST | `/campaigns/{id}/unarchive` | `campaigns.archive` | — | `200 {data: CampaignV2Detail}` |
| POST | `/campaigns/bulk` | `campaigns.create` (+ `campaigns.archive` for archive) | `CampaignBulkInput` | `200 {data: BulkResult}` |
| GET | `/campaigns/{id}/recipients` | existing | existing + `status?` | existing (rows of `campaign_recipients`) |
| GET | `/campaign-folders` | `campaigns.view` | — | `200 DataList<Folder>` (kind `campaign`) |
| POST | `/campaign-folders` | `campaigns.create` | `FolderInput` | `201 {data: Folder}` |
| PATCH | `/campaign-folders/{id}` | `campaigns.create` | `FolderInput` | `200 {data: Folder}` |
| DELETE | `/campaign-folders/{id}` | `campaigns.create` | — | `204`; campaigns inside move to "no folder" |

```jsonc
// CampaignListQuery (GET /campaigns)
{ "status_tab": "all",        // "all" | "draft" | "scheduled" | "running" | "sent" | "suspended" | "archived"
  "status": "draft",          // legacy filter, still honoured
  "folder_id": "uuid|none",   // "none" = campaigns without folder
  "tag": "promo", "type": "classic", "q": "rentrée",
  "sort": "-updated_at",      // updated_at | name | sent_at | scheduled_at | created_at (± prefix)
  "page": 1, "per_page": 25 }

// CampaignListResponse = PaginatedResponse<CampaignV2> + meta.status_counts
{ "data": [ CampaignV2 ], "links": {…},
  "meta": { …page meta…, "status_counts": { "all": 41, "draft": 6, "scheduled": 2, "running": 1,
            "sent": 29, "suspended": 1, "archived": 9 } } }

// CampaignV2 (list item) — superset of the legacy Campaign resource (every legacy key kept)
{ "id": "uuid", "name": "Newsletter octobre", "type": "classic",   // "classic" | "ab_test"
  "status": "completed", "brevo_status": "sent",
  "is_archived": false, "archived_at": null, "suspended_reason": null,
  // suspended_reason: "quota_exhausted" | "smtp_unavailable" | "approval_revoked" | "manual" | null
  "folder_id": "uuid|null", "tags": ["newsletter"],
  "subject": "Nos nouveautés d'octobre", "preview_text": "Découvrez…",
  "sender": { "id": "uuid", "name": "PageJaunes", "email": "news@pagesjaunes.dz", "is_verified": true },
  "template_id": "uuid|null", "recipient_list_id": null, "segment_id": null,   // legacy, nullable
  "editor_type": "dnd",       // "dnd" | "html" | "rich_text" | null (no design yet)
  "thumbnail_url": "https://…|null",
  "scheduled_at": "…|null", "sent_at": "…|null", "timezone": "Africa/Algiers",
  "send_mode": "scheduled",   // "now" | "scheduled" | "best_time" | "batches" | null
  "approved_by": null, "cloned_from_campaign_id": null, "parent_campaign_id": null,
  "owner": { "id": "uuid", "name": "Amina B." },
  "stats_summary": { "recipients": 1000, "sent": 1000, "delivered": 980, "unique_opens": 402,
                     "unique_clicks": 95, "unsubscriptions": 4, "hard_bounces": 8, "soft_bounces": 12,
                     "complaints": 1, "open_rate": 0.41, "click_rate": 0.097 },   // null before sending
  "ab_test_enabled": false,
  "available_actions": ["report", "duplicate", "send_to_new_contacts", "archive", "move"],
  "created_at": "…", "updated_at": "…" }

// CampaignV2Detail = CampaignV2 + (setup + design)
{ …CampaignV2…,
  "html_body": "<html>…|null", "design_json": { …editor JSON… } | null,
  "reply_to": "contact@pagesjaunes.dz|null",
  "to_field": "{{ contact.PRENOM }} {{ contact.NOM }}|null",
  "audience": {
    "lists":             [ { "id": "uuid", "name": "Clients", "contact_count": 812 } ],
    "exclusion_lists":   [],
    "segments":          [ { "id": "uuid", "name": "Actifs 90 j", "contact_count": 340 } ],
    "excluded_segments": [],
    "estimated_count": 1000, "estimated_at": "…|null",
    "materialised_count": null      // set once campaign_recipients rows exist
  },
  "utm": { "enabled": true, "source": "pagesjaunes", "medium": "email", "campaign": "newsletter-octobre",
           "content": null, "term": null, "id": null },
  "mirror_link_enabled": true, "inline_images": false,
  "attachment": { "id": "uuid", "filename": "catalogue.pdf", "size": 182044 } ,   // or null
  "email_expiration": { "duration": 30, "unit": "days" },   // unit: "days" | "weeks" | "months"; or null
  "unsubscribe_page_id": "uuid|null", "update_profile_form_id": "uuid|null",
  "tracking_enabled": true,
  "smtp_strategy": "rotation",     // "rotation" | "single" ; "single" requires smtp_account_id
  "smtp_account_id": "uuid|null",
  "send_window": { "days": [1,2,3,4,0], "start": "08:00", "end": "17:00" },   // or null (weekday 0=Sun…6=Sat, JS getDay / Carbon dayOfWeek)
  "requires_approval": false,
  "send_time_optimized": false,
  "batch": { "size": 500, "interval_minutes": 30 },   // or null
  "ab_test": null,                 // AbTestConfig + variants, filled by WP-80 (§45.7.2)
  "test_sent_at": "…|null", "share_link": "https://…/m/c/uuid?signature=…|null",
  "checklist_status": "incomplete" // "complete" | "incomplete" | "blocking" (cached from WP-45)
}

// CampaignCreateInput (Brevo asks only for the internal name)
{ "name": "Newsletter octobre", "type": "classic", "folder_id": "uuid|null" }
// legacy keys template_id / recipient_list_id / segment_id still accepted (converted to audience)

// CampaignUpdateInput — every key optional; same names as CampaignV2Detail
{ "name": "…", "subject": "…", "preview_text": "…", "sender_id": "uuid", "reply_to": "…|null",
  "to_field": "…|null", "folder_id": "uuid|null", "tags": ["…"],
  "audience": CampaignAudienceInput,
  "utm": { "enabled": true, "source": "…", "medium": "…", "campaign": "…", "content": "…", "term": "…", "id": "…" },
  "mirror_link_enabled": true, "inline_images": false, "attachment_id": "uuid|null",
  "email_expiration": { "duration": 30, "unit": "days" } , "unsubscribe_page_id": "uuid|null",
  "update_profile_form_id": "uuid|null", "tracking_enabled": true, "smtp_strategy": "rotation",
  "smtp_account_id": "uuid|null", "send_window": null, "requires_approval": false }
// validation: subject ≤ 255; preview_text ≤ 255; tags ≤ 20 × 50 chars; sender must be verified (422);
// utm keys ≤ 100 chars; email_expiration.duration 1–365.

// CampaignAudienceInput — replaces the one-list-or-one-segment rule
{ "lists": ["uuid"], "exclusion_lists": ["uuid"], "segments": ["uuid"], "excluded_segments": ["uuid"] }
// at least one list or segment before scheduling (checklist), ≤ 50 targets in total.

// CampaignDesignInput (editor save; WP-60 builder and WP-44 code/rich-text paths)
{ "editor_type": "dnd", "html_body": "<html>…", "design_json": { … } }   // design_json null for html/rich_text
// CampaignDesignCopyInput (template picker)
{ "source_type": "template", "source_id": "uuid" }   // "template" | "gallery" | "campaign" | "blank"

// CampaignPreview
{ "subject": "Bonjour Amina", "preview_text": "…", "from": { "name": "…", "email": "…" },
  "to": "Amina Benali <amina@exemple.dz>", "html": "<html>…", "contact_id": "uuid|null" }

// AudienceEstimate
{ "included": 1152, "excluded": 140, "suppressed": 12, "duplicates": 0, "total": 1000,
  "unengaged": 310, "unengaged_percent": 0.31, "unengaged_warning": true,   // threshold setting campaigns.unengaged_warning_percent (default 0.30), "no open in 90 days"
  "by_target": [ { "type": "list", "mode": "include", "id": "uuid", "name": "Clients", "count": 812 } ],
  "computed_at": "…" }

// CampaignTestSendInput
{ "emails": ["a@exemple.dz"], "contact_id": "uuid|null", "variant": "A|B|null" }   // 1–5 emails
// TestSendResult
{ "queued": 1, "message_ids": ["uuid"], "test_sent_at": "…" }

// CampaignScheduleInput — the four delivery options of "Confirmer l'envoi"
{ "mode": "scheduled",            // "now" | "scheduled" | "best_time" | "batches"
  "scheduled_at": "2026-10-21T09:00:00+01:00",   // required for scheduled; optional start for best_time/batches
  "timezone": "Africa/Algiers",
  "batch_size": 500, "batch_interval_minutes": 30 }   // required for batches (size 50–100000, interval 5–1440)
// best_time: sets send_time_optimized=true; WP-81 releases each message at the contact's best hour
// within 24 h of scheduled_at (or now). batches: maps onto the throttle as a per-campaign rate cap.

// CampaignBulkInput
{ "action": "archive", "ids": ["uuid"], "folder_id": "uuid|null" }   // action: "archive" | "unarchive" | "move" | "delete"
```

### 45.4.3 WP-43 Campaign list + Setup UI

UI-only. Consumes §45.4.1, §45.4.2, §45.4.5, `GET /recipient-lists` and `GET /segments`
(§45.5) for the recipients dialog (until Wave 2 lands, the dialog lists whatever those endpoints
return today), `GET /contact-attributes/merge-tags` (§45.5.1) for the subject personalisation menu
(fallback to the static list `{{ contact.PRENOM }}`, `{{ contact.NOM }}`, `{{ contact.EMAIL }}`,
`{{ contact.SOCIETE }}` when the endpoint 404s), `GET /unsubscribe-pages` / `GET
/update-profile-forms` (§45.6.6, empty list until Wave 3). Labels: `fr.campaigns`,
`fr.campaignSetup`. The top-bar button links to `/campaigns?create=1`: `Campaigns/Index`
opens the create-campaign dialog when that query parameter is present.

### 45.4.4 WP-44 Template picker + code path (`routes/api/templates.php`, `Lib/types/templates.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/templates` | `templates.view` | `TemplateListQuery` | `200 PaginatedResponse<TemplateV2>` |
| POST | `/templates` | `templates.manage` | `TemplateInput` | `201 {data: TemplateV2Detail}` |
| GET | `/templates/{id}` | `templates.view` | — | `200 {data: TemplateV2Detail}` |
| PATCH | `/templates/{id}` | `templates.manage` | `TemplateInput` (partial) | `200 {data: TemplateV2Detail}` (creates a `template_versions` row as today) |
| POST | `/templates/{id}/duplicate` | `templates.manage` | `{ "name"?: "…", "folder_id"?: "uuid\|null", "scope"?: "marketing\|transactional" }` | `201 {data: TemplateV2Detail}` (works on gallery templates → copy in *Mes modèles*, `is_gallery=false`) |
| POST | `/templates/{id}/test-send` | `templates.manage` | `{ "emails": ["…"], "contact_id"?: "uuid", "subject"?: "…" }` (1–5) | `202 {data: TestSendResult}` |
| POST | `/templates/import-from-url` | `templates.manage` | `{ "url": "https://…" }` | `200 {data: HtmlImportResult}`; `422` blocked host / non-HTML / > 2 MB; fetch timeout 10 s, http(s) only, private/loopback/link-local IPs refused after DNS resolution, ≤ 3 redirects re-checked |
| POST | `/templates/import-zip` | `templates.manage` | multipart `file` (.zip ≤ 10 MB, one .html + images) | `200 {data: HtmlImportResult}` (images stored publicly, `src` rewritten) |

```jsonc
// TemplateListQuery
{ "scope": "marketing", "folder_id": "uuid|none", "q": "…", "is_gallery": false,
  "editor_type": "dnd", "is_archived": false, "category": "…", "sort": "-updated_at", "page": 1 }
// sort: updated_at | name | created_at | usage_count

// TemplateV2 — legacy Template keys kept (id, name, category, thumbnail_path, html_content,
// is_archived, usage_count, updated_at); html_content is omitted from list responses (null) from v2
{ …Template…, "editor_type": "dnd",    // "dnd" | "html" | "rich_text"
  "scope": "marketing",                // "marketing" | "transactional"
  "folder_id": "uuid|null", "is_gallery": false, "gallery_category": "newsletter|null",
  "thumbnail_url": "https://…|null", "subject": "…|null",   // subject used by transactional sends
  "created_by": UserRef|null, "created_at": "…" }
// TemplateV2Detail = TemplateV2 + { "html_content": "…", "design_json": {…}|null, "versions_count": 4 }

// TemplateInput
{ "name": "…", "category": "…|null", "editor_type": "html", "scope": "marketing",
  "folder_id": "uuid|null", "html_content": "…", "design_json": null, "subject": "…|null" }

// HtmlImportResult (nothing persisted; the picker then calls PUT /campaigns/{id}/design or POST /templates)
{ "html": "<html>…", "title": "Newsletter|null",
  "assets": [ { "filename": "logo.png", "url": "https://…/storage/imports/…/logo.png" } ],
  "warnings": ["Les scripts ont été supprimés."] }
```

Picker tabs → data sources: **Galerie** `GET /templates/gallery` (§45.6.2; WP-44 returns
`GET /templates?is_gallery=1` content until WP-62 seeds), **Mes modèles** `GET
/templates?is_gallery=0&scope=marketing`, **Campagnes précédentes** `GET
/campaigns?status_tab=sent&sort=-sent_at` then `POST /campaigns/{id}/design/copy`
(`source_type: "campaign"`), **Coder** paste / import-from-url / import-zip then `PUT
/campaigns/{id}/design` (`editor_type: "html"`), **Éditeur de texte enrichi** `PUT
/campaigns/{id}/design` (`editor_type: "rich_text"`).

### 45.4.5 WP-45 Pre-send checklist (`Lib/types/campaigns.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/campaigns/{id}/checklist` | `campaigns.view` | — | `200 {data: CampaignChecklist}` |
| POST | `/campaigns/{id}/checklist/links` | `campaigns.create` | — | `202 {data: CampaignChecklist}` (`links_valid.status = "pending"`; `CheckCampaignLinksJob`, 5 s timeout per URL, ≤ 100 URLs) |

```jsonc
// CampaignChecklist
{ "campaign_id": "uuid", "can_schedule": false, "status": "blocking",   // "complete" | "incomplete" | "blocking"
  "items": [
    { "key": "sender_verified",      "status": "ok",      "blocking": true,  "message": "Expéditeur vérifié", "details": null },
    { "key": "recipients_selected",  "status": "error",   "blocking": true,  "message": "Aucun destinataire", "details": { "count": 0 } },
    { "key": "subject_filled",       "status": "ok",      "blocking": true,  "message": "…", "details": null },
    { "key": "design_ready",         "status": "ok",      "blocking": true,  "message": "…", "details": null },
    { "key": "unsubscribe_link",     "status": "error",   "blocking": true,  "message": "Le design ne contient pas de lien de désinscription", "details": null },
    { "key": "test_sent",            "status": "warning", "blocking": false, "message": "Aucun e-mail de test envoyé", "details": null },
    { "key": "links_valid",          "status": "pending", "blocking": false, "message": "…", "details": { "broken": [ { "url": "…", "status": 404 } ] } },
    { "key": "email_size",           "status": "ok",      "blocking": false, "message": "…", "details": { "bytes": 48211, "limit": 102400 } },
    { "key": "spam_score",           "status": "skipped", "blocking": false, "message": "…", "details": null }
  ],
  "checked_at": "…" }
// key: "sender_verified" | "recipients_selected" | "subject_filled" | "design_ready" | "unsubscribe_link"
//    | "test_sent" | "links_valid" | "email_size" | "spam_score"
// status: "ok" | "warning" | "error" | "pending" | "skipped"
// spam_score is the hook for doc 39 (always "skipped" until that analyzer exists).
```

`POST /campaigns/{id}/schedule` calls `CampaignChecklistService::evaluate()` (§45.9) and refuses
(422, `errors.checklist = [keys…]`) when a blocking item is `error`.

## 45.5 Wave 2 — Contacts parity

### 45.5.1 WP-50 Contact attributes (`routes/api/attributes.php`, `Lib/types/attributes.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/contact-attributes` | `recipients.view` \| `campaigns.create` \| `templates.manage` | query `include_usage=1` | `200 DataList<ContactAttribute>` (≤ 200 rows, system first then by name) |
| POST | `/contact-attributes` | `attributes.manage` | `ContactAttributeInput` | `201 {data: ContactAttribute}`; `422` name invalid / duplicate / 200-limit reached |
| GET | `/contact-attributes/{id}` | `recipients.view` | — | `200 {data: ContactAttribute}` |
| PATCH | `/contact-attributes/{id}` | `attributes.manage` | `ContactAttributeUpdateInput` | `200 {data: ContactAttribute}`; `409` system attribute |
| DELETE | `/contact-attributes/{id}` | `attributes.manage` | body `{ "confirm": true }` | `204` (values removed from `custom_fields` by a queued job; audit `attribute.deleted`); `409` system attribute or used by a segment / form / automation (`errors.used_in = [{type, id, name}]`) |
| GET | `/contact-attributes/merge-tags` | `recipients.view` \| `campaigns.create` \| `templates.manage` | — | `200 DataList<MergeTag>` |

```jsonc
// ContactAttribute
{ "id": "uuid", "name": "VILLE",          // UPPERCASE, ^[A-Z][A-Z0-9_]{0,49}$
  "label": "Ville",                        // French display label, defaults to the name
  "type": "category",                      // ContactAttributeType
  "options": [ { "value": "alger", "label": "Alger" }, { "value": "oran", "label": "Oran" } ],
  // options: required (≥ 1, ≤ 500) for category & multiple_choice; [] otherwise
  "is_system": false,
  "storage": "custom_fields",              // "column" (system → recipients column) | "custom_fields"
  "column": null,                          // e.g. "first_name" for PRENOM when storage = column
  "merge_tag": "{{ contact.VILLE }}",
  "usage_count": 812,                      // only with include_usage=1, else null
  "created_at": "…", "updated_at": "…" }
// ContactAttributeType: "text" | "number" | "date" | "boolean" | "category" | "multiple_choice" | "user"
// Value encoding in custom_fields / API: text → string; number → number; date → "YYYY-MM-DD";
// boolean → true|false; category → option value (string); multiple_choice → string[] of option values;
// user → user uuid (string). Invalid values → 422 "attributes.VILLE".
// System attributes (seeded, read-only): EMAIL (text, column email), PRENOM (text, first_name),
// NOM (text, last_name), SOCIETE (text, company_name), SMS (text, custom_fields, placeholder),
// EXT_ID (text, ext_id).

// ContactAttributeInput
{ "name": "VILLE", "label": "Ville", "type": "category", "options": [ { "value": "alger", "label": "Alger" } ] }
// ContactAttributeUpdateInput — name and type immutable
{ "label": "Ville", "options": [ … ] }   // removing an option in use → 409

// MergeTag
{ "tag": "{{ contact.PRENOM }}", "label": "Prénom", "group": "contact", "attribute": "PRENOM" }
// group: "contact" | "links" | "campaign"; links group: {{ mirror }}, {{ unsubscribe }}, {{ update_profile }};
// campaign group: {{ campaign.NAME }}, {{ campaign.SUBJECT }}, {{ sender.NAME }}, {{ sender.EMAIL }}.
// Filters (resolved by MergeVariableResolver): {{ contact.PRENOM | default:"cher client" }}.
```

Every writer of attribute values (contact PATCH, bulk update, import, forms, automations) goes
through `ContactAttributeService::apply()` (§45.9), which validates, casts and fires
`ContactAttributesUpdated`.

### 45.5.2 WP-54 Segments v2 (`routes/api/segments.php`, `Lib/types/segments.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/segments` | `recipients.view` | `q`, `folder_id` (`none`), `sort` (`name`, `-updated_at`, `contact_count`), `page` | `200 PaginatedResponse<Segment>` |
| POST | `/segments` | `segments.manage` | `SegmentInput` | `201 {data: Segment}` |
| POST | `/segments/count` | `recipients.view` | `{ "rules": RuleTree }` | `200 {data: SegmentCount}` (not persisted; 10 s budget, else `202 {data: AsyncOperation}` type `segment_count`) |
| GET | `/segments/{id}` | `recipients.view` | — | `200 {data: Segment}` |
| PATCH | `/segments/{id}` | `segments.manage` | `SegmentInput` (partial) | `200 {data: Segment}` |
| DELETE | `/segments/{id}` | `segments.manage` | — | `204`; `409` used by a scheduled/running campaign or an active automation |
| POST | `/segments/{id}/count` | `recipients.view` | — | `200 {data: SegmentCount}` (refreshes cached `contact_count`) |
| GET | `/segments/{id}/members` | `recipients.view` | `cursor`, `per_page`, `columns` | `200 CursorPaginatedResponse<Contact>` |
| POST | `/segments/{id}/duplicate` | `segments.manage` | `{ "name"?: "…" }` | `201 {data: Segment}` |
| POST | `/segments/{uuid}/preview` | existing | existing | existing (kept; internally = count + 10-row sample) |
| GET | `/list-folders?kind=segment` | see §45.5.5 | | segment folders share the `list_folders` table (`kind = segment`) |

```jsonc
// Segment
{ "id": "uuid", "name": "Actifs 90 jours", "description": "…|null", "folder_id": "uuid|null",
  "rules": RuleTree, "contact_count": 340, "count_computed_at": "…|null",
  "recipient_list_id": null,               // legacy, nullable from v2
  "used_in": { "campaigns": 2, "automations": 0 },
  "created_by": UserRef|null, "created_at": "…", "updated_at": "…" }
// SegmentInput
{ "name": "…", "description": "…|null", "folder_id": "uuid|null", "rules": RuleTree }
// SegmentCount
{ "count": 340, "computed_at": "…", "duration_ms": 412 }
```

#### Rule-tree JSON grammar (version 2) — `RuleTree`

Shared by segments, the Contacts *Ajouter un filtre* builder, contact views, automation
condition steps, form/automation triggers and report *Voir les contacts*.

```jsonc
RuleTree      := { "version": 2, "type": "group", "operator": "and" | "or", "children": [ Group | Condition ] }
Group         := { "type": "group", "operator": "and" | "or", "children": [ Condition ] }
Condition     := { "type": "condition", "field": FieldKey, "operator": Operator, "value": Value }
```

Constraints: depth ≤ 2 (root → groups → conditions, as Brevo's UI: groups of conditions joined
by AND/OR, conditions inside a group joined by the group's operator; a root child may also be a
bare Condition); ≤ 100 conditions in total; empty root (`children: []`) = all contacts. The
compiler always excludes soft-deleted recipients; blocklisted contacts are **not** excluded by the
compiler (campaign audiences exclude them via suppression). A tree without `version` is the
legacy v1 tree of doc 13 §13.7 and is converted on read by WP-54 (stored back as v2 on save).

**Field keys (`FieldKey`)**

| Category (UI group) | Field key | Type (operator set) | Notes |
|---|---|---|---|
| Attributs du contact | `attribute.<NAME>` | the attribute's type | every row of `GET /contact-attributes`, incl. `attribute.EMAIL`, `attribute.PRENOM`… |
| Contact | `contact.tags` | `tags` | value = tag ids (numbers) |
| Contact | `contact.status` | `category` | options = recipient statuses (`active`, `suppressed`, `invalid`) |
| Contact | `contact.source` | `category` | options = recipient sources (`manual`, `csv_import`, `excel_import`, `pagejaunes`, `internal_contact`, `form`, `api`) |
| Contact | `contact.blocklisted` | `boolean` | from `recipients.blocklisted_at` |
| Contact | `contact.engagement_score` | `number` | 0–100 |
| Listes | `list` | `list` | membership |
| Engagement e-mail | `engagement.sent` · `engagement.delivered` · `engagement.opened` · `engagement.clicked` · `engagement.soft_bounced` · `engagement.hard_bounced` · `engagement.unsubscribed` · `engagement.complained` | `engagement` | value = `EngagementValue` |
| Dates | `contact.created_at` · `contact.updated_at` · `contact.last_contacted_at` · `contact.last_opened_at` · `contact.last_clicked_at` · `contact.opt_in_at` · `contact.blocklisted_at` | `date` | |
| PagesJaunes | `pagejaunes.sector` · `pagejaunes.wilaya` · `pagejaunes.locality` · `pagejaunes.legal_form` | `text` | from the recipient's PageJaunes snapshot |
| PagesJaunes | `pagejaunes.is_exporter` · `pagejaunes.is_importer` · `pagejaunes.is_producer` · `pagejaunes.is_distributor` | `boolean` | |

**Operators per type** (`fr.conditionBuilder.operators.<operator>` holds the French label)

| Type | Operators | `value` |
|---|---|---|
| `text` | `equals`, `not_equals`, `contains`, `not_contains`, `starts_with`, `ends_with`, `in`, `not_in`, `is_empty`, `is_not_empty` | string; `in`/`not_in`: string[] (≤ 500); empty ops: `null`. Case-insensitive. |
| `number` | `equals`, `not_equals`, `greater_than`, `greater_or_equal`, `less_than`, `less_or_equal`, `between`, `is_empty`, `is_not_empty` | number; `between`: `[min, max]` inclusive |
| `date` | `on`, `before`, `after`, `between`, `in_last_days`, `more_than_days_ago`, `in_next_days`, `anniversary_today`, `is_empty`, `is_not_empty` | `on`/`before`/`after`: `"YYYY-MM-DD"`; `between`: `["YYYY-MM-DD","YYYY-MM-DD"]`; `*_days`: integer 1–3650; `anniversary_today`/empty: `null`. Evaluated in the account timezone. |
| `boolean` | `is_true`, `is_false`, `is_empty` | `null` |
| `category` | `in`, `not_in`, `is_empty`, `is_not_empty` | option values string[] |
| `multiple_choice` | `contains_any`, `contains_all`, `not_contains_any`, `is_empty`, `is_not_empty` | option values string[] |
| `user` | `in`, `not_in`, `is_empty`, `is_not_empty` | user uuids string[] |
| `tags` | `contains_any`, `contains_all`, `not_contains_any`, `is_empty`, `is_not_empty` | tag ids number[] |
| `list` | `in`, `not_in` | list uuids string[] (contact is in **any** of / in **none** of) |
| `engagement` | `has`, `has_not` | `EngagementValue` |

```jsonc
// EngagementValue
{ "campaign_ids": null,            // null = "n'importe quelle campagne"; else 1–50 campaign uuids
  "within_days": 30,               // null = ever; 1–3650
  "from": null, "to": null,        // optional explicit window "YYYY-MM-DD" (overrides within_days)
  "link_url": null,                // engagement.clicked only: exact URL or prefix ending with "*"
  "min_count": null,               // optional: at least N events (opened/clicked)
  "automation_step_id": null }     // optional: restrict to e-mails sent by one automation step (WP-91)
```

Example — "opened campaign X in the last 30 days AND (city is Alger OR Oran) AND not in list
Prospects":

```json
{ "version": 2, "type": "group", "operator": "and", "children": [
  { "type": "condition", "field": "engagement.opened", "operator": "has",
    "value": { "campaign_ids": ["c1…"], "within_days": 30, "from": null, "to": null, "link_url": null, "min_count": null, "automation_step_id": null } },
  { "type": "group", "operator": "or", "children": [
    { "type": "condition", "field": "attribute.VILLE", "operator": "in", "value": ["alger"] },
    { "type": "condition", "field": "attribute.VILLE", "operator": "in", "value": ["oran"] } ] },
  { "type": "condition", "field": "list", "operator": "not_in", "value": ["l1…"] } ] }
```

Validation errors use dot paths into the tree (`rules.children.1.children.0.value`); unknown
attribute → `422 "Attribut inconnu : VILLE"`.

### 45.5.3 ConditionBuilder props contract (WP-54U builds, WP-51 / WP-91U embed)

Component path: `resources/js/Components/Contacts/ConditionBuilder/ConditionBuilder.tsx`, default
export `ConditionBuilder`; types in `Lib/types/segments.ts`.

```ts
export interface ConditionBuilderProps {
    /** Current rule tree (controlled). `null` = empty builder (renders one empty group). */
    value: RuleTree | null;
    /** Called with a new tree on every change (immutable update). Incomplete rows are kept in the
     *  tree with `value: null`; use `isRuleTreeComplete` (exported by the component module) to gate
     *  saving. */
    onChange: (tree: RuleTree) => void;
    /** Fields the user may pick. Build with `buildFieldCatalog(attributes, options)` exported by the
     *  component module: GET /contact-attributes rows + the static contact/list/engagement/date/
     *  PagesJaunes fields of §45.5.2. */
    fieldCatalog: FieldCatalog;
    /** Live count. Called (debounced 600 ms by the component) with a complete tree; resolve with the
     *  count. Typically `(tree) => api.segments.count(tree).then(r => r.count)`. Omit to hide the
     *  count button/badge. */
    onCount?: (tree: RuleTree, signal: AbortSignal) => Promise<number>;
    /** Lookup sources for value pickers (lists, campaigns, users, tags). */
    optionSources?: ConditionOptionSources;
    /** Max total conditions (default 100, Brevo's limit). */
    maxConditions?: number;
    /** Restrict available categories (e.g. automations hide "pagejaunes"). Default: all. */
    categories?: FieldCategory[];
    /** Server validation errors keyed by dot path relative to the tree root (e.g. "children.0.value"). */
    errors?: Record<string, string>;
    disabled?: boolean;
    /** "full" (segment page, groups allowed) | "compact" (Contacts filter bar: one group, inline). */
    variant?: 'full' | 'compact';
    /** Rendered in the footer, e.g. "Enregistrer comme segment" / "Appliquer". */
    footer?: React.ReactNode;
}

export interface FieldCatalog { fields: FieldDefinition[] }
export interface FieldDefinition {
    key: FieldKey;                 // "attribute.VILLE", "list", "engagement.opened", …
    label: string;                 // French
    category: FieldCategory;       // "attribute" | "contact" | "list" | "engagement" | "date" | "pagejaunes"
    type: ConditionValueType;      // operator set (§45.5.2 table)
    options?: { value: string | number; label: string }[];   // category / multiple_choice / contact.status …
    operators?: ConditionOperator[];   // override the default operator set of `type`
}
export interface ConditionOptionSources {
    lists?: (q: string) => Promise<{ value: string; label: string }[]>;
    campaigns?: (q: string) => Promise<{ value: string; label: string }[]>;
    users?: (q: string) => Promise<{ value: string; label: string }[]>;
    tags?: (q: string) => Promise<{ value: number; label: string }[]>;
}
```

The component never calls the API itself (sources are injected) so WP-51, WP-54U and WP-91U can
each wire their own `Lib/api/*` module. WP-51 may ship a temporary local stub with the same props
until WP-54U is merged; the orchestrator replaces the import at the gate.

### 45.5.4 WP-57 Contacts index, views, bulk, export (`Lib/types/contacts.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/recipients` | `recipients.view` | `ContactIndexQuery` with `pagination=cursor` | `200 ContactIndexResponse`. Without `pagination=cursor` the legacy page-based `Recipient` response is returned unchanged. |
| POST | `/recipients/search` | `recipients.view` | `ContactIndexQuery` as JSON body | same as GET |
| GET | `/contact-views` | `recipients.view` | — | `200 DataList<ContactView>` (system views `all`, `mine` first) |
| POST | `/contact-views` | `recipients.view` | `ContactViewInput` | `201 {data: ContactView}` |
| PATCH | `/contact-views/{id}` | owner \| `recipients.manage` (shared) | `ContactViewInput` (partial) | `200`; `409` on system views |
| DELETE | `/contact-views/{id}` | owner \| `recipients.manage` | — | `204`; `409` on system views |
| POST | `/recipients/bulk` | `recipients.manage` (`suppression.manage` for blocklist actions) | `ContactBulkInput` | `200 {data: BulkResult}` when ≤ 1000 contacts, else `202 {data: AsyncOperation}` (type `contacts_bulk`) |
| POST | `/recipients/export` | `contacts.export` | `ContactExportInput` | `202 {data: AsyncOperation}` (type `contacts_export`; `result.download_url` signed, 24 h); audit `contacts.exported` |
| GET | `web:/exports/{operation}/download` | signed URL | — | file stream (CSV UTF-8 with BOM, `;` separator, or XLSX) |
| GET | `/operations/{id}` | creator | — | `200 {data: AsyncOperation}` |
| PATCH | `/me/column-preferences` | authenticated | `{ "page": "contacts", "columns": ContactColumn[] }` | `200 {data: { "page": "contacts", "columns": [...] }}` (`users.column_preferences`) |

```jsonc
// ContactIndexQuery
{ "pagination": "cursor", "cursor": "…|null", "per_page": 50,      // ≤ 100
  "q": "acme",                     // email / name / company / ext_id
  "scope": "all",                  // "all" | "mine" (created_by = me)
  "list_id": "uuid|null", "segment_id": "uuid|null",   // "Charger une liste ou un segment"
  "view_id": "uuid|null",          // applies the view's filters/sort (explicit params override)
  "filters": RuleTree|null,        // GET: URL-encoded JSON
  "columns": ["attribute.PRENOM", "attribute.VILLE", "contact.created_at", "lists"],   // GET: comma-separated
  "sort": "-contact.created_at" }  // any column key of type text/number/date, ± prefix; default -contact.created_at

// ContactIndexResponse = CursorPaginatedResponse<Contact> + meta.total (null when > 10 s to count) + meta.columns
{ "data": [ Contact ], "links": {…},
  "meta": { …cursor meta…, "total": 15230, "columns": ["attribute.PRENOM", …] } }

// Contact (row; also the base of ContactDetail)
{ "id": "uuid", "email": "amina@exemple.dz", "first_name": "Amina", "last_name": "Benali",
  "company_name": "Acme", "ext_id": "CRM-1042|null", "source": "csv_import", "status": "active",
  "blocklisted": false, "blocklisted_at": null,
  "opt_in_at": "…|null", "opt_in_source": "form|null",
  "last_opened_at": "…|null", "last_clicked_at": "…|null", "engagement_score": 62,
  "attributes": { "PRENOM": "Amina", "VILLE": "alger" },   // only the requested attribute columns
  "list_ids": ["uuid"],            // only when "lists" column requested
  "tags": [ Tag ], "created_by": UserRef|null, "created_at": "…", "updated_at": "…" }

// ContactColumn (view columns and column preferences)
{ "key": "attribute.VILLE", "width": 160 }   // key: any FieldKey of type text/number/date/boolean/category/
                                             // multiple_choice/user, plus "lists" and "tags"

// ContactView
{ "id": "uuid|all|mine", "name": "Prospects Alger", "is_system": false, "is_shared": true,
  "filters": RuleTree|null, "list_id": "uuid|null", "segment_id": "uuid|null",
  "columns": [ ContactColumn ], "sort": "-contact.created_at",
  "owner": UserRef|null, "created_at": "…|null", "updated_at": "…|null" }
// ContactViewInput
{ "name": "…", "is_shared": false, "filters": RuleTree|null, "list_id": null, "segment_id": null,
  "columns": [ … ], "sort": "…" }

// ContactBulkInput
{ "action": "add_to_list",       // "add_to_list" | "remove_from_list" | "blocklist" | "unblocklist"
                                 // | "update_attribute" | "delete"
  "selection": ContactSelection,
  "params": { "list_id": "uuid",            // add/remove list
              "attribute": "VILLE", "value": "oran",   // update_attribute (value encoded per type; null clears)
              "reason": "manual_block" } }  // blocklist: SuppressionReason, default manual_block

// ContactExportInput
{ "selection": ContactSelection, "columns": ["attribute.EMAIL", "attribute.PRENOM", "lists"],
  "format": "csv" }              // "csv" | "xlsx"
```

The single-contact *Liste noire* toggle (WP-52) and the list detail *Exporter* (WP-53) use
`POST /recipients/bulk` / `POST /recipients/export` with `selection.ids = [id]` /
`selection.all_matching.list_id`.

### 45.5.5 WP-52 Contact detail + timeline (`Lib/types/contacts.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/recipients/{id}` | `recipients.view` | — | `200 {data: ContactDetail}` (legacy `Recipient` keys kept) |
| PATCH | `/recipients/{id}` | `recipients.manage` | legacy fields + `{ "ext_id"?: "…", "attributes"?: { "VILLE": "oran" } }` | `200 {data: ContactDetail}`; `422 attributes.<NAME>` |
| GET | `/recipients/{id}/timeline` | `recipients.view` | `cursor`, `per_page` (≤ 100), `types[]` (`TimelineEventType`) | `200 CursorPaginatedResponse<TimelineEntry>` (newest first) |
| GET/POST | `/recipients/{id}/notes` | existing | existing | existing |

```jsonc
// ContactDetail = Contact (all attributes) + counters + memberships
{ …Contact…, "attributes": { …every non-empty attribute… },
  "custom_fields": { … }, "notes": [ RecipientNote ],
  "lists": [ { "id": "uuid", "name": "Clients", "added_at": "…|null" } ],
  "counters": { "messages_sent": 14, "delivered": 14, "opened": 9, "clicked": 3, "hard_bounces": 0,
                "soft_bounces": 1, "complaints": 0, "unsubscriptions": 0 },
  "subscription_status": "subscribed",   // "subscribed" | "unsubscribed" | "blocklisted" | "pending_confirmation"
  "pagejaunes_external_id": 12345,       // or null
  "last_contacted_at": "…|null" }

// TimelineEntry
{ "id": "msg:uuid:delivered", "type": "delivered", "occurred_at": "…",
  "campaign": { "id": "uuid", "name": "Newsletter octobre" } , // or null
  "message_id": "uuid|null", "subject": "…|null",
  "list": { "id": "uuid", "name": "Clients" },                 // list_added / list_removed, else null
  "import_job_id": "uuid|null", "form_id": "uuid|null", "automation_id": "uuid|null",
  "url": "https://…|null", "device_type": "mobile|null", "email_client": "Gmail|null",
  "ip_country": "DZ|null", "reason": "…|null", "actor": UserRef|null }
// TimelineEventType: "created" | "sent" | "delivered" | "opened" | "clicked" | "soft_bounce" | "hard_bounce"
//   | "deferred" | "blocked" | "unsubscribed" | "complaint" | "imported" | "list_added" | "list_removed"
//   | "attribute_updated" | "form_submitted" | "opt_in_confirmed" | "blocklisted" | "unblocklisted"
//   | "automation_entered" | "note_added"
```

Counters and `last_*_at` are recomputed by `RecomputeRecipientCountersJob` (WP-52, command
`recipients:recompute-counters`) and incremented in real time by WP-70's listener from Wave 3.

### 45.5.6 WP-53 Lists & folders (`routes/api/lists.php`, `Lib/types/lists.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/recipient-lists` | `recipients.view` | `q`, `folder_id` (`none`), `sort` (`name`, `-created_at`, `contact_count`, `-last_import_at`), `page`, `per_page` | `200 PaginatedResponse<ContactList>` |
| POST | `/recipient-lists` | `recipients.manage` | `ContactListInput` | `201 {data: ContactList}` |
| GET | `/recipient-lists/{id}` | `recipients.view` | — | `200 {data: ContactList}` |
| PATCH | `/recipient-lists/{id}` | `recipients.manage` | `ContactListInput` (partial) | `200 {data: ContactList}` |
| DELETE | `/recipient-lists/{id}` | `recipients.manage` | query `delete_contacts=0` | `204` (or `202 AsyncOperation` type `list_delete` when > 5000 members); `409` used by a scheduled/running campaign, an active form or automation |
| POST | `/recipient-lists/{id}/contacts` | `recipients.manage` | `ListMembershipInput` | `200 {data: ListMembershipResult}` |
| GET | `/list-folders` | `recipients.view` | `kind=list\|segment` (default `list`) | `200 DataList<Folder>` |
| POST | `/list-folders` | `recipients.manage` (`segments.manage` for kind segment) | `FolderInput` + `{ "kind": "list" }` | `201 {data: Folder}` |
| PATCH | `/list-folders/{id}` | same | `FolderInput` | `200 {data: Folder}` |
| DELETE | `/list-folders/{id}` | same | query `delete_children=0` | `204`; children move to "no folder" unless `delete_children=1` (then `409` if any child is in use) |

```jsonc
// ContactList — superset of the existing recipient-list resource
{ "id": "uuid", "name": "Clients 2025", "description": "…|null", "folder_id": "uuid|null",
  "type": "static",              // "static" | "dynamic_segment" (legacy, read-only)
  "contact_count": 812, "blocklisted_count": 12, "last_import_at": "…|null",
  "created_by": UserRef|null, "created_at": "…", "updated_at": "…" }
// ContactListInput
{ "name": "…", "description": "…|null", "folder_id": "uuid|null" }
// ListMembershipInput
{ "action": "add",               // "add" | "remove"
  "recipient_ids": ["uuid"],     // ≤ 1000, or:
  "emails": ["a@exemple.dz"],    // ≤ 1000; unknown emails are reported, never created
  "selection": null }            // or a ContactSelection (async above 1000 → 202 AsyncOperation contacts_bulk)
// ListMembershipResult
{ "added": 10, "removed": 0, "already": 2, "not_found": ["x@exemple.dz"] }
```

List members are read with `GET /recipients?pagination=cursor&list_id={id}` (§45.5.4).

### 45.5.7 WP-55 Import wizard + history (`Lib/types/imports.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| POST | `/import-jobs` | `recipients.import` | multipart `file` + `source_type` (`csv`\|`xlsx`\|`txt`) **or** JSON `{ "source_type": "paste", "content": "…" }` **or** `{ "source_type": "pagejaunes", "external_ids": [123] }` | `201 {data: ImportJobV2}` incl. `preview` |
| GET | `/import-jobs` | `recipients.import` | `status`, `page`, `sort=-created_at` | `200 PaginatedResponse<ImportJobV2>` (history; `preview` omitted) |
| GET | `/import-jobs/{id}` | `recipients.import` | — | `200 {data: ImportJobV2}` |
| GET | `/import-jobs/{id}/preview` | `recipients.import` | — | `200 {data: ImportPreview}` |
| PUT | `/import-jobs/{id}/mapping` | `recipients.import` | `ImportMappingInput` | `200 {data: ImportJobV2}` (legacy body `{column_mapping}` still accepted) |
| PUT | `/import-jobs/{id}/settings` | `recipients.import` | `ImportSettingsInput` | `200 {data: ImportJobV2}` |
| POST | `/import-jobs/{id}/commit` | `recipients.import` | existing `{ "excluded_row_ids"?: [] }` | `202 {data: ImportJobV2}` ("Lancer l'import") |
| GET | `/import-jobs/{id}/rows` | existing | existing | existing |
| GET | `/import-jobs/{id}/errors.csv` | `recipients.import` | — | `text/csv` (row_number; email; column; error) |

```jsonc
// ImportJobV2 — legacy ImportJob keys kept
{ …ImportJob…, "list_ids": ["uuid"], "new_list": { "name": "Salon 2026", "folder_id": null } ,  // or null
  "mapping": [ ImportColumnMapping ], "update_existing": true, "empty_values_overwrite": false,
  "mark_blocklisted": false, "mark_opt_in": false,
  "summary": { "created": 820, "updated": 140, "skipped": 12, "failed": 8, "blocklisted": 0,
               "added_to_lists": 960 } ,     // or null until completed
  "user": UserRef|null, "preview": ImportPreview|null, "errors_url": "/api/v1/import-jobs/uuid/errors.csv|null" }

// ImportPreview
{ "headers": ["E-mail", "Prénom", "Ville"], "rows": [ ["a@x.dz", "Amina", "Alger"] ],   // first 5 rows
  "total_rows": 980, "delimiter": ";", "suggested_mapping": [ ImportColumnMapping ] }

// ImportColumnMapping
{ "column": 0, "header": "Ville",
  "target": { "type": "attribute", "attribute": "VILLE" } }   // or { "type": "ignore" }
// ImportMappingInput
{ "mapping": [ ImportColumnMapping ],
  "create_attributes": [ { "name": "VILLE", "type": "text" } ] }   // "Créer un attribut" inline (needs attributes.manage)
// exactly one column must map to EMAIL (422 otherwise)

// ImportSettingsInput
{ "list_ids": ["uuid"], "new_list": { "name": "…", "folder_id": null }, "update_existing": true,
  "empty_values_overwrite": false, "mark_blocklisted": false, "mark_opt_in": false }
```

`status` keeps the existing import pipeline's values unchanged (French labels in
`fr.imports.statusLabels`, raw value shown as fallback).

### 45.5.8 WP-56 Blocklist (`Lib/types/suppression.ts`)

No new endpoint. `Contacts/Blocklist` tabs:

- **Contacts en liste noire** → `GET /suppression-entries?reason[]=manual_unsubscribe&reason[]=global_unsubscribe&reason[]=spam_complaint&reason[]=manual_block&reason[]=invalid_address`.
- **Rebonds** → `GET /suppression-entries?reason[]=hard_bounce` (hard) + `GET /messages?status=soft_bounced` (soft, read-only).
- **Réactiver** → `DELETE /suppression-entries/{id}` (existing confirm flag).

`SuppressionEntry` gains optional keys `recipient_id` (uuid|null) and `source_campaign`
(`{id, name}`|null) — added by the suppression resource when available, UI renders "—" otherwise.
WP-56's listener keeps `recipients.blocklisted_at` in sync on suppression create/delete.

### 45.5.9 Additions recorded at the Wave 2 gate (orchestrator)

Routes delivered by Wave 2 that the tables above did not list, kept as the contract:

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET | `/segments/fields` | `recipients.view` | `{data: FieldCatalog}` (optional `?category=`); the ConditionBuilder's catalog source (WP-54). |
| GET | `/exports/{operation}/download` | signed URL (no session) | Contact export download (WP-57). Replaces `web:/exports/{operation}/download`; 410 after expiry. |
| POST | `/recipient-lists/{id}/export` | `contacts.export` | List export → `AsyncOperation` (WP-53). |
| GET | `/recipient-lists/exports/{operation}/download` | signed URL | List export download (WP-53). |
| GET | `/recipient-lists/operations/{operation}` | creator | Status of a list export/delete/bulk operation (WP-53's cache-based store; to be unified with `/operations/{id}` of WP-57 in a later contract-fix). |

Other deviations recorded: `POST /segments/count` is always synchronous (no 202 fallback yet); conditions may carry an optional `field_type`; `recipients.custom_fields` validation errors are keyed `attributes.NAME`; audit actions `segment.*`, `list.*`, `list_folder.*`, `attribute.*`, `contacts.bulk_*` added to §45.9's list; `RecipientBlocklistChanged` lives in `App\Modules\Suppression\Listeners`.

## 45.6 Wave 3 — Design & reports

> Gate note (orchestrator): `data-link-id` / `campaign_links.link_id` is the first **16** hex chars of
> sha1(absolute URL) (WP-63 injects, WP-70 reads; an optional signed `lid` query overrides).

### 45.6.1 WP-60 EmailBuilder core (`Lib/types/editor.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| POST | `/templates/mjml/validate` | `templates.manage` \| `campaigns.create` | `{ "mjml": "<mjml>…</mjml>" }` (≤ 1 MB) | `200 {data: MjmlValidationResult}` |

```jsonc
// MjmlValidationResult
{ "valid": true, "html": "<!doctype html>…", "errors": [ { "line": 12, "message": "…", "tag_name": "mj-column" } ],
  "size_bytes": 48211 }

// EmailDesign — the value stored in design_json (campaigns, templates, saved sections)
{ "format": "grapesjs-mjml", "version": 1,
  "mjml": "<mjml>…</mjml>",              // source of truth for server re-compilation
  "project": { …GrapesJS project data (pages, styles, assets)… },
  "global_styles": EmailGlobalStyles }

// EmailGlobalStyles ("Style" tab — WP-61 edits, WP-60 stores)
{ "font_family": "Arial, sans-serif", "text_color": "#242424", "link_color": "#0F6CBD",
  "link_underline": true, "background_color": "#F5F5F5", "content_background_color": "#FFFFFF",
  "content_width": 600, "button": { "background_color": "#0F6CBD", "text_color": "#FFFFFF",
  "border_radius": 4, "font_weight": "600" }, "mobile": { "font_scale": 1, "hide_on_mobile_padding": false } }
```

Block keys (`EmailBlockType`): `title`, `text`, `image`, `button`, `divider`, `spacer`, `social`,
`columns` (1–4), `html`, `video`, `logo`, `navigation`, `dynamic_content`, `product`,
`saved_section`. Save flow: the client compiles MJML (`mjml-browser`), then calls `PUT
/campaigns/{id}/design` (§45.4.2) or `PATCH /templates/{id}` with `editor_type: "dnd"`,
`design_json: EmailDesign`, `html_body`/`html_content` = compiled HTML; the server re-validates
the MJML with `MjmlCompiler` and stores **its** HTML (422 `design_json.mjml` on error).

### 45.6.2 WP-62 Templates v2 (`Lib/types/templates.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/templates/gallery` | `templates.view` \| `campaigns.create` | `category?` | `200 TemplateGalleryResponse` |
| GET | `/template-folders` | `templates.view` | — | `200 DataList<Folder>` (kind `template`) |
| POST | `/template-folders` | `templates.manage` | `FolderInput` | `201 {data: Folder}` |
| PATCH | `/template-folders/{id}` | `templates.manage` | `FolderInput` | `200 {data: Folder}` |
| DELETE | `/template-folders/{id}` | `templates.manage` | — | `204` (templates move to "no folder") |
| GET | `/templates/{id}/versions` | existing | — | `200 DataList<TemplateVersion>` |
| POST | `/templates/{id}/versions/{version}/restore` | `templates.manage` | — | `200 {data: TemplateV2Detail}` (new version created from the restored one) |
| POST | `/templates/{id}/thumbnail` | `templates.manage` | — | `202 {data: TemplateV2}` (`RenderThumbnailJob`; `thumbnail_url` updated when done) |
| POST | `/templates/{id}/archive` · `/unarchive` | `templates.manage` | — | `200 {data: TemplateV2}` (if not already provided by PATCH `is_archived`) |
| PATCH | `/templates/{id}` | `templates.manage` | `{ "folder_id": "uuid\|null" }` | "Déplacer" |
| DELETE | `/templates/{id}` | existing | — | existing (409 when used) |

```jsonc
// TemplateGalleryResponse
{ "data": [ TemplateV2 ], "meta": { "categories": [ { "key": "newsletter", "count": 4 } ] } }
// gallery categories (labels fr.templates.galleryCategories.*): "newsletter" | "promotion" | "event"
//   | "welcome" | "announcement" | "seasonal" | "transactional" | "basic"

// TemplateVersion
{ "id": "uuid", "version_number": 4, "author": UserRef|null, "editor_type": "dnd",
  "created_at": "…", "is_current": false }
```

Thumbnails are generated for templates **and** campaigns (`RenderThumbnailJob(model)`, Playwright,
600×800 PNG, 2 s settle) after every design save; WP-62 dispatches it from a listener on
`CampaignDesignSaved` / `TemplateSaved` (§45.9).

### 45.6.3 WP-63 Rendering pipeline, merge tags, mirror

No JSON endpoint. Web routes (`routes/web/mirror.php`):

| Method | Path | Auth | Behaviour |
|---|---|---|---|
| GET | `web:/m/{message}` | signed URL (no expiry unless `email_expiration` set → expiry = sent_at + duration) | Personalised web version of a sent message ("voir dans le navigateur"; `{{ mirror }}`), increments `mirrorClick` |
| GET | `web:/m/c/{campaign}` | signed URL | Anonymous public mirror of a campaign (`share_link`), merge tags rendered with defaults |

Pipeline order (doc 38): merge tags (`{{ contact.NAME | default:"…" }}`, `{{ mirror }}`,
`{{ unsubscribe }}`, `{{ update_profile }}`, `{{ campaign.* }}`, `{{ sender.* }}`) → `to_field`
rendering → UTM rewrite (only http(s) links without existing `utm_*`; values from `campaign.utm`)
→ `data-link-id` injection (stable id = first 12 hex of sha1(normalised URL + occurrence index),
stored in `campaign_links.link_id`) → tracking rewrite → inline images (`inline_images`) →
signature. The same `link_id` is used by the click map (§45.6.5).

### 45.6.4 WP-70 Tracking enrichment

No JSON endpoint (schema slot L, §45.8; events §45.9). Device classification for
`statsByDevice`: `desktop` | `mobile` | `tablet` | `unknown` (`message_events.device_type`);
`email_client` = parsed client/browser name (`statsByBrowser` key); `ip_country` = ISO alpha-2
(`statsByCountry` key); Apple MPP opens (`is_proxy_open = true`) count in `viewed`, `uniqueViews`
and `appleMppOpens` but not in `trackableViews`. Command `geoip:update` (monthly schedule).

### 45.6.5 WP-72 Report & statistics API (`routes/api/reporting.php`, `Lib/types/reports.ts`, `Lib/types/statistics.ts`)

#### Campaign report

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/campaigns/{id}/report/overview` | `reporting.view` \| `campaigns.view` | — | `200 {data: ReportOverview}` |
| GET | `/campaigns/{id}/report/deliverability` | same | — | `200 {data: ReportDeliverability}` |
| GET | `/campaigns/{id}/report/opens` | same | — | `200 {data: ReportOpens}` |
| GET | `/campaigns/{id}/report/clicks` | same | — | `200 {data: ReportClicks}` |
| GET | `/campaigns/{id}/report/unsubscriptions` | same | — | `200 {data: ReportUnsubscriptions}` |
| GET | `/campaigns/{id}/report/contacts` | same + `recipients.view` | `metric` (`ReportContactMetric`), `link_id?`, `list_id?`, `cursor`, `per_page` | `200 CursorPaginatedResponse<ReportContactRow>` |
| POST | `/campaigns/{id}/report/segment` | `segments.manage` | `ReportSegmentInput` | `201 {data: Segment}` ("Voir les contacts" → segment) |
| GET | `/campaigns/{id}/report/export` | `reporting.export` | `tab` (`ReportTab`) | `200 text/csv` (aggregates of that tab, `;`-separated, UTF-8 BOM) |
| GET | `/campaigns/compare` | `reporting.view` \| `campaigns.view` | `ids=uuid,uuid` (2–5) | `200 DataList<CampaignComparisonRow>` |

`409` on any report endpoint for a campaign that never started sending (`status` draft/scheduled
and no messages). Report payloads read rollups (`campaign_stats_hourly`, `campaign_stats_by_list`,
`campaign_links`) and the cached counters, never raw `message_events` above 100k messages.

```jsonc
// ReportCampaignHeader (embedded as "campaign" in every tab payload)
{ "id": "uuid", "name": "…", "subject": "…", "preview_text": "…|null", "status": "completed",
  "brevo_status": "sent", "sender": { "name": "…", "email": "…" }, "sent_at": "…|null",
  "completed_at": "…|null", "tags": ["…"], "type": "classic", "thumbnail_url": "…|null",
  "share_link": "…|null" }

// ReportTimeseries
{ "granularity": "hour",          // "hour" for the first 72 h after sent_at, then "day"
  "points": [ { "t": "2026-10-19T09:00:00Z", "sent": 1000, "delivered": 980, "viewed": 120,
                "uniqueViews": 90, "uniqueClicks": 22, "unsubscriptions": 1, "hardBounces": 2,
                "softBounces": 1, "complaints": 0 } ] }

// ReportOverview
{ "campaign": ReportCampaignHeader,
  "globalStats": BrevoStats, "rates": RateSet,
  "averages": RateSet,             // account averages of sent campaigns over the last 90 days ("vs moyenne du compte")
  "mirrorClick": 14, "remaining": 0,
  "timeseries": ReportTimeseries,
  "campaignStats": [ BrevoCampaignStatsEntry ],
  "audience": { "lists": [ AudienceTargetRef ], "exclusion_lists": [], "segments": [], "excluded_segments": [],
                "recipients": 1000 },   // AudienceTargetRef = { id, name, contact_count }
  "timeline": [ ReportTimelineItem ],
  "links": [ ReportLinkRow ],      // top 10 by unique clicks
  "statsByDevice": { "desktop": {…}, "mobile": {…}, "tablet": {…}, "unknown": {…} },
  "ab_test": AbTestReport|null,    // §45.7.2
  "apple_mpp_note": true }         // show Brevo's Apple Mail Privacy Protection note when appleMppOpens > 0

// ReportTimelineItem (from the audit log)
{ "action": "sent", "occurred_at": "…", "actor": UserRef|null, "details": { … } | null }
// action: "created" | "scheduled" | "unscheduled" | "sent" | "paused" | "resumed" | "suspended"
//   | "cancelled" | "completed" | "ab_winner_picked" | "archived" | "unarchived" | "test_sent"

// ReportDeliverability
{ "campaign": ReportCampaignHeader, "globalStats": BrevoStats, "rates": RateSet,
  "blocked_by_suppression": 12, "queued": 0,
  "bounce_reasons": [ { "type": "hard", "reason": "550 5.1.1 User unknown", "count": 6 } ],   // type: "hard" | "soft"
  "by_smtp_account": [ { "smtp_account_id": "uuid", "name": "OVH 1", "sent": 500, "delivered": 490,
                         "hardBounces": 4, "softBounces": 6, "deferred": 1 } ],
  "statsByDomain": { "gmail.com": BrevoDomainStats } }

// ReportOpens
{ "campaign": ReportCampaignHeader,
  "viewed": 610, "uniqueViews": 402, "trackableViews": 350, "estimatedViews": 402,
  "appleMppOpens": 52, "opensRate": 41.02, "averages": RateSet,
  "timeseries": ReportTimeseries,
  "by_hour_of_day": [ { "hour": 9, "uniqueViews": 80 } ],      // 0–23, account timezone
  "statsByDomain": { "gmail.com": BrevoDomainStats },
  "statsByDevice": { … }, "statsByBrowser": { "Gmail": BrevoEngagementBreakdown },
  "statsByCountry": { "DZ": BrevoEngagementBreakdown } }

// ReportClicks
{ "campaign": ReportCampaignHeader, "uniqueClicks": 95, "clickers": 95, "total_clicks": 140,
  "click_rate": 0.097, "click_to_open_rate": 0.236, "averages": RateSet,
  "linksStats": { "https://pagesjaunes.dz/promo": 80 },
  "links": [ ReportLinkRow ],      // all links, sorted by unique clicks
  "timeseries": ReportTimeseries, "statsByDevice": { … }, "statsByBrowser": { … } }

// ReportLinkRow
{ "id": "uuid", "link_id": "a1b2c3d4e5f6", "url": "https://…", "label": "Voir l'offre|null",
  "unique_clicks": 70, "total_clicks": 80, "share": 0.57 }   // share of all clicks
// Click map: the UI renders campaign html_body in HtmlPreview and overlays each anchor carrying
// data-link-id="<link_id>" with its ReportLinkRow.share.

// ReportUnsubscriptions
{ "campaign": ReportCampaignHeader, "unsubscriptions": 4, "complaints": 1,
  "unsubscribe_rate": 0.004, "complaint_rate": 0.001,
  "by_list": [ { "list_id": "uuid", "name": "Clients", "unsubscriptions": 3, "complaints": 1 } ],
  "reasons": [ { "reason": "Je reçois trop d'e-mails", "count": 2 } ],   // from the unsubscribe page survey
  "recent": [ ReportContactRow ] }   // first 20; more via report/contacts?metric=unsubscribed

// ReportContactMetric: "sent" | "delivered" | "opened" | "not_opened" | "clicked" | "not_clicked"
//   | "soft_bounced" | "hard_bounced" | "unsubscribed" | "complained" | "deferred" | "blocked"
// ReportContactRow
{ "recipient_id": "uuid|null", "email": "…", "first_name": "…|null", "last_name": "…|null",
  "occurred_at": "…|null", "count": 3, "url": "…|null", "reason": "…|null",
  "device_type": "mobile|null", "email_client": "…|null", "ip_country": "DZ|null" }

// ReportSegmentInput — builds a Segment whose RuleTree is engagement.<metric> has campaign_ids=[id]
{ "metric": "opened", "link_id": null, "name": "A ouvert « Newsletter octobre »", "folder_id": null }
// metric ∈ opened | not_opened (→ has_not opened) | clicked | not_clicked | unsubscribed | hard_bounced | soft_bounced

// ReportTab: "overview" | "deliverability" | "opens" | "clicks" | "unsubscriptions"

// CampaignComparisonRow
{ "campaign": ReportCampaignHeader, "globalStats": BrevoStats, "rates": RateSet }
```

#### Statistics (`Statistics/Index`, WP-73 UI)

Common query `StatisticsQuery`: `date_from`, `date_to` (YYYY-MM-DD, ≤ 400 days), `scope`
(`marketing` | `transactional` | `all`, default `marketing`), `compare` (`previous_period` | none).

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/statistics/summary` | `reporting.view` | `StatisticsQuery` | `200 {data: StatisticsSummary}` |
| GET | `/statistics/timeseries` | `reporting.view` | `StatisticsQuery` + `granularity` (`hour`\|`day`\|`week`\|`month`) | `200 {data: ReportTimeseries}` |
| GET | `/statistics/campaigns` | `reporting.view` | `StatisticsQuery` + `sort` (`-sent_at`, `name`, `-open_rate`, `-click_rate`, `-sent`), `tag?`, `page` | `200 PaginatedResponse<StatisticsCampaignRow>` |
| GET | `/statistics/lists` | `reporting.view` | `StatisticsQuery` | `200 DataList<StatisticsListRow>` |
| GET | `/statistics/averages` | `reporting.view` \| `campaigns.view` | `days=90` | `200 {data: RateSet}` (cached 1 h) |
| GET | `/statistics/export` | `reporting.export` | `StatisticsQuery` + `table` (`campaigns`\|`lists`) | `200 text/csv` |

```jsonc
// StatisticsSummary
{ "period": { "from": "2026-09-01", "to": "2026-09-30" }, "scope": "marketing",
  "globalStats": BrevoStats, "rates": RateSet, "campaigns_sent": 12,
  "previous": { "period": { "from": "…", "to": "…" }, "globalStats": BrevoStats, "rates": RateSet } }  // or null
// StatisticsCampaignRow
{ "campaign_id": "uuid", "name": "…", "sent_at": "…", "tags": [], "type": "classic",
  "globalStats": BrevoStats, "rates": RateSet }
// StatisticsListRow
{ "list_id": "uuid", "name": "…", "contact_count": 812, "globalStats": BrevoStats, "rates": RateSet }
```

The legacy `/reporting/*` endpoints stay until WP-73 deletes `Reporting/Index.tsx`; the SMTP
performance table moves into `Smtp/Index.tsx` using the existing `/reporting/smtp-accounts`.

### 45.6.6 WP-74 Unsubscribe & preference pages (`routes/web/preferences.php`, `Lib/types/preferences.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/unsubscribe-pages` | `settings.manage` \| `campaigns.create` | — | `200 DataList<UnsubscribePage>` |
| POST | `/unsubscribe-pages` | `settings.manage` | `UnsubscribePageInput` | `201 {data: UnsubscribePage}` |
| GET/PATCH/DELETE | `/unsubscribe-pages/{id}` | `settings.manage` | partial input | `200` / `204`; `409` delete default or in use by a scheduled campaign |
| POST | `/unsubscribe-pages/{id}/default` | `settings.manage` | — | `200 {data: UnsubscribePage}` |
| GET | `/update-profile-forms` | `settings.manage` \| `campaigns.create` | — | `200 DataList<UpdateProfileForm>` |
| POST | `/update-profile-forms` | `settings.manage` | `UpdateProfileFormInput` | `201 {data: UpdateProfileForm}` |
| GET/PATCH/DELETE | `/update-profile-forms/{id}` | `settings.manage` | partial input | `200` / `204` |
| POST | `/update-profile-forms/{id}/default` | `settings.manage` | — | `200` |
| GET | `/unsubscribe-pages/{id}/preview` · `/update-profile-forms/{id}/preview` | `settings.manage` | — | `200 text/html` |
| GET | `web:/unsubscribe/{token}` | public | — | existing, now rendered with the campaign's (or default) `UnsubscribePage` |
| POST | `web:/unsubscribe/{token}` | public | form `reason?`, `reason_other?` | existing + stores survey reason |
| GET | `web:/preferences/{token}` | public (signed token, same scheme as unsubscribe) | — | Blade page: attributes of the form + per-list checkboxes |
| POST | `web:/preferences/{token}` | public, 10/min per IP | form `attributes[NAME]`, `list_ids[]` (lists kept), `unsubscribe_all` (0/1) | redirect back with confirmation message |

```jsonc
// UnsubscribePage
{ "id": "uuid", "name": "Page par défaut", "is_default": true,
  "content": { "title": "Se désinscrire", "body": "…", "button_label": "Me désinscrire",
               "confirmation_title": "Vous êtes désinscrit", "confirmation_message": "…",
               "show_reason_survey": true, "reasons": ["Je reçois trop d'e-mails", "…"],
               "show_preferences_link": true, "resubscribe_enabled": false },
  "design": PageDesign, "created_at": "…", "updated_at": "…" }
// PageDesign (also used by UpdateProfileForm and Form)
{ "logo_url": "…|null",            // null = branding logo
  "primary_color": "#0F6CBD", "background_color": "#F5F5F5", "text_color": "#242424",
  "font_family": "Segoe UI, sans-serif", "button_text_color": "#FFFFFF", "border_radius": 4 }
// UnsubscribePageInput = { name, content, design }

// UpdateProfileForm
{ "id": "uuid", "name": "…", "is_default": false,
  "fields": [ { "attribute": "PRENOM", "label": "Prénom", "required": false } ],
  "list_ids": ["uuid"],            // lists the contact may opt in/out individually
  "content": { "title": "Mettre à jour mes préférences", "intro": "…", "submit_label": "Enregistrer",
               "success_message": "Vos préférences ont été enregistrées." },
  "design": PageDesign, "created_at": "…", "updated_at": "…" }
// UpdateProfileFormInput = { name, fields, list_ids, content, design }
```

Double opt-in confirmation template: WP-74 seeds a transactional template (`scope:
"transactional"`, name "Confirmation d'inscription (double opt-in)", merge tag
`{{ doi_link }}`) whose id is stored in setting `forms.default_doi_template_id`.

### 45.6.7 Tracking settings (`Settings/Tracking`, settings keys)

Uses the existing `GET/PATCH /settings` (`settings.manage`). Keys (value types):
`tracking.opens_enabled` (bool, default true), `tracking.clicks_enabled` (bool, true),
`tracking.domain` (string|null — custom tracking host), `tracking.utm_default_enabled` (bool,
false), `tracking.utm_default_source` (string, "pagesjaunes-mailer"), `tracking.utm_default_medium`
(string, "email"), `tracking.geoip_enabled` (bool, true), `campaigns.unengaged_warning_percent`
(float, 0.30), `senders.spf_include` (string), `senders.dmarc_rua` (string|null),
`forms.default_doi_template_id` (uuid|null). Campaign-level `tracking_enabled` overrides the
account default. No WP owns a dedicated page in doc 44; the nav entry stays hidden until one is
assigned (§45.12).

### 45.6.8 Additions recorded at the Wave 3 gate (orchestrator)

| Method | Path | Permission | Notes |
|---|---|---|---|
| GET/PATCH/DELETE | `/unsubscribe-pages/{id}`, `/update-profile-forms/{id}` | `settings.manage` | Full CRUD (WP-74); `POST …/{id}/default`, `GET …/{id}/preview` as well. |
| GET | `/campaigns/{id}/report/unsubscribes` | `reporting.view` | Alias of `report/unsubscriptions` (WP-72). |
| GET | `/statistics/overview` | `reporting.view` | Alias of `statistics/summary` (WP-72). |
| POST | `/templates/{id}/unarchive` | `templates.manage` | WP-62. |
| POST | `/templates/mjml/validate` | `templates.manage` \| `campaigns.create` | MJML validation (WP-60); invalid MJML → `200 {valid:false, errors[]}`; 422 only for a malformed request. |
| GET | `web:/m/c/{campaign}` | signed | Campaign share link (WP-63). |

Deviations: the rendering pipeline runs its personalise phase at message creation and its delivery phase in `SendEmailJob` (doc 38 order kept); `message_events.event_type` uses the `MessageStatus` values (`spam_complaint`, `hard_bounced`, `soft_bounced`, …); `CampaignStats` contract gained `links`, `statsByCountry`, `statistics`, `rates`, `accountAverages`, `rollup`.

## 45.7 Wave 4 — Advanced sending, forms, automation, transactional

### 45.7.1 WP-61 Saved sections (`Lib/types/editor.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/saved-sections` | `templates.view` \| `campaigns.create` | `q?` | `200 DataList<SavedSection>` |
| POST | `/saved-sections` | `templates.manage` \| `campaigns.create` | `SavedSectionInput` | `201 {data: SavedSection}` |
| PATCH | `/saved-sections/{id}` | owner \| `templates.manage` | `{ "name": "…" }` | `200 {data: SavedSection}` |
| DELETE | `/saved-sections/{id}` | owner \| `templates.manage` | — | `204` |
| GET | `/editor/sections` | same as GET saved | — | `200 DataList<PremadeSection>` (pre-made row layouts, static) |

```jsonc
// SavedSection — only whole sections can be saved (Brevo rule)
{ "id": "uuid", "name": "Pied de page société", "mjml": "<mj-section>…</mj-section>",
  "component": { …GrapesJS component JSON… }, "thumbnail_url": "…|null",
  "created_by": UserRef|null, "created_at": "…" }
// SavedSectionInput = { "name": "…", "mjml": "…", "component": { … } }
// PremadeSection = { "key": "two_columns_image_text", "label": "Image + texte", "category": "content",
//                    "mjml": "…", "thumbnail_url": "…" }  category: "header" | "content" | "footer" | "product"

// Dynamic content block (stored inside the MJML as mj-raw markers, evaluated by WP-63)
{ "type": "dynamic_content",
  "variants": [ { "condition": RuleTree, "mjml": "<mj-text>…</mj-text>" } ],
  "fallback_mjml": "<mj-text>…</mj-text>" }
```

### 45.7.2 WP-80 A/B testing (route lines appended to `routes/api/campaigns.php`, `Lib/types/campaigns.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| PUT | `/campaigns/{id}/ab-test` | `campaigns.create` | `AbTestInput` | `200 {data: CampaignV2Detail}` (`type` becomes `ab_test` when enabled) ; `409` unless draft |
| POST | `/campaigns/{id}/ab-test/winner` | `campaigns.send` \| `campaigns.send_any` | `{ "variant_id": "uuid" }` | `200 {data: CampaignV2Detail}` (manual pick; dispatches remainder) ; `409` unless test phase running |

```jsonc
// AbTestConfig (campaigns.ab_test_config; doc 43 §43.6.2)
{ "type": "subject",              // "subject" | "content"
  "sample_percent": 20,           // 5–50, % of the audience split evenly between A and B (Brevo splitRule)
  "winner_metric": "opens",       // "opens" | "clicks" (Brevo winnerCriteria open|click)
  "test_duration_minutes": 240,   // 60–10080 (Brevo winnerDelay in hours × 60)
  "auto_send_winner": true }

// AbTestVariant (ab_test_variants)
{ "id": "uuid", "label": "A",     // "A" | "B"
  "subject": "…", "preview_text": "…|null",
  "html_body": "…|null", "design_json": EmailDesign|null,   // content tests only
  "is_winner": false }

// AbTestInput
{ "enabled": true, "config": AbTestConfig,
  "variants": [ { "label": "A", "subject": "…", "preview_text": "…" }, { "label": "B", "subject": "…" } ] }

// CampaignV2Detail.ab_test (null when disabled)
{ "config": AbTestConfig, "variants": [ AbTestVariant ], "winner_variant_id": "uuid|null",
  "phase": "not_started" }        // "not_started" | "testing" | "winner_picked" | "completed"

// AbTestReport (ReportOverview.ab_test)
{ "config": AbTestConfig, "phase": "winner_picked", "winner_variant_id": "uuid", "picked_at": "…",
  "picked_by": "auto",            // "auto" | "manual"
  "variants": [ { "variant_id": "uuid", "label": "A", "subject": "…", "globalStats": BrevoStats, "rates": RateSet } ] }
```

Brevo name mapping for integrators: `abTesting` = `ab_test_enabled`, `subjectA`/`subjectB` =
`variants[A|B].subject`, `splitRule` = `sample_percent`, `winnerCriteria` = `winner_metric`
(`open`→`opens`, `click`→`clicks`), `winnerDelay` = `test_duration_minutes / 60`.

### 45.7.3 WP-81 Send at best time

No new endpoint: `POST /campaigns/{id}/schedule` with `mode: "best_time"` (§45.4.2). The
*Confirmer l'envoi* option shows `fr.campaignSetup.bestTimeHelp`. Read-only helper:

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/campaigns/{id}/best-time-preview` | `campaigns.create` | — | `200 {data: BestTimePreview}` |

```jsonc
// BestTimePreview
{ "contacts_with_history": 640, "contacts_fallback": 360,
  "fallback_hour": 10,                         // account-wide best hour (0–23, account timezone)
  "distribution": [ { "hour": 9, "count": 120 } ],   // planned release hour histogram
  "window_hours": 24 }
```

### 45.7.4 WP-90 Forms (`routes/api/forms.php`, `routes/web/forms.php`, `Lib/types/forms.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/forms` | `forms.manage` | `q`, `status`, `page` | `200 PaginatedResponse<SubscriptionForm>` |
| POST | `/forms` | `forms.manage` | `SubscriptionFormInput` | `201 {data: SubscriptionForm}` |
| GET | `/forms/{id}` | `forms.manage` | — | `200 {data: SubscriptionForm}` |
| PATCH | `/forms/{id}` | `forms.manage` | `SubscriptionFormInput` (partial) | `200 {data: SubscriptionForm}` |
| DELETE | `/forms/{id}` | `forms.manage` | — | `204` (public URL then 404) |
| POST | `/forms/{id}/duplicate` | `forms.manage` | — | `201 {data: SubscriptionForm}` (status draft) |
| POST | `/forms/{id}/publish` · `/unpublish` | `forms.manage` | — | `200 {data: SubscriptionForm}`; publish `422` without list or EMAIL field |
| GET | `/forms/{id}/submissions` | `forms.manage` | `status`, `cursor` | `200 CursorPaginatedResponse<FormSubmission>` |
| GET | `web:/f/{form}` | public | — | hosted form (Blade); `404` unless `active` |
| POST | `web:/f/{form}` | public; 5/min per IP + captcha when enabled | form fields `attributes[NAME]`, `gdpr_consent`, `captcha_token`, honeypot `website` | HTML: redirect/thank-you; `Accept: application/json`: `200 {data: FormSubmitResult}` / `422` |
| GET | `web:/f/{form}/confirm/{token}` | public, signed token (7 days) | — | double opt-in confirmation → `recipients.opt_in_at`, redirect to `doi_redirect_url` or thank-you page |
| GET | `web:/f/{form}/embed.js` | public | — | script injecting the form (iframe-less embed) |

```jsonc
// SubscriptionForm
{ "id": "uuid", "name": "Inscription newsletter", "status": "active",   // "draft" | "active" | "inactive"
  "list_ids": ["uuid"], "lists": [ { "id": "uuid", "name": "Newsletter" } ],
  "fields": [ FormField ],
  "gdpr": { "enabled": true, "text": "J'accepte de recevoir…", "required": true },
  "design": PageDesign & { "button_text": "S'inscrire", "title": "…", "description": "…", "layout": "vertical" },
  "settings": { "double_opt_in": true, "doi_template_id": "uuid|null", "doi_redirect_url": "…|null",
                "success_message": "Merci ! …", "redirect_url": "…|null", "captcha_enabled": true,
                "update_existing": true, "notify_emails": [] },
  "share": { "hosted_url": "https://…/f/uuid", "iframe_code": "<iframe …>",
             "embed_html": "<div data-pjm-form=\"uuid\"></div><script src=\"…/f/uuid/embed.js\" async></script>" },
  "submissions_count": 120, "confirmed_count": 96, "last_submission_at": "…|null",
  "created_by": UserRef|null, "created_at": "…", "updated_at": "…" }
// layout: "vertical" | "horizontal"

// FormField
{ "attribute": "PRENOM", "label": "Prénom", "placeholder": "…|null", "required": false,
  "help_text": "…|null", "type": "text",   // derived from the attribute (read-only in responses)
  "options": [ { "value": "alger", "label": "Alger" } ] }   // category / multiple_choice only

// SubscriptionFormInput = { name, list_ids, fields (attribute, label, placeholder, required, help_text), gdpr, design, settings }

// FormSubmission
{ "id": "uuid", "email": "…", "recipient_id": "uuid|null",
  "status": "confirmed",         // "pending_confirmation" | "confirmed" | "subscribed" | "rejected" | "spam"
  "data": { "PRENOM": "Amina" }, "submitted_at": "…", "confirmed_at": "…|null", "ip_country": "DZ|null" }

// FormSubmitResult
{ "status": "pending_confirmation", "message": "Consultez votre boîte de réception…", "redirect_url": null }
// status: "subscribed" | "pending_confirmation"
```

### 45.7.5 WP-91 Automations (`routes/api/automations.php`, `Lib/types/automations.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/automations` | `automations.manage` | `q`, `status`, `page` | `200 PaginatedResponse<Automation>` (no `steps`) |
| POST | `/automations` | `automations.manage` | `AutomationInput` | `201 {data: AutomationDetail}` |
| GET | `/automations/{id}` | `automations.manage` | — | `200 {data: AutomationDetail}` |
| PATCH | `/automations/{id}` | `automations.manage` | `AutomationInput` (partial, no steps) | `200`; trigger change `409` while active |
| PUT | `/automations/{id}/steps` | `automations.manage` | `{ "steps": AutomationStepInput[] }` (replaces the whole tree) | `200 {data: AutomationDetail}`; `409` while active with runs in progress |
| DELETE | `/automations/{id}` | `automations.manage` | — | `204`; `409` while active |
| POST | `/automations/{id}/activate` | `automations.manage` | — | `200`; `422 errors.steps` when invalid (no steps, missing template, …) |
| POST | `/automations/{id}/pause` | `automations.manage` | — | `200` (runs freeze; resume via activate) |
| POST | `/automations/{id}/duplicate` | `automations.manage` | — | `201` (status draft) |
| GET | `/automations/{id}/runs` | `automations.manage` | `status`, `cursor` | `200 CursorPaginatedResponse<AutomationRun>` |
| POST | `/automations/{id}/runs/{run}/exit` | `automations.manage` | — | `200 {data: AutomationRun}` |
| GET | `/automations/{id}/activity` | `automations.manage` | `date_from`, `date_to` | `200 {data: AutomationActivity}` |

```jsonc
// Automation
{ "id": "uuid", "name": "Série de bienvenue", "description": "…|null",
  "status": "active",            // "draft" | "active" | "paused"
  "trigger": AutomationTrigger,
  "allow_reentry": false, "reentry_after_days": null,
  "stats": { "started": 120, "in_progress": 30, "completed": 85, "exited": 5 },
  "created_by": UserRef|null, "activated_at": "…|null", "created_at": "…", "updated_at": "…" }
// AutomationDetail = Automation + { "steps": AutomationStep[] }

// AutomationTrigger — type + config
{ "type": "list_added",         "config": { "list_ids": ["uuid"] } }
{ "type": "form_submitted",     "config": { "form_ids": ["uuid"] } }
{ "type": "campaign_opened",    "config": { "campaign_ids": ["uuid"] } }            // null = any campaign
{ "type": "campaign_clicked",   "config": { "campaign_ids": null, "link_url": "…|null" } }
{ "type": "attribute_changed",  "config": { "attribute": "STATUT", "to_value": "client|null" } }
{ "type": "date_anniversary",   "config": { "attribute": "DATE_NAISSANCE", "offset_days": 0, "time": "09:00" } }
// optional on every trigger: "config.filter": RuleTree|null (only contacts matching enter)

// AutomationStep (response; tree: condition steps carry yes/no branches)
{ "id": "uuid", "type": "send_email", "position": 0, "config": { … },
  "yes": [ AutomationStep ], "no": [ AutomationStep ],   // condition only, else []
  "stats": { "entered": 120, "completed": 118 } }
// AutomationStepInput = same without id/stats ("id" optional to keep stats of an existing step)
// type + config:
//   send_email:       { "template_id": "uuid", "subject": "…|null", "sender_id": "uuid|null", "preview_text": "…|null" }
//   wait:             { "amount": 2, "unit": "days" }            // unit: "minutes" | "hours" | "days" (≥ 1 minute, ≤ 365 days)
//   condition:        { "rules": RuleTree }                      // evaluated for the contact; engagement may use automation_step_id
//   add_to_list:      { "list_id": "uuid" }
//   remove_from_list: { "list_id": "uuid" }
//   update_attribute: { "attribute": "STATUT", "value": "client" }
//   end:              {}
// Limits: ≤ 50 steps, condition nesting ≤ 3.

// AutomationRun
{ "id": "uuid", "recipient": { "id": "uuid", "email": "…" },
  "status": "waiting",           // "active" | "waiting" | "completed" | "exited" | "failed"
  "current_step_id": "uuid|null", "next_run_at": "…|null", "started_at": "…",
  "finished_at": "…|null", "error": "…|null" }

// AutomationActivity
{ "started": 120, "completed": 85, "in_progress": 30, "exited": 5,
  "emails": [ { "step_id": "uuid", "template_name": "Bienvenue J+0",
                "globalStats": BrevoStats, "rates": RateSet } ] }
```

Emails sent by `send_email` steps go through the transactional path with
`messages.automation_step_id` set (§45.8 slot R).

### 45.7.6 WP-100 API keys, transactional send, logs (`routes/api/transactional.php`, `Lib/types/transactional.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/api-keys` | `api_keys.manage` | — | `200 DataList<ApiKey>` |
| POST | `/api-keys` | `api_keys.manage` | `ApiKeyInput` | `201 {data: ApiKeyCreated}` (plain token shown once); audit `api_key.created` |
| DELETE | `/api-keys/{id}` | `api_keys.manage` | — | `204` revoke; audit `api_key.revoked` |
| POST | `/transactional/send` | `transactional.send` \| API-key scope `transactional:send` | `TransactionalSendInput` | `201 {data: TransactionalSendResult}`; `429` (default 600/min per key, 60/min per session user); `422` |
| GET | `/transactional/logs` | `transactional.view` \| scope `transactional:read` | `TransactionalLogQuery` | `200 CursorPaginatedResponse<TransactionalLog>` |
| GET | `/transactional/logs/{id}` | same | — | `200 {data: TransactionalLogDetail}` |
| GET | `/transactional/settings` | `transactional.view` | — | `200 {data: TransactionalSettings}` |

```jsonc
// ApiKey
{ "id": "uuid", "name": "ERP", "scopes": ["transactional:send"], "token_prefix": "pjm_3f9a",
  "last_used_at": "…|null", "last_used_ip": "…|null", "expires_at": "…|null",
  "created_by": UserRef|null, "created_at": "…" }
// ApiKeyScope: "transactional:send" | "transactional:read" | "contacts:read" | "contacts:write"
//   | "campaigns:read" | "statistics:read"
// ApiKeyInput = { "name": "ERP", "scopes": ["transactional:send"], "expires_at": "…|null" }
// ApiKeyCreated = { "api_key": ApiKey, "plain_text_token": "pjm_3f9a…" }

// TransactionalSendInput (snake_case equivalent of Brevo's POST /smtp/email)
{ "sender": { "name": "…", "email": "…" },   // or "sender_id": "uuid"; must be a verified sender
  "to": [ { "email": "a@exemple.dz", "name": "Amina" } ],   // 1–50
  "cc": [], "bcc": [], "reply_to": { "email": "…", "name": "…" },
  "subject": "…",                // required unless the template has one
  "html_content": "…", "text_content": "…|null",
  "template_id": "uuid|null",    // exactly one of html_content / template_id
  "params": { "ORDER_ID": "1042" },   // {{ params.ORDER_ID }} in the body
  "tags": ["commande"], "headers": { "X-Custom": "…" },
  "attachments": [ { "name": "facture.pdf", "content": "<base64>" } ],   // or { name, url }; ≤ 10 MB total
  "scheduled_at": "…|null" }
// TransactionalSendResult
{ "message_id": "<uuid@pagesjaunes-mailer>", "message_ids": ["uuid"] }   // one per "to" recipient

// TransactionalLogQuery
{ "event": "delivered",          // BrevoEventLabel filter (below)
  "q": "…",                       // recipient email, subject or Message-ID
  "tag": "…", "template_id": "uuid|null", "source": "api",   // "composer" | "api" | "automation" | "form"
  "date_from": "…", "date_to": "…", "cursor": "…", "per_page": 50 }

// TransactionalLog
{ "id": "uuid", "message_id": "<…>", "recipient_email": "…", "subject": "…", "tags": [],
  "sender_email": "…", "template_id": "uuid|null", "source": "api",
  "status": "delivered",         // MessageStatus (existing enum)
  "last_event": "delivered",     // BrevoEventLabel
  "created_at": "…", "last_event_at": "…" }
// TransactionalLogDetail = TransactionalLog + { "events": [ TransactionalLogEvent ] }
// TransactionalLogEvent
{ "event": "click", "occurred_at": "…", "reason": "…|null", "link": "…|null",
  "ip": "…|null", "user_agent": "…|null", "device_type": "mobile|null" }

// BrevoEventLabel (UI filter, labels fr.transactional.events.*):
//   "sent" | "delivered" | "opened" | "clicked" | "soft_bounce" | "hard_bounce" | "invalid"
//   | "deferred" | "complaint" | "unsubscribed" | "blocked" | "error"

// TransactionalSettings
{ "api_base_url": "https://…/api/v1", "rate_limit_per_minute": 600,
  "smtp_relay": { "enabled": false, "host": null, "port": null, "login": null } }   // phase G relay placeholder
```

API-key authentication applies to the endpoints whose scope is listed: `transactional/*`
(`transactional:*`), `GET recipients`, `GET recipients/{id}` (`contacts:read`), `POST/PATCH
recipients`, `POST recipient-lists/{id}/contacts` (`contacts:write`), `GET campaigns`, `GET
campaigns/{id}/report/*` (`campaigns:read`), `GET statistics/*` (`statistics:read`). Scopes are
stored as Sanctum token abilities.

### 45.7.7 WP-101 Webhooks (`routes/api/webhooks.php`, `Lib/types/webhooks.ts`)

| Method | Path | Permission | Request | Response |
|---|---|---|---|---|
| GET | `/webhooks` | `webhooks.manage` | `type?` | `200 DataList<Webhook>` |
| POST | `/webhooks` | `webhooks.manage` | `WebhookInput` | `201 {data: WebhookCreated}` (secret shown once) |
| GET | `/webhooks/{id}` | `webhooks.manage` | — | `200 {data: Webhook}` |
| PATCH | `/webhooks/{id}` | `webhooks.manage` | `WebhookInput` (partial) | `200 {data: Webhook}` |
| DELETE | `/webhooks/{id}` | `webhooks.manage` | — | `204` |
| POST | `/webhooks/{id}/rotate-secret` | `webhooks.manage` | — | `200 {data: WebhookCreated}` |
| POST | `/webhooks/{id}/test` | `webhooks.manage` | `{ "event": WebhookSubscriptionEvent }` | `200 {data: WebhookDelivery}` (sample payload, synchronous, 10 s timeout) |
| GET | `/webhooks/{id}/deliveries` | `webhooks.manage` | `status`, `event`, `cursor` | `200 CursorPaginatedResponse<WebhookDelivery>` |
| POST | `/webhook-deliveries/{id}/retry` | `webhooks.manage` | — | `202 {data: WebhookDelivery}` |

```jsonc
// Webhook
{ "id": "uuid", "url": "https://erp.exemple.dz/hooks/mail", "description": "…|null",
  "type": "transactional",       // "transactional" | "marketing"
  "events": ["delivered", "hardBounce", "click"],   // WebhookSubscriptionEvent[] valid for the type
  "is_active": true, "secret_hint": "…a1b2", "headers": { "X-Api-Token": "…" },
  "last_delivery_at": "…|null", "last_delivery_status": "succeeded|null", "failure_count": 0,
  "created_at": "…", "updated_at": "…" }
// WebhookInput = { url (https only in production), description, type, events, is_active, headers }
// WebhookCreated = { "webhook": Webhook, "secret": "whsec_…" }

// WebhookSubscriptionEvent (Brevo's subscription identifiers, verbatim)
//   transactional: "sent" | "request" | "delivered" | "opened" | "uniqueOpened" | "click" | "softBounce"
//                  | "hardBounce" | "invalid" | "deferred" | "spam" | "unsubscribed" | "blocked" | "error"
//                  | "proxyOpen"
//   marketing:     "delivered" | "opened" | "click" | "softBounce" | "hardBounce" | "spam"
//                  | "unsubscribed" | "listAddition" | "contactUpdated" | "contactDeleted"
// ("sent" and "request" are synonyms in Brevo; both accepted, stored as "request".)

// WebhookDelivery
{ "id": "uuid", "webhook_id": "uuid", "event": "delivered",   // payload "event" value
  "status": "succeeded",         // "pending" | "succeeded" | "failed" | "retrying"
  "attempts": 1, "response_status": 200, "response_body_excerpt": "ok", "duration_ms": 182,
  "payload": { … }, "next_retry_at": "…|null", "created_at": "…", "delivered_at": "…|null" }
```

**Payload — transactional** (Brevo keys verbatim; one POST per event, `Content-Type:
application/json`):

```json
{ "event": "delivered", "email": "amina@exemple.dz", "id": "webhook-uuid",
  "date": "2026-10-19 10:01:02", "ts": 1760868062, "message-id": "<uuid@pagesjaunes-mailer>",
  "ts_event": 1760868062, "ts_epoch": 1760868062123, "subject": "Votre commande 1042",
  "tag": "commande", "tags": ["commande"], "sending_ip": "203.0.113.10",
  "template_id": "template-uuid", "contact_id": "recipient-uuid",
  "link": "https://… (click only)", "reason": "… (soft_bounce, hard_bounce, blocked, deferred, error, invalid_email)",
  "user_agent": "… (opened/click)", "device_used": "MOBILE (opened/click)", "mirror_link": "https://…/m/…" }
```
Payload `event` values (transactional): `request`, `delivered`, `opened`, `unique_opened`,
`click`, `soft_bounce`, `hard_bounce`, `invalid_email`, `deferred`, `spam`, `unsubscribed`,
`blocked`, `error`, `proxy_open`. `date` is in the account timezone (`Y-m-d H:i:s`), `ts*` are
UTC epochs. Absent keys are omitted, never `null`.

**Payload — marketing**:

```json
{ "id": "webhook-uuid", "camp_id": "campaign-uuid", "campaign name": "Newsletter octobre",
  "email": "amina@exemple.dz", "event": "click", "tag": "newsletter",
  "date_sent": "2026-10-19 09:00:00", "date_event": "2026-10-19 10:01:02",
  "ts_sent": 1760864400, "ts_event": 1760868062, "ts": 1760868062,
  "URL": "https://… (click only)", "list_id": ["list-uuid"], "segment_ids": ["segment-uuid"],
  "reason": "… (bounces)", "sending_ip": "203.0.113.10", "contact_id": "recipient-uuid" }
```
Payload `event` values (marketing): `delivered`, `opened`, `click`, `soft_bounce`, `hard_bounce`,
`spam`, `unsubscribe`, `list_addition`, `contact_updated`, `contact_deleted`. List-addition payload:
`{ "event": "list_addition", "email": "…", "id": "webhook-uuid", "key": "contact-uuid",
"list_id": ["list-uuid"], "date": "…", "ts": … }`; contact events add `"attributes": { "NAME": value }`
(changed attributes only for `contact_updated`).

Delivery: headers `X-Mailer-Event: <event>`, `X-Mailer-Delivery: <delivery uuid>`,
`X-Mailer-Timestamp: <epoch>`, `X-Mailer-Signature: sha256=<hex HMAC-SHA256(secret, timestamp + "." + raw body)>`
plus the webhook's custom headers. 2xx = success; otherwise retry at +1 min, +5 min, +30 min,
+2 h, +12 h (6 attempts total), then `failed`; 50 consecutive failures auto-disable the webhook
(`is_active = false`, notification `delivery_infrastructure`).

### 45.7.8 Additions and deviations recorded at the Wave 4 gate (orchestrator)

| Method | Path | Permission | Notes |
|---|---|---|---|
| POST | `/automations` | `automations.manage` | Also accepts an optional `steps` array on create (WP-91); `PATCH` still rejects `steps`. |
| POST | `/transactional/send` | API key / `transactional.send` | Responds `201` with the message object (WP-100). API keys are Sanctum personal access tokens, there is no separate `api_keys` table. |
| GET | `web:/settings/api` | auth | 302 redirect to `/transactional/settings` (API keys tab); doc 43 §43.3 lists the page at `/settings/api`. |
| PUT | `/campaigns/{id}/ab-test` | `campaigns.create` (policy `prepare`) | 409 unless draft; `sample_percent` 5–50 (§45.7.2); disabling keeps stored variants (WP-80). |
| POST | `/campaigns/{id}/ab-test/winner` | `campaigns.send` / `campaigns.send_any` | 409 unless the test phase is running or a winner exists; 422 `errors.variant_id` for a foreign variant (WP-80). |

Deviations and contract notes:

- **WP-80** — the `variant` query on `GET /campaigns/{id}/preview` and `POST /campaigns/{id}/test-send` (§45.4.2) is **not implemented** (CampaignController out of WP-80 scope; deferred to a follow-up). Remainder messages keep `variant_id = null`, so per-variant stats cover the test sample only. `picked_at`/`picked_by` come from the `campaign.ab_winner_picked` audit entry. The event is `AbTestWinnerPicked` (§45.9). An A/B campaign is rejected at schedule time when combined with best-time sending.
- **WP-81** — `BestSendTimeService::plan()` writes `campaign_recipients.scheduled_for`; the dispatcher honours it. `ConfirmSendDialog` passes `campaignId` to `SendModeOptions` for the preview. Default timezone `Africa/Algiers` (constant, not per-user).
- **WP-90** — DOI confirmation for form submissions is handled by the shared opt-in flow; the per-form DOI template/redirect columns are stored but unused by the confirm step. Captcha verification is skipped (fail-open) when no provider is configured.
- **WP-100** — `reply_to`, `cc`, `bcc` and `attachments` are validated and stored on the transactional log entry but **not delivered** by the current pipeline (follow-up). Recipients created through the API get `RecipientSource::Api` and status `active`.
- **WP-101** — signature headers are `X-Mailer-Signature`, `X-Mailer-Timestamp`, `X-Mailer-Event`, `X-Mailer-Delivery` (HMAC-SHA256 over `timestamp.body`). HTTPS required in production only; private/loopback hosts always 422. `failure_count` counts deliveries that exhaust 6 attempts; `template_id` only present for campaign messages. `sent` is stored as `request`.
- **WP-91** — `send_email` steps use the `TransactionalSender` contract when bound, otherwise `RenderingPipeline::createMessage(campaign: null)` + `SendEmailJob`. A contact never has two concurrent runs in the same automation. Run `exited` includes `failed`.
- **WP-102** — registry groups now use the `NavGroup` values; `topicForUrl` supports `{param}` segments. Help-centre `groupOrder` extended to the Brevo groups at the gate.

## 45.8 Schema addendum (frozen column lists per migration slot)

Slots and timestamp prefixes are doc 44 §44.11. A column listed here is created **only** by the
slot that lists it. Conventions (doc 04 §4.1): every new table has `id` bigint PK, `uuid` (uuid,
unique) when it is exposed by the API, `created_at`/`updated_at` timestamps; JSON columns use
`json` (driver-portable); enums are `string(32)` + application-level validation (no DB enum);
FKs to tables created in a **later** slot are added by that later slot in an
`add_foreign_key_*` migration (column created here without constraint). "FK→x" = `foreignId`
constrained to `x.id`; `cascade`/`null` = on-delete behaviour.

### Slot A — WP-41 Senders (`2026_10_20_1000xx`)

**`sending_domains`** (new): `uuid`; `domain` string(253) unique; `status` string(32) default
`pending`; `dkim_selector` string(63); `dkim_private_key` text nullable (encrypted cast);
`dkim_public_key` text nullable; `records` json (array of record objects incl. status,
found_value, checked_at); `last_checked_at` timestamp nullable; `created_by` FK→users nullable
null; timestamps.

**`senders`** (new): `uuid`; `name` string(100); `email` string(254) unique; `domain_id` FK→sending_domains
nullable null; `reply_to` string(254) nullable; `is_default` boolean default false (index);
`verification_method` string(32) nullable; `verified_at` timestamp nullable;
`verification_sent_at` timestamp nullable; `created_by` FK→users nullable null; timestamps;
soft deletes.

### Slot B — WP-42 Campaigns v2 (`2026_10_20_1100xx`)

**`campaign_folders`** (new): `uuid`; `name` string(100) unique; `created_by` FK→users nullable null; timestamps.

**`campaigns`** (alter):

| Column | Type | Null | Default | Index / FK |
|---|---|---|---|---|
| `type` | string(32) | no | `classic` | index |
| `preview_text` | string(255) | yes | | |
| `sender_id` | bigint | yes | | FK→senders null |
| `reply_to` | string(254) | yes | | |
| `to_field` | string(255) | yes | | |
| `folder_id` | bigint | yes | | FK→campaign_folders null |
| `tags` | json | yes | | |
| `timezone` | string(64) | yes | | |
| `send_mode` | string(32) | yes | | |
| `utm_enabled` | boolean | no | false | |
| `utm_source`, `utm_medium`, `utm_campaign`, `utm_content`, `utm_term`, `utm_id` | string(100) each | yes | | |
| `mirror_link_enabled` | boolean | no | true | |
| `inline_images` | boolean | no | false | |
| `attachment_id` | bigint | yes | | FK→attachments null |
| `email_expiration_duration` | unsignedSmallInteger | yes | | |
| `email_expiration_unit` | string(16) | yes | | |
| `unsubscribe_page_id` | bigint | yes | | FK added by slot M |
| `update_profile_form_id` | bigint | yes | | FK added by slot M |
| `tracking_enabled` | boolean | no | true | (skip if the column already exists) |
| `smtp_strategy` | string(32) | no | `rotation` | (skip/rename if an equivalent exists) |
| `smtp_account_id` | bigint | yes | | FK→smtp_accounts null (skip if exists) |
| `send_window` | json | yes | | (skip/rename if business-hours columns exist) |
| `requires_approval` | boolean | no | false | (skip if exists) |
| `editor_type` | string(32) | yes | | |
| `design_json` | json (longText on MySQL) | yes | | |
| `thumbnail_path` | string(255) | yes | | |
| `archived_at` | timestamp | yes | | index |
| `suspended_reason` | string(64) | yes | | |
| `send_time_optimized` | boolean | no | false | |
| `batch_size` | unsignedInteger | yes | | |
| `batch_interval_minutes` | unsignedSmallInteger | yes | | |
| `ab_test_enabled` | boolean | no | false | |
| `ab_test_config` | json | yes | | |
| `winner_variant_id` | bigint | yes | | FK added by slot O |
| `test_sent_at` | timestamp | yes | | |
| `parent_campaign_id` | bigint | yes | | FK→campaigns null |
| `completed_at` | timestamp | yes | | (skip if exists) |
| `recipients_count`, `sent_count`, `delivered_count`, `unique_opens`, `total_opens`, `unique_clicks`, `total_clicks`, `unsubscribes`, `hard_bounces`, `soft_bounces`, `complaints`, `proxy_opens`, `mirror_clicks` | unsignedInteger each | no | 0 | written by WP-70 rollups |
| `stats_updated_at` | timestamp | yes | | |

`recipient_list_id` / `segment_id`: made nullable (if not already), kept for backfill.

**`campaign_audiences`** (new): `campaign_id` FK→campaigns cascade; `target_type` string(16)
(`list`|`segment`); `target_id` bigint (no FK, polymorphic); `mode` string(16)
(`include`|`exclude`); timestamps; unique(`campaign_id`,`target_type`,`target_id`); index(`target_type`,`target_id`).
Backfill migration: one `include` row from each campaign's `recipient_list_id` / `segment_id`.

**`campaign_recipients`** (new — or, if the table already exists (doc 29 §29.5), alter to add the
missing columns only): `campaign_id` FK→campaigns cascade; `recipient_id` FK→recipients cascade;
`variant_id` bigint nullable (FK added by slot O); `message_id` bigint nullable FK→messages null;
`status` string(32) default `pending` (`pending`|`queued`|`sent`|`skipped`); `skip_reason`
string(64) nullable; `scheduled_for` timestamp nullable; `materialised_at` timestamp; unique(`campaign_id`,`recipient_id`);
index(`campaign_id`,`status`).

**`messages`** (alter): `is_test` boolean default false (index).

### Slot C — WP-44 Templates columns (`2026_10_20_1200xx`)

**`templates`** (alter): `editor_type` string(32) default `html`; `design_json` json nullable;
`folder_id` bigint nullable (FK added by slot K); `scope` string(32) default `marketing` (index);
`is_gallery` boolean default false (index); `gallery_category` string(32) nullable; `subject`
string(255) nullable; `created_by` FK→users nullable null (skip if exists). Existing rows:
`editor_type = html`. (`thumbnail_path` already exists — populated by WP-62.)

### Slot D — WP-50 Attributes (`2026_10_21_1000xx`)

**`contact_attributes`** (new): `uuid`; `name` string(50) unique; `label` string(100) nullable;
`type` string(32); `options` json nullable; `is_system` boolean default false; `storage`
string(16) default `custom_fields`; `column` string(64) nullable; `position` unsignedSmallInteger
default 0; `created_by` FK→users nullable null; timestamps. Seeded system rows: EMAIL, PRENOM,
NOM, SOCIETE, SMS, EXT_ID.

### Slot E — WP-52 Recipient counters (`2026_10_21_1100xx`)

**`recipients`** (alter): `ext_id` string(100) nullable unique; `opt_in_at` timestamp nullable;
`opt_in_source` string(32) nullable; `last_opened_at` timestamp nullable (index);
`last_clicked_at` timestamp nullable (index); `engagement_score` unsignedTinyInteger nullable;
`messages_sent`, `delivered`, `opened`, `clicked`, `hard_bounces`, `soft_bounces`, `complaints`,
`unsubscriptions` unsignedInteger default 0; `counters_updated_at` timestamp nullable.

### Slot F — WP-53 Lists (`2026_10_21_1200xx`)

**`list_folders`** (new): `uuid`; `name` string(100); `kind` string(16) default `list`
(`list`|`segment`); `created_by` FK→users nullable null; timestamps; unique(`kind`,`name`).

**`recipient_lists`** (alter): `folder_id` FK→list_folders nullable null; `contact_count`
unsignedInteger default 0; `blocklisted_count` unsignedInteger default 0; `last_import_at`
timestamp nullable; `description` string(500) nullable (skip if exists).

**List membership pivot** (alter existing pivot): `source` string(32) nullable
(`manual`|`import`|`form`|`automation`|`api`|`bulk`); `added_at` timestamp nullable (skip if
`created_at` already exists on the pivot).

### Slot G — WP-54 Segments v2 (`2026_10_21_1300xx`)

**`segments`** (alter): `name` string(150) (backfilled from the owning list's name);
`description` string(500) nullable; `folder_id` FK→list_folders nullable null; `recipient_list_id`
made nullable; `contact_count` unsignedInteger nullable; `count_computed_at` timestamp nullable;
`rules_version` unsignedTinyInteger default 2; `created_by` FK→users nullable null (skip if
exists). Conversion migration: each `recipient_lists.type = dynamic_segment` becomes a standalone
segment (list kept read-only, legacy).

### Slot H — WP-55 Imports (`2026_10_21_1400xx`)

**`import_jobs`** (alter): `mapping` json nullable; `list_ids` json nullable; `new_list` json
nullable; `update_existing` boolean default true; `empty_values_overwrite` boolean default false;
`mark_blocklisted` boolean default false; `mark_opt_in` boolean default false; `summary` json
nullable; `created_list_id` FK→recipient_lists nullable null.

### Slot I — WP-56 Blocklist mirror (`2026_10_21_1500xx`)

**`recipients`** (alter): `blocklisted_at` timestamp nullable (index). Backfill from
`suppression_entries` by email.

### Slot J — WP-57 Views & export (`2026_10_21_1600xx`)

**`contact_views`** (new): `uuid`; `user_id` FK→users cascade; `name` string(100); `filters` json
nullable; `list_id` FK→recipient_lists nullable null; `segment_id` FK→segments nullable null;
`columns` json; `sort` string(100) nullable; `is_shared` boolean default false; `position`
unsignedSmallInteger default 0; timestamps; index(`user_id`).

**`async_operations`** (new): `uuid`; `user_id` FK→users nullable null; `type` string(32);
`status` string(16) default `queued`; `total`, `processed`, `failed` unsignedInteger default 0;
`payload` json nullable; `result` json nullable; `error` text nullable; `completed_at` timestamp
nullable; timestamps; index(`user_id`,`created_at`).

**`users`** (alter): `column_preferences` json nullable.

**`recipients`** (alter): `created_by` FK→users nullable null (skip if exists) — drives *Mes contacts*.

### Slot K — WP-62 Template folders (`2026_10_22_1000xx`)

**`template_folders`** (new): `uuid`; `name` string(100) unique; `created_by` FK→users nullable null; timestamps.
`add_foreign_key_templates_folder_id`: `templates.folder_id` → `template_folders.id` null.

### Slot L — WP-70 Tracking & stats (`2026_10_22_1100xx`)

**`campaign_links`** (new): `uuid`; `campaign_id` FK→campaigns cascade; `link_id` string(16);
`url` text; `url_hash` char(40) (index); `label` string(255) nullable; `unique_clicks`,
`total_clicks` unsignedInteger default 0; `last_clicked_at` timestamp nullable; timestamps;
unique(`campaign_id`,`link_id`).

**`campaign_stats_hourly`** (new): `campaign_id` FK→campaigns cascade; `hour` timestamp (UTC,
truncated); `variant_id` bigint nullable; counters unsignedInteger default 0: `sent`,
`delivered`, `opens`, `unique_opens`, `proxy_opens`, `clicks`, `unique_clicks`, `hard_bounces`,
`soft_bounces`, `complaints`, `unsubscriptions`, `deferred`, `blocked`; timestamps;
unique(`campaign_id`,`hour`,`variant_id`).

**`campaign_stats_by_list`** (new): `campaign_id` FK→campaigns cascade; `target_type` string(16);
`target_id` bigint; same counters as hourly; timestamps; unique(`campaign_id`,`target_type`,`target_id`).

**`message_events`** (alter): `url` text nullable; `link_id` string(16) nullable;
`user_agent` string(512) nullable; `device_type` string(16) nullable; `email_client` string(64)
nullable; `ip_country` char(2) nullable; `ip_city` string(100) nullable; `is_proxy_open` boolean
default false; index(`message_id`,`event_type`) if absent.

### Slot M — WP-74 Preferences (`2026_10_22_1200xx`)

**`unsubscribe_pages`** (new): `uuid`; `name` string(100); `is_default` boolean default false;
`content` json; `design` json; `created_by` FK→users nullable null; timestamps.

**`update_profile_forms`** (new): `uuid`; `name` string(100); `is_default` boolean default false;
`fields` json; `list_ids` json; `content` json; `design` json; `created_by` FK→users nullable
null; timestamps.

**`unsubscribe_feedback`** (new): `recipient_id` FK→recipients null nullable; `campaign_id`
FK→campaigns null nullable; `message_id` FK→messages null nullable; `reason` string(255)
nullable; `created_at`.

`add_foreign_key_campaigns_preference_pages`: `campaigns.unsubscribe_page_id` →
`unsubscribe_pages.id` null; `campaigns.update_profile_form_id` → `update_profile_forms.id` null.

### Slot N — WP-61 Saved sections (`2026_10_23_1000xx`)

**`saved_sections`** (new): `uuid`; `name` string(100); `mjml` longText; `component` json;
`thumbnail_path` string(255) nullable; `created_by` FK→users nullable null; timestamps.

### Slot O — WP-80 A/B (`2026_10_23_1100xx`)

**`ab_test_variants`** (new): `uuid`; `campaign_id` FK→campaigns cascade; `label` char(1);
`subject` string(255) nullable; `preview_text` string(255) nullable; `html_body` longText
nullable; `design_json` json nullable; `is_winner` boolean default false; `sample_size`
unsignedInteger default 0; timestamps; unique(`campaign_id`,`label`).

**`messages`** (alter): `variant_id` FK→ab_test_variants nullable null.

`add_foreign_key_ab`: `campaigns.winner_variant_id` → `ab_test_variants.id` null;
`campaign_recipients.variant_id` → `ab_test_variants.id` null.

### Slot P — WP-81 Best time (`2026_10_23_1200xx`)

**`messages`** (alter): `scheduled_for` timestamp nullable (index) — skip and reuse if the
existing scheduling column already serves this purpose (WP-81 decides, reports).

**`recipients`** (alter): `best_send_hour` unsignedSmallInteger nullable (hour of week 0–167,
account timezone); `best_send_hour_computed_at` timestamp nullable.

### Slot Q — WP-90 Forms (`2026_10_23_1300xx`)

**`forms`** (new): `uuid`; `name` string(150); `status` string(16) default `draft` (index);
`list_ids` json; `fields` json; `gdpr` json; `design` json; `settings` json;
`doi_template_id` FK→templates nullable null; `submissions_count`, `confirmed_count`
unsignedInteger default 0; `last_submission_at` timestamp nullable; `created_by` FK→users nullable
null; timestamps; soft deletes.

**`form_submissions`** (new): `uuid`; `form_id` FK→forms cascade; `recipient_id` FK→recipients
nullable null; `email` string(254) (index); `status` string(32) (index); `data` json;
`confirmation_token_hash` char(64) nullable unique; `ip_hash` char(64) nullable; `ip_country`
char(2) nullable; `user_agent` string(512) nullable; `submitted_at` timestamp; `confirmed_at`
timestamp nullable; timestamps.

### Slot R — WP-91 Automations (`2026_10_23_1400xx`)

**`automations`** (new): `uuid`; `name` string(150); `description` text nullable; `status`
string(16) default `draft` (index); `trigger_type` string(32) (index); `trigger_config` json;
`allow_reentry` boolean default false; `reentry_after_days` unsignedSmallInteger nullable;
`activated_at` timestamp nullable; `created_by` FK→users nullable null; timestamps; soft deletes.

**`automation_steps`** (new): `uuid`; `automation_id` FK→automations cascade; `parent_step_id`
FK→automation_steps nullable cascade; `branch` string(8) nullable (`yes`|`no`); `position`
unsignedSmallInteger; `type` string(32); `config` json; timestamps; index(`automation_id`,`parent_step_id`,`branch`,`position`).

**`automation_runs`** (new): `uuid`; `automation_id` FK→automations cascade; `recipient_id`
FK→recipients cascade; `status` string(16) (index); `current_step_id` FK→automation_steps
nullable null; `next_run_at` timestamp nullable (index); `context` json nullable; `started_at`
timestamp; `finished_at` timestamp nullable; `error` text nullable; timestamps;
index(`automation_id`,`recipient_id`).

**`automation_step_logs`** (new): `automation_run_id` FK→automation_runs cascade;
`automation_step_id` FK→automation_steps cascade; `outcome` string(16) (`completed`|`yes`|`no`|`failed`|`skipped`);
`message_id` FK→messages nullable null; `created_at`.

**`messages`** (alter): `automation_step_id` FK→automation_steps nullable null (index).

### Slot S — WP-101 Webhooks (`2026_10_23_1500xx`)

**`api_webhooks`** (new): `uuid`; `url` string(2048); `description` string(255) nullable;
`type` string(16); `events` json; `secret` text (encrypted cast); `headers` json nullable
(encrypted cast); `is_active` boolean default true; `failure_count` unsignedInteger default 0;
`last_delivery_at` timestamp nullable; `last_delivery_status` string(16) nullable; `created_by`
FK→users nullable null; timestamps.

**`api_webhook_deliveries`** (new): `uuid`; `api_webhook_id` FK→api_webhooks cascade; `event`
string(32) (index); `status` string(16) (index); `attempts` unsignedTinyInteger default 0;
`payload` json; `response_status` unsignedSmallInteger nullable; `response_body_excerpt`
string(1000) nullable; `duration_ms` unsignedInteger nullable; `next_retry_at` timestamp nullable
(index); `delivered_at` timestamp nullable; timestamps. Pruned after 30 days.

### Not in a slot

- API keys: Sanctum's existing `personal_access_tokens` (abilities = scopes); WP-100 adds no
  migration. If `last_used_ip` / `token_prefix` are wanted they are derived (prefix = first 8 chars
  shown at creation, stored in `name` metadata) — no column added (§45.12).
- Transactional messages reuse `messages` (`source` derived from existing columns).

## 45.9 Domain events, jobs and service contracts

Events are Laravel events (`ShouldDispatchAfterCommit`). FQCN, payload (constructor properties,
internal ids unless noted), fired by, typical listeners.

| Event / job | Payload | Fired by (WP) | Listeners (WP) |
|---|---|---|---|
| `App\Modules\Senders\Events\SenderVerified` | `senderId` | WP-41 | WP-45 checklist cache bust |
| `App\Modules\Senders\Events\DomainVerificationCompleted` | `domainId`, `status` | WP-41 | WP-41 auto-verifies senders of the domain |
| `App\Modules\Senders\Jobs\VerifyDomainJob` | `domainId` | WP-41 | — |
| `App\Modules\Campaigns\Events\CampaignStatusChanged` | `campaignId`, `from`, `to`, `actorId?`, `reason?` | WP-42 (every transition, incl. suspend / archive) | WP-72 timeline, notifications, WP-101 |
| `App\Modules\Campaigns\Events\CampaignDesignSaved` | `campaignId`, `editorType` | WP-42 (`PUT design`, `design/copy`) | WP-62 `RenderThumbnailJob`, WP-45 cache bust |
| `App\Modules\Campaigns\Events\CampaignTestSent` | `campaignId`, `emails[]`, `actorId` | WP-42 | WP-45 (`test_sent`) |
| `App\Modules\Campaigns\Events\AudienceMaterialised` | `campaignId`, `count` | WP-42 | WP-80 (sample split), WP-81 (best-time planning) |
| `App\Modules\Campaigns\Jobs\MaterialiseAudienceJob` | `campaignId` | WP-42 (on send start) | — |
| `App\Modules\Campaigns\Contracts\AudienceResolver` | `resolve(Campaign): int` (materialises), `estimate(Campaign, ?array $audience): AudienceEstimate` | WP-42 | WP-80, WP-81 |
| `App\Modules\Campaigns\Services\Checklist\CampaignChecklistService` | `evaluate(Campaign): CampaignChecklist`, `blockingFailures(Campaign): list<string>` | WP-45 (bound in AppServiceProvider) | WP-42 `schedule` (call guarded by `app()->bound()` until WP-45 merges) |
| `App\Modules\Campaigns\Jobs\CheckCampaignLinksJob` | `campaignId` | WP-45 | — |
| `App\Modules\Templates\Events\TemplateSaved` | `templateId` | WP-44 / WP-60 | WP-62 thumbnail |
| `App\Modules\Templates\Jobs\RenderThumbnailJob` | `modelType` (`template`\|`campaign`\|`saved_section`), `modelId` | WP-62 | — |
| `App\Modules\Recipients\Attributes\Contracts\ContactAttributeService` | `definitions(): Collection`, `apply(Recipient, array $values, string $source): array $changes` (validates, casts, saves, fires event), `castForStorage(...)` | WP-50 | WP-52, WP-55, WP-57, WP-90, WP-91 |
| `App\Modules\Recipients\Attributes\Events\ContactAttributesUpdated` | `recipientId`, `changes` (`NAME => {old,new}`), `source` | WP-50 (via `apply`) | WP-91 `attribute_changed`, WP-101 `contactUpdated`, WP-52 timeline |
| `App\Modules\Recipients\Lists\Events\RecipientsAddedToList` | `listId`, `recipientIds[]`, `source`, `importJobId?`, `formId?` | WP-53 (`ListMembershipService`) — every other writer (WP-55, WP-57, WP-90, WP-91) **must** go through `ListMembershipService::add()` | WP-91 `list_added`, WP-101 `listAddition`, WP-53 count cache |
| `App\Modules\Recipients\Lists\Events\RecipientsRemovedFromList` | `listId`, `recipientIds[]`, `source` | WP-53 | WP-53 count cache |
| `App\Modules\Recipients\Lists\Contracts\ListMembershipService` | `add(RecipientList, array $recipientIds, string $source, array $context = []): int`, `remove(...)`, `recount(RecipientList)` | WP-53 | WP-55, WP-57, WP-90, WP-91 |
| `App\Modules\Recipients\Segments\Contracts\RuleTreeCompiler` | `validate(array $tree): void` (throws `ValidationException`), `apply(Builder $recipients, array $tree): Builder`, `count(array $tree): int`, `matches(Recipient, array $tree): bool`, `upgrade(array $legacyTree): array` | WP-54 (bound in AppServiceProvider) | WP-57 (filters), WP-42 (segment audiences), WP-72 (`report/segment`), WP-91 (condition steps, trigger filter), WP-61/WP-63 (dynamic content) |
| `App\Modules\Recipients\Events\RecipientDeleted` | `recipientId`, `email` | WP-57 (bulk delete) and existing delete | WP-101 `contactDeleted` |
| `App\Modules\Recipients\Jobs\RecomputeRecipientCountersJob` | `recipientIds[]?` | WP-52 | — |
| `App\Modules\Importing\Events\ImportCompleted` | `importJobId`, `summary` | WP-55 | notifications, WP-52 timeline |
| `App\Modules\Recipients\Views\Contracts\AsyncOperations` | `start(string $type, ?User, array $payload): AsyncOperation`, `progress(...)`, `complete(..., array $result)`, `fail(...)` | WP-57 | WP-53, WP-54, WP-62, WP-72 |
| `App\Modules\Recipients\Views\Jobs\ExportContactsJob` · `BulkRecipientActionJob` | `operationId` | WP-57 | — |
| `App\Modules\Suppression\Listeners\SyncRecipientBlocklist` | listens to suppression create/delete | WP-56 | fires `RecipientBlocklistChanged(recipientId, blocklisted, reason)` → WP-52 timeline, WP-101 `contactUpdated` |
| `App\Modules\Tracking\Events\MessageEventRecorded` | `messageEventId`, `messageId`, `type` (MessageStatus-style event), `campaignId?`, `recipientId?`, `url?`, `linkId?`, `isProxyOpen` | WP-70 (tracking pixel, click, webhooks from providers) | WP-70 counters & rollups, WP-91 `campaign_opened/clicked`, WP-101 webhooks, WP-80 winner metrics |
| `App\Modules\Reporting\Jobs\RollupCampaignStatsJob` | `campaignId` | WP-70 (hourly + on completion) | — |
| `App\Modules\Reporting\Services\CampaignStatsService` | `globalStats(Campaign): array` (BrevoStats), `byList(Campaign)`, `timeseries(Campaign, string $granularity)` | WP-70 | WP-72, WP-80 |
| `App\Modules\Suppression\Preferences\Events\ContactPreferencesUpdated` | `recipientId`, `unsubscribedListIds[]`, `subscribedListIds[]`, `unsubscribedAll` | WP-74 | WP-101 `unsubscribed` |
| `App\Modules\Campaigns\AbTesting\Jobs\PickAbWinnerJob` | `campaignId` | WP-80 | — |
| `App\Modules\Campaigns\AbTesting\Events\AbTestWinnerPicked` | `campaignId`, `variantId`, `pickedBy` | WP-80 | WP-72 timeline |
| `App\Modules\DeliveryEngine\Scheduling\Contracts\BestSendTimeService` | `plan(Campaign): void` (writes `messages.scheduled_for`), `bestHourFor(Recipient): ?int` | WP-81 | WP-42 dispatcher hook |
| `App\Modules\Forms\Events\FormSubmitted` | `formId`, `submissionId`, `recipientId?`, `status` | WP-90 | WP-91 `form_submitted` (fires on `subscribed`/`confirmed`), WP-52 timeline |
| `App\Modules\Forms\Events\ContactOptInConfirmed` | `recipientId`, `formId` | WP-90 | WP-52 timeline |
| `App\Modules\Automations\Jobs\AdvanceAutomationRunsJob` | — (scheduler every minute, `withoutOverlapping`) | WP-91 | — |
| `App\Modules\Automations\Events\AutomationRunFinished` | `runId`, `status` | WP-91 | — |
| `App\Modules\Transactional\Events\TransactionalMessageQueued` | `messageId`, `apiKeyId?` | WP-100 | — |
| `App\Modules\Webhooks\Jobs\DeliverWebhookJob` | `deliveryId` | WP-101 | — |

Audit actions (doc 27, `action` strings): `sender.created`, `sender.verified`, `sender.deleted`,
`domain.created`, `domain.dkim_generated`, `domain.verified`, `domain.deleted`,
`campaign.archived`, `campaign.unarchived`, `campaign.test_sent`, `campaign.scheduled`,
`campaign.suspended`, `campaign.duplicated`, `attribute.created`, `attribute.deleted`,
`contacts.exported`, `contacts.bulk_deleted`, `contacts.bulk_blocklisted`, `api_key.created`,
`api_key.revoked`, `webhook.created`, `webhook.deleted`, `webhook.secret_rotated`,
`form.published`, `automation.activated`, `automation.paused`, `unsubscribe_page.updated`.

## 45.10 Legacy compatibility

| Legacy | v2 behaviour |
|---|---|
| `Campaign` resource keys (`recipient_list_id`, `segment_id`, `cloned_from_campaign_id`…) | kept in `CampaignV2`; `recipient_list_id`/`segment_id` become `null` for campaigns created with an `audience` |
| `POST /campaigns` with `recipient_list_id` / `segment_id` | still accepted, converted to one `include` audience row |
| `GET /campaigns/{id}/estimate` (completion estimate, doc 19.8) | kept; audience size moves to `POST /audience/estimate` |
| `POST /campaigns/{id}/clone` | alias of `duplicate` |
| `GET /recipients` without `pagination=cursor` | unchanged legacy page-based response (current `Recipients/Index.tsx` keeps working) |
| `POST /segments/{uuid}/preview`, `POST /segments` with `recipient_list_id` | kept; segments without a list are the v2 default |
| `PUT /import-jobs/{id}/mapping` with `column_mapping` | kept, converted to `mapping` |
| `/reporting/*` | kept until WP-73; then only `/reporting/smtp-accounts` stays |
| `GET /recipients/{id}/timeline` (doc 29 §29.4) | response becomes `CursorPaginatedResponse<TimelineEntry>` (no current consumer page) |
| Web URLs `/recipients`, `/suppression`, `/reporting` | redirects (WP-40N) |
| TS `Campaign`, `CampaignDetail`, `CampaignStatus`, `Template`, `ImportJob`, `Recipient` | unchanged; v2 types extend them (`CampaignV2`, `TemplateV2`, `ImportJobV2`, `Contact`) |

## 45.11 i18n map (`resources/js/Lib/i18n/fr.ts`)

| Namespace | Pages / components |
|---|---|
| `nav` (+ `nav.groups`) | shell (WP-40N) — the mandatory nav keys |
| `common` (extended) | shared buttons and states |
| `campaigns` (extended: `statusLabels`, `brevoStatusLabels`, `tabs`, `actions`, `columns`, `create`, `bulk`, `suspendedReasons`; folder strings: `common.folder*`) | `Campaigns/Index` |
| `campaignSetup` | `Campaigns/Setup`, recipients dialog, *Aperçu & test*, checklist, *Confirmer l'envoi*, A/B card |
| `campaignReport` | `Campaigns/Report`, compare drawer |
| `templates` (extended) | `Templates/Index`, `Templates/Edit` |
| `templatePicker` | `TemplatePicker` modal |
| `editor` | `EmailBuilder` |
| `contacts` | `Contacts/Index`, views, bulk bar, export |
| `contactDetail` | `Contacts/Show` |
| `lists` | `Contacts/Lists`, `Contacts/ListShow` |
| `segments` | `Contacts/Segments`, `Contacts/SegmentEdit` |
| `conditionBuilder` | `ConditionBuilder` (fields, operators, categories) |
| `attributes` | `Contacts/Attributes` (types) |
| `imports` (extended: `wizard`, `history`, `statusLabels`) | `Contacts/ImportWizard`, `Contacts/Imports` |
| `blocklist` | `Contacts/Blocklist` |
| `senders`, `domains` | `Settings/Senders`, `Settings/Domains`, `SenderPicker` |
| `statistics` | `Statistics/Index` |
| `transactional` | `Transactional/Logs`, `Transactional/Settings` (events) |
| `apiKeys`, `webhooks` | *Clés API* tab, `WebhooksPanel` |
| `forms` | `Marketing/Forms`, `Marketing/FormEdit` |
| `automations` | `Automations/Index`, `Automations/Edit` |
| `preferences`, `unsubscribePages` | `Settings/UpdateProfileForms`, `Settings/UnsubscribePages` |
| `tracking` | `Settings/Tracking` |
| `operations` | async operation toasts |

## 45.12 Open questions (for the orchestrator)

1. `campaigns` may already hold `tracking_enabled`, SMTP strategy, business-hours and approval
   columns (doc 15). Slot B lists them with "skip if exists"; WP-42 must report the real names and
   keep the resource keys of §45.4.2.
2. `campaign_recipients` is referenced by doc 29 §29.5 — WP-42 decides create vs alter.
3. Existing import `status` values and the suppression index filter (`reason[]`) were not read by
   WP-40 (closed read list); WP-55 / WP-56 report them if different.
4. `contact.source` values `form` and `api` are new recipient sources; the owning WP (WP-90 /
   WP-100) adds them to the source enum.
5. `SuppressionEntry.recipient_id` / `source_campaign` need a resource change in
   `app/Modules/Suppression/Http/**`, which no WP of doc 44 owns (WP-56 owns only Listeners).
6. `Settings/Tracking` (`/settings/tracking`) has no WP in doc 44; keys are fixed in §45.6.7.
7. `GET /transactional/logs` is assigned to WP-100 (backend) since WP-101's scope is UI-only for
   logs.
8. Branch naming: `brevo/wave-N/wp-XX` cannot coexist with the branch `brevo/wave-N` in git;
   WP branches are `brevo/wave-N-wp-XX`.
9. `docs/26-rbac.md §26.3` must be updated to the new matrix of §45.2 (the seeder comment says
   byte-for-byte sync; doc 26 is not owned by WP-40).
10. WP-40 did not run the test suite (prompt rule): any existing test asserting the exact number
    of permissions or the exact per-role permission list must be updated at the Wave 0 gate.
11. `lists.manage` and `statistics.view` were not created: lists stay on `recipients.manage` /
    `recipients.view` and statistics on `reporting.view` to keep current gates working; split
    them later if a role needs finer rights.
