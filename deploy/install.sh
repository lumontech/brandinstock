#!/usr/bin/env bash
# Installazione automatica di Brandinstock CRM su una VPS Ubuntu 24.04.
#
# Uso (come root sulla VPS):
#   bash <(curl -fsSL https://raw.githubusercontent.com/lumontech/brandinstock/claude/upbeat-bell-mi9fxu/deploy/install.sh)
#
# Variabili opzionali: ADMIN_EMAIL, ADMIN_NAME, APP_DOMAIN, CRM_BRANCH, INSTALL_DIR.
#
# Lo script:
#   - installa Docker se manca (i servizi già presenti non vengono toccati);
#   - genera tutte le password e le chiavi e le salva in un file leggibile solo da root;
#   - se le porte 80/443 sono libere, il CRM gestisce da solo HTTPS;
#     se c'è già Nginx o Apache, aggiunge un sito dedicato e ottiene il certificato con certbot;
#   - crea il database, l'amministratore e i backup cifrati notturni.
#
# L'output a schermo non contiene segreti: può essere condiviso per assistenza.
set -euo pipefail

REPO_URL="${REPO_URL:-https://github.com/lumontech/brandinstock.git}"
CRM_BRANCH="${CRM_BRANCH:-claude/upbeat-bell-mi9fxu}"
INSTALL_DIR="${INSTALL_DIR:-/opt/brandinstock-crm}"
CREDENTIALS_FILE="/root/brandinstock-crm-CREDENZIALI.txt"
BACKUP_DIR="/var/backups/brandinstock-crm"

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$*"; }
ok()   { printf '    \033[32m✓\033[0m %s\n' "$*"; }
warn() { printf '    \033[33m!\033[0m %s\n' "$*"; }
die()  { printf '\n\033[1;31mERRORE:\033[0m %s\n' "$*" >&2; exit 1; }
trap 'die "installazione interrotta alla riga $LINENO. Copia le ultime righe qui sopra e inviale a chi ti assiste."' ERR

ask() { # ask VAR "domanda" [default]
  local __var=$1 __prompt=$2 __default=${3:-} __answer
  if [ -n "${!__var:-}" ]; then return; fi
  if [ -r /dev/tty ]; then
    read -r -p "$__prompt${__default:+ [$__default]}: " __answer </dev/tty || true
  fi
  printf -v "$__var" '%s' "${__answer:-$__default}"
}

rand() { openssl rand -hex "${1:-24}"; }

# ---------------------------------------------------------------------------
step "Controlli iniziali"
[ "$(id -u)" -eq 0 ] || die "esegui lo script come root (oppure con sudo)."
. /etc/os-release
[ "${ID:-}" = "ubuntu" ] || warn "sistema ${PRETTY_NAME:-sconosciuto}: lo script è pensato per Ubuntu 24.04."
ok "Sistema: ${PRETTY_NAME:-?}"

MEM_MB=$(awk '/MemTotal/ {print int($2/1024)}' /proc/meminfo)
DISK_GB=$(df -BG --output=avail / | tail -1 | tr -dc '0-9')
ok "RAM: ${MEM_MB} MB, disco libero: ${DISK_GB} GB"
[ "$DISK_GB" -ge 5 ] || die "servono almeno 5 GB liberi sul disco."
[ "$MEM_MB" -ge 1800 ] || warn "meno di 2 GB di RAM: la compilazione potrebbe essere lenta."

command -v curl >/dev/null || die "manca curl: installalo con 'apt install curl' e rilancia."

