#!/usr/bin/env bash
#
# Production deploy for emailer.pagesjaunes-dz.com.
#
# docs/33-deployment.md, and docs/24-queue-management.md's supervisor notes:
# Horizon owns its worker processes, so a deploy must call `horizon:terminate`
# to make the master exit gracefully after the current job. systemd
# (horizon-emailer.service, Restart=always) re-spawns it against the new code.
# Terminating LAST means workers keep serving the old code only until the
# caches below are rebuilt, never a half-updated mix.
#
# Run as the application user, from the application root:
#   sudo -u pagesjaunes-emailer bash scripts/deploy.sh
set -euo pipefail

cd "$(dirname "$0")/.."

echo "==> Pulling main"
git pull --ff-only origin main

echo "==> Installing PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

echo "==> Running migrations"
php artisan migrate --force

echo "==> Rebuilding caches"
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Restarting Horizon"
php artisan horizon:terminate

echo "==> Done. Horizon will be re-spawned by systemd; verify with:"
echo "    php artisan horizon:status"
