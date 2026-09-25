# Sicurezza — Brandinstock CRM

Un CRM contiene i dati commerciali più sensibili dell'azienda (clienti, contatti, trattative, prezzi). Queste sono le difese adottate, livello per livello.

## Autenticazione

- **Sessione con cookie httpOnly** (Laravel Sanctum, modalità SPA): nessun token in `localStorage`, quindi un eventuale XSS non può rubare credenziali. Cookie `__Host-`, `Secure`, `SameSite=Strict`, sessione cifrata lato server.
- **Protezione CSRF** su tutte le richieste che modificano dati (cookie `XSRF-TOKEN` + header).
- **Autenticazione a due fattori (TOTP)** con codici di recupero monouso. È **obbligatoria** per admin e responsabili (`CRM_REQUIRE_2FA_ROLES`): finché non è attiva l'API nega l'accesso ai dati. I codici TOTP non sono riutilizzabili (anti-replay). Il QR code viene generato nel browser, quindi il segreto non passa da servizi esterni.
- **Password robuste**: almeno 12 caratteri con maiuscole, minuscole e numeri. In produzione vengono rifiutate le password presenti in data breach noti (Have I Been Pwned, tramite k-anonymity).
- **Rate limiting**: 5 tentativi di login al minuto per email+IP, 20 per IP. Limiti anche sulle operazioni sensibili e sulle API.
- **Nessuna enumerazione degli utenti**: stesso messaggio e stessi tempi di risposta per email inesistente e password errata.
- Rigenerazione della sessione al login (contro la session fixation). Al cambio password le altre sessioni vengono chiuse. Un utente disattivato perde l'accesso immediatamente.

## Autorizzazione

- **Policy lato server** su ogni record: il venditore vede e modifica solo i propri clienti, referenti e opportunità. I controlli nel frontend sono solo di UX.
- **Anti-IDOR**: quando si collegano record (es. opportunità → azienda), l'ID viene validato contro i record visibili all'utente. Non si possono collegare, né quindi scoprire, dati di altri venditori.
- **Nessun mass assignment dei privilegi**: `role`, `is_active` e `owner_id` non sono assegnabili dal client. Solo admin e responsabili possono riassegnare i record.
- Solo admin e responsabili possono eliminare. Le eliminazioni sono soft delete, quindi recuperabili.
- Un admin non può togliersi i privilegi da solo né disattivarsi.

## Protezione dei dati (database)

- **Cifratura a livello applicativo** (AES-256-CBC + HMAC, `APP_KEY`) dei dati personali e riservati: email, telefono e indirizzo delle aziende, email e telefono dei referenti, note, descrizioni delle attività, segreti 2FA. Chi ottiene un dump del database non li legge in chiaro.
- **Indice cieco (HMAC-SHA256)** sulle email dei referenti: permette la ricerca esatta senza salvare l'email in chiaro. Usa una chiave separata (`CRM_BLIND_INDEX_KEY`).
- **Privilegio minimo su PostgreSQL**, con due ruoli distinti:
  - `crm_owner` possiede lo schema ed è usato **solo** per le migration;
  - `crm_app`, usato dall'applicazione, ha solo `SELECT/INSERT/UPDATE/DELETE`. Non può creare, modificare o eliminare tabelle, e ha timeout sulle query.
- **Registro di audit append-only**: login (riusciti e falliti), 2FA, cambi password e ogni creazione, modifica o eliminazione di record, con utente, IP e user agent. `crm_app` non ha i permessi `UPDATE/DELETE/TRUNCATE` su `audit_logs`, quindi nemmeno un'app compromessa può cancellare le tracce. I valori cifrati non compaiono mai nel log.
- PostgreSQL gira su una **rete Docker interna senza accesso a Internet** e non espone porte. Autenticazione `scram-sha-256`, checksum dei dati attivi.
- **Backup cifrati** con [age](https://age-encryption.org) usando solo la chiave pubblica: chi compromette il server non può leggere i backup. Copia off-site opzionale.

## Applicazione e infrastruttura

- **HTTPS obbligatorio** con certificati automatici (Caddy + Let's Encrypt), HSTS con preload.
- **Header di sicurezza**: CSP rigorosa (nessuno script inline o esterno), `X-Frame-Options: DENY`, `nosniff`, `Referrer-Policy`, `Permissions-Policy`, `COOP`. Le risposte API non vengono messe in cache.
- Frontend e API sullo **stesso dominio**, quindi CORS non è necessario (nessuna origine esterna ammessa).
- Container **read-only**, senza capability Linux e con `no-new-privileges`. PHP gira come utente non privilegiato, con funzioni pericolose disabilitate (`exec`, `system`…) e `allow_url_fopen` spento.
- Codice sorgente di sola lettura per il processo PHP. `APP_DEBUG=false` in produzione.
- Hardening della VPS (SSH solo con chiave, firewall, fail2ban, aggiornamenti automatici): vedi [`deploy/README-deploy.md`](deploy/README-deploy.md).

## Segreti da custodire offline

| Segreto | Perché è critico |
| --- | --- |
| `APP_KEY` | Senza questa chiave i dati cifrati **non sono recuperabili**. |
| `CRM_BLIND_INDEX_KEY` | Serve alla ricerca per email sui referenti. |
| Chiave privata `age` dei backup | È l'unico modo per ripristinare i backup. |
| Password PostgreSQL | Accesso al database. |

Conservali in un password manager aziendale, **mai** nel repository.

## Segnalare una vulnerabilità

Contatta privatamente il team tecnico, senza aprire issue pubbliche.
