<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LeadStatusTest extends TestCase
{
    public function test_new_leads_start_as_nuovo_and_can_be_filtered(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/companies', ['name' => 'Alfa'])->assertJsonPath('data.lead_status', 'nuovo');
        $this->actingAs($user)->postJson('/api/companies', ['name' => 'Beta', 'lead_status' => 'prospect'])->assertCreated();

        $this->actingAs($user)->getJson('/api/companies?lead_status=prospect')
            ->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Beta');
        $this->actingAs($user)->postJson('/api/companies', ['name' => 'X', 'lead_status' => 'boh'])->assertJsonValidationErrors('lead_status');
    }

    public function test_import_maps_airtable_states(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/companies/import', ['duplicates' => 'skip', 'rows' => [
            ['name' => 'Uno', 'lead_status' => 'Nuova Leads'],
            ['name' => 'Due', 'lead_status' => 'In attesa'],
            ['name' => 'Tre', 'lead_status' => 'Stato strano'],
            ['name' => 'Quattro'],
        ]])->assertOk()->assertJson(['created' => 4]);

        $this->assertSame(
            ['Uno' => 'nuovo', 'Due' => 'in_attesa', 'Tre' => 'nuovo', 'Quattro' => 'nuovo'],
            Company::pluck('lead_status', 'name')->all(),
        );
        $this->assertStringContainsString('Stato: Stato strano', Company::where('name', 'Tre')->first()->notes);
    }

    public function test_bulk_update_sets_lead_status(): void
    {
        $user = User::factory()->create();
        $companies = Company::factory(2)->for($user, 'owner')->create();
        $this->actingAs($user)->postJson('/api/companies/bulk', ['ids' => $companies->modelKeys(), 'action' => 'update', 'changes' => ['lead_status' => 'da_richiamare']])
            ->assertOk()->assertJson(['processed' => 2]);
        $this->assertSame(2, Company::where('lead_status', 'da_richiamare')->count());
    }

    public function test_migration_recovers_status_from_imported_notes(): void
    {
        $user = User::factory()->create();
        $a = Company::factory()->for($user, 'owner')->create(['notes' => "Referente: Ada\nStato: Prospect\nEsito: Positivo"]);
        $b = Company::factory()->for($user, 'owner')->create(['notes' => 'Nessuno stato']);
        DB::table('companies')->update(['lead_status' => null]);

        $migration = require database_path('migrations/2026_10_07_100000_add_lead_status_to_companies_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame('prospect', $a->fresh()->lead_status);
        $this->assertNull($b->fresh()->lead_status);
        $this->assertNotSame('Nessuno stato', DB::table('companies')->where('id', $b->id)->value('notes'), 'le note restano cifrate');
        $this->assertSame('Nessuno stato', Crypt::decryptString(DB::table('companies')->where('id', $b->id)->value('notes')));
    }
}
