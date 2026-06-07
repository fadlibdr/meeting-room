# Runbook §J — Database Backups & Restore (Cross-cutting 1.2 / D-3)

Offsite, automated database backups with a periodic restore test. The backup
itself is shell + cron (`scripts/backup.sh`), **not** application code.

## What runs

`scripts/backup.sh`:
1. `mysqldump --single-transaction` (consistent, non-locking) of the app DB,
2. gzips it, verifies the archive integrity + minimum size,
3. copies it **offsite** (rclone or rsync target),
4. prunes local copies older than the retention window.

## One-time setup

1. Pick + provision an **offsite target** out of the prod host's blast radius
   (object storage bucket or a separate backup host). For object storage,
   configure `rclone` once: `rclone config` → e.g. remote `s3:bpjs-mr-backups`.
2. Add cron (as the deploy user) — nightly 01:30 Asia/Jakarta:
   ```cron
   30 1 * * *  OFFSITE_DEST="s3:bpjs-mr-backups" /var/www/meeting-room-prod/scripts/backup.sh >> /var/log/mr-backup.log 2>&1
   ```
3. Tunables (env): `BACKUP_DIR`, `BACKUP_KEEP_DAYS` (default 14), `OFFSITE_DEST`,
   `OFFSITE_TOOL` (`rclone` default | `rsync`).

> **Secrets blast radius (D-9):** the offsite store holds the DB, which contains
> the encrypted SMTP/OAuth secrets. Those are only decryptable with `APP_KEY`.
> Keep `APP_KEY` OUT of the backup target — never back up `.env` to the same place.

## Restore test (do quarterly — backups are only real if restore works)

On a **non-prod** host/DB:
```bash
# 1. fetch the latest archive from offsite
rclone copy s3:bpjs-mr-backups/<latest>.sql.gz ./

# 2. restore into a scratch database
gunzip -c <latest>.sql.gz | mysql -h127.0.0.1 -uroot -p meeting_room_restore_test

# 3. sanity check row counts vs prod expectations
mysql -e "SELECT COUNT(*) FROM bookings; SELECT COUNT(*) FROM users;" meeting_room_restore_test

# 4. point a scratch app instance at it and run the health check
php artisan system:health-check
```
Record the restore-test date + result in `docs/decision-log.md`.

## Monitoring

- The cron line appends to `/var/log/mr-backup.log`; the script exits non-zero on
  dump/integrity/offsite failure — alert on non-zero exit (cron MAILTO or a log
  watcher).
- Verify an archive lands offsite each morning (size > previous day is normal as
  data grows).
