# 42 — Parallel Execution Plan (Agent Orchestration)

## 42.1 Purpose

[34-roadmap.md](34-roadmap.md) defines *what* to build and in what phase order. This document defines *how to finish the remaining work with maximum agent parallelism and minimum token usage*: the remaining scope is decomposed into **work packages (WPs)** with disjoint file ownership, explicit per-package read lists, and a wave schedule in which every package inside a wave runs concurrently.

It supersedes nothing — feature specs (docs 01–41) remain the source of truth for behavior. Where this document and a feature spec disagree, the feature spec wins.

## 42.2 Status Snapshot (as of `main@2b6454a`)

| Roadmap phase | Status | Remaining |
|---|---|---|
| Phase 0 — Foundation (Identity/RBAC, Settings, Audit backbone) | ✅ Done | — |
| Phase 1 — Recipients & PageJaunes | ✅ Done | — |
| Phase 2 — Composer & Templates | 🟡 ~90% | Mailbox pages (doc 10) |
| Phase 3 — Delivery Engine (transactional) | 🟡 ~80% | Delivery-engine tests (fake transport exists, unused), Suppression HTTP+UI (23), Tracking/History API+UI (16, 20) |
| Phase 4 — Campaigns at scale | ❌ Not started | Campaigns (15), Queue mgmt/Horizon (24), Open/Click tracking (21) |
| Phase 5 — Trust & Governance | ❌ Not started | Verification (22), Reporting (25), Audit UI (27), Dashboard (09) |
| Refinements 37–41 | ❌ Not started | See §42.8 (partially folded in, partially deferred) |

## 42.3 Execution Model

- **Waves.** WPs are grouped into waves 0–3. All WPs within a wave run **in parallel**; a wave starts only when the previous wave is merged. Wave boundaries exist only where a real code dependency exists (e.g. campaigns need the tracking schema).
- **Isolation.** Every WP agent runs in its own **git worktree** and produces exactly **one commit** touching only its owned paths (§42.6). This makes merges trivially conflict-free by construction.
- **Orchestrator.** A single coordinator session launches each wave's agents concurrently, merges the resulting commits in the listed WP order, resolves the (rare) shared-file appends, and runs the verification gate (§42.9) once per wave — *not* once per agent.
- **One shot per WP.** Agents do not iterate interactively. If a WP fails the gate, the orchestrator re-launches only that WP with the failure output appended to its prompt.

## 42.4 Token Economy Protocol

Rules every WP agent prompt must embed:

1. **Closed read list.** Each WP lists exactly which spec docs (by section) and source files to read. Agents must not run repo-wide exploration (`Glob`/`Grep` sweeps, reading unrelated modules). The read lists below are complete — nothing else is needed.
2. **Pattern by reference, not by re-derivation.** Every backend WP names one existing module as its structural template (e.g. "mirror `app/Modules/Recipients`"). Read the template module once; copy its layering (Model → Service → FormRequest → Controller → Resource → Policy → routes → feature test) instead of re-reading conventions docs.
3. **No dependency install, no local test runs.** `composer install` is not viable in agent containers. Agents write code + tests; the gate (§42.9) verifies. Do not burn tokens attempting installs.
4. **Structured returns.** Agents return only: commit SHA, files created/modified, contracts exported (new routes/enums/events), and open questions — no prose narration, no code echoes.
5. **Effort tiers.** Mechanical/CRUD packages run on a small model at low effort; state-machine and engine packages (WP-20, WP-22) get the strong model. Tier noted per WP.
6. **Spec is French-UI aware.** All UI strings go through `resources/js/Lib/i18n/fr.ts` (doc 07 §7.12). Agents add keys, never hardcode French in components.

Estimated per-agent context: ≤ 300 lines of spec + ≤ 600 lines of template source. Total plan: ~14 agents across 4 waves.

## 42.5 Wave 0 — Parallelization Enablers (1 agent, serial)

**WP-00 · Shared-surface split** — *strong tier*

The three files every module touches are the only merge hazards: `routes/api.php`, `routes/web.php`, `resources/js/Components/Shell/NavRail.tsx`. Split them so later WPs never edit a shared file:

- Split `routes/api.php` into `routes/api/{identity,settings,recipients,importing,pagejaunes,composer,templates,delivery}.php`, `require`d from `routes/api.php` inside the existing `v1` + `auth:sanctum` group. Each later WP **creates a new file** under `routes/api/` and adds one `require` line (append-only, bottom of file).
- Same treatment for `routes/web.php` → `routes/web/{auth,app}.php`; later WPs append `require` lines only.
- Refactor `NavRail.tsx` to render from a data array in a new `resources/js/Components/Shell/navItems.ts`; later WPs append entries to that array (append-only).
- Add all not-yet-used nav/i18n keys for docs 09/10/15/22/23/24/25/27 to `fr.ts` **now** (one pass), so no later WP touches `fr.ts`.

