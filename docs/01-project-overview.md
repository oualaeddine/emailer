# 01 — Project Overview

## 1.1 Vision

PageJaunes Mailer is an internal, single-tenant enterprise platform that lets one organization plan, send, track, and analyze both transactional and bulk marketing email at scale, while directly leveraging the organization's PageJaunes company directory as a recipient source. It combines the day-to-day usability of Microsoft Outlook with the deliverability engineering of a dedicated Email Service Provider (ESP).

## 1.2 Problem Statement

The organization currently lacks a unified tool to:
- Compose and send one-off or templated transactional emails to companies/contacts sourced from the PageJaunes database.
- Run newsletter/marketing campaigns against segmented recipient lists without exceeding SMTP provider sending limits.
- Manage multiple SMTP relays (rotation, quotas, health, failover) instead of relying on a single provider.
- Track deliverability (bounces, complaints, opens, clicks) and maintain suppression lists to protect sender reputation.
- Retain a full communication history per company/contact for account management purposes.
- Give administrators governance: audit trails, RBAC, and reporting.

## 1.3 Product Pillars

1. **Outlook-familiar UX** — inbox/sent/drafts/outbox mental model, keyboard shortcuts, reading pane, conversation-like history.
2. **Deliverability engineering** — SMTP rotation, quota enforcement, warm-up, retry/failover, suppression.
3. **Campaign-grade marketing** — segmentation, scheduling, recurring sends, business-hour throttling, analytics.
4. **Directory-native** — first-class, read-only integration with the external PageJaunes company database as a recipient source.
5. **Governance** — RBAC, audit logging, and reporting suitable for enterprise compliance needs.

## 1.4 Out of Scope

- Multi-tenancy / multi-organization support.
- Inbound email receiving/parsing as a mailbox (IMAP/POP retrieval of replies) — only outbound sending and webhook-based delivery events are in scope. Reply-to addresses may be configured but reply ingestion is not part of v1.
- Native mobile apps (responsive web only).
- Building a general-purpose CRM (communication timeline is a read view, not a full CRM).

## 1.5 Primary Personas

| Persona | Role | Goals |
|---|---|---|
| **Administrator** | IT/Ops owner | Configure SMTP accounts, manage users/roles, monitor queues, view audit logs, ensure deliverability health. |
| **Marketing Manager** | Campaign owner | Create/schedule campaigns, build segments, review analytics, manage templates. |
| **Marketing Operator** | Day-to-day sender | Compose transactional emails, import recipients, execute approved campaigns. |
| **Viewer** | Stakeholder/analyst | Read-only access to dashboards, reports, and email history. |

Full permission matrix: [26-rbac.md](26-rbac.md).

## 1.6 Success Metrics

- SMTP provider suspension incidents: 0 (enforced via throttling engine).
- Bounce rate maintained below configurable threshold (default 2%) via suppression + verification.
- Campaign send-to-completion time predictable within queue ETA estimates (±10%).
- 100% of sensitive actions (SMTP credential changes, permission changes, exports) captured in audit log.

## 1.7 Glossary

| Term | Definition |
|---|---|
| **Campaign** | A scheduled or immediate bulk send of one email/template to a recipient list or segment. |
| **Recipient List** | A named, reusable collection of recipients (static or dynamic/segment-based). |
| **Segment** | A filtered, dynamically-evaluated subset of recipients based on rules (tags, engagement, source). |
| **SMTP Account** | A configured outbound relay credential set with its own quotas and health status. |
| **Rotation** | The strategy for distributing outbound messages across multiple SMTP accounts. |
| **Warm-up** | A gradual ramp-up schedule of sending volume for a new/cold SMTP account or IP. |
| **Suppression List** | Addresses that must never receive mail (bounced, complained, unsubscribed, manually blocked). |
| **Outbox** | Emails accepted for sending but not yet dispatched (queued, waiting on quota/SMTP/schedule). |
| **Delivery Tracker** | Subsystem recording the lifecycle status of each sent message. |
| **PageJaunes Company** | A company record sourced from the external, read-only PageJaunes database. |
| **Merge Variable** | A placeholder (e.g. `{{company.name}}`) resolved per-recipient at send time. |

## 1.8 Language

The application's user interface is **exclusively in French** — no multi-language support, no locale switcher. This is a firm product requirement, detailed with the full identifier→label mapping in [07-ui-design.md §7.12](07-ui-design.md#712-langue--français-uniquement). This documentation set itself remains in English (as authored), but everything a user of the built application sees must be French.

## 1.9 Document Relationships

This document defines *why* and *for whom*. [02-system-architecture.md](02-system-architecture.md) defines *how* the system is structured to deliver it. [04-database-design.md](04-database-design.md) defines the data model referenced by every feature document.
