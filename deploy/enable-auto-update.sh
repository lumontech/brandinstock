#!/usr/bin/env bash
# Attiva l'aggiornamento automatico di Brandinstock CRM: ogni 2 minuti la VPS controlla
# GitHub e, se c'è una nuova versione, esegue deploy/update.sh (backup, build, migrate, riavvio).
#
# Uso (come root sulla VPS, una volta sola):  /opt/brandinstock-crm/deploy/enable-auto-update.sh
# Log:        journalctl -u brandinstock-crm-update -n 100
# Disattiva:  systemctl disable --now brandinstock-crm-update.timer
set -euo pipefail

[ "$(id -u)" -eq 0 ] || { echo "Esegui come root."; exit 1; }
DIR=$(cd "$(dirname "$0")/.." && pwd)

cat > /etc/systemd/system/brandinstock-crm-update.service <<UNIT
[Unit]
Description=Aggiornamento automatico Brandinstock CRM da GitHub
After=network-online.target docker.service
Wants=network-online.target

[Service]
Type=oneshot
ExecStart=$DIR/deploy/update.sh
Nice=10
TimeoutStartSec=45min
UNIT

cat > /etc/systemd/system/brandinstock-crm-update.timer <<UNIT
[Unit]
Description=Controlla ogni 2 minuti se c'è una nuova versione di Brandinstock CRM

[Timer]
OnBootSec=2min
OnUnitActiveSec=2min
Persistent=true

[Install]
WantedBy=timers.target
UNIT

echo "==> Primo aggiornamento (subito, così vedi il risultato)"
"$DIR/deploy/update.sh" || echo "    (vedi il messaggio qui sopra)"

systemctl daemon-reload
systemctl enable --now brandinstock-crm-update.timer >/dev/null
echo
echo "✓ Aggiornamento automatico attivo: la VPS controlla GitHub ogni 2 minuti."
echo "  Prossimo controllo: $(systemctl list-timers brandinstock-crm-update.timer --no-legend | awk '{print $1, $2, $3}')"
echo "  Storico degli aggiornamenti:  journalctl -u brandinstock-crm-update -n 100"