# Su Contabo l'IP pubblico è direttamente sull'interfaccia di rete; i servizi web sono il ripiego.
PUBLIC_IP=${PUBLIC_IP:-$(ip -4 route get 1.1.1.1 2>/dev/null | grep -oP 'src \K[0-9.]+' || true)}
if [[ ! "$PUBLIC_IP" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || [[ "$PUBLIC_IP" =~ ^(10|127|172\.(1[6-9]|2[0-9]|3[01])|192\.168)\. ]]; then
  PUBLIC_IP=$(curl -4 -fsS --max-time 10 https://api.ipify.org || curl -4 -fsS --max-time 10 https://ifconfig.me || true)
fi
[[ "$PUBLIC_IP" =~ ^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$ ]] || die "impossibile determinare l'IP pubblico della VPS."
ok "IP pubblico: $PUBLIC_IP"

# ---------------------------------------------------------------------------
step "Dati per l'installazione"
DEFAULT_DOMAIN="crm.${PUBLIC_IP//./-}.sslip.io"
ask APP_DOMAIN "Indirizzo del CRM (premi Invio per usare quello automatico)" "$DEFAULT_DOMAIN"
ask ADMIN_EMAIL "Email dell'amministratore del CRM"
ask ADMIN_NAME "Nome e cognome dell'amministratore" "Amministratore"
[[ "$ADMIN_EMAIL" =~ ^[^@[:space:]]+@[^@[:space:]]+\.[^@[:space:]]+$ ]] || die "email non valida: '$ADMIN_EMAIL'."
APP_DOMAIN=${APP_DOMAIN,,}
ok "Indirizzo: https://$APP_DOMAIN"
ok "Amministratore: $ADMIN_EMAIL"

RESOLVED=$(getent ahostsv4 "$APP_DOMAIN" | awk 'NR==1 {print $1}' || true)
if [ "$RESOLVED" != "$PUBLIC_IP" ]; then
  warn "$APP_DOMAIN punta a '${RESOLVED:-nessun IP}' invece che a $PUBLIC_IP: il certificato HTTPS non potrà essere emesso finché il DNS non è corretto."
fi

# ---------------------------------------------------------------------------
step "Analisi dei servizi già presenti sulla VPS"
port_owner() { ss -Htlnp "sport = :$1" 2>/dev/null | grep -oE 'users:\(\("[^"]+"' | head -1 | cut -d'"' -f2 || true; }
OWNER_80=$(port_owner 80)
OWNER_443=$(port_owner 443)
ok "Porta 80: ${OWNER_80:-libera}   Porta 443: ${OWNER_443:-libera}"

MODE=standalone
WEB=""
if [ -n "$OWNER_80$OWNER_443" ]; then
  case "${OWNER_80:-$OWNER_443}" in
    nginx*) MODE=proxied; WEB=nginx ;;
    caddy*)
      HOST_CADDYFILE=/etc/caddy/Caddyfile
      if systemctl is-active --quiet caddy && [ -f "$HOST_CADDYFILE" ] && command -v caddy >/dev/null; then
        MODE=proxied; WEB=caddy
      else
        die "le porte 80/443 sono usate da Caddy, ma non come servizio di sistema con $HOST_CADDYFILE (forse in un container). Nessuna modifica è stata fatta: invia questo messaggio a chi ti assiste."
      fi ;;
    apache2*|httpd*) MODE=proxied; WEB=apache ;;
    *)
      die "le porte 80/443 sono occupate da '${OWNER_80:-$OWNER_443}', che lo script non sa configurare in automatico. Nessuna modifica è stata fatta: invia questo messaggio a chi ti assiste." ;;
  esac
fi

if [ "$MODE" = proxied ]; then
  for p in $(seq 8088 8099); do
    if [ -z "$(ss -Htln "sport = :$p")" ]; then LOCAL_PORT=$p; break; fi
  done
  [ -n "${LOCAL_PORT:-}" ] || die "nessuna porta locale libera tra 8088 e 8099."
  ok "Trovato $WEB: il CRM verrà pubblicato tramite $WEB (porta interna 127.0.0.1:$LOCAL_PORT)."
else
  ok "Porte 80/443 libere: il CRM gestirà direttamente HTTPS."
fi

if [ -e "$INSTALL_DIR" ] && [ ! -f "$INSTALL_DIR/deploy/install.sh" ]; then
  die "$INSTALL_DIR esiste già e non è un'installazione del CRM: non la tocco. Imposta INSTALL_DIR con un'altra cartella."
fi
ok "Container Docker già presenti: $(docker ps -q 2>/dev/null | wc -l) (non verranno toccati)"

