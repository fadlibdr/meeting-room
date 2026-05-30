# Production Cutover Runbook — Meeting Room (BPJS Kesehatan)

**Purpose:** take the launch-approved build from staging to a live production deployment.
**Precondition:** §N.4 = GO (product-owner + architecture sign-offs complete); staging healthy on the release commit.
**Assumes:** production runs on the **same VPS** as staging, isolated by its own directory, database, DB user, and domain. *Separate-box deltas in §K.*

**Fill these placeholders before running:**
- `<PROD_DOMAIN>` — e.g. `booking.pi2.co.id`
- `<PROD_DB_PASS>` — strong password for the prod DB user (generate; store in a vault, not here)
- `<SMTP_HOST>` `<SMTP_PORT>` `<SMTP_USER>` `<SMTP_PASS>` `<MAIL_FROM>` — transactional email
- Real admin/approver people — see §H

> Conventions: run as `deployer`. **`umask 022` is the first line of every deploy block** (a stray `077` once wrote cache files mode 600 and 500'd the app). Final ownership `deployer:www-data`. Secrets live in the prod `.env`, never in this doc.

---

## A. Tag the release
On the dev checkout (`~/meeting-room-dev`):
cd ~/meeting-room-dev
git checkout develop && git pull --ff-only
git checkout -b docs/production-cutover
DOCDIR=$(dirname "$(git ls-files | grep -iE 'deployment-runbook' | head -1)"); DOCDIR=${DOCDIR:-docs}
cat > "$DOCDIR/production-cutover.md" << 'PRODRUNBOOK'
# Production Cutover Runbook — Meeting Room (BPJS Kesehatan)

**Purpose:** take the launch-approved build from staging to a live production deployment.
**Precondition:** §N.4 = GO (product-owner + architecture sign-offs complete); staging healthy on the release commit.
**Assumes:** production runs on the **same VPS** as staging, isolated by its own directory, database, DB user, and domain. *Separate-box deltas in §K.*

**Fill these placeholders before running:**
- `<PROD_DOMAIN>` — e.g. `meetingroom.bpjs-kesehatan.go.id`
- `<PROD_DB_PASS>` — strong password for the prod DB user (generate; store in a vault, not here)
- `<SMTP_HOST>` `<SMTP_PORT>` `<SMTP_USER>` `<SMTP_PASS>` `<MAIL_FROM>` — transactional email
- Real admin/approver people — see §H

> Conventions: run as `deployer`. **`umask 022` is the first line of every deploy block** (a stray `077` once wrote cache files mode 600 and 500'd the app). Final ownership `deployer:www-data`. Secrets live in the prod `.env`, never in this doc.

---

## A. Tag the release
On the dev checkout (`~/meeting-room-dev`):
git checkout develop && git pull --ff-only
git tag -a v1.0.0 -m "Production launch (BPJS Meeting Room)" && git push origin v1.0.0

Production deploys the **tag**, not a moving branch. *(If your convention is to release from `main`, merge `develop` → `main` first and tag there.)*

## B. Production database + dedicated DB user
The shared `meeting_app` account still has rights on dev/test — **do not reuse it for prod**. Create an isolated user scoped to the prod schema only:
sudo mysql --no-defaults -u root <<'SQL'
CREATE DATABASE IF NOT EXISTS meeting_room_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'meeting_app_prod'@'localhost' IDENTIFIED BY '<PROD_DB_PASS>';
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP, REFERENCES ON meeting_room_prod.* TO 'meeting_app_prod'@'localhost';
FLUSH PRIVILEGES;
SQL
sudo mysql --no-defaults -u root -e "SHOW GRANTS FOR 'meeting_app_prod'@'localhost';"
Expect only `USAGE` globally + DML/DDL on `meeting_room_prod.*` — no `ALL`, no `GRANT OPTION`, no access to other schemas.

## C. Production code checkout
sudo mkdir -p /var/www/meeting-room-prod && sudo chown deployer:www-data /var/www/meeting-room-prod
git clone git@github.com:fadlibdr/meeting-room.git /var/www/meeting-room-prod
cd /var/www/meeting-room-prod && git checkout v1.0.0

## D. Production `.env`
cd /var/www/meeting-room-prod
cp .env.example .env
php artisan key:generate          # unique APP_KEY for prod

Edit `.env` — the values that MUST differ from staging:
APP_ENV=production
APP_DEBUG=false
APP_URL=https://<PROD_DOMAIN>
DB_DATABASE=meeting_room_prod
DB_USERNAME=meeting_app_prod
DB_PASSWORD=<PROD_DB_PASS>
Real email — NOT 'log'. Nothing reaches users until this is real.
MAIL_MAILER=smtp
MAIL_HOST=<SMTP_HOST>
MAIL_PORT=<SMTP_PORT>
MAIL_USERNAME=<SMTP_USER>
MAIL_PASSWORD=<SMTP_PASS>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=<MAIL_FROM>
MAIL_FROM_NAME="Meeting Room BPJS Kesehatan"
QUEUE_CONNECTION=database          # worker in §G
CACHE_STORE=file
SESSION_DRIVER=database            # match staging
## E. nginx vhost + SSL
DNS for `<PROD_DOMAIN>` must already point to this server (certbot's HTTP-01 challenge needs it). Copy the **staging** server block as the template, change `server_name` and `root` (→ the prod `public/`), and the `fastcgi_pass` socket only if you isolate the php-fpm pool. Then:
sudo ln -s /etc/nginx/sites-available/meeting-room-prod /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d <PROD_DOMAIN>      # issues + installs cert, sets the 443 redirect
## F. First production deploy
umask 022                          # FIRST — prevents the cache-perms 500
cd /var/www/meeting-room-prod
composer install --no-dev --optimize-autoloader
npm ci && npm run build
php artisan migrate --force
php artisan db:seed --class=RolesAndPermissionsSeeder --force
php artisan optimize               # config/route/view cache
sudo chown -R deployer:www-data storage bootstrap/cache vendor/composer public/build
chmod a+rX vendor/composer public/build
chmod -R g+rwX storage bootstrap/cache
ls -l bootstrap/cache/config.php   # MUST be -rw-r--r--, not 600
## G. Operational services
**Scheduler** — add to `deployer`'s crontab:
cd /var/www/meeting-room-prod && php artisan schedule:run >> /dev/null 2>&1
**Queue worker** — real email + queued notifications need a running worker (staging had none). systemd unit `/etc/systemd/system/meeting-room-prod-worker.service`:
[Unit]
Description=Meeting Room (prod) queue worker
After=network.target mariadb.service
[Service]
User=deployer
WorkingDirectory=/var/www/meeting-room-prod
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
[Install]
WantedBy=multi-user.target
sudo systemctl daemon-reload && sudo systemctl enable --now meeting-room-prod-worker
## H. Real admin / approver accounts
The shared-password UAT logins were **staging only** — don't carry them over. Create real accounts; because mail is now real, send each a set-password link rather than setting a password yourself:
cd /var/www/meeting-room-prod && php artisan tinker
// per real person — adjust name/email/role code:
$u = \App\Models\User::firstOrCreate(
['email' => 'real.admin@bpjs-kesehatan.go.id'],
['name' => 'Real Admin', 'is_active' => true, 'password' => \Illuminate\Support\Str::password(32)]
);
$u->roles()->sync([\App\Models\Role::where('code','system_admin')->firstOrFail()->id]);
\Illuminate\Support\Facades\Password::sendResetLink(['email' => $u->email]); // emails a set-password link
Role codes: `super_admin`, `system_admin`, `ga_admin`, `unit_approver`, `requester`.

## I. Smoke test (production)
curl -I https://<PROD_DOMAIN>/up   # 200
Then in a browser: log in as the seeded admin, create a booking as a requester, push it through approval as the approver, and **confirm the approval email lands in a real inbox** — the one check staging could never give you. Watch the worker log and `storage/logs/laravel.log` as you go.

## J. Backups (don't skip past launch)
Point the automated, rotated, offsite backup at `meeting_room_prod` (the manual drill proved restore parity; now schedule it). Nightly `mysqldump --single-transaction` to an offsite target, with a periodic restore test.

---

## K. If production is a SEPARATE box
Same steps, plus: install the full stack (PHP 8.3 + ext, nginx, MariaDB, composer, node, certbot); provision the `deployer` user + an SSH deploy key for the repo; `ufw` allow 22/80/443; point DNS at the new box. In §B, create the DB user as `@'<app-server-ip>'` instead of `@'localhost'` if DB and app are on different hosts.

## Rollback
The prod DB starts empty, so the safe path is fix-forward (migrate/seed are re-runnable). For a bad release, `git checkout` the previous tag and re-run §F. Leave the staging environment untouched as a known-good reference.
