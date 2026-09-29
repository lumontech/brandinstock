<?php

namespace Tests\Feature;

use App\Models\Contact;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataProtectionTest extends TestCase
{
    public function test_personal_data_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();

        $id = $this->actingAs($user)->postJson('/api/contacts', [
            'first_name' => 'Anna',
            'email' => 'anna.buyer@boutique.it',
            'phone' => '+39 333 1234567',
            'notes' => 'Budget riservato 50k',
        ])->assertCreated()->assertJsonPath('data.email', 'anna.buyer@boutique.it')->json('data.id');

        $raw = DB::table('contacts')->find($id);
        $this->assertStringNotContainsString('anna.buyer', $raw->email);
        $this->assertStringNotContainsString('333', $raw->phone);
        $this->assertStringNotContainsString('Budget', $raw->notes);
        $this->assertSame(Contact::hashEmail('ANNA.buyer@boutique.it '), $raw->email_hash);
    }

    public function test_contacts_can_be_found_by_exact_email(): void
    {
        $user = User::factory()->create();
        Contact::factory()->for($user, 'owner')->create(['email' => 'anna@boutique.it']);
        Contact::factory()->for($user, 'owner')->create(['email' => 'altro@boutique.it']);

        $this->actingAs($user)->getJson('/api/contacts?q=Anna@Boutique.it')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.email', 'anna@boutique.it');
    }

    public function test_audit_log_never_stores_encrypted_values_in_clear(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/contacts', ['first_name' => 'Anna', 'email' => 'segreta@example.com'])->assertCreated();

        $changes = DB::table('audit_logs')->where('event', 'created')->where('auditable_type', Contact::class)->value('changes');
        $this->assertStringNotContainsString('segreta@example.com', $changes);
        $this->assertStringContainsString('[cifrato]', $changes);
    }

    public function test_sensitive_user_fields_are_never_serialized(): void
    {
        $admin = $this->adminWithTwoFactor();

        $json = $this->actingAs($admin)->getJson('/api/users')->assertOk()->getContent();

        $this->assertStringNotContainsString('two_factor_secret', $json);
        $this->assertStringNotContainsString('password', $json);
    }

    public function test_billing_data_is_encrypted_at_rest(): void
    {
        $user = User::factory()->create();
        $id = $this->actingAs($user)->postJson('/api/companies', ['name' => 'Cliente Srl'])->json('data.id');

        $this->actingAs($user)->putJson("/api/companies/{$id}", [
            'iban' => 'it60 x054 2811 1010 0000 0123 456',
            'pec' => 'amministrazione@pec.cliente.it',
            'billing_address' => 'Via Segreta 1',
            'sdi_code' => 'm5uxcr1',
        ])->assertOk()->assertJsonPath('data.iban', 'IT60X0542811101000000123456')->assertJsonPath('data.sdi_code', 'M5UXCR1');

        $raw = DB::table('companies')->find($id);
        $this->assertStringNotContainsString('IT60', $raw->iban);
        $this->assertStringNotContainsString('pec.cliente', $raw->pec);
        $this->assertStringNotContainsString('Segreta', $raw->billing_address);
    }
}