# ---------------------------------------------------------------------------
step "Riepilogo: cosa verrà AGGIUNTO (nulla di esistente viene modificato o rimosso)"
echo "    - pacchetti: git, age$( { [ "$WEB" = nginx ] || [ "$WEB" = apache ]; } && echo ", certbot")$(command -v docker >/dev/null || echo ", Docker")"
echo "    - cartella del CRM: $INSTALL_DIR"
echo "    - 3 container Docker isolati (progetto 'brandinstock-crm'): web, app, database"
if [ "$MODE" = proxied ]; then
  if [ "$WEB" = caddy ]; then
    echo "    - un NUOVO blocco in fondo a $HOST_CADDYFILE solo per $APP_DOMAIN"
    echo "      (prima viene fatta una copia di sicurezza del file; gli altri siti restano invariati;"
    echo "       se la nuova configurazione non è valida il file viene ripristinato)"
  else
    echo "    - un NUOVO sito $WEB solo per $APP_DOMAIN (gli altri siti restano invariati)"
    echo "      e il relativo certificato HTTPS"
  fi
else
  echo "    - il CRM userà le porte 80/443, oggi libere"
fi
echo "    - backup notturno alle 03:15 (una riga aggiunta al crontab di root)"
ufw status 2>/dev/null | grep -q "Status: active" && echo "    - firewall: regole per consentire le porte 80 e 443, se non già presenti"
if [ "${ASSUME_YES:-}" != 1 ]; then
  CONFIRM=""
  [ -r /dev/tty ] && read -r -p $'\n    Procedo con l\'installazione? (s/N): ' CONFIRM </dev/tty || true
  [[ "$CONFIRM" =~ ^[sSyY] ]] || { echo "    Installazione annullata: nessuna modifica è stata fatta."; exit 0; }
fi

step "Pacchetti necessari"
apt-get update -qq
DEBIAN_FRONTEND=noninteractive apt-get install -y -qq git openssl age ca-certificates >/dev/null
ok "Pacchetti presenti"

# ---------------------------------------------------------------------------
step "Docker"
if ! command -v docker >/dev/null 2>&1; then
  curl -fsSL https://get.docker.com | sh >/dev/null
  ok "Docker installato"
else
  ok "Docker già presente: $(docker --version)"
fi
if ! docker compose version >/dev/null 2>&1; then
  DEBIAN_FRONTEND=noninteractive apt-get install -y -qq docker-compose-plugin >/dev/null
fi
systemctl enable --now docker >/dev/null 2>&1 || true
ok "$(docker compose version)"

# ---------------------------------------------------------------------------
step "Download del CRM"
if [ -d "$INSTALL_DIR/.git" ]; then
  git -C "$INSTALL_DIR" fetch -q origin "$CRM_BRANCH"
  git -C "$INSTALL_DIR" checkout -q -B "$CRM_BRANCH" "origin/$CRM_BRANCH"
  ok "Codice aggiornato in $INSTALL_DIR"
else
  git clone -q --branch "$CRM_BRANCH" "$REPO_URL" "$INSTALL_DIR"
  ok "Codice scaricato in $INSTALL_DIR"
fi
cd "$INSTALL_DIR/deploy"

# ---------------------------------------------------------------------------
step "Configurazione e segreti"
umask 077
if [ -f .env ]; then
  ok "Configurazione esistente mantenuta (deploy/.env)"
else
  AGE_KEYS=$(age-keygen 2>/dev/null)
  AGE_PUBLIC=$(printf '%s\n' "$AGE_KEYS" | grep -o 'age1[0-9a-z]*' | head -1)
  AGE_PRIVATE=$(printf '%s\n' "$AGE_KEYS" | grep '^AGE-SECRET-KEY-')

  cat > .env <<EOF
