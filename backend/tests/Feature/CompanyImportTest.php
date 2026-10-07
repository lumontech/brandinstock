<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CompanyImportTest extends TestCase
{
    private function rows(): array
    {
        return [
            ['name' => 'Boutique Aurora', 'segment' => 'B2B', 'type' => 'Boutique', 'vat_number' => 'IT 012 345 678 90', 'city' => 'Milano', 'website' => 'aurora.it', 'contact_name' => 'Anna Ferri', 'contact_email' => 'Anna@Aurora.it'],
            ['name' => 'Mario Rossi', 'segment' => 'Privato', 'tax_code' => 'rssmra80a01f205x', 'email' => 'mario@example.com'],
            ['name' => 'Moda Franchising Srl', 'segment' => 'Franchising', 'type' => 'catena'],
            ['name' => '', 'city' => 'Roma'],
            ['name' => 'Email sbagliata', 'email' => 'non-email'],
        ];
    }

    public function test_dry_run_reports_results_without_saving(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => $this->rows(), 'duplicates' => 'skip', 'dry_run' => true])
            ->assertOk()
            ->assertJson(['created' => 4, 'contacts_created' => 1, 'dry_run' => true])
            ->assertJsonCount(1, 'errors')
            ->assertJsonPath('errors.0.row', 3);

        $this->assertSame(0, Company::count());
        $this->assertSame(0, Contact::count());
    }

    public function test_import_creates_clients_with_normalized_values_and_contacts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => $this->rows(), 'duplicates' => 'skip'])
            ->assertOk()->assertJson(['created' => 4, 'contacts_created' => 1, 'dry_run' => false]);

        // Un'email non valida non blocca la riga: finisce nelle note.
        $this->assertStringContainsString('non-email', Company::where('name', 'Email sbagliata')->first()->notes);

        $aurora = Company::where('name', 'Boutique Aurora')->first();
        $this->assertSame('b2b', $aurora->segment);
        $this->assertSame('boutique', $aurora->type);
        $this->assertSame('IT01234567890', $aurora->vat_number);
        $this->assertSame('https://aurora.it', $aurora->website);
        $this->assertSame($user->id, $aurora->owner_id);
        $this->assertSame('Anna', $aurora->contacts()->first()->first_name);
        $this->assertSame('b2c', Company::where('name', 'Mario Rossi')->value('segment'));
        $this->assertSame('RSSMRA80A01F205X', Company::where('name', 'Mario Rossi')->first()->tax_code);
        $this->assertDatabaseHas('audit_logs', ['event' => 'companies_imported']);
    }

    public function test_duplicates_are_skipped_or_updated_without_erasing_data(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->for($user, 'owner')->create(['name' => 'Boutique Aurora', 'vat_number' => 'IT01234567890', 'city' => 'Milano', 'phone' => '02 123']);

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => [['name' => 'boutique aurora', 'city' => 'Torino']], 'duplicates' => 'skip'])
            ->assertJson(['created' => 0, 'skipped' => 1]);
        $this->assertSame('Milano', $company->fresh()->city);

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => [['name' => 'Aurora', 'vat_number' => 'IT01234567890', 'city' => 'Torino']], 'duplicates' => 'update'])
            ->assertJson(['updated' => 1]);
        $this->assertSame('Torino', $company->fresh()->city);
        $this->assertSame('02 123', $company->fresh()->phone);
    }

    public function test_sales_cannot_import_over_another_sellers_client(): void
    {
        [$giulia, $marco] = User::factory(2)->create();
        $company = Company::factory()->for($marco, 'owner')->create(['vat_number' => 'IT99999999999']);

        $this->actingAs($giulia)->postJson('/api/companies/import', ['rows' => [['name' => 'X', 'vat_number' => 'IT99999999999', 'city' => 'Hack']], 'duplicates' => 'update'])
            ->assertJson(['updated' => 0, 'created' => 0])->assertJsonCount(1, 'errors');
        $this->assertNotSame('Hack', $company->fresh()->city);
    }

    public function test_manager_can_assign_imported_clients_to_sellers_by_email(): void
    {
        $manager = User::factory()->manager()->create(['two_factor_secret' => 'JBSWY3DPEHPK3PXP', 'two_factor_confirmed_at' => now()]);
        $giulia = User::factory()->create(['email' => 'giulia@example.com']);

        $this->actingAs($manager)->postJson('/api/companies/import', ['rows' => [['name' => 'Cliente di Giulia', 'owner_email' => 'Giulia@Example.com']], 'duplicates' => 'skip'])
            ->assertJson(['created' => 1]);

        $this->assertSame($giulia->id, Company::where('name', 'Cliente di Giulia')->value('owner_id'));
    }

    public function test_sales_imports_are_always_their_own(): void
    {
        [$giulia, $marco] = User::factory(2)->create();

        $this->actingAs($giulia)->postJson('/api/companies/import', ['rows' => [['name' => 'Mio', 'owner_email' => $marco->email]], 'duplicates' => 'skip']);

        $this->assertSame($giulia->id, Company::where('name', 'Mio')->value('owner_id'));
    }

    public function test_batch_size_is_limited(): void
    {
        $user = User::factory()->create();
        $rows = array_fill(0, 501, ['name' => 'X']);

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => $rows, 'duplicates' => 'skip'])->assertJsonValidationErrors('rows');
    }

    public function test_placeholder_values_from_airtable_do_not_break_the_import(): void
    {
        $user = User::factory()->create();
        $rows = [
            ['name' => 'Negozio A', 'vat_number' => '-', 'tax_code' => '-', 'email' => 'N/A'],
            ['name' => 'Negozio B', 'vat_number' => '-', 'email' => '-'],
            ['name' => 'Negozio C', 'vat_number' => 'N/A', 'city' => 'n.d.'],
            ['name' => 'Negozio D', 'vat_number' => 'N/A', 'tax_code' => 'boh'],
            ['name' => 'Negozio E', 'vat_number' => 'IT 123.456.789-01'],
        ];

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => $rows, 'duplicates' => 'skip', 'dry_run' => true])
            ->assertOk()->assertJson(['created' => 5, 'skipped' => 0])->assertJsonCount(0, 'errors');

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => $rows, 'duplicates' => 'skip'])
            ->assertOk()->assertJson(['created' => 5])->assertJsonCount(0, 'errors');

        $this->assertSame(5, Company::count());
        $this->assertNull(Company::where('name', 'Negozio A')->value('vat_number'));
        $this->assertNull(Company::where('name', 'Negozio C')->value('city'));
        $this->assertSame('IT12345678901', Company::where('name', 'Negozio E')->value('vat_number'));
    }

    public function test_a_row_rejected_by_the_database_does_not_stop_the_others(): void
    {
        $user = User::factory()->create();
        // Stessa P.IVA scritta in due modi nel file: la seconda riga, normalizzata, coincide con la prima.
        $rows = [
            ['name' => 'Alfa', 'vat_number' => 'IT01234567890'],
            ['name' => 'Beta', 'vat_number' => 'IT 01234567890'],
            ['name' => 'Gamma'],
        ];

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => $rows, 'duplicates' => 'skip'])
            ->assertOk()->assertJson(['created' => 2, 'skipped' => 1]);
        $this->assertSame(2, Company::count());
    }

    public function test_lead_source_is_imported_and_unknown_values_are_kept_in_notes(): void
    {
        $user = User::factory()->create();
        $rows = [
            ['name' => 'Da Instagram', 'source' => 'Instagram'],
            ['name' => 'Da fiera', 'source' => 'Fiera Pitti'],
            ['name' => 'Misterioso', 'source' => 'Volantino in centro'],
        ];

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => $rows, 'duplicates' => 'skip'])->assertJson(['created' => 3]);

        $this->assertSame('social', Company::where('name', 'Da Instagram')->value('source'));
        $this->assertSame('fiera', Company::where('name', 'Da fiera')->value('source'));
        $mystery = Company::where('name', 'Misterioso')->first();
        $this->assertSame('altro', $mystery->source);
        $this->assertStringContainsString('Volantino in centro', $mystery->notes);
    }

    public function test_import_as_customers_with_billing_data(): void
    {
        $user = User::factory()->create();
        $rows = [['name' => 'Cliente Fatturato Srl', 'vat_number' => 'IT01234567890', 'billing_address' => 'Via Roma 1', 'billing_zip' => '20100',
            'billing_city' => 'Milano', 'sdi_code' => 'm5uxcr1', 'iban' => 'IT60 X054 2811 1010 0000 0123 456', 'pec' => 'Amm@PEC.it']];

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => $rows, 'duplicates' => 'skip', 'status' => 'customer'])->assertJson(['created' => 1]);

        $c = Company::first();
        $this->assertSame('customer', $c->status);
        $this->assertNotNull($c->converted_at);
        $this->assertSame('M5UXCR1', $c->sdi_code);
        $this->assertSame('IT60X0542811101000000123456', $c->iban);
        $this->assertTrue($c->hasCompleteBilling());
        $this->assertSame('lead', Company::factory()->create()->status);
    }

    public function test_database_errors_are_reported_with_their_code(): void
    {
        $user = User::factory()->create();
        // Provincia oltre i 10 caratteri: la validazione la blocca, ma forziamo il caso a livello database.
        DB::statement('CREATE UNIQUE INDEX tmp_unique_city ON companies (city)');
        $rows = [['name' => 'Uno', 'city' => 'Roma'], ['name' => 'Due', 'city' => 'Roma']];

        $response = $this->actingAs($user)->postJson('/api/companies/import', ['rows' => $rows, 'duplicates' => 'skip'])
            ->assertOk()->assertJson(['created' => 1])->assertJsonCount(1, 'errors');

        $this->assertStringContainsString('esiste già un record con lo stesso valore', $response->json('errors.0.messages.0'));
        $this->assertStringContainsString('codice 23', $response->json('errors.0.messages.0'));
    }
}
