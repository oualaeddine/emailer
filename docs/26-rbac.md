# 26 — Role-Based Access Control

## 26.1 Model

Single-role-per-user (`users.role_id`), each role a named collection of granular `permissions` via `permission_role` (see [04-database-design.md §4.2](04-database-design.md#42-identity-module)). Permissions are namespaced `{module}.{action}` strings (e.g. `campaigns.send`, `smtp.manage_credentials`). Laravel Policies wrap permission checks per model (e.g. `CampaignPolicy::send(User $user, Campaign $campaign)` checks `$user->hasPermission('campaigns.send')` plus any object-level rule, e.g. "operators can only send their own drafts unless additionally granted `campaigns.send_any`").

This is intentionally **not** a fully dynamic custom-role-builder in v1 (four fixed roles) — extensibility to custom roles is a roadmap item ([34-roadmap.md](34-roadmap.md)), but the `permissions`/`permission_role` schema already supports it without migration changes, only new seed data and an admin UI.

## 26.2 Roles Summary

| Role | Summary |
|---|---|
| **Administrator** | Full access to everything, including SMTP credentials, user management, audit logs, settings |
| **Marketing Manager** | Full campaign/template/recipient/reporting access; approves campaigns under review-before-sending policy; cannot manage SMTP credentials or users |
| **Marketing Operator** | Day-to-day composing/sending/importing; cannot manage templates library structurally (can use, not curate), cannot approve campaigns, cannot manage SMTP/users |
| **Viewer** | Read-only across dashboards, reports, email history, campaign analytics; no create/edit/send/import anywhere |

## 26.3 Full Permission Matrix

| Permission | Administrator | Marketing Manager | Marketing Operator | Viewer |
|---|---|---|---|---|
| `dashboard.view` | ✔ | ✔ | ✔ | ✔ |
| `mailbox.view_own` | ✔ | ✔ | ✔ | — |
| `mailbox.view_all` | ✔ | ✔ | — | — |
| `composer.compose` | ✔ | ✔ | ✔ | — |
| `composer.send` | ✔ | ✔ | ✔ | — |
| `templates.view` | ✔ | ✔ | ✔ | ✔ |
| `templates.manage` (create/edit/archive/delete) | ✔ | ✔ | — | — |
| `recipients.view` | ✔ | ✔ | ✔ | ✔ |
| `recipients.manage` (create/edit/tag/delete) | ✔ | ✔ | ✔ | — |
| `recipients.import` | ✔ | ✔ | ✔ | — |
| `recipients.verify` | ✔ | ✔ | ✔ | — |
| `recipients.annotate` (notes) | ✔ | ✔ | ✔ | — |
| `segments.manage` | ✔ | ✔ | ✔ | — |
| `campaigns.view` | ✔ | ✔ | ✔ | ✔ |
| `campaigns.create` | ✔ | ✔ | ✔ | — |
| `campaigns.send` (own drafts) | ✔ | ✔ | ✔ | — |
| `campaigns.send_any` | ✔ | ✔ | — | — |
| `campaigns.approve` (review-before-sending gate) | ✔ | ✔ | — | — |
| `campaigns.cancel_pause` | ✔ | ✔ | ✔ (own) | — |
| `smtp.view` | ✔ | ✔ (read-only) | ✔ (read-only) | — |
| `smtp.manage_credentials` | ✔ | — | — | — |
| `smtp.manage_quotas_rotation` | ✔ | — | — | — |
| `smtp.test` | ✔ | — | — | — |
| `queues.view` | ✔ | ✔ (campaign ETA only) | — | — |
| `reporting.view` | ✔ | ✔ | ✔ | ✔ |
| `reporting.export` | ✔ | ✔ | ✔ | — |
| `suppression.view` | ✔ | ✔ | ✔ | — |
| `suppression.manage` (add/remove) | ✔ | ✔ | — | — |
| `audit.view` | ✔ | — | — | — |
| `audit.export` | ✔ | — | — | — |
| `users.manage` | ✔ | — | — | — |
| `settings.manage` | ✔ | — | — | — |
| `settings.branding_only` | ✔ | ✔ | — | — |

`—` denotes no access (nav item/action hidden entirely, per [08-navigation.md §8.4](08-navigation.md#84-navigation-behavior)).

## 26.4 Object-Level Rules (beyond the flat permission matrix)

- Marketing Operators see only their own Drafts/Sent/Outbox by default; `mailbox.view_all` (granted to Manager/Admin) is required to see organization-wide mail activity.
- `campaigns.send` alone lets an Operator send campaigns **they created**; `campaigns.send_any` (Manager/Admin only) is required to send a campaign created by someone else — relevant when a Manager wants to take over an Operator's draft campaign.
- Recipient Notes ([16-email-history.md §16.2](16-email-history.md#162-communication-timeline-per-recipientcompany)) are attributed to their author but editable/deletable by their author or an Administrator only.

## 26.5 Enforcement Layers

1. **Route middleware** — coarse gate (`can:permission-name`) on controller routes.
2. **Policies** — object-level nuance (26.4) via Laravel Policies, invoked from Application Services.
3. **Query scoping** — repositories apply row-level scoping (e.g. "own drafts only") as a query constraint, not just a post-fetch filter, to avoid leaking counts/existence of restricted data.
4. **Frontend gating** — nav items and action buttons hidden per current user's permission set (passed down via Inertia shared props, e.g. `usePage().props.auth.permissions`), purely for UX; never relied upon as the security boundary (backend enforcement is authoritative).

## 26.6 Seed Data

Roles and their permission grants are seeded via a `RolePermissionSeeder` reflecting exactly the matrix in 26.3, so a fresh install has correct RBAC without manual configuration.

Continue to [27-audit-logs.md](27-audit-logs.md).
