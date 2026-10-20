# 48 — Deploy runbook (first production release)

Step-by-step checklist for the first production deployment of PageJaunes Mailer. The
production database is **empty**: no data migration is needed, and the upgrade
migrations (`2026_10_24_*`) run as no-ops. Topology, environment variable groups and
backups are described in [33-deployment.md](33-deployment.md); this page is the order
of operations.

Tick each box. Commands run from the application root as the deploy user (never root).

## 1. Prerequisites

| Component | Version / note |
|---|---|
| PHP | **8.4** (`composer.lock` requires it), extensions: `pdo_mysql`, `mbstring`, `intl`, `gd`, `zip`, `bcmath`, `redis` (or Predis), `pcntl` (Horizon) |
| MySQL | **8.0+**. Create the database as `utf8mb4` / `utf8mb4_unicode_ci`. MariaDB 10.11 also works. |
| Redis | 6+, for queues, cache and Horizon |
| Node | 22 LTS, for the build and for thumbnails (`scripts/thumbnail.mjs`) |
| Chromium | For template/section thumbnails: `npx playwright install --with-deps chromium`, or a system Chromium referenced by `THUMBNAIL_CHROMIUM_PATH`. Optional: without it, thumbnails are skipped. |
| Web server | Nginx (or Apache) serving `public/`, HTTPS only |

- [ ] App database and a user with full rights on it only.
- [ ] Read-only user on the PageJaunes database `pjnewdb` (SELECT only).
- [ ] DNS for the app hostname, plus a TLS certificate.
- [ ] SMTP provider accounts (host, port, user, password), which are entered in the app after install.

## 2. Code and build

```bash
git clone https://github.com/oualaeddine/emailer.git && cd emailer
git checkout main
composer install --no-dev --optimize-autoloader
npm ci
npm run build                      # public/build is also committed; rebuilding is safer
```

## 3. Environment (`.env`)

Start from `.env.example`. Values that must change:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://mailer.example.dz
APP_KEY=                           # filled by: php artisan key:generate

DB_CONNECTION=mysql
DB_HOST=…
DB_PORT=3306
DB_DATABASE=pagejaunes_mailer
DB_USERNAME=…
DB_PASSWORD=…

PAGEJAUNES_DB_HOST=…               # external read-only DB
PAGEJAUNES_DB_PORT=3306
PAGEJAUNES_DB_DATABASE=pjnewdb
PAGEJAUNES_DB_USERNAME=…           # SELECT-only user
PAGEJAUNES_DB_PASSWORD=…

QUEUE_CONNECTION=redis
CACHE_STORE=redis
SESSION_DRIVER=database            # needed so a password change signs out other sessions
REDIS_HOST=… / REDIS_PASSWORD=…

MAIL_FROM_ADDRESS=no-reply@pagejaunes.dz   # becomes the default sender
MAIL_FROM_NAME="PageJaunes"

SANCTUM_STATEFUL_DOMAINS=mailer.example.dz
SESSION_SECURE_COOKIE=true

SUPER_ADMIN_EMAIL=admin@pagejaunes.dz      # first administrator
SUPER_ADMIN_PASSWORD=                      # empty = random, printed once by db:seed

# Optional
THUMBNAIL_CHROMIUM_PATH=/usr/bin/chromium
TRANSACTIONAL_ATTACHMENT_RETENTION_DAYS=30
MAXMIND_LICENSE_KEY=…                      # geolocation of opens/clicks (geoip:update)
```

- [ ] `php artisan key:generate --force` (once only. Changing the key later makes encrypted SMTP passwords unreadable.)
- [ ] Keep the `.env` file out of git, readable only by the app user.

## 4. Database

```bash
php artisan migrate --force
php artisan db:seed --force
```

`db:seed` creates the roles and permissions, default settings, the default sender
(`MAIL_FROM_ADDRESS`), the standard contact attributes, the template gallery, and **one**
administrator (`SUPER_ADMIN_EMAIL`). In production it never creates the development
`password` accounts. Re-running it does not reset an existing administrator's password.

- [ ] Note the administrator password printed by `db:seed` if `SUPER_ADMIN_PASSWORD` was empty.
- [ ] Do **not** run `migrate:fresh` or `db:seed --class=…Demo…` in production.

## 5. Application caches and storage

```bash
php artisan storage:link
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

