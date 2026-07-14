# 09 — Dashboard

## 9.1 Purpose

The Dashboard is the landing page after login, giving each role an at-a-glance operational summary. Widget visibility is role-aware (see [26-rbac.md](26-rbac.md)): Viewers see analytics-only widgets, Administrators additionally see infrastructure health widgets.

## 9.2 Widget Catalog

| Widget | Data Source | Roles |
|---|---|---|
| Send volume (last 7/30 days, sparkline) | `mv_smtp_account_daily_stats` aggregate | All |
| Delivery funnel (sent → delivered → opened → clicked) | `messages` aggregate for current period | All |
| Active campaigns (status, progress bar, ETA) | `campaigns` where status in (`scheduled`,`running`,`paused`) | Marketing Manager, Marketing Operator, Administrator |
| Outbox summary (pending/waiting/retrying counts) | `messages` where status pending-family | Marketing Operator, Administrator |
| SMTP health overview (per-account status chips) | `smtp_accounts.health_status`, quota usage bars | Administrator |
| Bounce/complaint rate trend | `mv_smtp_account_daily_stats` | Administrator, Marketing Manager |
| Recent imports (last 5 jobs, status) | `import_jobs` | Marketing Operator, Administrator |
| Suppression list growth | `suppression_entries` count over time | Administrator |
| Queue backlog snapshot (jobs pending/failed) | Horizon metrics API | Administrator |
| Recent audit activity (last 10 sensitive actions) | `audit_logs` | Administrator |
| Top performing templates | `mv_template_performance` | Marketing Manager |
| Upcoming scheduled sends (next 24h) | `campaigns`/`campaign_schedules` | Marketing Manager, Marketing Operator |

## 9.3 Layout

Fluent `Grid`-based responsive card layout: 3-column on desktop, 2-column tablet, 1-column mobile. Each widget is an independently loading card (skeleton while fetching) so a slow widget never blocks the rest of the dashboard from rendering — implemented as separate Inertia deferred props or client-side polling fetches per widget (roadmap: deferred props via Inertia partial reloads).

## 9.4 Refresh Strategy

- Most widgets read from cached/materialized aggregates refreshed every 15 minutes (see [02-system-architecture.md](02-system-architecture.md#27-caching-strategy)); a "last updated" timestamp is shown per widget.
- Outbox summary, queue backlog, and SMTP health are near-real-time: polled client-side every 30 seconds (lightweight JSON endpoints, not full page reloads).

## 9.5 Empty/First-Run State

Before any campaigns/sends exist, the dashboard shows a guided empty state with primary CTAs: "Import recipients", "Create your first campaign", "Add an SMTP account" — surfaced only for roles permitted to perform each action.

Continue to [10-mailbox.md](10-mailbox.md).
