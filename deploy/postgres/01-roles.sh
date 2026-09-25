#!/bin/bash
# Eseguito una sola volta alla creazione del volume PostgreSQL.
# Crea due ruoli separati (principio del privilegio minimo):
#   - DB_MIGRATION_USERNAME: proprietario dello schema, usato SOLO per le migration;
#   - DB_USERNAME: usato dall'app a runtime, può solo leggere/scrivere dati (niente DDL).
set -euo pipefail

psql -v ON_ERROR_STOP=1 \
  --username "$POSTGRES_USER" --dbname "$POSTGRES_DB" \
  -v db="$POSTGRES_DB" \
  -v owner="$DB_MIGRATION_USERNAME" -v owner_pw="$DB_MIGRATION_PASSWORD" \
  -v app="$DB_USERNAME" -v app_pw="$DB_PASSWORD" <<'EOSQL'
CREATE ROLE :"owner" LOGIN PASSWORD :'owner_pw';
CREATE ROLE :"app" LOGIN PASSWORD :'app_pw' CONNECTION LIMIT 50;

REVOKE ALL ON DATABASE :"db" FROM PUBLIC;
GRANT CONNECT ON DATABASE :"db" TO :"owner", :"app";

REVOKE ALL ON SCHEMA public FROM PUBLIC;
ALTER SCHEMA public OWNER TO :"owner";
GRANT USAGE ON SCHEMA public TO :"app";

-- Ogni tabella/sequenza creata dalle migration è accessibile all'app solo in DML.
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner" IN SCHEMA public
  GRANT SELECT, INSERT, UPDATE, DELETE ON TABLES TO :"app";
ALTER DEFAULT PRIVILEGES FOR ROLE :"owner" IN SCHEMA public
  GRANT USAGE, SELECT ON SEQUENCES TO :"app";

-- Protezione da query anomale / connessioni appese.
ALTER ROLE :"app" SET statement_timeout = '30s';
ALTER ROLE :"app" SET idle_in_transaction_session_timeout = '60s';
EOSQL
