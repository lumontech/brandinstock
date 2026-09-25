<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorService
{
    public function __construct(private readonly Google2FA $google2fa) {}

    public function generateSecret(): string
    {
        return $this->google2fa->generateSecretKey(32);
    }

    public function otpauthUrl(User $user, string $secret): string
    {
        return $this->google2fa->getQRCodeUrl(config('app.name'), $user->email, $secret);
    }

    /** Verifica un codice TOTP impedendo il riutilizzo dello stesso codice (replay). */
    public function verify(User $user, string $code): bool
    {
        $code = preg_replace('/\s+/', '', $code);
        if (! $user->two_factor_secret || ! preg_match('/^\d{6}$/', $code)) {
            return false;
        }

        if (! $this->google2fa->verifyKey($user->two_factor_secret, $code, 1)) {
            return false;
        }

        return Cache::add("2fa-used:{$user->id}:{$code}", true, now()->addMinutes(3));
    }

    /** Consuma un codice di recupero (monouso). */
    public function useRecoveryCode(User $user, string $code): bool
    {
        $codes = $user->two_factor_recovery_codes ?? [];
        $code = trim($code);

        foreach ($codes as $index => $stored) {
            if (hash_equals($stored, $code)) {
                unset($codes[$index]);
                $user->forceFill(['two_factor_recovery_codes' => array_values($codes)])->save();

                return true;
            }
        }

        return false;
    }

    /** @return list<string> */
    public function generateRecoveryCodes(): array
    {
        return collect(range(1, 8))->map(fn () => Str::lower(Str::random(5).'-'.Str::random(5)))->all();
    }
}
