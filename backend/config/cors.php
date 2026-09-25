<?php

/*
| In produzione frontend e API sono serviti dallo stesso dominio (Caddy),
| quindi CORS non è necessario. In sviluppo il dev server Vite fa da proxy.
| Se in futuro il frontend fosse su un'origine diversa, elencarla qui:
| mai usare '*' insieme a supports_credentials.
*/

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'],
    'allowed_origins' => array_filter(explode(',', (string) env('CORS_ALLOWED_ORIGINS', ''))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['Content-Type', 'X-Requested-With', 'X-XSRF-TOKEN', 'Accept'],
    'exposed_headers' => [],
    'max_age' => 600,
    'supports_credentials' => true,
];
