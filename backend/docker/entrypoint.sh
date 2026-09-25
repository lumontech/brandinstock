#!/bin/sh
set -e

# La configurazione viene messa in cache all'avvio (le variabili arrivano dall'ambiente del container).
php artisan config:cache
php artisan route:cache
php artisan event:cache

exec "$@"
