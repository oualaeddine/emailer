# 04 — Database Design

## 4.1 Conventions

- Database: PostgreSQL 16+.
- Primary keys: `id BIGSERIAL PRIMARY KEY` unless noted.
- All FKs indexed. Naming: `{singular_referenced_table}_id`.
- Timestamps: `created_at`, `updated_at` as `timestamptz NOT NULL DEFAULT now()`; `deleted_at timestamptz NULL` where soft delete applies.
- Enums implemented as Postgres `CHECK` constraints on `varchar` columns (not native Postgres enum types, to keep additive changes migration-only) — the allowed values are documented per table.
- JSON columns use `jsonb`.
- Money/quota columns are `integer` (counts) — no currency in this domain.
- All external-facing IDs additionally expose a `uuid` column (`uuid UNIQUE NOT NULL DEFAULT gen_random_uuid()`) so internal auto-increment IDs are never leaked in URLs/APIs. API resources serialize `uuid` as `id`.

> **Schema addenda from the refinement set (35–41)**: two small tables are specified alongside their owning feature rather than here, to keep this document focused on the base v1 model — `deliverability_checks` (polymorphic, `Campaign`/`SmtpAccount`) in [39-deliverability-analyzer.md §39.5](39-deliverability-analyzer.md#395-data-model-addendum), and `notification_preferences` in [41-notification-center.md §41.4](41-notification-center.md#414-per-user-preferences). Both follow every convention on this page (bigserial PK, timestamptz, etc.) and have no bearing on any relationship documented in §4.2–§4.16 below.

## 4.2 Identity Module

### `users`
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| uuid | uuid | unique |
| name | varchar(255) | |
| email | varchar(255) | unique |
| password | varchar(255) | hashed |
| role_id | bigint FK → roles.id | restrict delete |
| avatar_path | varchar(255) | nullable |
| is_active | boolean | default true |
| last_login_at | timestamptz | nullable |
| email_verified_at | timestamptz | nullable |
| created_at/updated_at/deleted_at | | soft delete |

Indexes: unique(email), index(role_id).

### `roles`
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| name | varchar(50) | unique — `administrator`, `marketing_manager`, `marketing_operator`, `viewer` |
| description | text | |

### `permissions`
| id | name (e.g. `campaigns.send`) | module | description |

### `permission_role` (pivot)
`role_id FK`, `permission_id FK`, composite PK, cascade delete both sides.

### `personal_access_tokens`
Standard Sanctum table (tokenable_type, tokenable_id, name, token hash, abilities jsonb, last_used_at, expires_at).

### `sessions`
Standard Laravel session table (if using database session driver) — id, user_id FK nullable, ip_address, user_agent, payload, last_activity.

## 4.3 Composer Module

### `signatures`
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| uuid | uuid | |
| user_id | bigint FK → users.id | cascade delete |
| name | varchar(100) | |
| html_content | text | |
| is_default | boolean | default false |
| created_at/updated_at | | |

Constraint: one `is_default=true` per user enforced in application service (partial unique index optional: `unique (user_id) where is_default`).

### `drafts`
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| uuid | uuid | |
| user_id | bigint FK → users.id | cascade delete |
| template_id | bigint FK → templates.id | nullable, set null on delete |
| subject | varchar(998) | RFC 5322 max |
| html_body | text | current working copy |
| signature_id | bigint FK → signatures.id | nullable, set null |
| status | varchar(20) | `draft`,`ready_to_send` — CHECK |
| created_at/updated_at/deleted_at | | soft delete |

### `draft_versions`
| id | draft_id FK cascade | version_number int | html_body text | subject varchar | created_by bigint FK users | created_at |

Index: (draft_id, version_number) unique.

### `attachments`
Polymorphic — attachable to drafts, templates, messages.
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| uuid | uuid | |
| attachable_type | varchar(100) | polymorphic |
| attachable_id | bigint | polymorphic |
| disk | varchar(50) | e.g. `s3` |
| path | varchar(500) | |
| original_filename | varchar(255) | |
| mime_type | varchar(150) | |
| size_bytes | bigint | |
| created_at | | |

Index: (attachable_type, attachable_id).

## 4.4 Templates Module

### `templates`
| id | uuid | name varchar(150) | category varchar(50) nullable | thumbnail_path varchar(500) nullable | html_content text | is_archived boolean default false | created_by bigint FK users set null | created_at/updated_at/deleted_at |

### `template_versions`
| id | template_id FK cascade | version_number int | html_content text | created_by FK users | change_note varchar(255) nullable | created_at |

### `template_blocks`
Reusable drag-and-drop block library, independent of any single template.
| id | uuid | name varchar(150) | block_type varchar(50) (`header`,`footer`,`hero`,`text`,`button`,`image`,`social`,`divider`,`custom`) | html_content text | created_by FK users | created_at/updated_at |

## 4.5 Recipients Module

### `recipients`
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| uuid | uuid | |
| email | citext | unique (case-insensitive) |
| first_name | varchar(100) | nullable |
| last_name | varchar(100) | nullable |
| company_name | varchar(255) | nullable, denormalized |
| pagejaunes_company_cache_id | bigint FK → pagejaunes_company_cache.id | nullable, set null |
| source | varchar(30) | `manual`,`csv_import`,`excel_import`,`pagejaunes`,`internal_contact` — CHECK |
| status | varchar(20) | `active`,`suppressed`,`invalid` — CHECK, denormalized from suppression/verification for fast filtering |
| custom_fields | jsonb | arbitrary merge-variable data |
| last_contacted_at | timestamptz | nullable, denormalized from messages |
| created_at/updated_at/deleted_at | | soft delete |

Indexes: unique(email), gin(custom_fields), index(status), index(pagejaunes_company_cache_id).

### `tags`
`id`, `name varchar(50) unique`, `color varchar(20)`.

### `recipient_tag` (pivot)
`recipient_id FK cascade`, `tag_id FK cascade`, composite PK.

### `recipient_lists`
| id | uuid | name varchar(150) | description text nullable | type varchar(20) (`static`,`dynamic_segment`) | created_by FK users | created_at/updated_at/deleted_at |

### `recipient_list_recipient` (pivot, static lists only)
`recipient_list_id FK cascade`, `recipient_id FK cascade`, `added_at timestamptz`, composite PK.

### `segments`
Dynamic recipient lists driven by rules.
| id | uuid | recipient_list_id FK cascade (links a `dynamic_segment` list to its rule set, 1:1) | rules jsonb (rule tree: field, operator, value, AND/OR grouping) | last_evaluated_at timestamptz nullable | cached_count int nullable | created_at/updated_at |

## 4.6 Importing Module

### `import_jobs`
| id | uuid | user_id FK users | source_type varchar(20) (`csv`,`excel`,`pagejaunes`) | file_path varchar(500) nullable | original_filename varchar(255) nullable | status varchar(20) (`pending`,`validating`,`processing`,`completed`,`completed_with_errors`,`failed`) | total_rows int default 0 | processed_rows int default 0 | imported_count int default 0 | duplicate_count int default 0 | error_count int default 0 | column_mapping jsonb nullable | target_recipient_list_id bigint FK recipient_lists nullable | started_at/completed_at timestamptz nullable | created_at/updated_at |

### `import_rows`
Staging table for row-level review before commit (esp. useful for CSV/Excel preview + validation step).
| id | import_job_id FK cascade | row_number int | raw_data jsonb | parsed_email citext nullable | validation_status varchar(20) (`valid`,`invalid`,`duplicate`) | created_recipient_id bigint FK recipients nullable |

### `import_errors`
| id | import_job_id FK cascade | row_number int nullable | error_code varchar(50) | message text | created_at |

## 4.7 PageJaunes Integration Module

> The external system is a real, already-existing **MariaDB/MySQL** database (`pjnewdb`), not Postgres — see [06-pagejaunes-integration.md](06-pagejaunes-integration.md) for its actual schema (`companies`, `companies_details`, `emails`, plus lookup tables `legal_forms`, `countries`, `wilayas`, `localities`, `publications`, `states`, `legal_regimes`, `types`, `clients`). The tables below are **our own PostgreSQL cache**, mirroring only the fields we need, connected via a separate read-only `mysql` connection. Field mapping (source column → cache column) is documented in [06-pagejaunes-integration.md §6.3](06-pagejaunes-integration.md#63-source-to-cache-field-mapping).

### `pagejaunes_company_cache`
One row per external `companies` record (1:1), keyed by the source's actual bigint `id` (not a synthetic UUID, since the source already guarantees a stable numeric identity).
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| pagejaunes_company_id | bigint | unique — mirrors source `companies.id` |
| pagejaunes_code | varchar(20) | unique — mirrors source `companies.code` (source's own business key, more stable across environments than `id`) |
| company_name | varchar(191) | mirrors `company_name` |
| abv_company_name | varchar(191) | nullable, mirrors `abv_company_name` |
| slug | varchar(191) | nullable |
| legal_form_label | varchar(150) | nullable, resolved from source `legal_forms` at sync time (label, not FK — we don't mirror the lookup tables) |
| country_label | varchar(150) | nullable, resolved from source `countries` |
| wilaya_label | varchar(150) | nullable, resolved from source `wilayas` (Algerian province/region) |
| locality_label | varchar(150) | nullable, resolved from source `localities` |
| address | text | nullable, mirrors `Address` |
| about | text | nullable |
| is_distributor / is_exporter / is_importer / is_producer / is_customer | boolean | mirror `distributor`/`exportation`/`importation`/`producer`/`customer` tinyint flags |
| nb_employee | varchar(20) | nullable, mirrors `companies_details.nb_employee` |
| website | varchar(191) | nullable, mirrors `companies_details` social/site fields if present |
| main_picture_url | varchar(255) | nullable |
| source_created_at | timestamptz | nullable, mirrors source `companies.created_at` |
| source_deleted_at | timestamptz | nullable — mirrors source soft-delete; a non-null value excludes the row from search/import (see 6.5) |
| raw_source_data | jsonb | full joined snapshot (`companies` + `companies_details`) for traceability/debugging |
| synced_at | timestamptz | last sync timestamp |
| created_at/updated_at | | our own cache row timestamps |

Indexes: unique(pagejaunes_company_id), unique(pagejaunes_code), index(wilaya_label), gin/full-text index on `company_name`/`abv_company_name`/`address`/`about` (mirroring the source's own `companies_search_ft` fulltext index).

### `pagejaunes_company_emails_cache`
The source models emails as a **one-to-many child table** (`emails`, one company can have several addresses), so our cache mirrors that shape rather than flattening to a single `email` column.
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| pagejaunes_company_cache_id | bigint FK → pagejaunes_company_cache.id | cascade delete |
| pagejaunes_email_id | bigint | mirrors source `emails.id`, unique |
| email | citext | mirrors `emails.mail` |
| source_deleted_at | timestamptz | nullable, mirrors source soft-delete |
| synced_at | timestamptz | |

Indexes: unique(pagejaunes_email_id), index(pagejaunes_company_cache_id), index(email).

## 4.8 Campaigns Module

### `campaigns`
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| uuid | uuid | |
| name | varchar(200) | |
| template_id | bigint FK templates | nullable, set null |
| subject | varchar(998) | |
| html_body | text | snapshot at send time (copied from template/draft so later template edits don't affect a running campaign) |
| recipient_list_id | bigint FK recipient_lists | restrict delete |
| smtp_account_strategy | varchar(20) | `auto_rotate`,`single_account` — CHECK |
| single_smtp_account_id | bigint FK smtp_accounts | nullable |
| status | varchar(20) | `draft`,`scheduled`,`running`,`paused`,`completed`,`cancelled` — CHECK |
| send_mode | varchar(20) | `immediate`,`scheduled`,`recurring` — CHECK |
| scheduled_at | timestamptz | nullable |
| business_hours_only | boolean | default false |
| business_hours_start | time | nullable |
| business_hours_end | time | nullable |
| business_days | jsonb | array of ISO weekday ints, nullable |
| recurrence_rule | varchar(255) | nullable, RFC 5545 RRULE string |
| cloned_from_campaign_id | bigint FK campaigns | nullable, set null |
| created_by | bigint FK users | set null |
| approved_by | bigint FK users | nullable, set null — "review before sending" gate |
| started_at / completed_at | timestamptz | nullable |
| created_at/updated_at/deleted_at | | soft delete |

### `campaign_schedules`
Supports recurring campaign occurrences without duplicating the campaign row.
| id | campaign_id FK cascade | occurrence_at timestamptz | status varchar(20) (`pending`,`dispatched`,`skipped`,`cancelled`) | dispatched_at timestamptz nullable | created_at |

### `campaign_recipients`
Snapshot of resolved recipients for a given send (materializes the segment/list at dispatch time for auditability).
| id | campaign_id FK cascade | recipient_id FK recipients restrict | message_id bigint FK messages nullable, set null | added_at timestamptz |

Unique (campaign_id, recipient_id).

## 4.9 Delivery Engine Module

### `smtp_accounts`
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| uuid | uuid | |
| name | varchar(150) | display label |
| provider | varchar(50) | e.g. `custom`,`sendgrid`,`mailgun`,`ses`,`postmark` — free text/CHECK against configurable provider list, not hardcoded limits |
| host | varchar(255) | |
| port | int | |
| encryption | varchar(10) | `none`,`ssl`,`tls` — CHECK |
| username | varchar(255) | |
| password_encrypted | text | Laravel encrypted cast |
| from_email | citext | |
| from_name | varchar(150) | nullable |
| daily_quota | int | nullable = unlimited |
| hourly_quota | int | nullable |
| minute_quota | int | nullable |
| max_concurrent_connections | int | default 1 |
| max_messages_per_connection | int | nullable |
| priority | int | default 100, lower = higher priority |
| rotation_weight | int | default 1 |
| warmup_enabled | boolean | default false |
| warmup_schedule_id | bigint FK warmup_schedules | nullable |
| health_status | varchar(20) | `healthy`,`degraded`,`unhealthy`,`disabled` — CHECK, denormalized |
| bounce_rate | numeric(5,2) | denormalized rolling metric |
| complaint_rate | numeric(5,2) | denormalized rolling metric |
| reputation_score | numeric(5,2) | nullable, computed |
| is_active | boolean | default true |
| last_tested_at | timestamptz | nullable |
| created_at/updated_at/deleted_at | | soft delete |

### `warmup_schedules`
| id | uuid | name varchar(150) | stages jsonb (array of `{day: int, max_per_day: int}`) | created_at/updated_at |

### `quota_ledger`
Rolling counters persisted for audit/reporting (real-time enforcement uses Redis; this table is the durable record reconciled periodically — see [19-throttling.md](19-throttling.md)).
| id | smtp_account_id FK cascade | window_type varchar(10) (`minute`,`hour`,`day`) | window_start timestamptz | sent_count int default 0 | created_at/updated_at |

Unique (smtp_account_id, window_type, window_start).

### `send_attempts`
Every attempt to send a message via a specific SMTP account (supports retry/failover history).
| id | message_id FK messages cascade | smtp_account_id FK smtp_accounts restrict | attempt_number int | status varchar(20) (`success`,`failed`,`connection_error`) | provider_response text nullable | error_code varchar(100) nullable | attempted_at timestamptz | duration_ms int nullable |

## 4.10 Tracking Module

### `messages`
The canonical record of every individual email sent (transactional or campaign).
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| uuid | uuid | used as external Message-ID correlation |
| campaign_id | bigint FK campaigns | nullable, set null (null = transactional) |
| draft_id | bigint FK drafts | nullable, set null |
| recipient_id | bigint FK recipients | restrict delete |
| smtp_account_id | bigint FK smtp_accounts | nullable, set null — last/successful account used |
| subject | varchar(998) | |
| html_body | text | fully rendered (merge vars resolved) snapshot |
| status | varchar(20) | `queued`,`sending`,`accepted`,`delivered`,`opened`,`clicked`,`soft_bounced`,`hard_bounced`,`failed`,`rejected`,`spam_complaint`,`unsubscribed` — CHECK |
| queued_at / sent_at / delivered_at / opened_at / clicked_at / bounced_at / failed_at | timestamptz | nullable, each set once first reached |
| provider_message_id | varchar(255) | nullable, from SMTP provider |
| last_provider_response | text | nullable |
| open_count | int | default 0 |
| click_count | int | default 0 |
| created_at/updated_at | | |

Indexes: index(status), index(campaign_id), index(recipient_id), index(provider_message_id).

### `message_events`
Append-only event log backing the status column above (full audit of every transition, including repeated opens/clicks).
| id | message_id FK cascade | event_type varchar(20) (mirrors message status values plus `provider_webhook_raw`) | occurred_at timestamptz | metadata jsonb (ip, user_agent, link_id, provider payload) | created_at |

Index: (message_id, occurred_at).

### `click_links`
Registry of trackable links extracted from a message/template at send time, so click redirects can resolve to the true destination.
| id | message_id FK cascade | original_url text | tracking_token varchar(64) unique | click_count int default 0 | created_at |

## 4.11 Verification Module

### `verification_results`
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| email | citext | |
| syntax_valid | boolean | |
| mx_valid | boolean | nullable |
| is_disposable | boolean | nullable |
| is_role_based | boolean | nullable (e.g. `info@`, `admin@`) |
| is_catch_all | boolean | nullable |
| verdict | varchar(20) | `deliverable`,`risky`,`undeliverable`,`unknown` — CHECK |
| provider | varchar(50) | verification provider used |
| raw_response | jsonb | nullable |
| checked_at | timestamptz | |
| expires_at | timestamptz | cache expiry, e.g. checked_at + 90 days |

Index: (email, checked_at desc) — latest result per email is `expires_at > now()` lookup, forms the verification cache.

## 4.12 Suppression Module

### `suppression_entries`
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| email | citext | unique |
| reason | varchar(30) | `hard_bounce`,`spam_complaint`,`manual_unsubscribe`,`global_unsubscribe`,`invalid_address`,`manual_block` — CHECK |
| source_message_id | bigint FK messages | nullable, set null |
| notes | text | nullable |
| added_by | bigint FK users | nullable, set null (null = system-added) |
| created_at | | |

## 4.13 Reporting Module

Reporting relies primarily on **materialized views** over `messages`, `message_events`, `campaigns`, and `smtp_accounts` rather than a bespoke mutable table, refreshed on schedule (`REFRESH MATERIALIZED VIEW CONCURRENTLY`):

- `mv_campaign_daily_stats` (campaign_id, day, sent, delivered, opened, clicked, bounced, complained)
- `mv_smtp_account_daily_stats` (smtp_account_id, day, sent, delivered, bounced, complaint_rate)
- `mv_template_performance` (template_id, sent, open_rate, click_rate)

### `report_snapshots`
Optional durable point-in-time export record for on-demand generated reports (PDF/CSV exports of the above).
| id | uuid | report_type varchar(50) | parameters jsonb | file_path varchar(500) | generated_by FK users | generated_at | expires_at |

## 4.14 Audit Module

### `audit_logs`
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| user_id | bigint FK users | nullable, set null (null = system action) |
| action | varchar(100) | e.g. `smtp_account.updated`, `campaign.sent`, `recipient.imported` |
| auditable_type | varchar(100) | polymorphic subject |
| auditable_id | bigint | polymorphic subject |
| old_values | jsonb | nullable |
| new_values | jsonb | nullable |
| ip_address | inet | |
| user_agent | text | nullable |
| created_at | | append-only, no updated_at |

Indexes: (auditable_type, auditable_id), (user_id, created_at), (action).

This table is **append-only**; no application code updates or deletes rows (enforced via DB role permissions in production, see [28-security.md](28-security.md)).

## 4.15 Settings Module

### `settings`
Key-value system configuration (throttle defaults, tracking domain, verification provider config, branding).
| id | key varchar(150) unique | value jsonb | value_type varchar(20) (`string`,`int`,`bool`,`json`) | is_secret boolean default false | updated_by FK users nullable | updated_at |

Secrets (`is_secret=true`) store an encrypted value; never returned in plaintext via API — see [28-security.md](28-security.md).

## 4.16 Cross-Cutting Cascade Rules Summary

| Parent deleted | Child behavior |
|---|---|
| `users` | Soft-deleted only; never hard-deleted while referenced. Ownership FKs (`created_by`, etc.) set null. |
| `recipients` | Restrict delete if referenced by `messages` or `campaign_recipients` (must be suppressed/archived, not deleted, once contacted). |
| `smtp_accounts` | Restrict delete if referenced by `send_attempts`; soft-delete/deactivate instead. |
| `templates` | Set null on `drafts.template_id`/`campaigns.template_id` (campaigns keep their own `html_body` snapshot so this is non-destructive). |
| `campaigns` | Cascade delete `campaign_recipients`, `campaign_schedules`; `messages.campaign_id` set null (messages persist for history). |
| `recipient_lists` | Restrict delete if referenced by an active/scheduled `campaigns` row. |

Continue to [05-erd.md](05-erd.md) for the visual entity-relationship diagram.
