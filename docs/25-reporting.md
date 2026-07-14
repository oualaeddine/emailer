# 25 — Reporting

## 25.1 Reporting Architecture

Reports read primarily from the materialized views defined in [04-database-design.md §4.13](04-database-design.md#413-reporting-module) (`mv_campaign_daily_stats`, `mv_smtp_account_daily_stats`, `mv_template_performance`), refreshed every 15 minutes ([02-system-architecture.md §2.9](02-system-architecture.md#29-scheduling-strategy)). Ad hoc/real-time figures (e.g. "campaign in progress right now") fall back to live aggregate queries against `messages` for the current, not-yet-materialized window, clearly labeled "live" vs. the "as of {last refresh}" snapshot data.

## 25.2 Report Catalog

| Report | Dimensions | Key Metrics |
|---|---|---|
| **Delivery Report** | Date range, campaign, SMTP account | Sent, delivered, delivery rate, failure rate |
| **Open Report** | Date range, campaign | Unique/total opens, open rate (bot-filtered and raw, see [21-open-click-tracking.md §21.5](21-open-click-tracking.md#215-bot-filtering)) |
| **Click Report** | Date range, campaign, link | Unique/total clicks, click rate, click-to-open rate, top links |
| **Bounce Rate Report** | Date range, SMTP account, campaign | Soft vs. hard bounce breakdown, trend line |
| **Complaint Rate Report** | Date range, SMTP account | Complaint rate trend vs. threshold line |
| **SMTP Performance Report** | SMTP account, date range | Volume sent, deliverability, health status history, quota utilization % |
| **Campaign Comparison Report** | Multi-select campaigns | Side-by-side funnel metrics, ranked table |
| **Template Performance Report** | Template | Aggregate open/click rate across all campaigns using it, usage count |
| **Queue Performance Report** | Date range | Throughput trend, failed/retry job counts, average time-to-send |

## 25.3 Report UI Pattern

Each report page: filter bar (date range + relevant dimension selectors) → summary stat tiles → primary chart (trend line or bar comparison, per [dataviz guidance in the design system]) → detail data table (sortable, exportable). Consistent layout across all report types for learnability.

## 25.4 Export

CSV and PDF export per report, generated as a queued job for anything beyond a small inline result set, delivered via signed URL per [14-import-export.md §14.7](14-import-export.md#147-export). PDF export renders the same chart+table via a headless-render step (server-side chart rendering) to avoid relying on client-side screenshotting.

## 25.5 Drill-Down Navigation

Every report row links to its source detail (campaign name → Campaign Detail Analytics tab, SMTP account name → SMTP Detail Usage tab, template name → Template edit page usage stats) so reporting is a navigational hub, not a dead end.

## 25.6 Permissions

All roles including Viewer have read access to Reporting (Viewer's defining characteristic per [01-project-overview.md §1.5](01-project-overview.md#15-primary-personas)); export actions require an additional `reporting.export` permission (default granted to all roles except Viewer, since PII export is a heavier action — configurable, see [26-rbac.md](26-rbac.md)).

Continue to [26-rbac.md](26-rbac.md).