APP_DOMAIN=$APP_DOMAIN
ACME_EMAIL=$ADMIN_EMAIL
APP_KEY=base64:$(openssl rand -base64 32)
APP_PREVIOUS_KEYS=
CRM_BLIND_INDEX_KEY=$(openssl rand -base64 32)
DB_DATABASE=brandinstock_crm
POSTGRES_PASSWORD=$(rand)
DB_MIGRATION_USERNAME=crm_owner
DB_MIGRATION_PASSWORD=$(rand)
DB_USERNAME=crm_app
DB_PASSWORD=$(rand)
CRM_REQUIRE_2FA_ROLES=admin,manager
BACKUP_AGE_RECIPIENT=$AGE_PUBLIC
BACKUP_DIR=$BACKUP_DIR
BACKUP_RETENTION_DAYS=30
BACKUP_RCLONE_REMOTE=
EOF
  if [ "$MODE" = proxied ]; then
    cat >> .env <<EOF
HTTP_BIND=127.0.0.1:$LOCAL_PORT
HTTPS_BIND=127.0.0.1:$((LOCAL_PORT + 100))
CADDY_SITE_ADDRESS=http://$APP_DOMAIN
EOF
  fi
  chmod 600 .env

  {
    echo "BRANDINSTOCK CRM - CREDENZIALI E CHIAVI"
    echo "Creato il $(date '+%d/%m/%Y %H:%M')"
    echo "======================================================================"
    echo "Copia TUTTO in un password manager aziendale, poi cancella questo file:"
    echo "    rm $CREDENTIALS_FILE"
    echo "Non inviarlo via email o chat."
    echo
    echo "Indirizzo del CRM:  https://$APP_DOMAIN"
    echo
    echo "--- Chiavi di cifratura dei dati (senza APP_KEY i dati NON si recuperano) ---"
    grep -E '^(APP_KEY|CRM_BLIND_INDEX_KEY)=' .env
    echo
    echo "--- Chiave PRIVATA per aprire i backup (non resta sulla VPS) ---"
    echo "$AGE_PRIVATE"
    echo
    echo "--- Password del database ---"
    grep -E '^(POSTGRES_PASSWORD|DB_MIGRATION_PASSWORD|DB_PASSWORD)=' .env
  } > "$CREDENTIALS_FILE"
  chmod 600 "$CREDENTIALS_FILE"
  unset AGE_KEYS AGE_PRIVATE
  ok "Segreti generati e salvati in $CREDENTIALS_FILE (leggibile solo da root)"
fi
umask 022

# ---------------------------------------------------------------------------
step "Compilazione del CRM (può richiedere 5-10 minuti)"
BUILD_LOG=/var/log/brandinstock-build.log
if ! docker compose build --pull >"$BUILD_LOG" 2>&1; then
  tail -40 "$BUILD_LOG"
  die "la compilazione delle immagini non è riuscita (log completo: $BUILD_LOG)."
fi
ok "Immagini pronte"

step "Avvio del database e creazione delle tabelle"
docker compose up -d db
for _ in $(seq 1 30); do
  [ "$(docker inspect -f '{{.State.Health.Status}}' "$(docker compose ps -q db)")" = healthy ] && break
  sleep 2
done
docker compose run --rm -T migrate
docker compose run --rm -T app php artisan db:seed --force
ok "Database pronto"

step "Avvio del CRM"
docker compose up -d
ok "Servizi avviati"

