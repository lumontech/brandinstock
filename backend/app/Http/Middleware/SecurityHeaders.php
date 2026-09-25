<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        // Le risposte API non devono mai eseguire contenuti né essere messe in cache.
        $headers->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'");
        if (! $headers->has('Cache-Control') || $request->is('api/*')) {
            $headers->set('Cache-Control', 'no-store, private');
        }
        $headers->remove('X-Powered-By');

        if (app()->isProduction()) {
            $headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }

        return $response;
    }
}
