<?php

return [
    // Ruoli per cui l'autenticazione a due fattori è obbligatoria.
    'require_two_factor_for' => array_filter(explode(',', (string) env('CRM_REQUIRE_2FA_ROLES', 'admin,manager'))),

    // Tentativi di login consentiti al minuto per coppia email+IP.
    'login_attempts_per_minute' => (int) env('CRM_LOGIN_ATTEMPTS', 5),

    'currency' => env('CRM_CURRENCY', 'EUR'),

    // Chiave HMAC per gli indici ciechi (ricerca esatta su campi cifrati, es. email).
    // Separata da APP_KEY: ruotare APP_KEY non deve invalidare gli indici.
    'blind_index_key' => env('CRM_BLIND_INDEX_KEY') ?: env('APP_KEY'),
];
