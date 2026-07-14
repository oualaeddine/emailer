# 18 — SMTP Management

## 18.1 Overview

Administrators manage an unlimited number of SMTP accounts (`smtp_accounts`, see [04-database-design.md §4.9](04-database-design.md#49-delivery-engine-module)). This document covers the management UI/workflow; enforcement mechanics live in [19-throttling.md](19-throttling.md) and [17-delivery-engine.md](17-delivery-engine.md).

## 18.2 SMTP Account Form Fields

Provider (free-text/select-with-custom, e.g. `sendgrid`, `mailgun`, `ses`, `postmark`, `custom` — the platform never hardcodes provider-specific limits, see 18.3), Host, Port, Encryption (`none`/`ssl`/`tls`), Username, Password (write-only field, stored encrypted, never redisplayed — edit requires re-entry or explicit "keep current" toggle), From Email, From Name, Daily/Hourly/Minute Quota (nullable = unlimited, with a confirmation warning if left unlimited), Max Concurrent Connections, Max Messages per Connection, Priority, Rotation Weight, Warm-up toggle + schedule selection.

## 18.3 Provider Defaults (Configurable, Not Hardcoded)

A `provider_defaults` settings entry (Settings → SMTP → Provider Presets, stored in the `settings` table as a `jsonb` value, e.g. key `smtp.provider_presets`) holds a admin-editable map of suggested defaults per provider name (typical daily quota, typical max connections) purely as **form pre-fill suggestions** when a user selects a provider — never enforced as hard limits in code. This satisfies "provider defaults must be configurable / do not hardcode provider limits": the actual enforced limits always come from the specific `smtp_accounts` row the administrator saved, which may differ from the preset.

## 18.4 SMTP Testing

"Send Test Email" action on an account: sends a real single message via that account to an address the requesting user provides (defaults to their own), bypassing quota/rotation (test sends are logged to `send_attempts` with a flag/metadata `is_test = true` and do not count toward quota ledgers). Displays raw provider response (connection success, auth result, any SMTP error) directly in the UI for diagnostics. Also runs a lightweight connection-only check ("Verify Connection", no email sent) using an SMTP `NOOP`/`EHLO` handshake.

## 18.5 Health Monitoring

`health_status` (`healthy`/`degraded`/`unhealthy`/`disabled`) transitions are governed by the shared `WorkflowEngine` as `SmtpHealthWorkflow` ([37-workflow-engine.md](37-workflow-engine.md)); `ProbeSmtpHealthJob` supplies the triggering signal, the engine enforces which transitions are legal and fires the resulting event. It is computed by a scheduled job (`ProbeSmtpHealthJob`, every 5 minutes, see [02-system-architecture.md §2.9](02-system-architecture.md#29-scheduling-strategy)) combining:
- Recent connection test result.
- Rolling `bounce_rate`/`complaint_rate` (from `mv_smtp_account_daily_stats`) against configurable thresholds (Settings, e.g. degrade at >5% bounce or >0.1% complaint, mark unhealthy at >10%/>0.5%).
- Consecutive `send_attempts` failures within a short window (Failover Engine signal, [17-delivery-engine.md §17.8](17-delivery-engine.md#178-failover-engine)).

Health transitions raise domain events (`SmtpAccountHealthChanged`, see [31-events.md](31-events.md)) driving an admin Notification when an account degrades/becomes unhealthy.

## 18.6 SMTP Accounts List View

Fluent `DataGrid` with columns: Name, Provider, Health (badge), Today's Usage (progress bar vs. daily quota), Priority, Weight, Warm-up stage (if enabled), Actions. Row click → Detail page with tabs: Overview (config), Usage (quota charts, [19-throttling.md](19-throttling.md)), Health History (health_status change log via audit), Send Attempts (recent attempts/failures for this account).

## 18.7 Rotation & Priority Configuration

Priority/weight fields are edited directly on the account form; a dedicated "Rotation Preview" panel simulates the weighted distribution given current priorities/weights across active healthy accounts (pure client-side calculation for admin understanding, no backend call needed).

## 18.8 Deactivation & Deletion

"Deactivate" (`is_active = false`) immediately removes the account from rotation candidates without deleting history; reversible. Hard delete blocked if `send_attempts` reference the account (per cascade rule in [04-database-design.md §4.16](04-database-design.md#416-cross-cutting-cascade-rules-summary)) — the UI surfaces "Deactivate instead" when delete is attempted on a used account.

## 18.9 Permissions

Full SMTP management restricted to Administrator. Marketing Manager/Operator may view health status (read-only) to understand send capacity but cannot edit credentials or quotas — see [26-rbac.md](26-rbac.md). All SMTP account changes (create/update/delete/deactivate, especially credential changes) are audited per [27-audit-logs.md](27-audit-logs.md), with `password_encrypted` value itself never written into `audit_logs.old_values`/`new_values` (redacted as `"[REDACTED]"`).

Continue to [19-throttling.md](19-throttling.md).
