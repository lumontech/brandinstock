#!/usr/bin/env bash
# Ripristino di un backup cifrato. ATTENZIONE: sovrascrive il database attuale.
#   ./restore.sh /percorso/crm-XXXX.dump.age /percorso/chiave-privata-age.txt
set -euo pipefail

BACKUP="${1:?Uso: restore.sh <backup.dump.age> <age-identity-file>}"
IDENTITY="${2:?Serve il file con la chiave privata age}"

cd "$(dirname "$0")/.."
set -a; source ./.env; set +a
DB="${DB_DATABASE:-brandinstock_crm}"

read -r -p "Il database '$DB' verrà sovrascritto. Scrivi RIPRISTINA per continuare: " answer
[ "$answer" = "RIPRISTINA" ] || { echo "Annullato."; exit 1; }

docker compose stop app caddy
age --decrypt --identity "$IDENTITY" "$BACKUP" \
  | docker compose exec -T -e PGPASSWORD="$POSTGRES_PASSWORD" db pg_restore -U postgres -d "$DB" --clean --if-exists --no-owner --role="${DB_MIGRATION_USERNAME:-crm_owner}"
docker compose start app caddy
echo "Ripristino completato."
