# PageJaunes Mailer — Architecture & Design Documentation

## Purpose

This documentation set defines the complete enterprise architecture for **PageJaunes Mailer**, an Outlook-inspired email management platform combining transactional email, newsletter campaigns, recipient management, SMTP infrastructure management, and delivery analytics.

This documentation is written so that **another AI or engineering team can implement the entire Laravel + React application without making architectural assumptions**. It intentionally contains **no application code** — only architecture, data design, workflows, UI/UX specification, and API contracts.

## Scope

- Single organization (not multi-tenant)
- Laravel 12 / PHP 8.4 / PostgreSQL / Redis backend
- React + Inertia.js + TypeScript + Fluent UI v9 + Tailwind frontend
- Read-only integration with an external PageJaunes company database
- Full email delivery engine with SMTP rotation, throttling, tracking, and suppression

## Document Index

| # | Document | Description |
|---|----------|-------------|
| 01 | [Project Overview](01-project-overview.md) | Vision, goals, personas, glossary |
| 02 | [System Architecture](02-system-architecture.md) | Layered architecture, service decomposition, C4 diagrams |
| 03 | [Folder Structure](03-folder-structure.md) | Laravel + React project layout and module conventions |
| 04 | [Database Design](04-database-design.md) | Full relational schema for every domain |
| 05 | [ERD](05-erd.md) | Entity-relationship diagrams (Mermaid) |
| 06 | [PageJaunes Integration](06-pagejaunes-integration.md) | External DB connection, sync, import |
| 07 | [UI Design System](07-ui-design.md) | Fluent UI tokens, components, theming |
| 08 | [Navigation](08-navigation.md) | IA, shell layout, routing map |
| 09 | [Dashboard](09-dashboard.md) | Dashboard widgets and data sources |
| 10 | [Mailbox (Inbox/Sent/Drafts)](10-mailbox.md) | Outlook-style mail views |
| 11 | [Email Composer](11-email-composer.md) | Composer architecture and editor |
| 12 | [Template System](12-template-system.md) | HTML template editor and library |
| 13 | [Recipient Management](13-recipient-management.md) | Contacts, lists, segmentation |
| 14 | [Import/Export](14-import-export.md) | CSV/Excel/PageJaunes import pipelines |
| 15 | [Campaign Management](15-campaign-management.md) | Campaign lifecycle and scheduling |
| 16 | [Email History](16-email-history.md) | Message log and communication timeline |
| 17 | [Delivery Engine](17-delivery-engine.md) | Service decomposition for sending |
| 18 | [SMTP Management](18-smtp-management.md) | SMTP accounts, health, rotation |
| 19 | [Throttling](19-throttling.md) | Quota, rate limiting, warm-up |
| 20 | [Delivery Tracking](20-delivery-tracking.md) | Message status lifecycle |
| 21 | [Open/Click Tracking](21-open-click-tracking.md) | Pixel and redirect tracking services |
| 22 | [Email Verification](22-email-verification.md) | Address validation architecture |
| 23 | [Suppression List](23-suppression-list.md) | Bounce/complaint/unsubscribe handling |
| 24 | [Queue Management](24-queue-management.md) | Redis queues, Horizon, DLQ |
| 25 | [Reporting](25-reporting.md) | Analytics and report catalog |
| 26 | [RBAC](26-rbac.md) | Roles and permission matrix |
| 27 | [Audit Logs](27-audit-logs.md) | Audit event catalog and storage |
| 28 | [Security](28-security.md) | AuthN/AuthZ, OWASP, secrets |
| 29 | [API Specification](29-api-specification.md) | REST endpoint catalog |
| 30 | [Background Jobs](30-background-jobs.md) | Job catalog and scheduling |
| 31 | [Events](31-events.md) | Domain events and listeners |
| 32 | [Testing Strategy](32-testing.md) | Test pyramid and tooling |
| 33 | [Deployment](33-deployment.md) | Environments, Docker, scaling |
| 34 | [Roadmap](34-roadmap.md) | Phasing and future work |
| 35 | [Domain-Driven Design](35-domain-driven-design.md) | Formal bounded contexts, ubiquitous language, integration patterns |
| 36 | [C4 Architecture Model](36-c4-architecture.md) | Context, Container, Component, Code-level diagrams |
| 37 | [Workflow Engine](37-workflow-engine.md) | Shared state-machine abstraction across Campaigns/Imports/SMTP Health/Messages |
| 38 | [Rendering Pipeline](38-rendering-pipeline.md) | Authoritative, ordered email rendering stages |
| 39 | [Deliverability Analyzer](39-deliverability-analyzer.md) | Pre-send SPF/DKIM/DMARC/content scoring |
| 40 | [Central Search Service](40-search-service.md) | Shared cross-entity search abstraction |
| 41 | [Notification Center](41-notification-center.md) | Centralized in-app notification routing & preferences |

## How to Use This Documentation

1. Read documents 01–03 for context and conventions before anything else.
2. Document 04 (Database Design) and 05 (ERD) are the source of truth for all entities referenced elsewhere — every other document references table/column names defined there.
3. Feature documents (09–27) describe workflows, states, and UI; they defer schema detail to 04 and API detail to 29.
4. Document 29 (API Specification) is the contract between frontend and backend and must stay consistent with 04 and the feature documents.
5. Cross-cutting concerns (26 RBAC, 27 Audit, 28 Security, 30 Jobs, 31 Events) apply to every feature document — each feature document links back to them rather than repeating them.
6. Documents 35–41 are **refinements layered on top of 01–34**, added after an architecture review — they formalize patterns (bounded contexts, C4 levels, a shared workflow engine, an explicit rendering pipeline order, deliverability scoring, a shared search contract, and centralized notification routing) that were already implicit in 01–34. They introduce no contradictions with the base 34 documents; where they add a schema element (e.g. `deliverability_checks`, `notification_preferences`) it is called out as an addendum to [04-database-design.md](04-database-design.md), not a replacement.

## Global Non-Negotiable Requirements

- **French-only UI**: the entire application (labels, menus, errors, notifications, system emails) is monolingual French — no language switcher, no i18n toggle. Status enums stay English internally (DB/API identifiers) but are always mapped to a French display label. Full detail and the identifier→label mapping table: [07-ui-design.md §7.12](07-ui-design.md#712-langue--français-uniquement).
- **Fluent UI MCP server**: the implementation team must connect and use a Fluent UI MCP server during frontend development to get accurate, version-correct component props/examples rather than guessing the API. No official Microsoft server is public yet (see [microsoft/fluentui#35732](https://github.com/microsoft/fluentui/discussions/35732)); the community `fluentui-mcp` npm package is the reference tool until one ships. Detail: [07-ui-design.md §7.13](07-ui-design.md#713-outillage-de-développement--serveur-mcp-fluent-ui).
- **PageJaunes source is MySQL/MariaDB**, not PostgreSQL — a real schema (`companies`/`companies_details`/`emails`) with a one-to-many company→email relationship. Full mapping: [06-pagejaunes-integration.md](06-pagejaunes-integration.md) and [04-database-design.md §4.7](04-database-design.md#47-pagejaunes-integration-module).

## Conventions Used Throughout

- All table names: `snake_case`, plural.
- All primary keys: `id` (BIGINT, auto-increment) unless noted otherwise.
- All monetary/quota values documented explicitly with units.
- All timestamps: `timestamptz`, stored UTC.
- Soft deletes (`deleted_at`) used on user-facing entities that require recovery/audit; hard deletes only for ephemeral/tracking data as noted.
- Mermaid is used for all diagrams so they render natively in GitHub/GitLab and most Markdown viewers.
