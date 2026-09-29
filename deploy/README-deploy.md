# Deploy su VPS Contabo

Architettura: un solo server con Docker. Da Internet è raggiungibile solo **Caddy**, sulle porte 80 e 443: gestisce HTTPS, serve il frontend e inoltra le API a PHP-FPM. Il database non è mai esposto.

```
Internet ──443──▶ Caddy ──FastCGI (rete interna)──▶ Laravel (PHP-FPM) ──(rete interna)──▶ PostgreSQL
```

Consigliato: VPS Contabo con **Ubuntu 24.04 LTS**, almeno 4 GB di RAM, e un dominio (es. `crm.brandinstock.it`) con record DNS `A` (e `AAAA`) che punta all'IP della VPS.

## Installazione automatica (consigliata)

Collegati alla VPS come root (`ssh root@IP_DELLA_VPS`) ed esegui:

```bash
bash <(curl -fsSL https://raw.githubusercontent.com/lumontech/brandinstock/claude/upbeat-bell-mi9fxu/deploy/install.sh)
```

Lo script chiede solo l'email dell'amministratore e poi fa tutto da solo:
- installa Docker, se manca;
- genera password e chiavi e le salva in `/root/brandinstock-crm-CREDENZIALI.txt`;
- avvia il CRM con HTTPS. Se non hai un dominio usa un indirizzo automatico `crm.<ip>.sslip.io`. Se sulla VPS c'è già Nginx o Apache, aggiunge un sito dedicato senza toccare quelli esistenti;
- crea l'amministratore e programma i backup cifrati notturni.

Se lo rilanci, aggiorna il CRM mantenendo dati e configurazione.

Le sezioni seguenti descrivono l'installazione manuale e l'hardening del server.

## 1. Hardening del server (una volta)

Collegati come root con la password ricevuta da Contabo, poi:

```bash
# Aggiornamenti
apt update && apt full-upgrade -y
apt install -y ufw fail2ban unattended-upgrades age rclone git
dpkg-reconfigure -plow unattended-upgrades        # aggiornamenti di sicurezza automatici

# Utente amministrativo (non usare root)
adduser deploy && usermod -aG sudo deploy
mkdir -p /home/deploy/.ssh && cp ~/.ssh/authorized_keys /home/deploy/.ssh/ 2>/dev/null || true
# ⇒ dal TUO computer: ssh-copy-id deploy@IP_VPS   (usa una chiave ed25519)
chown -R deploy:deploy /home/deploy/.ssh && chmod 700 /home/deploy/.ssh
```

Verifica di riuscire ad accedere con `ssh deploy@IP_VPS` **usando la chiave**, poi disattiva password e login di root:

```bash
sudo tee /etc/ssh/sshd_config.d/99-hardening.conf >/dev/null <<'CONF'
PermitRootLogin no
PasswordAuthentication no
KbdInteractiveAuthentication no
PubkeyAuthentication yes
MaxAuthTries 3
X11Forwarding no
AllowUsers deploy
CONF
sudo systemctl restart ssh
```

