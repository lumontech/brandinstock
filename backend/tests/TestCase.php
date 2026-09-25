<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        // Le richieste arrivano dalla SPA: abilita la sessione Sanctum (stateful).
        $this->withHeader('Origin', 'http://localhost');
    }

    protected function adminWithTwoFactor(): User
    {
        return User::factory()->admin()->create([
            'two_factor_secret' => 'JBSWY3DPEHPK3PXP',
            'two_factor_confirmed_at' => now(),
        ]);
    }
}
