#!/usr/bin/env bash
# Aggiorna Brandinstock CRM all'ultima versione, mantenendo dati e configurazione.
# Uso (come root sulla VPS):  /opt/brandinstock-crm/deploy/update.sh
set -euo pipefail

cd "$(dirname "$0")/.."
step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
trap 'printf "\n\033[1;31mERRORE:\033[0m aggiornamento interrotto alla riga %s. Il CRM continua a funzionare con la versione precedente.\n" "$LINENO" >&2' ERR

step "Scarico la nuova versione"
BRANCH=$(git rev-parse --abbrev-ref HEAD)
git fetch -q origin "$BRANCH"
git merge -q --ff-only "origin/$BRANCH"
CURRENT=$(git rev-parse HEAD)
DEPLOYED=$(cat deploy/.deployed-commit 2>/dev/null || true)
if [ "$CURRENT" = "$DEPLOYED" ]; then
  echo "    Il CRM è già aggiornato."
  exit 0
fi
[ -z "$DEPLOYED" ] || git log --oneline "$DEPLOYED..$CURRENT" 2>/dev/null | sed 's/^/    /' || true

cd deploy
step "Backup di sicurezza prima dell'aggiornamento"
./scripts/backup.sh

step "Compilazione (qualche minuto; il CRM resta online nel frattempo)"
docker compose build --pull >/var/log/brandinstock-build.log 2>&1 || { tail -30 /var/log/brandinstock-build.log; exit 1; }

step "Aggiornamento del database"
docker compose run --rm -T migrate

step "Riavvio dei servizi del CRM"
docker compose up -d
echo "$CURRENT" > .deployed-commit

trap - ERR
printf '\n\033[32m✓ Brandinstock CRM aggiornato.\033[0m\n'
