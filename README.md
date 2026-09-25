# Brandinstock CRM

CRM e pipeline di vendita per i venditori Brandinstock: aziende clienti, referenti, opportunità su pipeline Kanban, attività (chiamate, email, incontri, promemoria) e dashboard con previsioni.

| Parte | Tecnologia | Cartella |
| --- | --- | --- |
| Backend API | Laravel 13 (PHP 8.4) + Sanctum | [`backend/`](backend) |
| Frontend | Vue 3 + TypeScript + Vite + Pinia | [`frontend/`](frontend) |
| Database | PostgreSQL 17 (SQLite in sviluppo) | — |
| Deploy | Docker Compose + Caddy (HTTPS automatico) su VPS Contabo | [`deploy/`](deploy) |

La sicurezza è il requisito principale: le misure adottate sono descritte in [`SECURITY.md`](SECURITY.md).

## Funzionalità

- **Pipeline Kanban** con drag & drop tra le fasi (configurabili dall'admin), valore e probabilità per fase.
- **Opportunità**: valore, brand, categoria, quantità (pezzi), chiusura prevista, origine, motivo di perdita.
- **Aziende e referenti** (boutique, outlet, grossisti, e-commerce…), con storico opportunità e attività.
- **Attività** con scadenze: in ritardo, oggi, prossime, completate.
- **Dashboard**: pipeline aperta, previsione ponderata, vinto nel mese, tasso di vittoria, pipeline per venditore, chiusure imminenti.
- **Ruoli**:
  - *Venditore*: vede e gestisce solo i propri clienti e le proprie opportunità.
  - *Responsabile vendite*: vede tutto il team, riassegna i record, elimina.
  - *Amministratore*: in più gestisce utenti, fasi della pipeline e registro di audit.

## Sviluppo in locale

Requisiti: PHP 8.4 + Composer, Node.js 20+.

```bash
# Backend
cd backend
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # utenti demo con password "password"
php artisan serve                         # http://127.0.0.1:8000

# Frontend (altro terminale)
cd frontend
npm install
npm run dev                               # http://localhost:5173 (proxy verso il backend)
```

Utenti demo: `admin@brandinstock.test`, `giulia@brandinstock.test`, `marco@brandinstock.test` (password `password`).
L'admin al primo accesso deve configurare la 2FA. Per disattivare l'obbligo in locale, imposta `CRM_REQUIRE_2FA_ROLES=` nel `.env`.

## Test

```bash
cd backend && php artisan test          # 30 test: autenticazione, 2FA, permessi, cifratura, pipeline
cd frontend && npm run typecheck && npm run build
```

## Produzione

Guida passo passo per la VPS Contabo (hardening del server, HTTPS, database, backup cifrati): [`deploy/README-deploy.md`](deploy/README-deploy.md).
