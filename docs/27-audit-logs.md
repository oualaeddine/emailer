# 27 — Audit Logging

## 27.1 Purpose

`audit_logs` (see [04-database-design.md §4.14](04-database-design.md#414-audit-module)) provides a complete, append-only trail of sensitive actions for compliance, incident investigation, and accountability. It is a cross-cutting concern consumed by (and writing from) nearly every module.

## 27.2 Audit Event Catalog

| Category | Actions Logged |
|---|---|
| Authentication | `auth.login_succeeded`, `auth.login_failed`, `auth.logout`, `auth.password_changed` |
| Campaign actions | `campaign.created`, `campaign.updated`, `campaign.sent`, `campaign.paused`, `campaign.resumed`, `campaign.cancelled`, `campaign.approved` |
| SMTP changes | `smtp_account.created`, `smtp_account.updated` (credentials redacted, see 27.4), `smtp_account.deactivated`, `smtp_account.deleted`, `smtp_account.tested` |
| Imports | `import.started`, `import.committed`, `import.failed` |
| Exports | `export.recipients`, `export.report`, `export.audit_log` |
| Template changes | `template.created`, `template.updated`, `template.archived`, `template.deleted` |
| Email sends | `message.sent` (transactional), aggregated at campaign level for bulk (individual campaign messages are not each an audit row — that volume belongs in `message_events`, not `audit_logs`, per 27.6) |
| Settings changes | `settings.updated` (per key) |
| Permission changes | `user.role_changed`, `role.permission_granted`, `role.permission_revoked`, `user.created`, `user.deactivated` |
| Suppression | `suppression.added_manual`, `suppression.removed` |

## 27.3 Captured Fields

Per [04-database-design.md §4.14](04-database-design.md#414-audit-module): `user_id` (null = system-initiated, e.g. an automated bounce-triggered suppression), `action`, polymorphic `auditable_type`/`auditable_id`, `old_values`/`new_values` (jsonb diffs — only changed fields, not full row dumps, to keep entries compact and readable), `ip_address`, `user_agent`, `created_at`.

## 27.4 Redaction Rules

Sensitive fields are never written in plaintext to `old_values`/`new_values`:
- `smtp_accounts.password_encrypted` → recorded as `"[REDACTED]"` regardless of old/new value.
- Any `settings` row with `is_secret = true` → value redacted the same way.
- `users.password` → never included in any audit diff (password changes log the *event*, not the value).

## 27.5 Implementation Pattern

A single `AuditLogger` service is invoked from **Domain Event listeners** (not scattered manual calls throughout controllers/services) — e.g. `CampaignSent` event → `AuditListener::handle()` → `AuditLogger::record('campaign.sent', $campaign, ...)`. This keeps audit coverage consistent and centrally reviewable (a single listener class per module documents exactly what is/isn't audited) rather than relying on developers remembering to call an audit function inline everywhere. See [31-events.md](31-events.md) for the full event→listener map, which doubles as the audit coverage checklist.

## 27.6 What Is NOT in `audit_logs`

High-volume, non-sensitive operational data lives in its own domain table rather than bloating the audit trail: individual message delivery status changes → `message_events` ([20-delivery-tracking.md](20-delivery-tracking.md)); SMTP send attempts → `send_attempts`; email verification checks → `verification_results`. `audit_logs` is reserved for discrete, human-attributable, security/compliance-relevant actions.

## 27.7 Audit Log Viewer UI

Administration → Audit Logs: filterable grid (by user, action category, date range, affected entity type) with a detail flyout per row rendering the `old_values`/`new_values` diff in a readable before/after format (not raw JSON dump — key-by-key comparison rows). Full-text search across `action` and the affected entity's display name.

## 27.8 Retention & Immutability

`audit_logs` is append-only at the database role level (application's DB user is granted `INSERT`/`SELECT` only on this table in production, not `UPDATE`/`DELETE` — see [28-security.md](28-security.md)). Retained indefinitely; no automated pruning job touches this table (unlike imports/exports which have retention windows, per [02-system-architecture.md §2.12](02-system-architecture.md#212-non-functional-requirements)).

## 27.9 Permissions

View/export restricted to Administrator only ([26-rbac.md §26.3](26-rbac.md#263-full-permission-matrix)); exporting the audit log is itself an audited action (`export.audit_log`), preventing silent exfiltration of the compliance trail itself.

Continue to [28-security.md](28-security.md).
