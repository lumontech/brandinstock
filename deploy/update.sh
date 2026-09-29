#!/usr/bin/env bash
# Aggiorna Brandinstock CRM all'ultima versione su GitHub, mantenendo dati e configurazione.
# Se non ci sono novità non fa nulla, quindi può girare in automatico (vedi enable-auto-update.sh).
#
# Uso manuale (come root sulla VPS):  /opt/brandinstock-crm/deploy/update.sh
set -euo pipefail

# Tutto è dentro main(): bash legge l'intero script prima di eseguirlo, così
# l'aggiornamento del file stesso tramite git non interferisce con l'esecuzione.
main() {
  cd "$(dirname "$0")/.."
  log_prefix() { date '+%Y-%m-%d %H:%M:%S'; }
  step() { printf '\n[%s] ==> %s\n' "$(log_prefix)" "$*"; }
  trap 'printf "\n[%s] ERRORE: aggiornamento interrotto alla riga %s. Il CRM continua a funzionare con la versione precedente.\n" "$(log_prefix)" "$LINENO" >&2' ERR

  # Evita due aggiornamenti in contemporanea (es. manuale + automatico).
  exec 9>/run/brandinstock-crm-update.lock
  if ! flock -n 9; then
    echo "[$(log_prefix)] Un aggiornamento è già in corso: esco."
    exit 0
  fi

  local branch current deployed
  branch=$(git rev-parse --abbrev-ref HEAD)
  git fetch -q origin "$branch"
  git merge -q --ff-only "origin/$branch"
  current=$(git rev-parse HEAD)
  deployed=$(cat deploy/.deployed-commit 2>/dev/null || true)
  if [ "$current" = "$deployed" ]; then
    [ -t 1 ] && echo "Il CRM è già aggiornato ($(git log -1 --format='%h %s'))."
    exit 0
  fi

  step "Nuova versione trovata"
  if [ -n "$deployed" ]; then
    git log --oneline "$deployed..$current" 2>/dev/null | sed 's/^/    /' || true
  fi

  cd deploy
  step "Backup di sicurezza prima dell'aggiornamento"
  ./scripts/backup.sh

  step "Compilazione (qualche minuto; il CRM resta online nel frattempo)"
  if ! docker compose build --pull >/var/log/brandinstock-build.log 2>&1; then
    tail -30 /var/log/brandinstock-build.log
    false
  fi

  step "Aggiornamento del database"
  docker compose run --rm -T migrate

  step "Riavvio dei servizi del CRM"
  docker compose up -d
  echo "$current" > .deployed-commit

  step "Verifica"
  local domain code=""
  domain=$(grep -E '^APP_DOMAIN=' .env | cut -d= -f2-)
  for _ in $(seq 1 12); do
    code=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "https://$domain/up" || true)
    [ "$code" = 200 ] && break
    sleep 5
  done
  trap - ERR
  if [ "$code" = 200 ]; then
    printf '\n[%s] ✓ Brandinstock CRM aggiornato alla versione %s.\n' "$(log_prefix)" "$(git log -1 --format='%h %s')"
  else
    printf '\n[%s] ATTENZIONE: aggiornamento eseguito ma https://%s/up risponde %s.\n' "$(log_prefix)" "$domain" "${code:-nessuna risposta}" >&2
    exit 1
  fi
}

main "$@"
