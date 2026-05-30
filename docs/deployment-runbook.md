# Deployment Runbook — Meeting Room BPJS Kesehatan

**Document type:** Operational runbook (Blueprint §N.4 — "operational runbook available")
**Status:** v1.0
**Date:** 30 May 2026
**Owner:** Project lead (Fadli)
**Applies to:** Staging (`booking.pi2.co.id`). The production section is a forward placeholder.

---

## 1. Purpose

A repeatable, safe procedure to deploy a new `develop` revision to staging, verify it, and roll back if needed. Written for execution by one operator with `deployer` SSH access.

---

## 2. Environments / Topology

There are **three** Laravel checkouts on the VPS; only **one** is served. Confirm which you are in before running anything.

| Checkout | Role | Served? | APP_ENV | DB | Notes |
|---|---|---|---|---|---|
| `/var/www/meeting-room` | **Staging (served)** | ✅ nginx + php8.3-fpm | `staging` | `meeting_room` | `deployer:www-data`. **The deploy target.** |
| `~/meeting-room-dev` | Dev / test | ❌ | `local` | `meeting_room_dev` | Where the test suite runs. Never served. |
| `~/meeting-room` | Stale clone | ❌ | — | — | Not served, not dev. Ignore / clean up. |

**Staging `.env` (confirmed):** `APP_ENV=staging`, `APP_DEBUG=false`, `APP_URL=https://booking.pi2.co.id`, `DB_DATABASE=meeting_room`, `QUEUE_CONNECTION=database`, `SESSION_DRIVER=database`, `CACHE_STORE=file`, `MAIL_MAILER=log`, `FILESYSTEM_DISK=local`.

**Services:** nginx (TLS), php8.3-fpm (app), MariaDB, cron (`schedule:run`). No queue worker (see §8).

---

## 3. Pre-Deploy Checklist

- [ ] The revision to deploy is green on `develop` (CI / `~/meeting-room-dev` suite).
- [ ] You are on the staging host: `hostname` → `meeting-room-staging`; `pwd` → `/var/www/meeting-room`.
- [ ] Record the current commit for rollback: `git rev-parse --short HEAD`.
- [ ] **Back up the database** (always, before `migrate`):
      `mysqldump -u <DB_USERNAME> -p meeting_room > ~/backups/meeting_room_$(date +%F_%H%M).sql`
- [ ] Note any new migrations, `.env` keys, or npm deps in the incoming revision.

---

## 4. Deploy Procedure

Run as `deployer` in `/var/www/meeting-room`. Steps are `&&`-chained so execution **stops at the first failure**. If it stops mid-way: fix the cause, run the remaining steps manually, and **always finish with `php artisan up`** so the site does not stay in maintenance mode.

```bash
cd /var/www/meeting-room

# 0. discard local working-tree noise so the pull is a clean fast-forward
git checkout -- package-lock.json \
  storage/framework/cache/.gitignore \
  storage/framework/cache/data/.gitignore 2>/dev/null

# 1. deploy
umask 022                      # deploy is immune to the calling shell's umask
php artisan down \
  && git pull origin develop \
  && composer install --no-dev --optimize-autoloader \
  && npm ci \
  && npm run build \
  && php artisan migrate --force \
  && php artisan optimize:clear \
  && php artisan optimize \
  && sudo systemctl reload php8.3-fpm \
  && php artisan up
```

Step rationale:
- `down` — maintenance mode for the window.
- `composer install --no-dev` — production dependencies only.
- `npm ci && npm run build` — rebuild Vite assets. **Required whenever blade/JS/CSS changed**; skipping it leaves a stale `public/build` (old styling).
- `migrate --force` — non-interactive migrations.
- `optimize:clear` **then** `optimize` — rebuild config/route/view/event caches. **Skipping `optimize:clear` after a code pull is the classic "new routes 404 on the live site" / stale-route-cache bug.**
- `reload php8.3-fpm` — drop opcache so PHP serves the new code.
- `up` — exit maintenance.

---

## 5. One-Time Setup (already done on staging; documented for prod)

- **Scheduler cron** (drives reminders and any scheduled task):
  `* * * * * cd /var/www/meeting-room && /usr/bin/php artisan schedule:run >> /dev/null 2>&1`
  Verify with `crontab -l`. (Harmless no-op until a scheduled task exists.)
- **Storage:** private attachments live under `storage/app/private`; ensure it exists and is writable by `www-data`. If a public disk is later introduced, run `php artisan storage:link`.
- **.env:** `APP_KEY` set, `APP_DEBUG=false`, mailer configured (currently `log`).

---

## 6. Post-Deploy Verification

```bash
php artisan about | sed -n '1,25p'              # env, debug, cache states
php artisan migrate:status | tail -15           # no "Pending"
php artisan schedule:list                       # expected scheduled tasks present
php artisan route:list | grep -cE "GET|POST|PUT|PATCH|DELETE"   # route count sane (≈39+)
curl -sSI https://booking.pi2.co.id | head -1   # HTTP 200/302, not 5xx
```

Smoke (browser): log in → open `bookings` (list renders) → open a booking (show page) → open `admin/rooms` (admin module loads) → submit a test booking → confirm it lands in `admin/logs`.

---

## 7. Rollback

```bash
cd /var/www/meeting-room
php artisan down
git checkout <previous-short-sha>            # the SHA recorded in §3
composer install --no-dev --optimize-autoloader
npm ci && npm run build
# ONLY if the bad deploy ran a migration that must be undone, and it is safely reversible:
# php artisan migrate:rollback --step=1 --force
php artisan optimize:clear && php artisan optimize
sudo systemctl reload php8.3-fpm
php artisan up
```

Restore the DB from the §3 backup **only** if data was corrupted:
`mysql -u <DB_USERNAME> -p meeting_room < ~/backups/<file>.sql`.

---

## 8. Queue Worker (future)

Current notifications send **synchronously** (no `ShouldQueue`), and nothing is enqueued, so **no worker is required today** — `QUEUE_CONNECTION=database` is inert. When async work is added (email-queued notifications, large async exports — both currently deferred), add a supervised worker (e.g. a `systemd` unit running `php artisan queue:work --sleep=3 --tries=3`) and document its restart here.

---

## 9. Troubleshooting

- **New routes 404 / admin pages missing:** stale route cache → `php artisan optimize:clear && php artisan optimize`.
- **`git pull` refuses (local changes):** re-run step 0 (discard tree noise), then pull.
- **500 / blank page after deploy:** check `storage/logs/laravel.log`; usually a missing `.env` key or `storage/` permissions → `sudo chown -R deployer:www-data storage bootstrap/cache`.
- **Stale styling:** `npm run build` was skipped or failed — re-run it.
- **Site stuck in maintenance:** `php artisan up`.

---

*Internal Use Only • BPJS Kesehatan • Deployment Runbook v1.0*
