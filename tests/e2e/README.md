# End-to-end smoke suite

A real Chromium (Playwright) drives the **built** application: French UI, Fluent UI, real
HTTP, a fresh database and a local SMTP sink. No extra dependency: it uses the `playwright`
package already in `package.json` and Node's built-in test runner (`node --test`).

## Run it

```bash
composer install && npm ci
npm run build                      # the suite runs against public/build (fails fast if missing)
npx playwright install chromium    # once, unless PLAYWRIGHT_CHROMIUM_PATH points to a Chromium

npm run test:e2e                   # everything (about 2 minutes)
npm run test:e2e -- campaign forms # only specs whose file name contains one of the words
E2E_SERVE=1 npm run test:e2e       # seed + serve only (prints URL, admin login and TOTP secret), to write specs
E2E_HEADED=1 npm run test:e2e      # show the browser
```

`tests/e2e/run.mjs` does, in order: create the database if needed → `php artisan migrate:fresh
--seed` → `db:seed --class=E2eSeeder` → start the SMTP sink (free port) and
`php -S` from `public/` with Laravel's `server.php` router (free port; `SESSION_DRIVER=file`,
`CACHE_STORE=file`, `QUEUE_CONNECTION=sync`, `MAIL_MAILER=log`, `APP_URL`,
`SANCTUM_STATEFUL_DOMAINS`) → run the specs one file at a time → stop everything. The exit
code is the test runner's.

## Environment variables

| Variable | Default | Purpose |
|---|---|---|
| `E2E_DB_DATABASE` | `pagejaunes_mailer_e2e` | Database that is **wiped** (`migrate:fresh`). Must contain `e2e` or `test` unless `E2E_ALLOW_ANY_DB=1`. |
| `DB_CONNECTION` / `DB_HOST` / `DB_PORT` / `DB_USERNAME` / `DB_PASSWORD` | `mysql` / `127.0.0.1` / `3306` / `root` / empty | Database server (CI and the local MariaDB use port 3307). |
| `E2E_TOTP_SECRET` | `JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP` | Base32 TOTP secret of the seeded 2FA admin. |
| `PLAYWRIGHT_CHROMIUM_PATH` | Playwright's own browser | Use an existing Chromium binary. |
| `PHP_BINARY` | `php` | PHP executable. |
| `E2E_HEADED`, `E2E_SERVE` | unset | See above. |

Seeded admin: `e2e-admin@pagejaunes-mailer.local` / `password` (+ TOTP). Fixtures come from
`Database\Seeders\E2eSeeder` (refuses to run when `APP_ENV=production`): a verified sender with a
reply-to, one SMTP account pointing at the sink, three lists (`E2E Liste Alpha/Bravo/Charlie`),
eight contacts (one in the wilaya « Alger » through the PageJaunes cache), a segment and one sent
campaign with messages (so the report page has data).

## Specs (`tests/e2e/specs/`)

| Spec | Covers |
|---|---|
| `pages.spec.mjs` | Every authenticated page (record URLs resolved from the API/fixtures) at 1440 px and 390 px: HTTP 200, no uncaught page error, no failed same-origin request (`ERR_ABORTED` ignored), no horizontal overflow on the phone. |
| `campaign.spec.mjs` | Create → sender → subject (personalisation menu) + preheader → include 2 lists / exclude 1 (estimate) → gallery template → editor: saved section, dynamic-content block with a wilaya rule, double-click insert → save & quit → checklist unblocked → test send to 2 contacts (From, Reply-To, personalised subject, preheader, dynamic block kept/dropped per contact) → « Envoyer au meilleur moment » → « En cours » tab. |
| `forms.spec.mjs` | Form with double opt-in + list, publish, active « Formulaire envoyé » automation with a send-email step, anonymous public submission, no membership yet, confirmation link (signed with `artisan tinker`; presence of the DOI mail checked in `storage/logs/laravel.log`), membership + automation e-mail in the sink. |
| `apikeys.spec.mjs` | API key with only « Consulter les journaux transactionnels » (ticked by clicking labels): `200` on `GET /api/v1/transactional/logs`, `403` on `/campaigns`, `/webhooks` and `POST /transactional/send`. |

Each spec is independent and creates its own uniquely named records. Shared helpers live in
`support/` (`browser.mjs` login/2FA + failure screenshots, `smtp-sink.mjs`, `mail.mjs`, `totp.mjs`).

## Output

`tests/e2e/artifacts/` (git-ignored): `*.png` screenshot of every page of a failed scenario,
`server.log` (php -S output), `mail/*.eml` (everything the sink received).

## Known workaround

After a Fluent dialog closes on an Inertia navigation, `#app` keeps `aria-hidden="true"`, so
`getByRole` finds nothing until a full reload. The specs reload (or use CSS selectors) where this
happens; the lines are marked `KNOWN APP BUG` and can be dropped once the app is fixed.
