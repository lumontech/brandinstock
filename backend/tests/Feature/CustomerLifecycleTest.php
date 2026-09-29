<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\User;
use Tests\TestCase;

class CustomerLifecycleTest extends TestCase
{
    public function test_new_clients_start_as_leads(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/companies', ['name' => 'Nuovo contatto'])->assertJsonPath('data.status', 'lead');
    }

    public function test_winning_a_deal_turns_the_lead_into_a_customer(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->for($user, 'owner')->create();
        $deal = Deal::factory()->for($company)->for($user, 'owner')->create();
        $this->assertSame('lead', $company->fresh()->status);

        $won = PipelineStage::where('is_won', true)->value('id');
        $this->actingAs($user)->patchJson("/api/deals/{$deal->id}/move", ['pipeline_stage_id' => $won])->assertOk();

        $company->refresh();
        $this->assertSame('customer', $company->status);
        $this->assertNotNull($company->converted_at);
    }

    public function test_leads_and_customers_are_listed_separately(): void
    {
        $user = User::factory()->create();
        Company::factory(2)->for($user, 'owner')->create();
        Company::factory()->for($user, 'owner')->create(['status' => 'customer']);

        $this->actingAs($user)->getJson('/api/companies?status=lead')->assertJsonCount(2, 'data');
        $this->actingAs($user)->getJson('/api/companies?status=customer')->assertJsonCount(1, 'data')->assertJsonPath('data.0.billing_complete', false);
    }

    public function test_billing_is_complete_with_fiscal_id_address_and_sdi_or_pec(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->for($user, 'owner')->create(['status' => 'customer', 'vat_number' => 'IT01234567890']);

        $this->actingAs($user)->putJson("/api/companies/{$company->id}", [
            'billing_address' => 'Via Roma 1', 'billing_zip' => '20100', 'billing_city' => 'Milano', 'sdi_code' => '0000000',
        ])->assertJsonPath('data.billing_complete', true);

        $this->actingAs($user)->putJson("/api/companies/{$company->id}", ['sdi_code' => 'XX'])->assertJsonValidationErrors('sdi_code');
        $this->actingAs($user)->putJson("/api/companies/{$company->id}", ['iban' => 'non-un-iban'])->assertJsonValidationErrors('iban');
    }

    public function test_status_can_be_changed_manually(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->putJson("/api/companies/{$company->id}", ['status' => 'customer'])->assertJsonPath('data.status', 'customer');
        $this->assertNotNull($company->fresh()->converted_at);
        $this->actingAs($user)->putJson("/api/companies/{$company->id}", ['status' => 'lead'])->assertJsonPath('data.status', 'lead');
    }
}
