<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // In sviluppo segnala lazy loading e attributi inesistenti (errori silenziosi = bug di sicurezza).
        Model::shouldBeStrict(! $this->app->isProduction());

        Password::defaults(function () {
            $rule = Password::min(12)->letters()->mixedCase()->numbers();

            // In produzione rifiuta password comparse in data breach noti (Have I Been Pwned, k-anonymity).
            return $this->app->isProduction() ? $rule->uncompromised() : $rule;
        });

        RateLimiter::for('login', function (Request $request) {
            $email = mb_strtolower((string) $request->input('email'));
            $perMinute = config('crm.login_attempts_per_minute');

            return [
                Limit::perMinute($perMinute)->by('login:'.$email.'|'.$request->ip()),
                Limit::perMinute($perMinute * 4)->by('login-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(6)->by('sensitive:'.$request->user()?->id));

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(240)->by('api:'.($request->user()?->id ?: $request->ip())));
    }
}
