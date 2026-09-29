<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\User;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    public function test_new_deal_starts_in_first_stage(): void
    {
        $user = User::factory()->create();
        $company = Company::factory()->for($user, 'owner')->create();

        $this->actingAs($user)->postJson('/api/deals', [
            'title' => 'Stock Liu Jo AI25',
            'company_id' => $company->id,
            'value' => 12500,
            'brand' => 'Liu Jo',
        ])->assertCreated()->assertJsonPath('data.stage.name', 'Nuovo lead')->assertJsonPath('data.value', 12500);
    }

    public function test_moving_deal_to_won_sets_closed_at(): void
    {
        $user = User::factory()->create();
        $deal = Deal::factory()->for($user, 'owner')->create();
        $won = PipelineStage::where('is_won', true)->first();

        $this->actingAs($user)->patchJson("/api/deals/{$deal->id}/move", ['pipeline_stage_id' => $won->id])->assertOk();
        $this->assertNotNull($deal->fresh()->closed_at);

        $open = PipelineStage::orderBy('position')->first();
        $this->actingAs($user)->patchJson("/api/deals/{$deal->id}/move", ['pipeline_stage_id' => $open->id])->assertOk();
        $this->assertNull($deal->fresh()->closed_at);
    }

    public function test_pipeline_board_groups_deals_by_stage(): void
    {
        $user = User::factory()->create();
        Deal::factory(3)->for($user, 'owner')->create(['value' => 1000]);

        $response = $this->actingAs($user)->getJson('/api/pipeline')->assertOk();

        $first = $response->json('data.0');
        $this->assertSame('Nuovo lead', $first['name']);
        $this->assertCount(3, $first['deals']);
        $this->assertEquals(3000, $first['total_value']);
    }

    public function test_dashboard_returns_kpis(): void
    {
        $user = User::factory()->create();
        Deal::factory(2)->for($user, 'owner')->create(['value' => 5000]);

        $this->actingAs($user)->getJson('/api/dashboard')
            ->assertOk()
            ->assertJsonPath('open_count', 2)
            ->assertJsonPath('by_seller', null)
            ->assertJsonStructure(['open_value', 'weighted_value', 'won_this_month_value', 'pipeline', 'closing_soon']);
    }

    public function test_activity_on_deal_inherits_company_and_can_be_completed(): void
    {
        $user = User::factory()->create();
        $deal = Deal::factory()->for($user, 'owner')->create();

        $activity = $this->actingAs($user)->postJson('/api/activities', [
            'type' => 'call', 'subject' => 'Richiamare buyer', 'deal_id' => $deal->id, 'due_at' => now()->addDay()->toIso8601String(),
        ])->assertCreated()->assertJsonPath('data.company_id', $deal->company_id)->json('data');

        $this->actingAs($user)->postJson("/api/activities/{$activity['id']}/toggle-complete")
            ->assertOk()->assertJsonPath('data.is_overdue', false);
        $this->actingAs($user)->getJson('/api/activities?status=done')->assertJsonCount(1, 'data');
    }

    public function test_stage_with_deals_cannot_be_removed(): void
    {
        $admin = $this->adminWithTwoFactor();
        $stages = PipelineStage::orderBy('position')->get();
        Deal::factory()->for($admin, 'owner')->create(['pipeline_stage_id' => $stages[0]->id]);

        $payload = $stages->skip(1)->map(fn ($s) => $s->only('id', 'name', 'probability', 'color', 'is_won', 'is_lost'))->values()->all();

        $this->actingAs($admin)->putJson('/api/stages', ['stages' => $payload])->assertJsonValidationErrors('stages');
    }

    public function test_dashboard_reports_by_segment_month_and_lost_reason(): void
    {
        $user = User::factory()->create();
        $b2b = Company::factory()->for($user, 'owner')->create(['segment' => 'b2b']);
        $fr = Company::factory()->for($user, 'owner')->create(['segment' => 'franchising']);
        $won = PipelineStage::where('is_won', true)->first();
        $lost = PipelineStage::where('is_lost', true)->first();

        Deal::factory()->for($b2b, 'company')->for($user, 'owner')->create(['value' => 1000]);
        Deal::factory()->for($fr, 'company')->for($user, 'owner')->create(['value' => 3000]);
        $deal = Deal::factory()->for($fr, 'company')->for($user, 'owner')->create(['value' => 7000]);
        $this->actingAs($user)->patchJson("/api/deals/{$deal->id}/move", ['pipeline_stage_id' => $won->id]);
        $lostDeal = Deal::factory()->for($b2b, 'company')->for($user, 'owner')->create(['value' => 500]);
        $this->actingAs($user)->patchJson("/api/deals/{$lostDeal->id}/move", ['pipeline_stage_id' => $lost->id, 'lost_reason' => 'Prezzo']);

        $data = $this->actingAs($user)->getJson('/api/dashboard')->assertOk()->json();

        $segments = collect($data['by_segment'])->keyBy('segment');
        $this->assertEquals(1000, $segments['b2b']['open_value']);
        $this->assertEquals(3000, $segments['franchising']['open_value']);
        $this->assertEquals(7000, $segments['franchising']['won_year_value']);
        $this->assertCount(12, $data['monthly']);
        $this->assertEquals(7000, end($data['monthly'])['won_value']);
        $this->assertSame('Prezzo', $data['lost_reasons'][0]['reason']);

        $this->actingAs($user)->getJson('/api/dashboard?segment=franchising')->assertJsonPath('open_value', 3000);
        $this->actingAs($user)->getJson('/api/dashboard?segment=boh')->assertJsonValidationErrors('segment');
    }
}
