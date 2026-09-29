<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminCommandTest extends TestCase
{
    public function test_admin_can_be_created_with_generated_password(): void
    {
        $exit = Artisan::call('crm:create-admin', ['email' => 'Boss@Example.com', '--generate-password' => true]);

        $this->assertSame(0, $exit);
        preg_match('/GENERATED_PASSWORD=(\S+)/', Artisan::output(), $m);
        $user = User::where('email', 'boss@example.com')->firstOrFail();

        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertTrue(Hash::check($m[1], $user->password));
        $this->assertGreaterThanOrEqual(20, strlen($m[1]));
    }
}