Read: `routes/api.php`, `routes/web.php`, `NavRail.tsx`, `fr.ts`, doc [08-navigation.md](08-navigation.md).
DoD: behavior-identical refactor; all existing feature tests still pass at the gate.

## 42.6 Wave 1 — Phase 3 Closeout (5 agents, parallel)

| WP | Scope | Spec read list | Owned paths | Template | Tier |
|---|---|---|---|---|---|
| **WP-10** Delivery-engine tests | Feature tests for `SendEmailJob` end-to-end: success, transport failure→`RetryEngine`, concurrency limit→release, failover, quota consumption, suppression skip. Uses existing `Tests\Support\FakeSmtpManager` (bind over `SmtpManagerContract`). **Tests only, no src changes.** | [17](17-delivery-engine.md) §17.4–17.8, [32](32-testing.md) §32.4 | `tests/Feature/Modules/DeliveryEngine/SendEmailJobTest.php` | `SmtpAccountManagementTest` | strong |
| **WP-11** Suppression API + unsubscribe | `SuppressionEntryController` (index/store/destroy w/ `suppression.view|manage`), public unsubscribe route + signed-URL flow, `SuppressionEntryResource`, factory, feature tests. | [23](23-suppression-list.md) all, [29](29-api-specification.md) suppression §  | `app/Modules/Suppression/**` (additive), `routes/api/suppression.php`, `routes/web/unsubscribe.php`, tests | `Recipients` module | small |
| **WP-12** Tracking/History API | `MessageController` (index w/ folder+status filters per doc 16, show w/ timeline), `MessageEventResource`, `message_events` usage per doc 20 §20.2, feature tests. Owns `app/Modules/Tracking/**` — no other WP touches it this wave. | [16](16-email-history.md), [20](20-delivery-tracking.md) | `app/Modules/Tracking/**` (additive), `routes/api/tracking.php`, tests | `Recipients` module | small |
| **WP-13** Mailbox UI | Three-pane mailbox pages (Inbox=system notices, Sent, Drafts, Outbox, Scheduled folders) against WP-12's endpoints (contract fixed by doc 29 — build in parallel against the spec, not the code). | [10](10-mailbox.md), [29](29-api-specification.md) messages § | `resources/js/Pages/Mailbox/**`, `resources/js/Lib/api/messages.ts`, `Lib/types/messages.ts`, one `navItems.ts` append, one `routes/web/` require | `Pages/Recipients` | small |
| **WP-14** Suppression UI | Suppression list management page (search, add w/ reason, remove w/ confirm per doc 23 §23.5). | [23](23-suppression-list.md) §23.5 | `resources/js/Pages/Suppression/**`, `Lib/api/suppression.ts`, `Lib/types/suppression.ts`, appends as WP-13 | `Pages/Smtp` | small |

## 42.7 Wave 2 — Campaigns at Scale (4 agents, parallel)

| WP | Scope | Spec read list | Owned paths | Tier |
|---|---|---|---|---|
| **WP-20** Campaigns backend | `Campaign`/`CampaignRecipientSnapshot` models + migrations, lifecycle state machine (doc 15 §15.1 — draft→scheduled→sending→sent/paused/cancelled), `DispatchCampaignJob` fanning out to existing `SendEmailJob`, schedule/pause/resume/cancel/clone endpoints, review-flow permission gates (`campaigns.*` enum cases already seeded), feature tests. | [15](15-campaign-management.md), [30](30-background-jobs.md) campaigns §, [37](37-workflow-engine.md) | `app/Modules/Campaigns/**`, `routes/api/campaigns.php`, migrations slot A (§42.10), tests | strong |
| **WP-21** Open/Click tracking | Pixel endpoint, click-redirect service w/ signed URLs, HTML rewrite step injecting pixel + wrapping links (doc 38 pipeline order), bot filtering heuristics, `open_count`/`click_count` + `message_events` writes, unique-recipient dedup, feature tests. Public routes, no auth. | [21](21-open-click-tracking.md), [38](38-rendering-pipeline.md) | `app/Modules/Tracking/{Services,Http}/**` (new files only), `routes/web/track.php`, migrations slot B, tests | strong |
| **WP-22** Queue management | Horizon install config, queue topology per doc 24 (`transactional`, `campaigns`, `imports`, `maintenance`), queue dashboards API (`queues.view`), supervisor config docs. | [24](24-queue-management.md) | `config/horizon.php`, `app/Modules/Queues/**`, `routes/api/queues.php`, tests | small |
| **WP-23** Campaigns UI | Campaign list, wizard (doc 15 §15.2), detail tabs, pause/resume/cancel actions — against doc 29 contract. | [15](15-campaign-management.md) §15.2/15.8, [29](29-api-specification.md) campaigns § | `resources/js/Pages/Campaigns/**`, `Lib/api/campaigns.ts`, `Lib/types/campaigns.ts`, appends | small |

