# Production Deploy Record — v1.12.0

**Document type:** Release deploy record (companion to `deployment-runbook.md`)
**Date:** 6 June 2026
**Target:** Production — `/var/www/meeting-room-prod`
**From → To:** `v1.7.0` (`ff4ac04`) → `v1.12.0` (`e15e994`)
**Operator split:** non-sudo steps run by the deploy agent; sudo finalizers run by the project lead.

---

## 1. What this release ships

The complete **Stage 2 epic** (`v1.8.0`–`v1.11.0`) plus **full ID/EN internationalization** (`v1.12.0`):

- 2.1 Calendar (.ics) · 1.2 per-user notification preferences · 1.3 admin reschedule
- 2.1d Utilization dashboard · 2.2 XLSX + queued large-export job
- 3.1 i18n (Indonesian-first, opt-in English — 467 translation keys, every view) · 3.2 PWA installable shell + offline
- 4.1 Front-office daily view + manual check-in

ADRs **012–015** cover the design decisions. Test suite at release: **686 passing**.

---

## 2. Delta from the previously deployed version

| Type | Items |
|---|---|
| **Migrations** (all additive, non-destructive) | `users.email_notifications`, `exports`, `users.locale`, `bookings.checked_in_at` |
| **Composer dependency** | `openspout/openspout` (in `require`; pulled by `composer install --no-dev`) |
| **Required env change** | `APP_LOCALE=id` + `APP_FALLBACK_LOCALE=id` (see §5 — without it `en.json` flips the default UI to English) |
| **Seed data** | `front_office` role + `bookings.check-in` permission (added by `RolesAndPermissionsSeeder`, idempotent) |
| **Runtime** | Queue worker + hourly scheduler are now load-bearing (queued exports + `exports:prune`) |
| **Static assets** | PWA `manifest.webmanifest`, `sw.js`, `images/pwa/*`, and `lang/` ship via `git checkout` |

---

## 3. Pre-flight (read-only)

```bash
cd /var/www/meeting-room-prod
git status --porcelain          # must be empty (working tree clean)
git describe --tags             # confirm starting version
git fetch origin --tags
php artisan migrate:status       # note current state
node -v && php -v && which composer npm   # tooling present
```

Release pre-flight observed: clean tree, HEAD `v1.7.0`, Node v20.20.2, PHP 8.3.30, jobs/failed_jobs already migrated.

---

## 4. Deploy procedure (non-sudo — executed by the agent)

```bash
cd /var/www/meeting-room-prod

# 1. Code
git checkout v1.12.0

# 2. Env — Indonesian-first default (CRITICAL, see §5)
#    Set/append in .env:
#      APP_LOCALE=id
#      APP_FALLBACK_LOCALE=id

# 3. Dependencies + assets
composer install --no-dev --optimize-autoloader --no-interaction
npm ci
npm run build

# 4. Schema (4 additive migrations)
php artisan migrate --force

# 5. Seed data (idempotent; AppSettingsSeeder is value-preserving — never wipes admin SMTP config)
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan db:seed --class=AppSettingsSeeder --force

# 6. Caches (bakes APP_LOCALE=id into the cached config)
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

---

## 5. Why the locale env change is required

The app is **Indonesian-first**: under the default locale, `__('Indonesian string')` returns the key verbatim and only `en` does a JSON lookup (ADR-013). The config default is `id`, so a prod `.env` with **no** `APP_LOCALE` is already safe — but `APP_LOCALE=en` (or a stale cached config that resolved to `en`) would make every `__()` call hit `en.json` and render the whole UI in English. Setting `APP_LOCALE=id` explicitly and re-running `config:cache` removes all ambiguity.

---

## 6. Sudo finalizers (run by the project lead)

The freshly written cache/view files are owned `deployer:deployer`, and the queue worker is still running the previous code.

```bash
cd /var/www/meeting-room-prod
sudo chown -R deployer:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
sudo systemctl reload php8.3-fpm
sudo systemctl restart meeting-room-prod-worker   # CRITICAL: loads Export model + GenerateBookingExportJob + new notifications
```

The scheduler (`meeting-room-prod-worker` is the queue worker; the scheduler cron is separate) is already `active`/`enabled`, so the new `exports:prune` hourly entry is picked up automatically.

> **Why the worker restart is critical:** until it reloads, a queued large export (> `EXPORT_SYNC_ROW_LIMIT`, default 1000 rows) would fail because the old worker process lacks the `Export` model, `GenerateBookingExportJob`, and `ExportReadyNotification` classes.

---

## 7. Post-deploy verification

```bash
php artisan system:health-check        # expect "Sistem sehat." and exit 0
php artisan migrate:status | grep Pending   # expect none
```

Manual smoke (after finalizers):

- App loads, UI in Indonesian; user menu → **ID / EN** toggle → English renders, persists across pages.
- `.ics` "Tambah ke Kalender" on a booking downloads a calendar file.
- Bookings list → **CSV / Excel** export; a large filtered set queues and notifies on completion.
- `/admin/reports/utilization` renders; `/front-desk` reachable by a `bookings.check-in` holder.
- Profile → email-notification toggle present; Settings → activate the email toggle when ready.
- PWA: install prompt appears; offline navigation shows the `/offline` page.

Release verification observed: `system:health-check` → **"Sistem sehat." exit 0**, 0 pending migrations, 467 `en.json` keys, config cached `locale=id fallback=id env=production`.

---

## 8. Operational follow-ups

- Assign the **`front_office`** role to reception staff (front-desk check-in).
- Activate the **email** toggle in Settings once SMTP is verified (use "Kirim Email Uji").
- External uptime monitor on the public `/up` endpoint.
- k6 load test (`docs/loadtest.js`) → record p95/error-rate in the decision log.

---

## 9. Rollback

All four migrations are additive (new nullable columns / a new table); they are safe to leave in place on a code rollback.

```bash
cd /var/www/meeting-room-prod
git checkout v1.11.0          # or v1.7.0 to fully revert this cutover
composer install --no-dev --optimize-autoloader --no-interaction
npm ci && npm run build
php artisan optimize:clear && php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan event:cache
# then the §6 sudo finalizers (chown/chmod/fpm reload/worker restart)
```

Only if a column must be removed (rarely necessary, since they are nullable and unused by older code):

```bash
php artisan migrate:rollback --step=4 --force   # drops checked_in_at, locale, exports, email_notifications
```

---

*Internal Use Only • BPJS Kesehatan • Deploy Record v1.12.0*
