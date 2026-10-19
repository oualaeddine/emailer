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
