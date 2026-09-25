<?php

use App\Http\Middleware\EnsureTwoFactorEnabled;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Autenticazione SPA Sanctum: sessione + protezione CSRF per le richieste dal frontend.
        $middleware->statefulApi();
        $middleware->append(SecurityHeaders::class);
        // Dietro il reverse proxy (Caddy) sulla stessa rete Docker privata.
        $middleware->trustProxies(at: explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1')));
        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'two-factor' => EnsureTwoFactorEnabled::class,
        ]);
        // API only: nessun redirect verso una pagina di login, sempre 401 JSON.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        // Mai loggare le password (nemmeno nei dati di validazione).
        $exceptions->dontFlash(['password', 'password_confirmation', 'current_password', 'code', 'recovery_code']);
    })->create();
