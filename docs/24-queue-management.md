# 24 — Queue Management

## 24.1 Queue Architecture

Redis-backed Laravel queues, managed/observed via **Laravel Horizon**. Named queues separate workloads by priority and failure characteristics so a backlog in one never starves another:

| Queue Name | Purpose | Relative Priority |
|---|---|---|
| `smtp-send-high` | Transactional (non-campaign) message sends | Highest |
| `smtp-send-campaign` | Campaign message sends (chunked dispatch) | Normal |
| `imports` | CSV/Excel/PageJaunes import processing | Normal |
| `tracking-webhooks` | Inbound provider webhook processing | High (low-latency ingestion matters for accurate near-real-time status) |
| `reporting` | Materialized view refresh, snapshot generation | Low |
| `maintenance` | File pruning, health probes, quota reconciliation | Low |
| `notifications` | Internal admin notifications | Normal |
| `default` | Anything not explicitly routed | Lowest |

Horizon supervisor config (`config/horizon.php`) assigns worker process counts per queue, with `smtp-send-*` given the most workers/balanced auto-scaling (`balance: auto`) since send throughput is the platform's primary workload.

## 24.2 Priority Queues

Implemented via distinct named queues (above) rather than in-queue priority scores, consistent with Laravel's queue worker model (`php artisan queue:work --queue=smtp-send-high,smtp-send-campaign,default` ordering establishes priority pull order). Transactional sends must never wait behind a large campaign's backlog — hence a dedicated high-priority queue.

## 24.3 Retry Queues

Failed jobs eligible for retry ([19-throttling.md §19.7](19-throttling.md#197-retry-policies)) are not re-queued into the same queue immediately; they use Laravel's `release($delay)` mechanism to return to their original queue after the computed backoff delay, keeping retry logic colocated with the job rather than a separate physical "retry queue." This is simpler to reason about and Horizon still surfaces them distinctly via job tagging (`retry_count` tag).

## 24.4 Dead-Letter Queue (DLQ)

Jobs that exhaust `max_retries` are **not** silently dropped: Laravel's `failed_jobs` table captures them automatically, and a dedicated `DeadLetterHandler` failed-job listener additionally: (a) updates the associated `messages.status = failed` with the final failure reason, (b) raises an internal Notification if the failure rate crosses a threshold (possible systemic issue vs. isolated bad address), (c) surfaces the job in a dedicated "Dead Letter" tab of the Queue Monitoring dashboard for manual inspection/requeue.

## 24.5 Horizon Monitoring Integration

Horizon's built-in dashboard (`/horizon`, restricted to Administrator via middleware) provides low-level queue metrics; the application additionally exposes a first-class in-app **Queue Monitoring** page ([08-navigation.md](08-navigation.md)) built on Horizon's metrics API (`Horizon::app()` / `MetricsRepository`) so Administrators don't need to context-switch to the raw Horizon UI for routine monitoring, while Horizon itself remains available for deep debugging.

## 24.6 Advanced Queue Monitoring Dashboard (In-App)

| Widget | Data Source |
|---|---|
| Queue size per named queue | Horizon `MetricsRepository`/Redis queue lengths |
| Worker status (active/idle/count per supervisor) | Horizon supervisor status API |
| SMTP throughput (messages/min, last hour trend) | `send_attempts` aggregated in near-real-time (windowed count) |
| Average send rate per SMTP account | `quota_ledger` + Redis counters |
| Failed jobs (count, recent list with error) | `failed_jobs` table |
| Retry jobs (count currently delayed/pending retry) | Job tagging + Horizon pending metrics |
| Estimated completion time (per running campaign) | Remaining `campaign_recipients` without `message_id` ÷ current effective throughput across eligible SMTP accounts, consistent with [19-throttling.md §19.8](19-throttling.md#198-dynamic-scheduling) |

Refresh cadence: 10–15 second client polling for near-real-time feel without overwhelming the backend (Horizon metrics are already lightweight Redis reads).

## 24.7 Permissions

Queue Monitoring page restricted to Administrator (and read-only for Marketing Manager to see campaign-specific ETA without full infra visibility) — see [26-rbac.md](26-rbac.md).

Continue to [25-reporting.md](25-reporting.md).
