#!/usr/bin/env bash
# Backup cifrato del database. Da eseguire sul server via cron, es.:
#   15 3 * * * /opt/brandinstock/deploy/scripts/backup.sh >> /var/log/brandinstock-backup.log 2>&1
#
# Il dump viene cifrato con age usando SOLO la chiave pubblica: chi compromette il
# server non può leggere i backup. La chiave privata va conservata offline.
set -euo pipefail

cd "$(dirname "$0")/.."
set -a; source ./.env; set +a

: "${BACKUP_AGE_RECIPIENT:?Imposta BACKUP_AGE_RECIPIENT in deploy/.env}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/brandinstock-crm}"
RETENTION="${BACKUP_RETENTION_DAYS:-30}"
STAMP="$(date -u +%Y%m%dT%H%M%SZ)"
FILE="${BACKUP_DIR}/crm-${STAMP}.dump.age"

umask 077
mkdir -p "$BACKUP_DIR"

# La password va passata esplicitamente: altrimenti pg_dump la chiede e resta in attesa.
docker compose exec -T -e PGPASSWORD="$POSTGRES_PASSWORD" db \
  pg_dump -U postgres -d "${DB_DATABASE:-brandinstock_crm}" --format=custom --no-owner </dev/null \
  | age --encrypt --recipient "$BACKUP_AGE_RECIPIENT" --output "$FILE"

# Verifica minima: il file esiste e non è vuoto.
[ -s "$FILE" ] || { echo "Backup vuoto: $FILE" >&2; exit 1; }
sha256sum "$FILE" > "$FILE.sha256"

if [ -n "${BACKUP_RCLONE_REMOTE:-}" ]; then
  rclone copy "$FILE" "$BACKUP_RCLONE_REMOTE" && rclone copy "$FILE.sha256" "$BACKUP_RCLONE_REMOTE"
fi

find "$BACKUP_DIR" -name 'crm-*.dump.age*' -mtime +"$RETENTION" -delete
echo "$(date -Is) backup ok: $FILE"
