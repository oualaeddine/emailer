# 34 — Roadmap

## 34.1 Phasing Philosophy

This documentation set specifies a complete v1 architecture. This roadmap identifies (a) suggested build-order phasing for v1 itself, and (b) explicitly deferred capabilities noted throughout the documents as "roadmap items," so implementers know what was deliberately scoped out rather than overlooked.

> **Execution status & orchestration:** Phases 0–2 are implemented and Phase 3 is ~80% complete on `main`. The remaining scope is decomposed into parallel-agent work packages — with per-package read lists, file-ownership boundaries, and a wave schedule — in [42-parallel-execution-plan.md](42-parallel-execution-plan.md). That document tracks *how* the rest gets built; this one remains the *what/why*.

## 34.2 Suggested Build Phases (v1)

| Phase | Scope | Rationale |
|---|---|---|
| **Phase 0 — Foundation** | Identity/RBAC ([26](26-rbac.md)), Settings ([04 §4.15](04-database-design.md#415-settings-module)), base schema migrations, Audit backbone ([27](27-audit-logs.md)) | Everything else depends on auth, permissions, and the audit event bus |
| **Phase 1 — Recipients & PageJaunes** | Recipients/Lists/Segments ([13](13-recipient-management.md)), Import pipeline ([14](14-import-export.md)), PageJaunes integration ([06](06-pagejaunes-integration.md)) | Nothing can be sent without recipients; validates the external DB integration early, since it's the highest-risk external dependency |
| **Phase 2 — Composer & Templates** | Composer ([11](11-email-composer.md)), Templates ([12](12-template-system.md)), Mailbox shell ([10](10-mailbox.md)) | Establishes the core authoring UX |
| **Phase 3 — Delivery Engine (Transactional first)** | SMTP Management ([18](18-smtp-management.md)), Throttling ([19](19-throttling.md)), Delivery Engine ([17](17-delivery-engine.md)) scoped to single transactional sends, Delivery Tracking ([20](20-delivery-tracking.md)), Suppression ([23](23-suppression-list.md)) | Prove out the hardest subsystem (rotation/quota/retry/failover) against simpler transactional volume before layering campaign-scale complexity |
| **Phase 4 — Campaigns at Scale** | Campaigns ([15](15-campaign-management.md)), Queue Management/Horizon ([24](24-queue-management.md)), Open/Click Tracking ([21](21-open-click-tracking.md)) | Builds on a proven delivery engine to add bulk scheduling/dispatch |
| **Phase 5 — Trust & Governance** | Verification ([22](22-email-verification.md)), Reporting ([25](25-reporting.md)), full Audit UI, Dashboard ([09](09-dashboard.md)) | Rounds out the platform with analytics and compliance visibility once real send data exists to report on |

## 34.3 Explicitly Deferred (Not in v1)

- **Multi-tenancy** — the entire schema/architecture assumes one organization; introducing tenancy later would require an `organization_id` scoping pass across nearly every table and a re-audit of every query for tenant isolation. Noted as a major, deliberate non-goal (see [01-project-overview.md §1.4](01-project-overview.md#14-out-of-scope)).
- **Inbound mailbox (IMAP/POP reply ingestion)** — v1's "Inbox" surfaces only system-generated notices, not true received mail. A future phase could add reply-tracking by polling a reply-to mailbox and threading replies against `messages`.
- **Custom role builder** — v1 ships four fixed roles ([26-rbac.md §26.1](26-rbac.md#261-model)); the underlying `permissions`/`permission_role` schema already supports arbitrary roles, so this is primarily an admin-UI addition, not a schema migration.
- **OpenAPI-generated TypeScript types** — v1 keeps `Lib/types` manually mirrored to API Resources ([03-folder-structure.md §3.2](03-folder-structure.md#32-frontend-react--inertia--typescript--fluent-ui-v9--tailwind)); generating an OpenAPI spec from [29-api-specification.md](29-api-specification.md) and codegen-ing types would remove manual-sync drift risk.
- **Table partitioning for `messages`/`message_events`** — noted as a scaling lever in [33-deployment.md §33.7](33-deployment.md#337-scaling-strategy) if volume growth warrants it; not required at initial launch scale.
- **A/B testing for campaigns** (subject line or content variants with automatic winner selection) — natural extension of the Campaign and Reporting modules once baseline analytics are proven out.
- **Native mobile apps** — responsive web only in v1, per [01-project-overview.md §1.4](01-project-overview.md#14-out-of-scope).
- **Send-time content personalization beyond merge variables** (e.g. conditional content blocks per segment) — the block editor and merge-variable system ([11-email-composer.md](11-email-composer.md)) are the extension point for this later.

## 34.4 Architectural Extension Points Already Designed In

These are called out because the schema/architecture was deliberately shaped to accommodate them without breaking changes:
- `recipient_notes` and per-user theme preference were noted as minor additive schema addenda ([16-email-history.md §16.2](16-email-history.md#162-communication-timeline-per-recipientcompany), [07-ui-design.md §7.11](07-ui-design.md#711-theming--branding-settings-driven)) — additive columns/tables, no migration risk to existing data.
- `campaigns.tracking_enabled` toggle ([21-open-click-tracking.md §21.6](21-open-click-tracking.md#216-privacy-considerations)) is additive.
- `permissions`/`permission_role` already generalizes beyond the four seeded roles (34.3).
- The SMTP/Verification provider-adapter pattern ([18-smtp-management.md §18.3](18-smtp-management.md#183-provider-defaults-configurable-not-hardcoded), [22-email-verification.md §22.3](22-email-verification.md#223-provider-abstraction)) means adding a new provider is configuration + an adapter class, not a core rework.

This concludes the PageJaunes Mailer architecture and design documentation set. Return to [README.md](README.md) for the full index.