# ---------------------------------------------------------------------------
if [ "$MODE" = proxied ]; then
  step "Configurazione di $WEB e certificato HTTPS"
  if [ "$WEB" = caddy ]; then
    MARK_BEGIN="# >>> brandinstock-crm (aggiunto da install.sh) >>>"
    MARK_END="# <<< brandinstock-crm <<<"
    if grep -qF "$MARK_BEGIN" "$HOST_CADDYFILE"; then
      ok "Blocco del CRM già presente in $HOST_CADDYFILE"
    else
      CADDY_BACKUP="$HOST_CADDYFILE.bak-brandinstock-$(date +%Y%m%d%H%M%S)"
      cp -p "$HOST_CADDYFILE" "$CADDY_BACKUP"
      ok "Copia di sicurezza: $CADDY_BACKUP"
      {
        echo
        echo "$MARK_BEGIN"
        echo "# Brandinstock CRM: inoltra le richieste al container del CRM (solo 127.0.0.1)."
        echo "$APP_DOMAIN {"
        printf '\tencode zstd gzip\n'
        printf '\trequest_body {\n\t\tmax_size 12MB\n\t}\n'
        printf '\treverse_proxy 127.0.0.1:%s\n' "$LOCAL_PORT"
        echo "}"
        echo "$MARK_END"
      } >> "$HOST_CADDYFILE"
      if ! caddy validate --config "$HOST_CADDYFILE" --adapter caddyfile >/tmp/brandinstock-caddy-validate.log 2>&1; then
        cp -p "$CADDY_BACKUP" "$HOST_CADDYFILE"
        tail -5 /tmp/brandinstock-caddy-validate.log
        die "la configurazione di Caddy non risulta valida con il nuovo blocco: il file originale è stato ripristinato e Caddy NON è stato ricaricato."
      fi
      if ! systemctl reload caddy; then
        cp -p "$CADDY_BACKUP" "$HOST_CADDYFILE"
        systemctl reload caddy >/dev/null 2>&1 || true
        die "Caddy non ha accettato la nuova configurazione: il file originale è stato ripristinato."
      fi
    fi
    ok "Caddy ricaricato senza interruzioni: il certificato HTTPS per $APP_DOMAIN viene richiesto in automatico"
  elif [ "$WEB" = nginx ]; then
    # Layout Debian/Ubuntu (sites-available/enabled) oppure conf.d.
    if [ -d /etc/nginx/sites-enabled ]; then
      CONF=/etc/nginx/sites-available/brandinstock-crm.conf; LINK=/etc/nginx/sites-enabled/brandinstock-crm.conf
    else
      CONF=/etc/nginx/conf.d/brandinstock-crm.conf; LINK=""
    fi
    if [ ! -f "$CONF" ]; then
      cat > "$CONF" <<EOF
# Brandinstock CRM: inoltra le richieste al container Caddy (solo 127.0.0.1).
server {
    listen 80;
    listen [::]:80;
    server_name $APP_DOMAIN;
    client_max_body_size 12m;

    location / {
        proxy_pass http://127.0.0.1:$LOCAL_PORT;
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
    }
}
EOF
      [ -z "$LINK" ] || ln -sf "$CONF" "$LINK"
    fi
    if ! nginx -t >/dev/null 2>&1; then
      rm -f ${LINK:+"$LINK"} "$CONF"
      die "la configurazione di nginx non è valida con il nuovo sito: il sito del CRM è stato rimosso e nginx NON è stato ricaricato."
    fi
    systemctl reload nginx
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq certbot python3-certbot-nginx >/dev/null
    certbot --nginx -d "$APP_DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect --keep-until-expiring
  else
    CONF=/etc/apache2/sites-available/brandinstock-crm.conf
    a2enmod -q proxy proxy_http headers >/dev/null
    if [ ! -f "$CONF" ]; then
      cat > "$CONF" <<EOF
# Brandinstock CRM: inoltra le richieste al container Caddy (solo 127.0.0.1).
<VirtualHost *:80>
    ServerName $APP_DOMAIN
    ProxyPreserveHost On
    ProxyPass / http://127.0.0.1:$LOCAL_PORT/
    ProxyPassReverse / http://127.0.0.1:$LOCAL_PORT/
    RequestHeader set X-Forwarded-Proto expr=%{REQUEST_SCHEME}
    LimitRequestBody 12582912
</VirtualHost>
EOF
      a2ensite -q brandinstock-crm >/dev/null
    fi
    if ! apache2ctl configtest >/dev/null 2>&1; then
      a2dissite -q brandinstock-crm >/dev/null 2>&1 || true
      rm -f "$CONF"
      die "la configurazione di Apache non è valida con il nuovo sito: il sito del CRM è stato rimosso e Apache NON è stato ricaricato."
    fi
    systemctl reload apache2
    DEBIAN_FRONTEND=noninteractive apt-get install -y -qq certbot python3-certbot-apache >/dev/null
    certbot --apache -d "$APP_DOMAIN" --non-interactive --agree-tos -m "$ADMIN_EMAIL" --redirect --keep-until-expiring
  fi
  ok "HTTPS attivo tramite $WEB"
