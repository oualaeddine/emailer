# 39 — Deliverability Analyzer

## 39.1 Purpose

Today, deliverability is protected **reactively**: throttling ([19-throttling.md](19-throttling.md)) prevents provider-side rate violations, and suppression ([23-suppression-list.md](23-suppression-list.md)) reacts to bounces/complaints after they happen. The Deliverability Analyzer adds a **proactive, pre-send** check that scores a message/campaign before it goes out, catching problems (missing unsubscribe link, broken links, no plain-text part, weak domain authentication) while they're still cheap to fix — before reputation damage occurs.

## 39.2 Where It Runs

Invoked at two points, both non-blocking by default (advisory) unless a specific check is configured as a hard gate:

1. **Campaign Wizard Review step** ([15-campaign-management.md §15.2](15-campaign-management.md#152-campaign-wizard)) — runs against the fully-rendered pipeline output (post [38-rendering-pipeline.md](38-rendering-pipeline.md) stages 1–6, using a sample recipient) and shows a scorecard before the user can proceed to send/approve.
2. **SMTP Account detail page** ([18-smtp-management.md §18.6](18-smtp-management.md#186-smtp-accounts-list-view)) — a standing "Domain Authentication" check independent of any single message, since SPF/DKIM/DMARC are properties of the sending domain, not of an individual send.

## 39.3 Check Catalog

| Check | Scope | Method | Severity |
|---|---|---|---|
| SPF record present & includes the sending host | Domain (per SMTP account's `from_email` domain) | DNS TXT lookup for `v=spf1` | Blocking (configurable) |
| DKIM signing configured | Domain / SMTP account | DNS lookup for the provider's DKIM selector record, cross-checked against `smtp_accounts` provider config | Blocking (configurable) |
| DMARC policy present | Domain | DNS TXT lookup for `_dmarc.{domain}` | Warning |
| Unsubscribe link/header present | Message (campaign only) | Presence check on rendered HTML + `List-Unsubscribe` header, per [23-suppression-list.md §23.4](23-suppression-list.md#234-unsubscribe-workflow) | Blocking (non-configurable for campaigns — this one is a hard rule, matching 23.4's own policy) |
| Plain-text part present | Message | Confirms [38-rendering-pipeline.md §38.4](38-rendering-pipeline.md#384-plain-text-alternative-new-requirement) actually produced non-empty plain text | Warning |
| Spam-trigger heuristics | Message content | Weighted keyword/pattern scan (configurable word list in Settings, e.g. excessive exclamation marks, ALL-CAPS subject, common spam phrases) — advisory only, never a substitute for a real content-reputation service | Warning |
| Broken links | Message | HEAD request to every `click_links.original_url` at send-preview time (queued job, since this is network I/O), flags non-2xx/timeout | Warning |
| Image-to-text ratio | Message | Rendered HTML text-length vs. embedded image count/size heuristic | Warning |
| Accessibility | Message | Checks for `alt` text on all `<img>`, sufficient color contrast in inline-styled text (reuses the contrast rules from [07-ui-design.md §7.8](07-ui-design.md#78-accessibility) applied to authored content, not just app UI) | Warning |
| Recipient verification coverage | Campaign | % of targeted audience with a non-expired `deliverable`/`unknown` verdict ([22-email-verification.md](22-email-verification.md)) | Warning (or Blocking if the pre-send verification gate policy from [22-email-verification.md §22.7](22-email-verification.md#227-workflows) is enabled) |

## 39.4 Scorecard & Gating

Results render as a Fluent `MessageBar`-per-finding list plus an aggregate score (e.g. A–F letter grade, computed as a weighted function of blocking vs. warning findings) in the Campaign Wizard Review step. Administrators configure (Settings → Deliverability) which checks are **blocking** (cannot proceed past Review without resolving or explicitly acknowledging) versus **advisory** (shown, doesn't block) — mirroring the same configurability principle already used for provider defaults and retry policy ([18-smtp-management.md §18.3](18-smtp-management.md#183-provider-defaults-configurable-not-hardcoded)): the platform ships sensible defaults but never hardcodes what "good enough" means for a given organization.

## 39.5 Data Model Addendum

A lightweight, non-authoritative results table (analogous in spirit to `verification_results`, [04-database-design.md §4.11](04-database-design.md#411-verification-module)):

### `deliverability_checks` (schema addendum)
| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| checkable_type / checkable_id | polymorphic | `Campaign` or `SmtpAccount` |
| check_key | varchar(50) | e.g. `spf`, `unsubscribe_link`, `broken_links` |
| status | varchar(20) | `pass`/`warning`/`fail` |
| details | jsonb | e.g. list of broken URLs found |
| checked_at | timestamptz | |

Domain-level checks (SPF/DKIM/DMARC) are cached similarly to verification results (default 24h TTL) since DNS records change rarely; message-level checks (broken links, spam heuristics) are recomputed every time the Review step is opened, since content can change between visits.

## 39.6 Relationship to Existing Modules

This is a **read-only advisory layer** that consults but does not modify the Delivery Engine, Throttling, or Suppression modules — it never blocks sends autonomously beyond the Settings-configured gates in 39.4, and it never itself adds/removes suppression entries (that remains exclusively the Suppression Manager's responsibility, [23-suppression-list.md](23-suppression-list.md)). Its job is to surface risk *before* the Delivery Engine's reactive mechanisms (retry, failover, suppression) would otherwise have to react to it.

Continue to [40-search-service.md](40-search-service.md).
