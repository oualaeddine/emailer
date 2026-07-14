# 13 — Recipient Management

## 13.1 Recipient Sources

Every `recipients` row carries a `source` (see [04-database-design.md](04-database-design.md#45-recipients-module)): `manual`, `csv_import`, `excel_import`, `pagejaunes`, `internal_contact`. Source is retained permanently for traceability even though the recipient's data can later be edited by hand — it answers "how did this address enter the system."

- **Internal contacts**: recipients created directly by staff as known business contacts (manual entry through the "Add Recipient" form, functionally the same storage as `manual` but tagged `internal_contact` when explicitly marked as such in the form).
- **Manual entry**: ad hoc single or small-batch entry (composer "To" field "add new recipient" flow, or the Recipients → New form). Supports comma/newline-separated bulk paste of emails with inline per-row validation before commit.
- **CSV / Excel import**: bulk pipeline, see [14-import-export.md](14-import-export.md).
- **PageJaunes**: sourced from the external directory, see [06-pagejaunes-integration.md](06-pagejaunes-integration.md).

## 13.2 Recipient Profile

Each recipient has a detail view showing: contact fields, company info (denormalized `company_name` plus linked `pagejaunes_company_cache` record if applicable), tags, custom fields (arbitrary `jsonb`), status (`active`/`suppressed`/`invalid`), and the full **Communication Timeline** (see [16-email-history.md](16-email-history.md)).

## 13.3 Validation

- Syntax validation on every create/edit (RFC 5322-conformant regex plus `filter_var`-equivalent).
- Deep validation (MX/disposable/role-based/catch-all) is opt-in per import batch or triggered ad hoc from the profile — delegates to the [22-email-verification.md](22-email-verification.md) service, result cached in `verification_results` and reflected in `recipients.status`.

## 13.4 Duplicate Detection

- Hard uniqueness: `recipients.email` unique (case-insensitive). Any create/import path that targets an existing email **updates/links** rather than duplicating (merge behavior: new `custom_fields` keys are merged in, `source` is NOT overwritten unless the record was created via `manual`+no company data and the new source is richer — precise merge policy configurable, default = "first source wins for `source`, richest non-null wins per field").
- Soft duplicate warnings: fuzzy match on name+company for records lacking email during CSV/Excel review (shown in import review grid, does not block, since email uniqueness is the authoritative dedupe key for an email platform).

## 13.5 Tagging

Free-form `tags` (many-to-many via `recipient_tag`) with color coding, used for both organizational labeling ("VIP", "Cold lead") and as segment filter criteria. Tag management (create/rename/recolor/delete-with-reassignment-check) lives under Recipients → Tags.

## 13.6 Search & Filtering

- Full-text-ish search across name/email/company (Postgres `ILIKE`/trigram index for typo tolerance).
- Structured filters: source, status, tag(s), date added range, has-been-contacted (yes/no), last-contacted range, custom field equality/contains (for indexed `jsonb` keys).
- Filters compose as an AND rule set; saved filters can be promoted directly into a dynamic Segment (13.7).

## 13.7 Lists vs. Segments

| Concept | Table | Membership |
|---|---|---|
| **Static Recipient List** | `recipient_lists` (`type=static`) + `recipient_list_recipient` | Explicit, manually curated membership; stable until edited |
| **Dynamic Segment** | `recipient_lists` (`type=dynamic_segment`) + `segments.rules` | Membership re-evaluated at read time (and cached, see below) from a rule tree |

### Segment Rule Structure (`segments.rules` jsonb)

```json
{
  "operator": "AND",
  "conditions": [
    { "field": "tags", "operator": "includes_any", "value": ["vip", "prospect"] },
    { "field": "status", "operator": "equals", "value": "active" },
    { "field": "last_contacted_at", "operator": "before", "value": "-90d" },
    { "operator": "OR", "conditions": [
        { "field": "custom_fields.industry", "operator": "equals", "value": "retail" },
        { "field": "pagejaunes_company_cache.sector", "operator": "equals", "value": "restaurants" }
    ]}
  ]
}
```

Supported fields: any `recipients` column, `tags`, `custom_fields.*`, and joined `pagejaunes_company_cache.*` fields. Supported operators vary by field type (equals/not_equals/contains/includes_any/before/after/is_empty/is_not_empty).

### Segment Evaluation

`SegmentEvaluationService` compiles the rule tree into a query builder expression against `recipients` (joining `pagejaunes_company_cache`/`tags` as needed), always excluding `status = suppressed` implicitly (suppressed recipients are never included in a segment's resolved audience regardless of rules — a hard safety rule, not overridable). Result count and IDs are cached in Redis (`segments.cached_count` mirrors it for quick display) with invalidation on: manual re-evaluate action, or TTL expiry (default 15 min), or relevant recipient data change events (best-effort invalidation; TTL is the guaranteed backstop).

## 13.8 Reusable Lists in Campaigns

A `Campaign.recipient_list_id` may point to either a static list or a dynamic segment; at dispatch time the Delivery Engine always resolves the **current** membership (for dynamic segments) or **fixed** membership (for static lists) into `campaign_recipients` as an immutable snapshot for that specific send (see [04-database-design.md](04-database-design.md#48-campaigns-module) and [15-campaign-management.md](15-campaign-management.md)).

## 13.9 Bulk Actions

From the Recipients grid: bulk tag, bulk add-to-list, bulk export (CSV), bulk delete (soft, blocked if referenced by `messages`/`campaign_recipients` — must suppress instead, consistent with [04-database-design.md §4.16](04-database-design.md#416-cross-cutting-cascade-rules-summary)).

Continue to [14-import-export.md](14-import-export.md).
