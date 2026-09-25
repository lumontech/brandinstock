<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Services\TwoFactorService;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    public function test_user_can_login_and_fetch_profile(): void
    {
        $user = User::factory()->create(['email' => 'giulia@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'giulia@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('user.id', $user->id)
            ->assertJsonMissingPath('user.password');

        $this->getJson('/api/auth/me')->assertOk()->assertJsonPath('data.email', 'giulia@example.com');
        $this->assertDatabaseHas('audit_logs', ['event' => 'login', 'user_id' => $user->id]);
    }

    public function test_wrong_password_and_unknown_email_return_same_generic_error(): void
    {
        User::factory()->create(['email' => 'giulia@example.com']);

        $wrong = $this->postJson('/api/auth/login', ['email' => 'giulia@example.com', 'password' => 'sbagliata'])->assertUnprocessable();
        $unknown = $this->postJson('/api/auth/login', ['email' => 'nessuno@example.com', 'password' => 'sbagliata'])->assertUnprocessable();

        $this->assertSame($wrong->json('errors'), $unknown->json('errors'));
        $this->assertSame(2, AuditLog::where('event', 'login_failed')->count());
    }

    public function test_inactive_user_cannot_login(): void
    {
        User::factory()->inactive()->create(['email' => 'ex@example.com']);

        $this->postJson('/api/auth/login', ['email' => 'ex@example.com', 'password' => 'password'])->assertUnprocessable();
    }

    public function test_deactivated_user_loses_access_immediately(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->getJson('/api/pipeline')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user->fresh())->getJson('/api/pipeline')->assertUnauthorized();
    }

    public function test_login_is_rate_limited(): void
    {
        User::factory()->create(['email' => 'giulia@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', ['email' => 'giulia@example.com', 'password' => 'x'])->assertUnprocessable();
        }

        $this->postJson('/api/auth/login', ['email' => 'giulia@example.com', 'password' => 'password'])->assertTooManyRequests();
    }

    public function test_guest_cannot_access_crm_data(): void
    {
        $this->getJson('/api/deals')->assertUnauthorized();
        $this->getJson('/api/companies')->assertUnauthorized();
    }

    public function test_two_factor_login_flow(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        User::factory()->create([
            'email' => 'sec@example.com',
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
        ]);

        $this->postJson('/api/auth/login', ['email' => 'sec@example.com', 'password' => 'password'])
            ->assertOk()->assertJson(['two_factor' => true]);

        // Senza il secondo fattore l'utente NON è autenticato.
        $this->getJson('/api/auth/me')->assertUnauthorized();

        $this->postJson('/api/auth/two-factor-challenge', ['code' => '000000'])->assertUnprocessable();

        $code = $google2fa->getCurrentOtp($secret);
        $this->postJson('/api/auth/two-factor-challenge', ['code' => $code])->assertOk()->assertJsonPath('user.email', 'sec@example.com');
        $this->getJson('/api/auth/me')->assertOk();
    }

    public function test_totp_code_cannot_be_reused(): void
    {
        $google2fa = new Google2FA;
        $secret = $google2fa->generateSecretKey();
        $user = User::factory()->create(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now()]);
        $service = app(TwoFactorService::class);
        $code = $google2fa->getCurrentOtp($secret);

        $this->assertTrue($service->verify($user, $code));
        $this->assertFalse($service->verify($user, $code));
    }

    public function test_admin_without_two_factor_is_forced_to_enable_it(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/deals')
            ->assertForbidden()
            ->assertJsonPath('code', 'two_factor_setup_required');

        // Può comunque accedere alle rotte per configurare la 2FA.
        $this->actingAs($admin)->postJson('/api/auth/two-factor', ['password' => 'password'])
            ->assertOk()->assertJsonStructure(['secret', 'otpauth_url']);
    }

    public function test_enable_and_confirm_two_factor(): void
    {
        $user = User::factory()->create();
        $secret = $this->actingAs($user)->postJson('/api/auth/two-factor', ['password' => 'password'])->json('secret');

        $code = (new Google2FA)->getCurrentOtp($secret);
        $this->actingAs($user)->postJson('/api/auth/two-factor/confirm', ['code' => $code])
            ->assertOk()->assertJsonCount(8, 'recovery_codes');

        $this->assertTrue($user->fresh()->hasTwoFactorEnabled());
    }

    public function test_responses_include_security_headers(): void
    {
        $this->getJson('/api/auth/me')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