## 42.8 Wave 3 — Trust & Governance (5 agents, parallel)

| WP | Scope | Spec read list | Owned paths | Tier |
|---|---|---|---|---|
| **WP-30** Verification | Provider-adapter verification service (doc 22 §22.3), verify-on-import + bulk verify jobs, status columns per doc 04, feature tests w/ fake adapter. | [22](22-email-verification.md) | `app/Modules/Verification/**`, `routes/api/verification.php`, migrations slot C, tests | small |
| **WP-31** Reporting API + UI | Aggregate endpoints (sends/opens/clicks/bounces per campaign + per account, doc 25), CSV export (`reporting.export`), reporting page. | [25](25-reporting.md) | `app/Modules/Reporting/**`, `routes/api/reporting.php`, `resources/js/Pages/Reporting/**`, appends, tests | small |
| **WP-32** Audit UI | Audit log list/filter/export endpoints over existing `audit_logs` (`audit.view|export`), admin page. | [27](27-audit-logs.md) | `app/Modules/Audit/{Http}/**`, `routes/api/audit.php`, `resources/js/Pages/Admin/AuditLog.tsx`, appends, tests | small |
| **WP-33** Dashboard | Widget data endpoints (doc 09 — send volume, deliverability, quota usage, recent campaigns) + replace `Dashboard.tsx` placeholder with real widgets. | [09](09-dashboard.md) | `app/Modules/Dashboard/**`, `routes/api/dashboard.php`, `resources/js/Pages/Dashboard.tsx` (owned this wave), appends, tests | small |
| **WP-34** Notification center | In-app notification routing + preferences per doc 41 (backend + bell panel in `TopBar`). | [41](41-notification-center.md) | `app/Modules/Notifications/**`, `routes/api/notifications.php`, migrations slot D, `resources/js/Components/Shell/NotificationBell.tsx`, `TopBar.tsx` (owned this wave), tests | small |

**Deferred from docs 37–41** (unchanged from roadmap §34.3 spirit): the generalized workflow-engine abstraction (37) is satisfied by WP-20's concrete state machine; deliverability analyzer (39) and central search service (40) remain post-v1 — no WP allocated.

## 42.9 Verification Gate (per wave, once)

Run by the orchestrator after merging a wave, in an environment with dependencies cached (CI):

1. `composer install` (cached) → `php artisan test` — full suite, zero failures.
2. `npm ci` (cached) → `npx tsc --noEmit` → `npm run build`.
3. `php artisan migrate:fresh --seed` succeeds (catches migration-order/foreign-key errors across parallel WPs).
4. Grep gate: no hardcoded French strings outside `fr.ts`; no route registered outside `routes/api/*.php` / `routes/web/*.php`.

A WP is *done* only when its wave passes the gate. Note: agent containers cannot run step 1–3 locally (dependency install exceeds container limits) — the gate runs in CI only, by design.

## 42.10 Migration Timestamp Allocation

Parallel WPs must not collide on migration filenames or run-order. Reserved prefixes:

| Slot | WP | Prefix range |
|---|---|---|
| A | WP-20 Campaigns | `2026_08_06_1000xx` |
| B | WP-21 Open/Click | `2026_08_06_2000xx` |
| C | WP-30 Verification | `2026_08_06_3000xx` |
| D | WP-34 Notifications | `2026_08_06_4000xx` |

Cross-WP foreign keys (e.g. `messages.campaign_id` → `campaigns.id`, already a nullable column) are added as **separate `add_foreign_key` migrations** in the *later* slot, mirroring the existing `2026_07_14_170002_add_foreign_key_send_attempts_message_id` pattern.

## 42.11 Shared-File Rules (post-Wave-0)

| File | Rule |
|---|---|
| `routes/api.php`, `routes/web.php` | Append one `require` line at the bottom only |
| `resources/js/Components/Shell/navItems.ts` | Append one entry at the bottom only |
| `resources/js/Lib/i18n/fr.ts` | Frozen after Wave 0 — keys pre-added; a missing key is a WP defect reported in the structured return, fixed by orchestrator |
| `app/Providers/AppServiceProvider.php` | Contract bindings only; append-only; orchestrator resolves |
| `app/Domain/Enums/*` | Frozen — all needed cases already exist (verified against `PermissionName`, `MessageStatus`) |

Everything else is single-owner per the WP tables. Two WPs never own the same path in the same wave.
