#!/usr/bin/env bash
#
# Cross-cutting 1.2 [D-3] / runbook §J — nightly offsite database backup.
#
# Takes a consistent mysqldump, compresses it, copies it offsite, and prunes
# old local copies. Designed for cron (NOT app code). Pair with a periodic
# restore test (see docs/runbook-backups.md).
#
# Cron (as the deploy user), nightly 01:30 Jakarta:
#   30 1 * * *  /var/www/meeting-room-prod/scripts/backup.sh >> /var/log/mr-backup.log 2>&1
#
# Reads DB_* from the app .env. Configure the offsite target via env:
#   BACKUP_DIR       local staging dir            (default: storage/app/backups)
#   BACKUP_KEEP_DAYS local retention in days      (default: 14)
#   OFFSITE_DEST     rclone remote or rsync target (e.g. "s3:bpjs-mr-backups" or
#                    "user@host:/backups"); empty = local only (NOT recommended)
#   OFFSITE_TOOL     "rclone" (default) or "rsync"
#
set -euo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
ENV_FILE="${APP_DIR}/.env"

# --- read a key from .env (strips quotes) ---
env_get() { grep -E "^$1=" "$ENV_FILE" | head -1 | cut -d= -f2- | sed -E 's/^"(.*)"$/\1/; s/^'\''(.*)'\''$/\1/'; }

DB_HOST="$(env_get DB_HOST)"; DB_PORT="$(env_get DB_PORT)"; DB_DATABASE="$(env_get DB_DATABASE)"
DB_USERNAME="$(env_get DB_USERNAME)"; DB_PASSWORD="$(env_get DB_PASSWORD)"
: "${DB_PORT:=3306}"

BACKUP_DIR="${BACKUP_DIR:-${APP_DIR}/storage/app/backups}"
BACKUP_KEEP_DAYS="${BACKUP_KEEP_DAYS:-14}"
OFFSITE_TOOL="${OFFSITE_TOOL:-rclone}"
STAMP="$(date +%Y%m%d-%H%M%S)"
OUT="${BACKUP_DIR}/${DB_DATABASE}-${STAMP}.sql.gz"

mkdir -p "$BACKUP_DIR"

echo "[$(date -Is)] dumping ${DB_DATABASE} -> ${OUT}"
MYSQL_PWD="$DB_PASSWORD" mysqldump \
  --host="$DB_HOST" --port="$DB_PORT" --user="$DB_USERNAME" \
  --single-transaction --quick --routines --triggers --no-tablespaces \
  "$DB_DATABASE" | gzip -9 > "$OUT"

# Integrity: a valid gzip that is non-trivially sized.
gzip -t "$OUT"
SIZE="$(stat -c%s "$OUT")"
if [ "$SIZE" -lt 1024 ]; then echo "ERROR: dump suspiciously small (${SIZE} bytes)"; exit 1; fi
echo "[$(date -Is)] dump ok (${SIZE} bytes)"

# --- offsite copy ---
if [ -n "${OFFSITE_DEST:-}" ]; then
  echo "[$(date -Is)] copying offsite via ${OFFSITE_TOOL} -> ${OFFSITE_DEST}"
  case "$OFFSITE_TOOL" in
    rclone) rclone copy "$OUT" "$OFFSITE_DEST" ;;
    rsync)  rsync -a "$OUT" "$OFFSITE_DEST/" ;;
    *) echo "ERROR: unknown OFFSITE_TOOL '${OFFSITE_TOOL}'"; exit 1 ;;
  esac
  echo "[$(date -Is)] offsite copy ok"
else
  echo "WARNING: OFFSITE_DEST unset — backup is LOCAL ONLY. Configure an offsite target."
fi

# --- prune local copies older than retention ---
find "$BACKUP_DIR" -name "${DB_DATABASE}-*.sql.gz" -type f -mtime +"$BACKUP_KEEP_DAYS" -delete
echo "[$(date -Is)] done; pruned local backups older than ${BACKUP_KEEP_DAYS}d"
