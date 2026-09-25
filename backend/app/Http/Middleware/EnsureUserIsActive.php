<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** Un utente disattivato perde immediatamente l'accesso, anche con una sessione ancora aperta. */
class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && ! $user->is_active) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();

            return response()->json(['message' => 'Account disattivato.'], 401);
        }

        return $next($request);
    }
}
