# 29 — API Specification

## 29.1 Conventions

- Base path: `/api/v1`. Inertia page routes (`/mail/*`, `/campaigns/*`, etc., see [08-navigation.md](08-navigation.md)) return full-page props and are not part of this JSON contract; this document covers the versioned JSON API used by the SPA's client-side fetches (polling widgets, autosave, pickers) and by the webhook/tracking endpoints.
- Auth: Sanctum session cookie (`X-XSRF-TOKEN` header on writes) for all authenticated endpoints; tracking/webhook endpoints are unauthenticated by design (see 29.9/29.10) with their own verification scheme.
- All IDs in URLs/payloads are the `uuid` column, never the internal `bigint` PK (see [04-database-design.md §4.1](04-database-design.md#41-conventions)).
- Pagination: cursor-based for large/high-churn lists (`messages`, `audit_logs`), page-based for smaller admin lists (`smtp_accounts`, `users`). Response envelope:

```json
{
  "data": [ ... ],
  "meta": { "current_page": 1, "per_page": 25, "total": 143 },
  "links": { "next": "...", "prev": null }
}
```

- Error envelope (consistent across all endpoints):

```json
{
  "message": "The given data was invalid.",
  "errors": { "email": ["The email field is required."] }
}
```

| HTTP Status | Meaning |
|---|---|
| 401 | Unauthenticated |
| 403 | Authenticated but lacks permission (26-rbac) |
| 404 | Resource not found / not visible to this user's scope |
| 409 | Conflict (e.g. duplicate email, campaign not in a valid state for this action) |
| 422 | Validation failure |
| 429 | Rate limited |
| 500 | Unhandled server error (logged, generic message returned) |

## 29.2 Identity & Auth

| Endpoint | Method | Auth | Permission | Notes |
|---|---|---|---|---|
| `/login` | POST | none | — | body: `email`, `password`; rate-limited (28.7) |
| `/logout` | POST | session | — | |
| `/api/v1/me` | GET | session | — | current user + permissions array (drives frontend gating, 26.5) |
| `/api/v1/users` | GET | session | `users.manage` | paginated |
| `/api/v1/users` | POST | session | `users.manage` | create user; body: `name`,`email`,`role_id`,`password` |
| `/api/v1/users/{uuid}` | PATCH | session | `users.manage` | update role/active status |
| `/api/v1/roles` | GET | session | `users.manage` | list roles + permission sets |

## 29.3 Composer & Templates

| Endpoint | Method | Permission | Notes |
|---|---|---|---|
| `/api/v1/drafts` | GET | `mailbox.view_own` | scoped to own unless `mailbox.view_all` |
| `/api/v1/drafts` | POST | `composer.compose` | autosave create; body: `subject`,`html_body`,`template_id?` |
| `/api/v1/drafts/{uuid}` | PATCH | `composer.compose` | autosave update (debounced client-side) |
| `/api/v1/drafts/{uuid}/versions` | GET | `composer.compose` | version history list |
| `/api/v1/drafts/{uuid}/versions/{versionId}/restore` | POST | `composer.compose` | creates new version from restored content |
| `/api/v1/drafts/{uuid}/send` | POST | `composer.send` | body: `recipient_ids[]` or `emails[]`; 422 if no body/subject; 409 if already sent |
| `/api/v1/signatures` | GET/POST/PATCH/DELETE | `composer.compose` | own signatures |
| `/api/v1/templates` | GET | `templates.view` | filter by `category`, `is_archived` |
| `/api/v1/templates` | POST/PATCH | `templates.manage` | |
| `/api/v1/templates/{uuid}/versions` | GET | `templates.manage` | |
| `/api/v1/template-blocks` | GET/POST | `templates.manage` | |

**Example — Create Draft**

Request:
```json
POST /api/v1/drafts
{ "subject": "Q3 Update", "html_body": "<p>Hello {{recipient.first_name}}</p>", "template_id": null }
```
Response `201`:
```json
{ "data": { "id": "b3f1...", "subject": "Q3 Update", "status": "draft", "updated_at": "2026-07-13T10:00:00Z" } }
```

## 29.4 Recipients, Lists, Segments, Import

| Endpoint | Method | Permission | Notes |
|---|---|---|---|
| `/api/v1/recipients` | GET | `recipients.view` | filters: `q`,`source`,`status`,`tag_id[]`,`added_after`,`added_before` |
| `/api/v1/recipients` | POST | `recipients.manage` | manual create; 409 if email exists (returns existing recipient link suggestion) |
| `/api/v1/recipients/{uuid}` | GET/PATCH/DELETE | `recipients.manage` | DELETE is soft, 409 if referenced by messages (23/04 cascade rule) |
| `/api/v1/recipients/{uuid}/notes` | GET/POST | `recipients.annotate` | |
| `/api/v1/recipients/{uuid}/timeline` | GET | `recipients.view` | communication timeline (16) |
| `/api/v1/recipients/{uuid}/verify` | POST | `recipients.verify` | synchronous, may take up to provider timeout |
| `/api/v1/recipient-lists` | GET/POST/PATCH/DELETE | `recipients.manage` | |
| `/api/v1/segments` | POST | `segments.manage` | body: `recipient_list_id`,`rules` (jsonb tree, 13.7) |
| `/api/v1/segments/{uuid}/preview` | POST | `segments.manage` | returns `{count, sample: [...]}` without persisting |
| `/api/v1/import-jobs` | GET | `recipients.import` | history |
| `/api/v1/import-jobs` | POST (multipart) | `recipients.import` | file upload, `source_type` |
| `/api/v1/import-jobs/{uuid}/mapping` | PUT | `recipients.import` | column mapping submission |
| `/api/v1/import-jobs/{uuid}/rows` | GET | `recipients.import` | paginated review grid |
| `/api/v1/import-jobs/{uuid}/commit` | POST | `recipients.import` | body: `excluded_row_ids[]?` |
| `/api/v1/pagejaunes/search` | GET | `recipients.import` | query: `q`,`sector`,`city`; proxies external DB via repository (06) |
| `/api/v1/pagejaunes/import` | POST | `recipients.import` | body: `external_ids[]`, `target_recipient_list_id?` |

## 29.5 Campaigns

| Endpoint | Method | Permission | Notes |
|---|---|---|---|
| `/api/v1/campaigns` | GET | `campaigns.view` | filter `status` |
| `/api/v1/campaigns` | POST | `campaigns.create` | wizard step 1 payload |
| `/api/v1/campaigns/{uuid}` | GET/PATCH | `campaigns.create` (own) / `campaigns.send_any` (others) | |
| `/api/v1/campaigns/{uuid}/estimate` | GET | `campaigns.create` | audience size + estimated completion (19.8) |
| `/api/v1/campaigns/{uuid}/submit-for-approval` | POST | `campaigns.create` | only if review-before-sending is on (15.4) |
| `/api/v1/campaigns/{uuid}/approve` | POST | `campaigns.approve` | 409 if not pending approval |
| `/api/v1/campaigns/{uuid}/send` | POST | `campaigns.send` / `campaigns.send_any` | 409 if not `draft`/approved |
| `/api/v1/campaigns/{uuid}/pause` | POST | `campaigns.cancel_pause` | 409 if not `running` |
| `/api/v1/campaigns/{uuid}/resume` | POST | `campaigns.cancel_pause` | 409 if not `paused` |
| `/api/v1/campaigns/{uuid}/cancel` | POST | `campaigns.cancel_pause` | |
| `/api/v1/campaigns/{uuid}/clone` | POST | `campaigns.create` | |
| `/api/v1/campaigns/{uuid}/recipients` | GET | `campaigns.view` | paginated `campaign_recipients` + message status |
| `/api/v1/campaigns/{uuid}/analytics` | GET | `campaigns.view` | funnel + link analytics (21.4) |

**Example — Send Campaign**

Response `200`:
```json
{ "data": { "id": "c9a2...", "status": "running", "started_at": "2026-07-13T10:05:00Z" } }
```
Response `409` (not approved):
```json
{ "message": "Campaign requires approval before it can be sent." }
```

## 29.6 SMTP & Delivery

| Endpoint | Method | Permission | Notes |
|---|---|---|---|
| `/api/v1/smtp-accounts` | GET | `smtp.view` | includes health/usage summary |
| `/api/v1/smtp-accounts` | POST/PATCH | `smtp.manage_credentials` | password write-only field |
| `/api/v1/smtp-accounts/{uuid}` | DELETE | `smtp.manage_credentials` | 409 if referenced by send_attempts |
| `/api/v1/smtp-accounts/{uuid}/test` | POST | `smtp.test` | body: `test_email?`; returns raw provider response |
| `/api/v1/smtp-accounts/{uuid}/usage` | GET | `smtp.view` | quota_ledger trend |
| `/api/v1/warmup-schedules` | GET/POST | `smtp.manage_quotas_rotation` | |

## 29.7 Email History & Mailbox

| Endpoint | Method | Permission | Notes |
|---|---|---|---|
| `/api/v1/messages` | GET | `mailbox.view_own`/`view_all` | cursor-paginated; filters `status`,`campaign_id`,`smtp_account_id`,`date_from`,`date_to`,`recipient_q` |
| `/api/v1/messages/{uuid}` | GET | same | includes `message_events` + `send_attempts` |
| `/api/v1/messages/{uuid}/cancel` | POST | `composer.send` | outbox cancel (10.5) |
| `/api/v1/messages/{uuid}/retry` | POST | `composer.send` | outbox retry-now |
| `/api/v1/messages/{uuid}/reschedule` | POST | `composer.send` | body: `scheduled_at` |

## 29.8 Suppression & Verification

| Endpoint | Method | Permission | Notes |
|---|---|---|---|
| `/api/v1/suppression-entries` | GET | `suppression.view` | |
| `/api/v1/suppression-entries` | POST | `suppression.manage` | manual block |
| `/api/v1/suppression-entries/{uuid}` | DELETE | `suppression.manage` | requires confirm flag in body |
| `/unsubscribe/{token}` | GET/POST | none | public confirmation page + action (23.4) |

## 29.9 Tracking (Public, Unauthenticated)

| Endpoint | Method | Notes |
|---|---|---|
| `/t/o/{message_uuid}.gif` | GET | tracking pixel (21.1), rate-limited per IP |
| `/t/c/{tracking_token}` | GET | click redirect (21.2), 302 to original URL, rate-limited per IP |

## 29.10 Webhooks (Provider → App)

| Endpoint | Method | Auth | Notes |
|---|---|---|---|
| `/webhooks/smtp/{provider}` | POST | HMAC signature per provider (28.8) | normalized into `message_events`; 401 on bad signature, 200 always on success to prevent provider retry storms |

## 29.11 Reporting & Queues

| Endpoint | Method | Permission | Notes |
|---|---|---|---|
| `/api/v1/reports/{report_type}` | GET | `reporting.view` | query params per report (25.2) |
| `/api/v1/reports/{report_type}/export` | POST | `reporting.export` | returns `{export_job_id}`, poll for signed URL |
| `/api/v1/queues/metrics` | GET | `queues.view` | Horizon-backed snapshot (24.6) |
| `/api/v1/audit-logs` | GET | `audit.view` | cursor-paginated, filters `user_id`,`action`,`date_from`,`date_to` |
| `/api/v1/audit-logs/export` | POST | `audit.export` | |
| `/api/v1/settings` | GET/PATCH | `settings.manage` (branding subset: `settings.branding_only`) | secret values never returned in GET |

## 29.12 Rate Limiting Summary

| Endpoint class | Limit |
|---|---|
| Auth endpoints | 5/min per IP |
| Public tracking/unsubscribe | 60/min per IP |
| Webhooks | 300/min per provider source IP range |
| General authenticated API | 120/min per user |

Continue to [30-background-jobs.md](30-background-jobs.md).
