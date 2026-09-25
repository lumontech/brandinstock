<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    private const PENDING_2FA_KEY = 'login.two_factor';

    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'remember' => ['boolean'],
        ]);

        $user = User::where('email', mb_strtolower($credentials['email']))->first();

        // Verifica eseguita sempre, anche se l'utente non esiste, per non rivelare
        // quali email sono registrate (timing uniforme, messaggio generico).
        $valid = Hash::check($credentials['password'], $user?->password ?? '$2y$12$'.str_repeat('x', 53));

        if (! $user || ! $valid || ! $user->is_active) {
            AuditLog::record('login_failed', $user, ['email' => $credentials['email']]);

            throw ValidationException::withMessages(['email' => 'Credenziali non valide.']);
        }

        if ($user->hasTwoFactorEnabled()) {
            $request->session()->put(self::PENDING_2FA_KEY, [
                'user_id' => $user->id,
                'remember' => $request->boolean('remember'),
                'expires_at' => now()->addMinutes(5)->timestamp,
            ]);

            return response()->json(['two_factor' => true]);
        }

        return $this->completeLogin($request, $user, $request->boolean('remember'));
    }

    public function twoFactorChallenge(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:10'],
            'recovery_code' => ['nullable', 'string', 'max:20'],
        ]);

        $pending = $request->session()->get(self::PENDING_2FA_KEY);
        if (! $pending || $pending['expires_at'] < now()->timestamp) {
            $request->session()->forget(self::PENDING_2FA_KEY);
            throw ValidationException::withMessages(['code' => 'Sessione di accesso scaduta, effettua di nuovo il login.']);
        }

        $user = User::find($pending['user_id']);
        $ok = $user && $user->is_active && (
            (! empty($data['code']) && $this->twoFactor->verify($user, $data['code']))
            || (! empty($data['recovery_code']) && $this->twoFactor->useRecoveryCode($user, $data['recovery_code']))
        );

        if (! $ok) {
            AuditLog::record('two_factor_failed', $user, null, $user?->id);
            throw ValidationException::withMessages(['code' => 'Codice non valido.']);
        }

        $request->session()->forget(self::PENDING_2FA_KEY);

        return $this->completeLogin($request, $user, $pending['remember']);
    }

    public function logout(Request $request): JsonResponse
    {
        AuditLog::record('logout', $request->user());
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    public function me(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function updatePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = $request->user();
        $user->forceFill(['password' => $data['password']])->save();

        // Invalida le sessioni aperte su altri dispositivi.
        Auth::guard('web')->logoutOtherDevices($data['password']);
        AuditLog::record('password_changed', $user);

        return response()->json(['message' => 'Password aggiornata.']);
    }

    public function enableTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();

        $secret = $this->twoFactor->generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $this->twoFactor->otpauthUrl($user, $secret),
        ]);
    }

    public function confirmTwoFactor(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:10']]);
        $user = $request->user();

        if (! $user->two_factor_secret || ! $this->twoFactor->verify($user, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Codice non valido.']);
        }

        $codes = $this->twoFactor->generateRecoveryCodes();
        $user->forceFill([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $codes,
        ])->save();
        AuditLog::record('two_factor_enabled', $user);

        // I codici di recupero vengono mostrati una sola volta.
        return response()->json(['recovery_codes' => $codes]);
    }

    public function disableTwoFactor(Request $request): JsonResponse
    {
        $request->validate(['password' => ['required', 'current_password']]);
        $user = $request->user();

        if ($user->requiresTwoFactor()) {
            return response()->json(['message' => 'Per il tuo ruolo la 2FA non può essere disattivata.'], 403);
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ])->save();
        AuditLog::record('two_factor_disabled', $user);

        return response()->json(null, 204);
    }

    private function completeLogin(Request $request, User $user, bool $remember): JsonResponse
    {
        Auth::guard('web')->login($user, $remember);
        // Nuovo ID di sessione dopo l'autenticazione (anti session fixation).
        $request->session()->regenerate();

        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->saveQuietly();
        AuditLog::record('login', $user, null, $user->id);

        return response()->json(['two_factor' => false, 'user' => new UserResource($user)]);
    }
}