Firewall: solo SSH, HTTP e HTTPS.

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw allow 443/udp
sudo ufw enable
sudo systemctl enable --now fail2ban               # blocca i brute force su SSH
```

> ⚠️ Docker scavalca UFW per le porte **pubblicate**. Per questo nel `docker-compose.yml` solo Caddy pubblica porte (80/443). App e database non ne pubblicano nessuna: non aggiungere `ports:` a quei servizi.
> Come ulteriore livello, puoi attivare anche il **firewall del pannello Contabo**, consentendo solo le porte 22, 80 e 443.

Installa Docker (repository ufficiale):

```bash
curl -fsSL https://get.docker.com | sudo sh
sudo usermod -aG docker deploy   # poi esci e rientra
```

## 2. Prima installazione

```bash
sudo mkdir -p /opt/brandinstock && sudo chown deploy:deploy /opt/brandinstock
git clone <URL-REPO> /opt/brandinstock
cd /opt/brandinstock/deploy
cp .env.production.example .env && chmod 600 .env
```

Genera i segreti e inseriscili in `deploy/.env`:

```bash
echo "APP_KEY=base64:$(openssl rand -base64 32)"
echo "CRM_BLIND_INDEX_KEY=$(openssl rand -base64 32)"
echo "POSTGRES_PASSWORD=$(openssl rand -base64 32 | tr -d '/+=')"
echo "DB_MIGRATION_PASSWORD=$(openssl rand -base64 32 | tr -d '/+=')"
echo "DB_PASSWORD=$(openssl rand -base64 32 | tr -d '/+=')"
```

> 🔐 **Copia subito `APP_KEY` e `CRM_BLIND_INDEX_KEY` nel password manager aziendale.** Senza `APP_KEY` i dati cifrati del CRM (email, telefoni, note) non sono più leggibili, nemmeno dai backup.

Avvio:

```bash
docker compose build
docker compose up -d db
docker compose run --rm migrate                    # crea le tabelle (ruolo crm_owner)
docker compose run --rm app php artisan db:seed --force   # fasi pipeline di default
docker compose up -d
docker compose run --rm app php artisan crm:create-admin tuo.nome@brandinstock.it --name="Nome Cognome"
```

Apri `https://crm.brandinstock.it`: il certificato HTTPS viene emesso automaticamente. Al primo accesso l'amministratore deve attivare la 2FA, poi può creare gli utenti dei venditori da **Amministrazione → Utenti**.

## 3. Backup cifrati

Sul **tuo computer**, non sul server, genera la coppia di chiavi `age`:

```bash
age-keygen -o brandinstock-backup.key     # chiave PRIVATA: conservala offline
# stampa anche la chiave pubblica "age1..."
```

Metti solo la chiave pubblica sul server, in `deploy/.env` → `BACKUP_AGE_RECIPIENT=age1...`. Poi programma il backup notturno:

```bash
sudo mkdir -p /var/backups/brandinstock-crm && sudo chown deploy:deploy /var/backups/brandinstock-crm
crontab -e
# 15 3 * * * /opt/brandinstock/deploy/scripts/backup.sh >> /home/deploy/backup.log 2>&1
```

**Off-site (consigliato)**: crea un bucket su Contabo Object Storage (o un altro storage S3), configuralo con `rclone config` e imposta `BACKUP_RCLONE_REMOTE=nome-remote:bucket` nel `.env`. Un backup che sta solo sulla stessa VPS non protegge dalla perdita della VPS.

**Prova il ripristino** almeno una volta, e poi periodicamente:

```bash
./scripts/restore.sh /var/backups/brandinstock-crm/crm-AAAAMMGGTHHMMSSZ.dump.age ~/brandinstock-backup.key
```

## 4. Aggiornamenti dell'applicazione

```bash
cd /opt/brandinstock && git pull
cd deploy
docker compose build
docker compose run --rm migrate
docker compose up -d
```

## 5. Manutenzione e monitoraggio

- Log: `docker compose logs -f caddy app` (Caddy registra gli accessi in JSON).
- Stato: `https://crm.brandinstock.it/up` risponde 200 se l'app è attiva. Collegalo a un servizio di uptime monitoring.
- Aggiorna periodicamente le immagini di base: `docker compose pull && docker compose build --pull && docker compose up -d`.
- Registro di audit (login falliti, modifiche ai dati): API `GET /api/audit-logs` (solo admin).
- **Rotazione di `APP_KEY`**: sposta la vecchia chiave in `APP_PREVIOUS_KEYS`, imposta la nuova in `APP_KEY` e riavvia. I dati si decifrano con entrambe.
- Utente compromesso o dimissioni: in **Amministrazione → Utenti** clicca **Disattiva**. L'accesso viene revocato subito e tutte le sessioni chiuse.
