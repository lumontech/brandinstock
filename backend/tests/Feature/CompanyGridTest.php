<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\User;
use Tests\TestCase;

class CompanyGridTest extends TestCase
{
    public function test_grid_returns_aggregates_for_each_company(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->for($user, 'owner')->create();
        Contact::factory(2)->for($company)->for($user, 'owner')->create();
        Deal::factory()->for($company)->for($user, 'owner')->create(['value' => 1000]);
        Deal::factory()->for($company)->for($user, 'owner')->create(['value' => 500]);
        Deal::factory()->for($company)->for($user, 'owner')->create([
            'value' => 9999,
            'pipeline_stage_id' => PipelineStage::where('is_won', true)->value('id'),
        ]);
        Activity::factory()->create(['user_id' => $user->id, 'company_id' => $company->id]);

        $row = $this->actingAs($user)->getJson('/api/companies')->assertOk()->json('data.0');

        $this->assertSame(3, $row['deals_count']);
        $this->assertSame(2, $row['contacts_count']);
        $this->assertEquals(1500, $row['open_deals_value']);
        $this->assertNotNull($row['last_activity_at']);
    }

    public function test_grid_can_be_sorted_by_whitelisted_columns_only(): void
    {
        $user = User::factory()->create();
        $small = Company::factory()->for($user, 'owner')->create(['name' => 'Alfa']);
        $big = Company::factory()->for($user, 'owner')->create(['name' => 'Zeta']);
        Deal::factory()->for($big)->for($user, 'owner')->create(['value' => 50000]);
        Deal::factory()->for($small)->for($user, 'owner')->create(['value' => 10]);

        $this->actingAs($user)->getJson('/api/companies?sort=open_deals_value&direction=desc')
            ->assertOk()->assertJsonPath('data.0.id', $big->id);
        $this->actingAs($user)->getJson('/api/companies?sort=name&direction=desc')
            ->assertJsonPath('data.0.name', 'Zeta');
        $this->actingAs($user)->getJson('/api/companies?sort=owner')->assertOk();

        // Colonne cifrate o arbitrarie non sono ordinabili.
        $this->actingAs($user)->getJson('/api/companies?sort=email')->assertJsonValidationErrors('sort');
        $this->actingAs($user)->getJson('/api/companies?sort=password')->assertJsonValidationErrors('sort');
    }

    public function test_grid_page_size_is_capped(): void
    {
        $user = User::factory()->create();
        Company::factory(30)->for($user, 'owner')->create();

        $this->actingAs($user)->getJson('/api/companies?per_page=100')->assertJsonCount(30, 'data');
        $this->actingAs($user)->getJson('/api/companies?per_page=5000')->assertJsonValidationErrors('per_page');
    }

    public function test_single_cell_can_be_updated_inline(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->for($user, 'owner')->create(['city' => 'Milano', 'name' => 'Boutique X']);

        $this->actingAs($user)->putJson("/api/companies/{$company->id}", ['city' => 'Torino'])
            ->assertOk()->assertJsonPath('data.city', 'Torino')->assertJsonPath('data.name', 'Boutique X');
        $this->actingAs($user)->putJson("/api/companies/{$company->id}", ['email' => 'non-una-email'])
            ->assertJsonValidationErrors('email');
    }

    public function test_sales_do_not_see_aggregates_of_other_sellers_deals(): void
    {
        [$giulia, $marco] = User::factory(2)->create();
        $company = Company::factory()->for($giulia, 'owner')->create();
        Deal::factory()->for($company)->for($giulia, 'owner')->create(['value' => 100]);
        Deal::factory()->for($company)->for($marco, 'owner')->create(['value' => 5000]);

        $row = $this->actingAs($giulia)->getJson('/api/companies')->json('data.0');

        $this->assertSame(1, $row['deals_count']);
        $this->assertEquals(100, $row['open_deals_value']);
    }

    public function test_clients_can_be_filtered_by_segment(): void
    {
        $user = User::factory()->create();
        Company::factory()->for($user, 'owner')->create(['segment' => 'b2b']);
        Company::factory(2)->for($user, 'owner')->create(['segment' => 'franchising']);

        $this->actingAs($user)->getJson('/api/companies?segment=franchising')->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson('/api/companies?segment=altro')->assertJsonValidationErrors('segment');
    }

    public function test_b2c_client_tax_code_is_validated_and_encrypted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/companies', ['name' => 'Mario Rossi', 'segment' => 'b2c', 'tax_code' => 'x'])
            ->assertJsonValidationErrors('tax_code');

        $id = $this->actingAs($user)->postJson('/api/companies', ['name' => 'Mario Rossi', 'segment' => 'b2c', 'tax_code' => 'RSSMRA80A01F205X'])
            ->assertCreated()->assertJsonPath('data.segment', 'b2c')->assertJsonPath('data.tax_code', 'RSSMRA80A01F205X')->json('data.id');

        $this->assertStringNotContainsString('RSSMRA', \DB::table('companies')->where('id', $id)->value('tax_code'));
    }

    public function test_new_clients_default_to_b2b(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/companies', ['name' => 'Boutique Y'])->assertCreated()->assertJsonPath('data.segment', 'b2b');
    }

    public function test_client_lead_source_can_be_set_filtered_and_is_inherited_by_new_deals(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->for($user, 'owner')->create(['source' => null]);
        Company::factory()->for($user, 'owner')->create(['source' => 'fiera']);

        $this->actingAs($user)->putJson("/api/companies/{$company->id}", ['source' => 'linkedin'])->assertJsonPath('data.source', 'linkedin');
        $this->actingAs($user)->putJson("/api/companies/{$company->id}", ['source' => 'piccione'])->assertJsonValidationErrors('source');
        $this->actingAs($user)->getJson('/api/companies?source=linkedin')->assertJsonCount(1, 'data');

        $this->actingAs($user)->postJson('/api/deals', ['title' => 'Stock', 'company_id' => $company->id])->assertJsonPath('data.source', 'linkedin');
        $this->actingAs($user)->postJson('/api/deals', ['title' => 'Stock 2', 'company_id' => $company->id, 'source' => 'email'])->assertJsonPath('data.source', 'email');

        $report = collect($this->actingAs($user)->getJson('/api/dashboard')->json('clients_by_source'))->keyBy('source');
        $this->assertSame(1, $report['linkedin']['clients_count']);
        $this->assertSame(1, $report['fiera']['clients_count']);
    }
}
