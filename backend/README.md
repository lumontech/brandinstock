# Brandinstock CRM — Backend (Laravel API)

API REST del CRM. Documentazione generale, avvio in locale e sicurezza: vedi [`../README.md`](../README.md) e [`../SECURITY.md`](../SECURITY.md).

```bash
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # dati demo, solo in locale
php artisan serve                         # http://127.0.0.1:8000
php artisan test                          # test
vendor/bin/pint                           # formattazione
```
