# 47 — Brevo Parity: Orchestration Log

Running log of the autonomous orchestrator session (doc 44 §44.16). One entry per launch, merge,
gate run, retry and decision. Newest entries at the bottom. Timestamps are UTC.

## Environment facts (established 2026-10-19)

- `main` does not carry docs 43/44; they live on `ccr-c1ee7816-6ul82y` (main + 4 doc commits).
  **Decision:** `brevo/main` is cut from `ccr-c1ee7816-6ul82y` (= main + docs), not from bare `main`.
- Container: PHP 8.3.6, Node 22, no MySQL/Postgres server, Docker client only (no daemon).
  `composer.lock` pins packages requiring PHP ≥ 8.4.1 → `composer install --ignore-platform-req=php`
  is used locally. Local tests will try `DB_CONNECTION=sqlite` (in-memory); the real gate is CI.
- No `.github/workflows` exists → `ci.yml` is an extra owned path of WP-40N (Wave 0).
- `npm ci` works locally.

## Conventions added by the orchestrator

- `navItems.ts` entries for pages that do not exist yet carry `hidden: true`; the WP that delivers
  the page may remove that single flag on its own entries (the only edit allowed on the frozen file).
- Nav/i18n key contract between WP-40 (writes `fr.ts`) and WP-40N (reads it) is fixed in both prompts.

## Log

| Time (UTC) | Wave | WP | Model | Event | Outcome / SHA |
|---|---|---|---|---|---|
| 2026-10-19 | — | — | — | Read docs 44, 43, 42, README, 03, 07 §7.12–7.13 | done |
| 2026-10-19 | — | — | — | Created `brevo/main` @ b3d9d83, `brevo/wave-0` | done |
| 2026-10-19 | — | — | — | CI situation: none; local composer needs platform override; no DB server | recorded |
| 2026-10-19 | 0 | WP-40 | opus/high | Launched (worktree) | running |
| 2026-10-19 | 0 | WP-40N | sonnet/medium | Launched (worktree), extra owned path `.github/workflows/ci.yml` | running |
| 2026-10-19 | 0 | both | — | Worktrees were cut from `main`, not `brevo/wave-0`; agents told to `checkout -B <wp-branch> brevo/wave-0` | corrected |
| 2026-10-19 | 0 | both | — | **Decision:** git refuses nested `brevo/wave-N/wp-XX` under branch `brevo/wave-N`; WP branches are `brevo/wave-N-wp-XX` | applied |
| 2026-10-19 | — | — | — | `composer install --ignore-platform-req=php` succeeded locally | vendor ready |
| 2026-10-19 | 0 | WP-40N | sonnet | Agent reported BLOCKED twice (branch nesting, worktree cut from main); resumed on pre-made worktree `.claude/worktrees/wp-40n` @ brevo/wave-0-wp-40n | relaunched (attempt 1 continues) |
| 2026-10-19 | — | — | — | Local `php artisan test` on SQLite fails: migrations use fulltext indexes. Installing mariadb-server via apt for a local MySQL gate on port 3307 | in progress |
| 2026-10-19 | 0 | WP-40N | sonnet | Returned structured result; commit on brevo/wave-0-wp-40n, owned paths only; notes: `Lib/docs/registry.ts` still holds old URLs (fix in gate as trivial or via WP-102); nav gates for hidden entries chosen by agent | 9d52573 |
| 2026-10-19 | — | — | — | MariaDB 3307 running locally; baseline `php artisan test` on brevo/wave-0 started | running |
| 2026-10-19 | 0 | WP-40 | opus | Returned structured result; 22 files, additions only, owned paths only | 21d5de4 |
| 2026-10-19 | 0 | — | — | Merged WP-40 (7ae2cae) then WP-40N (25de259) into brevo/wave-0; no conflicts | merged |
| 2026-10-19 | 0 | gate | — | tsc: 2 new errors (Lib/docs/ui.ts group labels) + 3 pre-existing on main; all fixed directly (trivial); `npm run build` ok; `migrate:fresh --seed` ok on MariaDB; grep gates: only pre-existing hits (bootstrap.ts/Dashboard/Composer literals, public routes in routes/web.php) | dd2281c |
| 2026-10-19 | 0 | gate | — | Pushed brevo/main + brevo/wave-0 (CI via new ci.yml); full PHP suite running locally; Opus review agent launched | running |
| 2026-10-19 | 0 | gate | — | **CI unavailable**: GitHub Actions job not started, "account is locked due to a billing issue" (same for every historical run). ci.yml kept; the local gate (MariaDB 10.11 on 3307, PHP 8.3 with `--ignore-platform-req=php`) is the gate for all waves. Wave PRs are merged after the local gate, stated in each PR body | recorded |
| 2026-10-19 | — | — | — | **Decision:** from Wave 1 on, the orchestrator pre-creates each WP worktree (`.claude/worktrees/wp-XX` on `brevo/wave-N-wp-XX` from `brevo/wave-N`, with `vendor`/`node_modules` symlinked) and launches the agent on it, because the Agent tool's own worktree is cut from `main` | applied |
| 2026-10-19 | 0 | review | opus/high | Review: 1 blocking (PermissionNameTest count 33→43), 7 should-fix, 10 nits. Applied directly on brevo/wave-0: blocking + should-fix #2–#8 + nits #10, #12–#18 (all ≤10-line edits except the RBAC grid rows and the permissions data migration). Skipped: nit #9 (query-string on redirects) and #11 was applied as `isNavItemVisible` export only | see next SHA |
| 2026-10-19 | 0 | gate | — | Full suite on merged tree: 19 failed / 204 passed. 1 = the blocking finding (fixed). 18 = pre-existing environment failures (PageJaunes external DB `pjnewdb` absent → QueryException, then lock-wait cascades in Queues/Recipients tests); identical set on the pre-merge baseline. Sonnet helper agent builds a `pjnewdb` schema from docs/06 so later gates run clean | recorded |
| 2026-10-19 | 0 | gate | — | Provisioned `pjnewdb` (PageJaunes external test schema from docs/06) on local MariaDB; full suite: **223 passed, 0 failed** (18 s) | green |
| 2026-10-19 | 0 | PR | — | PR #14 brevo/wave-0 → brevo/main opened, marked ready, merged (local gate; CI billing-locked) | 6f4ff00 |
| 2026-10-19 | 1 | — | — | Cut `brevo/wave-1` from brevo/main; 7 worktrees `.claude/worktrees/wp-{41,41u,42,43,44,45,60s}` on `brevo/wave-1-wp-XX`; per-WP test DBs `brevo_wpXX` | ready |
| 2026-10-19 | 1 | — | — | **Decisions:** (1) backend agents MAY run their own test files against their private DB (`DB_DATABASE=brevo_wpXX php artisan test <paths>`), never the full suite; (2) UI contracts not in doc 45 fixed in prompts: `SenderPicker` props `{value, onChange(id, sender), verifiedOnly?, disabled?}`; `TemplatePicker` props `{open, onClose, campaignId, onApplied(design)}` and it performs the design PUT/copy itself; (3) new Inertia page routes: WP-41U `routes/web/senders.php`, WP-60S `routes/web/lab.php` (one `require` appended to routes/web/app.php each), WP-43 edits `routes/web/campaigns.php` | applied |
| 2026-10-19 | 1 | 41, 41U, 44, 45 | sonnet/medium | Launched (pre-made worktrees) | running |
| 2026-10-19 | 1 | 42, 43, 60S | opus/high | Launched (pre-made worktrees) | running |
