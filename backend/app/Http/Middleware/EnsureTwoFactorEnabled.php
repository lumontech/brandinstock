<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocca l'accesso ai dati del CRM ai ruoli per cui la 2FA è obbligatoria finché non l'hanno attivata. */
class EnsureTwoFactorEnabled
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && $user->requiresTwoFactor() && ! $user->hasTwoFactorEnabled()) {
            return response()->json([
                'message' => "Per il tuo ruolo è obbligatorio attivare l'autenticazione a due fattori.",
                'code' => 'two_factor_setup_required',
            ], 403);
        }

        return $next($request);
    }
}
