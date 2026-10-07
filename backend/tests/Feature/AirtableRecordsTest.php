<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AirtableRecordsTest extends TestCase
{
    private const URL = 'https://api.airtable.com/v0/app51nuQ4TNwbQWXo/tblLaypPmUeqZ6EoW*';

    private function payload(): array
    {
        return ['token' => 'patTEST.123', 'base_id' => 'app51nuQ4TNwbQWXo', 'table_id' => 'tblLaypPmUeqZ6EoW'];
    }

    public function test_reads_all_pages_as_text_rows(): void
    {
        Http::fakeSequence(self::URL)
            ->push(['records' => [['id' => 'rec1', 'fields' => ['Azienda' => 'Uno', 'Città' => ['Parma', 'Roma'], 'Creato' => 'Stef Brandinstock', 'Created by' => 'X']]], 'offset' => 'next'])
            ->push(['records' => [['id' => 'rec2', 'fields' => ['Azienda' => 'Due', 'Email' => 'a@b.it', 'Vuoto' => '']]]]);

        $this->actingAs(User::factory()->create())->postJson('/api/airtable/records', $this->payload())
            ->assertOk()
            ->assertJson([
                'headers' => ['Azienda', 'Città', 'Email'],
                'rows' => [['Azienda' => 'Uno', 'Città' => 'Parma, Roma'], ['Azienda' => 'Due', 'Email' => 'a@b.it']],
            ]);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer patTEST.123')
            && str_contains($request->url(), 'cellFormat=string'));
    }

    public function test_reports_invalid_token(): void
    {
        Http::fake([self::URL => Http::response(['error' => 'AUTHENTICATION_REQUIRED'], 401)]);

        $this->actingAs(User::factory()->create())->postJson('/api/airtable/records', $this->payload())
            ->assertStatus(422)->assertJsonPath('message', 'Token Airtable non valido o scaduto.');
    }

    public function test_rejects_malformed_ids_and_guests(): void
    {
        Http::fake();
        $this->postJson('/api/airtable/records', $this->payload())->assertUnauthorized();
        $this->actingAs(User::factory()->create())
            ->postJson('/api/airtable/records', [...$this->payload(), 'base_id' => '../evil'])
            ->assertStatus(422)->assertJsonValidationErrors('base_id');
        Http::assertNothingSent();
    }
}
