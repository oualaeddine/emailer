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
