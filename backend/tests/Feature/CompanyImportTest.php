<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
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
            ->assertJson(['created' => 3, 'contacts_created' => 1, 'dry_run' => true])
            ->assertJsonCount(2, 'errors')
            ->assertJsonPath('errors.0.row', 3);

        $this->assertSame(0, Company::count());
        $this->assertSame(0, Contact::count());
    }

    public function test_import_creates_clients_with_normalized_values_and_contacts(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/companies/import', ['rows' => $this->rows(), 'duplicates' => 'skip'])
            ->assertOk()->assertJson(['created' => 3, 'contacts_created' => 1, 'dry_run' => false]);

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
        $company = Company::factory()->for($marco, 'owner')->create(['vat_number' => 'IT999']);

        $this->actingAs($giulia)->postJson('/api/companies/import', ['rows' => [['name' => 'X', 'vat_number' => 'IT999', 'city' => 'Hack']], 'duplicates' => 'update'])
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
}
