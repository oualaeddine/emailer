# 16 — Email History & Communication Timeline

## 16.1 Email History (Global Log)

A searchable, filterable log over the entire `messages` table (superset of the "Sent Items" mailbox folder, see [10-mailbox.md](10-mailbox.md), but including transactional **and** campaign messages together with richer filters intended for reporting/lookup use cases rather than day-to-day triage).

Filters: date range, campaign, template, SMTP account, status, recipient (search by email/name/company). Columns configurable; default: Recipient, Subject, Campaign, Status, Sent At, Opens, Clicks.

Row click opens a **Message Detail** view: full rendered content, complete `message_events` timeline (every status transition with timestamp and, where applicable, IP/user-agent/link metadata from tracking events), and `send_attempts` history (which SMTP account(s) were tried, in what order, with what result — critical for diagnosing delivery problems).

## 16.2 Communication Timeline (Per Recipient/Company)

Every Recipient profile ([13-recipient-management.md §13.2](13-recipient-management.md#132-recipient-profile)) includes a unified timeline aggregating:

| Timeline Entry | Source |
|---|---|
| Emails sent (transactional + campaign) | `messages` where `recipient_id = X` |
| Opens / Clicks | `message_events` joined through those messages |
| Attachments sent | `attachments` joined through `drafts`/`messages` |
| Campaign participation | `campaign_recipients` |
| Tags applied/removed | derived from `recipient_tag` history (requires an audit-backed change log — sourced from `audit_logs` where `auditable_type = Recipient` and `action` in tag-related actions) |
| Notes | a lightweight `notes` free-text field on the recipient profile (manually entered by staff; stored as a simple append-only note list — modeled as its own small table, `recipient_notes(id, recipient_id, user_id, body, created_at)`, added as a minor schema extension alongside [04-database-design.md](04-database-design.md) §4.5 for completeness of this feature) |
| Last contact | `recipients.last_contacted_at` (denormalized, updated via listener on `MessageSent` event) |
| Next planned contact | Derived: nearest upcoming `campaign_schedules`/scheduled `messages` targeting this recipient, shown as "Next scheduled contact" |

### `recipient_notes` (schema addendum)

| Column | Type | Notes |
|---|---|---|
| id | bigserial PK | |
| recipient_id | bigint FK recipients | cascade delete |
| user_id | bigint FK users | set null |
| body | text | |
| created_at | timestamptz | |

This table is referenced here rather than in §4.5 because it is purely additive UI-support data for the timeline feature and has no relationships beyond `recipients`/`users`.

## 16.3 Timeline Rendering

Chronological, reverse order (most recent first), grouped by day, using Fluent's timeline/list patterns with icons distinguishing entry type (mail-sent, mail-opened, link-click, tag-changed, note-added, campaign-badge). Each entry deep-links to its source (message detail, campaign detail).

## 16.4 "Next Planned Contact" Computation

Computed on read (not stored) by querying: (a) any `campaign_schedules` in `pending` status whose parent campaign's resolved audience includes this recipient (segment evaluation or static list membership check), and (b) any individually scheduled transactional message targeting this recipient. The nearest future timestamp across both is shown; absent any, "No contact planned."

## 16.5 Permissions

Communication Timeline visibility follows Recipient read permission ([26-rbac.md](26-rbac.md)); notes creation requires `recipients.annotate` permission (Marketing Operator and above).

Continue to [17-delivery-engine.md](17-delivery-engine.md).