Directories `storage/` and `bootstrap/cache/` must be writable by the app user.

## 6. Background processes

**Queue workers: Horizon** (Supervisor or systemd, one process per host that runs workers):

```ini
[program:mailer-horizon]
command=php /var/www/emailer/artisan horizon
user=www-data
autostart=true
autorestart=true
stopwaitsecs=3600
```

**Scheduler** (on **one** host only):

```cron
* * * * * cd /var/www/emailer && php artisan schedule:run >> /dev/null 2>&1
```

What it runs:

| Frequency | Task |
|---|---|
| Every minute | Automation runs (`AdvanceAutomationRunsJob`) |
| Every 5 min | Webhook delivery retries |
| Hourly | Campaign statistics rollup |
| Daily | PageJaunes company sync, recipient counters, export pruning, transactional attachment pruning; 02:17 best send hours |
| Monthly | GeoIP database update (needs `MAXMIND_LICENSE_KEY`) |

- [ ] `php artisan horizon:status` shows `running`.
- [ ] `php artisan schedule:list` lists the tasks above.

## 7. First login and configuration (in the app)

1. [ ] Log in as the administrator. Two-factor authentication is mandatory: scan the QR code shown at first login. Then **change the password** from the account menu.
2. [ ] *Paramètres → Comptes SMTP & rotation*: add the provider accounts and send a test.
3. [ ] *Paramètres → Expéditeurs, domaines & IP*: add each sender domain and publish the SPF, DKIM and DMARC records shown. Wait until they are verified.
4. [ ] Same page: verify the default sender and any other sender addresses. Campaigns can only use a verified sender.
5. [ ] *Paramètres → Identité visuelle*: logo, colour, default theme.
6. [ ] If public forms are used, set the captcha in *Marketing → Formulaires → Captcha des formulaires* (administrator): choose hCaptcha or Cloudflare Turnstile and enter the site key and the secret key (the secret is stored encrypted and never shown again). Forms that have « Activer le captcha » ticked then require it.
   Fallback without the screen (secret not encrypted, not recommended): `php artisan tinker --execute='$s = app(App\Modules\Settings\Services\SettingsService::class); foreach (["forms.captcha_provider" => "turnstile", "forms.captcha_site_key" => "…", "forms.captcha_secret" => "…"] as $k => $v) { $s->setSystemValue($k, $v, App\Domain\Enums\SettingValueType::String); }'`
7. [ ] *Paramètres → Utilisateurs & rôles*: invite staff with the right roles.
8. [ ] *Paramètres → Clés API & webhooks*: create scoped keys for integrations (transactional sending, contacts), never a full-access key.

## 8. Smoke test

Against the production URL, with a test list containing only internal addresses:

- [ ] Log in (with 2FA), open the dashboard, contacts, campaigns, templates.
- [ ] Create a campaign from a gallery template, pass the checklist, send a test to yourself. Check the sender, Reply-To, preheader and the *Se désinscrire* link.
- [ ] Send the campaign to the test list and check it appears in statistics within the hour.
- [ ] Send a transactional e-mail via the API with a scoped key.
- [ ] Submit a public form (double opt-in) and confirm the contact appears.

The automated suite can also be pointed at a **staging** copy (it needs seeded demo
users, so never at production): `npm run test:e2e` (see `tests/e2e/README.md`).

## 9. Monitoring

- Horizon dashboard `/horizon` (administrators only) and the in-app *Files d'attente* page.
- `storage/logs/laravel.log` (or the configured log channel): alert on `ERROR`.
- Failed jobs: `php artisan queue:failed`.
- Database backups per [33-deployment.md §33.5](33-deployment.md#335-backups), starting on day one.

## 10. Later deployments and rollback

```bash
php artisan down --render="errors::503"
git pull origin main
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
php artisan horizon:terminate           # Supervisor restarts it on the new code
php artisan up
```

**Rollback:** take a database backup before each `migrate`. To roll back, check out the previous
release tag and restore that backup. Don't rely on `migrate:rollback` for data migrations.
Run `php artisan horizon:terminate` after switching code.
