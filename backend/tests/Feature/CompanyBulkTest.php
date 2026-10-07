<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\User;
use Tests\TestCase;

class CompanyBulkTest extends TestCase
{
    private function manager(): User
    {
        return User::factory()->manager()->create(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
    }

    public function test_bulk_update_changes_selected_records(): void
    {
        $user = User::factory()->create();
        $companies = Company::factory(3)->for($user, 'owner')->create(['segment' => 'b2b', 'source' => null]);

        $this->actingAs($user)->postJson('/api/companies/bulk', [
            'ids' => $companies->pluck('id')->take(2)->all(),
            'action' => 'update',
            'changes' => ['segment' => 'franchising', 'source' => 'fiera'],
        ])->assertOk()->assertJson(['processed' => 2, 'skipped' => 0]);

        $this->assertSame(2, Company::where('segment', 'franchising')->where('source', 'fiera')->count());
        $this->assertDatabaseHas('audit_logs', ['event' => 'companies_bulk_update']);
    }

    public function test_bulk_status_change_moves_leads_to_customers(): void
    {
        $user = User::factory()->create();
        $ids = Company::factory(2)->for($user, 'owner')->create()->pluck('id')->all();

        $this->actingAs($user)->postJson('/api/companies/bulk', ['ids' => $ids, 'action' => 'update', 'changes' => ['status' => 'customer']])
            ->assertJson(['processed' => 2]);

        $this->assertSame(2, Company::where('status', 'customer')->whereNotNull('converted_at')->count());
    }

    public function test_sales_cannot_touch_other_sellers_records_or_delete(): void
    {
        [$giulia, $marco] = User::factory(2)->create();
        $mine = Company::factory()->for($giulia, 'owner')->create(['segment' => 'b2b']);
        $theirs = Company::factory()->for($marco, 'owner')->create(['segment' => 'b2b']);

        $this->actingAs($giulia)->postJson('/api/companies/bulk', ['ids' => [$mine->id, $theirs->id], 'action' => 'update', 'changes' => ['segment' => 'b2c']])
            ->assertJson(['processed' => 1, 'skipped' => 1]);
        $this->assertSame('b2b', $theirs->fresh()->segment);

        $this->actingAs($giulia)->postJson('/api/companies/bulk', ['ids' => [$mine->id], 'action' => 'delete'])->assertForbidden();
        $this->actingAs($giulia)->postJson('/api/companies/bulk', ['ids' => [$mine->id], 'action' => 'update', 'changes' => ['owner_id' => $marco->id]])->assertForbidden();
    }

    public function test_manager_bulk_delete_keeps_records_with_deals(): void
    {
        $manager = $this->manager();
        $seller = User::factory()->create();
        $empty = Company::factory(2)->for($seller, 'owner')->create();
        $withDeal = Company::factory()->for($seller, 'owner')->create();
        Deal::factory()->for($withDeal)->for($seller, 'owner')->create();

        $this->actingAs($manager)->postJson('/api/companies/bulk', [
            'ids' => [...$empty->pluck('id')->all(), $withDeal->id],
            'action' => 'delete',
        ])->assertOk()->assertJson(['processed' => 2, 'skipped_with_deals' => 1]);

        $this->assertSame(1, Company::count());
        $this->assertSame(2, Company::onlyTrashed()->count());
    }

    public function test_bulk_delete_with_deals_removes_everything_linked(): void
    {
        $manager = $this->manager();
        $company = Company::factory()->for($manager, 'owner')->create();
        $deal = Deal::factory()->for($company)->for($manager, 'owner')->create();
        Contact::factory()->for($company)->for($manager, 'owner')->create();
        $this->actingAs($manager)->postJson('/api/activities', ['type' => 'note', 'subject' => 'Chiamata', 'deal_id' => $deal->id])->assertCreated();

        $this->actingAs($manager)->postJson('/api/companies/bulk', [
            'ids' => [$company->id],
            'action' => 'delete',
            'with_deals' => true,
        ])->assertOk()->assertJson(['processed' => 1, 'skipped_with_deals' => 0, 'deals_deleted' => 1]);

        $this->assertSame(0, Company::count());
        $this->assertSame(0, Deal::count());
        $this->assertSame(0, $company->contacts()->count());
        $this->assertSame(0, Activity::count());
        $this->assertSame(1, Deal::onlyTrashed()->count());
    }

    public function test_single_delete_removes_record_with_deals(): void
    {
        $manager = $this->manager();
        $company = Company::factory()->for($manager, 'owner')->create();
        Deal::factory()->for($company)->for($manager, 'owner')->create();

        $this->actingAs($manager)->deleteJson("/api/companies/{$company->id}")->assertNoContent();
        $this->assertSame(0, Company::count());
        $this->assertSame(0, Deal::count());
    }

    public function test_manager_can_reassign_in_bulk(): void
    {
        $manager = $this->manager();
        [$giulia, $marco] = User::factory(2)->create();
        $ids = Company::factory(2)->for($giulia, 'owner')->create()->pluck('id')->all();

        $this->actingAs($manager)->postJson('/api/companies/bulk', ['ids' => $ids, 'action' => 'update', 'changes' => ['owner_id' => $marco->id]])
            ->assertJson(['processed' => 2]);
        $this->assertSame(2, Company::where('owner_id', $marco->id)->count());
    }
}
