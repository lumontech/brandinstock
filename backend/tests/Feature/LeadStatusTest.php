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
            ['Uno' => 'nuovo', 'Due' => 'in_attesa', 'Tre' => null, 'Quattro' => 'nuovo'],
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

    public function test_contact_person_is_stored_encrypted_and_recovered_from_notes(): void
    {
        $user = User::factory()->create();
        $id = $this->actingAs($user)->postJson('/api/companies', ['name' => 'Alfa', 'contact_person' => 'Ada Rossi'])
            ->assertJsonPath('data.contact_person', 'Ada Rossi')->json('data.id');
        $this->assertStringNotContainsString('Ada', (string) DB::table('companies')->where('id', $id)->value('contact_person'));

        $old = Company::factory()->for($user, 'owner')->create(['notes' => "Nome e Cognome: Mario Bianchi\nStato: Prospect"]);
        $migration = require database_path('migrations/2026_10_07_110000_add_contact_person_to_companies_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame('Mario Bianchi', $old->fresh()->contact_person);
        $this->assertNull(Company::find($id)->contact_person, 'senza riga nelle note resta vuoto');
    }

    public function test_created_by_text_is_removed_from_contact_person(): void
    {
        $user = User::factory()->create();
        $a = Company::factory()->for($user, 'owner')->create(['contact_person' => "Mario Rossi\nCreato da: Stef Brandinstock"]);
        $b = Company::factory()->for($user, 'owner')->create(['contact_person' => 'Creato: Stef Brandinstock']);
        $c = Company::factory()->for($user, 'owner')->create(['contact_person' => 'Lucia Bianchi']);

        (require database_path('migrations/2026_10_07_130000_clean_contact_person_created_by.php'))->up();

        $this->assertSame('Mario Rossi', $a->fresh()->contact_person);
        $this->assertNull($b->fresh()->contact_person);
        $this->assertSame('Lucia Bianchi', $c->fresh()->contact_person);
    }

    public function test_flattened_import_notes_are_repaired(): void
    {
        $user = User::factory()->create();
        $flat = 'Chiamato a giugno Nome e Cognome: Sokol Barjami Stato: In attesa Disponibilià economica: si Hai altri negozi?: no Creato: Stef Brandinstock Data creazione: 30/6/2026';
        $lead = Company::factory()->for($user, 'owner')->create(['notes' => $flat, 'contact_person' => 'Sokol Barjami Stato: In attesa', 'lead_status' => null]);
        $plain = Company::factory()->for($user, 'owner')->create(['notes' => 'Cliente simpatico', 'lead_status' => null]);

        (require database_path('migrations/2026_10_07_140000_repair_flattened_import_notes.php'))->up();

        $lead->refresh();
        $this->assertSame('in_attesa', $lead->lead_status);
        $this->assertSame('Sokol Barjami', $lead->contact_person);
        $this->assertSame("Chiamato a giugno\nNome e Cognome: Sokol Barjami\nStato: In attesa\nDisponibilià economica: si\nHai altri negozi?: no\nData creazione: 30/6/2026", $lead->notes);
        $this->assertSame('Cliente simpatico', $plain->fresh()->notes);
    }

    public function test_import_keeps_line_breaks_in_notes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->postJson('/api/companies/import', ['duplicates' => 'skip', 'rows' => [
            ['name' => 'Uno', 'notes' => "Nome e Cognome: Ada Rossi\nStato:   Prospect"],
        ]])->assertOk();
        $this->assertSame("Nome e Cognome: Ada Rossi\nStato: Prospect", Company::first()->notes);
    }
}
