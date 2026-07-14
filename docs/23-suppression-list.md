# 23 — Suppression List

## 23.1 Purpose

The suppression list (`suppression_entries`, see [04-database-design.md §4.12](04-database-design.md#412-suppression-module)) is the single authoritative gate consulted before **every** send attempt (transactional or campaign) — no message is ever sent to a suppressed address, full stop. This is the platform's core deliverability and compliance safeguard.

## 23.2 Automatic Suppression Triggers

| Trigger | Listener | Reason recorded |
|---|---|---|
| Hard bounce (`messages.status = hard_bounced`) | `MessageHardBounced` event listener | `hard_bounce` |
| Spam complaint (feedback loop webhook) | `MessageSpamComplaint` event listener | `spam_complaint` |
| Recipient clicks unsubscribe link | `RecipientUnsubscribed` event listener | `manual_unsubscribe` |
| Global unsubscribe (recipient explicitly opts out of **all** future mail, vs. a single list) | Unsubscribe page "unsubscribe from everything" option | `global_unsubscribe` |
| Verification returns `undeliverable` | Optional policy (Settings toggle) — not automatic by default since verification-undeliverable and hard-bounce-undeliverable are different confidence levels | `invalid_address` |
| Manual admin action | Administration → Suppression List → Add | `manual_block` |

All automatic additions go through `SuppressionManager::suppress(email, reason, sourceMessageId)`, which upserts `suppression_entries` (unique on `email` — a re-triggered suppression for an already-suppressed address is a no-op) and also sets `recipients.status = suppressed` for any matching recipient record.

## 23.3 Suppression Check (Pre-Send Gate)

Every send path (campaign dispatch pre-filter, individual `SendEmailJob` execution, transactional composer send) calls `SuppressionManager::isSuppressed(email): bool` before proceeding. A hit short-circuits the send: the `messages` row (if already created) is set to `status = rejected` with reason metadata, no SMTP attempt is made, no quota is consumed. This check is deliberately duplicated at both dispatch-time (bulk, efficient) and per-job execution-time (defense in depth, per [17-delivery-engine.md §17.10](17-delivery-engine.md#1710-idempotency-safety)).

## 23.4 Unsubscribe Workflow

1. Every campaign send includes an unsubscribe link/footer (policy-enforced unless the send is purely transactional and Settings excludes transactional from the requirement — CAN-SPAM/GDPR-style compliance is configurable but defaults to "always include for campaigns").
2. Link target is a public, unauthenticated confirmation page (`/unsubscribe/{token}`) showing which list(s)/organization the recipient is unsubscribing from, with a single-click confirm (no login required) and an optional "unsubscribe from everything" checkbox for global suppression.
3. Confirmation triggers `RecipientUnsubscribed` → suppression entry created with reason `manual_unsubscribe` (or `global_unsubscribe` if the "everything" option was chosen).
4. A one-click **List-Unsubscribe** header (`List-Unsubscribe`, `List-Unsubscribe-Post`) is also included for mailbox providers (Gmail/Outlook) that support header-based one-click unsubscribe, hitting the same endpoint.

## 23.5 Suppression List Management UI

Administration → Suppression List: searchable/filterable grid (by reason, date added, source campaign) with actions: **Remove from suppression** (manual override, requires Administrator permission and a confirmation dialog explaining the deliverability risk, itself audited), **Bulk import** (upload a list of addresses to pre-suppress, e.g. addresses known-bad from another system), **Export** (CSV, for compliance reporting).

## 23.6 Interaction with Segments/Lists

Per [13-recipient-management.md §13.7](13-recipient-management.md#137-lists-vs-segments), segment evaluation always implicitly excludes `status = suppressed` recipients regardless of the segment's own rules — this is a non-overridable safety rule, not a configurable option, since allowing a rule to re-include suppressed recipients would defeat the purpose of the suppression list entirely.

## 23.7 Reason-Specific Nuance

- **Soft bounces do not suppress** — they are retried per [19-throttling.md §19.7](19-throttling.md#197-retry-policies); only repeated/hard bounces do.
- **Manual block** is the only reason with a free-text `notes` field encouraged (e.g. "requested removal via support ticket #1234") for audit clarity.
- Suppression by reason `invalid_address` (from verification) is treated as reversible/lower-confidence — Administrators can more freely remove these than a `hard_bounce`/`spam_complaint` entry, though the UI does not technically restrict removal by reason (permission-gated the same way for all reasons, with a stronger confirmation copy for complaint/bounce-based entries).

Continue to [24-queue-management.md](24-queue-management.md).