fi

if ufw status 2>/dev/null | grep -q "Status: active"; then
  ufw status | grep -qE '^(80|80/tcp)[[:space:]].*ALLOW' || ufw allow 80/tcp >/dev/null
  ufw status | grep -qE '^(443|443/tcp)[[:space:]].*ALLOW' || ufw allow 443/tcp >/dev/null
  ok "Firewall: porte 80 e 443 consentite"
fi

# ---------------------------------------------------------------------------
step "Amministratore del CRM"
OUTPUT=$(docker compose run --rm -T app php artisan crm:create-admin "$ADMIN_EMAIL" --name="$ADMIN_NAME" --generate-password 2>&1 || true)
ADMIN_PASSWORD=$(printf '%s\n' "$OUTPUT" | sed -n 's/^GENERATED_PASSWORD=//p' | tr -d '\r')
if [ -z "$ADMIN_PASSWORD" ]; then
  if printf '%s' "$OUTPUT" | grep -qi 'taken'; then
    ok "L'amministratore $ADMIN_EMAIL esiste già"
  else
    die "creazione dell'amministratore non riuscita: $OUTPUT"
  fi
else
  {
    echo
    echo "--- Primo accesso al CRM ---"
    echo "Email:    $ADMIN_EMAIL"
    echo "Password: $ADMIN_PASSWORD"
    echo "(al primo accesso ti verrà chiesto di attivare la verifica in due passaggi)"
  } >> "$CREDENTIALS_FILE"
  unset ADMIN_PASSWORD OUTPUT
  ok "Amministratore creato (password nel file delle credenziali)"
fi

# ---------------------------------------------------------------------------
step "Backup automatici"
mkdir -p "$BACKUP_DIR"
chmod 700 "$BACKUP_DIR"
CRON_LINE="15 3 * * * $INSTALL_DIR/deploy/scripts/backup.sh >> /var/log/brandinstock-backup.log 2>&1"
( crontab -l 2>/dev/null | grep -v 'brandinstock-crm/deploy/scripts/backup.sh' || true; echo "$CRON_LINE" ) | crontab -
if "$INSTALL_DIR/deploy/scripts/backup.sh" >/dev/null 2>&1; then
  ok "Backup di prova eseguito; poi ogni notte alle 03:15 in $BACKUP_DIR"
else
  warn "Backup di prova non riuscito: controlla con $INSTALL_DIR/deploy/scripts/backup.sh"
fi

# ---------------------------------------------------------------------------
step "Verifica finale"
CODE=""
for _ in $(seq 1 20); do   # il primo certificato HTTPS può richiedere fino a un minuto
  CODE=$(curl -s -o /dev/null -w '%{http_code}' --max-time 10 "https://$APP_DOMAIN/up" || true)
  [ "$CODE" = 200 ] && break
  sleep 5
done
if [ "$CODE" = 200 ]; then
  ok "Il CRM risponde correttamente su https://$APP_DOMAIN"
else
  warn "https://$APP_DOMAIN/up ha risposto '$CODE'. Se il certificato è appena stato richiesto, attendi 1-2 minuti e riprova."
  warn "Stato dei servizi:"
  docker compose ps --format '      {{.Service}}: {{.Status}}'
fi

trap - ERR
cat <<EOF

======================================================================
  Brandinstock CRM è online:  https://$APP_DOMAIN
======================================================================
  1. Leggi le credenziali con:   cat $CREDENTIALS_FILE
  2. Copiale nel password manager, poi cancella il file:
         rm $CREDENTIALS_FILE
  3. Apri il CRM e accedi con l'email dell'amministratore.
     Tieni pronta un'app come Google Authenticator per la verifica
     in due passaggi.

  Questo riepilogo non contiene password e può essere condiviso.
======================================================================
EOF
