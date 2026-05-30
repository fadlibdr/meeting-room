#!/bin/bash
set -euo pipefail

cd /var/www/meeting-room

echo "==> Pulling latest from develop"
git fetch origin
git reset --hard origin/develop

echo "==> Installing PHP dependencies (with dev deps for staging)"
composer install --optimize-autoloader --no-interaction

echo "==> Installing and building frontend assets"
npm install --silent
npm run build

echo "==> Running migrations"
php artisan migrate --force

echo "==> Re-syncing seed data (RBAC + system settings)"
# RolesAndPermissionsSeeder uses firstOrCreate + sync — idempotent, additive only.
# AppSettingsSeeder uses updateOrCreate — idempotent.
# These re-runs catch new permission/setting rows added by feature branches.
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=AppSettingsSeeder --force

echo "==> Repairing cache directory ownership (prevents drift from web traffic)"
sudo chown -R deployer:www-data storage/framework/cache/ 2>/dev/null || true
sudo chmod -R 775 storage/framework/cache/ 2>/dev/null || true

echo "==> Clearing and rebuilding caches"
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

echo "==> Reloading PHP-FPM"
sudo systemctl reload php8.3-fpm

echo "==> Deploy complete at $(date)"
