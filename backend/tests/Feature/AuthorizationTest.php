<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Company;
use App\Models\Deal;
use App\Models\User;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    public function test_sales_only_see_their_own_deals(): void
    {
        [$giulia, $marco] = User::factory(2)->create();
        $mine = Deal::factory()->for($giulia, 'owner')->create();
        $other = Deal::factory()->for($marco, 'owner')->create();

        $this->actingAs($giulia)->getJson('/api/deals')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);

        $this->actingAs($giulia)->getJson("/api/deals/{$other->id}")->assertForbidden();
        $this->actingAs($giulia)->putJson("/api/deals/{$other->id}", ['title' => 'x'])->assertForbidden();
        $this->actingAs($giulia)->getJson("/api/companies/{$other->company_id}")->assertForbidden();
    }

    public function test_sales_cannot_link_records_of_other_sellers(): void
    {
        [$giulia, $marco] = User::factory(2)->create();
        $foreignCompany = Company::factory()->for($marco, 'owner')->create();

        $this->actingAs($giulia)->postJson('/api/deals', [
            'title' => 'Tentativo',
            'company_id' => $foreignCompany->id,
        ])->assertUnprocessable()->assertJsonValidationErrors('company_id');
    }

    public function test_sales_cannot_assign_deals_to_others_or_delete(): void
    {
        [$giulia, $marco] = User::factory(2)->create();
        $company = Company::factory()->for($giulia, 'owner')->create();

        $id = $this->actingAs($giulia)->postJson('/api/deals', [
            'title' => 'Stock Guess',
            'company_id' => $company->id,
            'owner_id' => $marco->id,
        ])->assertCreated()->json('data.id');

        $this->assertSame($giulia->id, Deal::find($id)->owner_id);
        $this->actingAs($giulia)->deleteJson("/api/deals/{$id}")->assertForbidden();
    }

    public function test_manager_sees_everything_and_can_reassign(): void
    {
        $manager = User::factory()->manager()->create(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
        [$giulia, $marco] = User::factory(2)->create();
        $deal = Deal::factory()->for($giulia, 'owner')->create();
        Deal::factory()->for($marco, 'owner')->create();

        $this->actingAs($manager)->getJson('/api/deals')->assertJsonCount(2, 'data');
        $this->actingAs($manager)->putJson("/api/deals/{$deal->id}", ['owner_id' => $marco->id])->assertOk();

        $this->assertSame($marco->id, $deal->fresh()->owner_id);
    }

    public function test_only_admin_manages_users_and_role_cannot_be_mass_assigned(): void
    {
        $sales = User::factory()->create();

        $this->actingAs($sales)->getJson('/api/users')->assertForbidden();
        $this->actingAs($sales)->postJson('/api/users', [])->assertForbidden();
        $this->actingAs($sales)->putJson("/api/users/{$sales->id}", ['role' => 'admin'])->assertForbidden();

        $this->assertSame(UserRole::Sales, $sales->fresh()->role);
    }

    public function test_admin_can_create_users_with_strong_password_only(): void
    {
        $admin = $this->adminWithTwoFactor();

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Nuovo', 'email' => 'nuovo@example.com', 'role' => 'sales',
            'password' => 'debole', 'password_confirmation' => 'debole',
        ])->assertJsonValidationErrors('password');

        $this->actingAs($admin)->postJson('/api/users', [
            'name' => 'Nuovo', 'email' => 'Nuovo@Example.com', 'role' => 'sales',
            'password' => 'Brandinstock2026!', 'password_confirmation' => 'Brandinstock2026!',
        ])->assertCreated()->assertJsonPath('data.email', 'nuovo@example.com');
    }

    public function test_admin_cannot_demote_themselves(): void
    {
        $admin = $this->adminWithTwoFactor();

        $this->actingAs($admin)->putJson("/api/users/{$admin->id}", ['role' => 'sales'])->assertStatus(422);
    }

    public function test_only_admin_can_configure_stages(): void
    {
        $sales = User::factory()->create();

        $this->actingAs($sales)->putJson('/api/stages', ['stages' => []])->assertForbidden();
    }

    public function test_audit_log_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create())->getJson('/api/audit-logs')->assertForbidden();
        $this->actingAs($this->adminWithTwoFactor())->getJson('/api/audit-logs')->assertOk();
    }
}
