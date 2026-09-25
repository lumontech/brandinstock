<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Deal;
use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate(['owner_id' => ['nullable', 'integer']]);
        $user = $request->user();

        $deals = fn () => Deal::query()
            ->visibleTo($user)
            ->when($filters['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', $id));

        $stages = PipelineStage::orderBy('position')->get();
        $openStageIds = $stages->reject->isClosed()->pluck('id');
        $wonStageIds = $stages->where('is_won', true)->pluck('id');
        $lostStageIds = $stages->where('is_lost', true)->pluck('id');
        $monthStart = now()->startOfMonth();

        $byStage = $deals()
            ->selectRaw('pipeline_stage_id, COUNT(*) as deals_count, COALESCE(SUM(value), 0) as total_value')
            ->whereIn('pipeline_stage_id', $openStageIds)
            ->groupBy('pipeline_stage_id')
            ->get()
            ->keyBy('pipeline_stage_id');

        $pipeline = $stages->reject->isClosed()->values()->map(fn (PipelineStage $stage) => [
            'id' => $stage->id,
            'name' => $stage->name,
            'color' => $stage->color,
            'probability' => $stage->probability,
            'deals_count' => (int) ($byStage[$stage->id]->deals_count ?? 0),
            'total_value' => (float) ($byStage[$stage->id]->total_value ?? 0),
        ]);

        $wonThisMonth = $deals()->whereIn('pipeline_stage_id', $wonStageIds)->where('closed_at', '>=', $monthStart);
        $closedThisQuarter = $deals()->whereIn('pipeline_stage_id', $wonStageIds->merge($lostStageIds))->where('closed_at', '>=', now()->startOfQuarter());
        $closedCount = (clone $closedThisQuarter)->count();
        $wonCount = (clone $closedThisQuarter)->whereIn('pipeline_stage_id', $wonStageIds)->count();

        $bySeller = $user->seesEverything()
            ? $deals()
                ->join('users', 'users.id', '=', 'deals.owner_id')
                ->whereIn('pipeline_stage_id', $openStageIds)
                ->selectRaw('users.id, users.name, COUNT(*) as deals_count, COALESCE(SUM(deals.value), 0) as total_value')
                ->groupBy('users.id', 'users.name')
                ->orderByDesc('total_value')
                ->get()
                ->map(fn ($row) => ['id' => $row->id, 'name' => $row->name, 'deals_count' => (int) $row->deals_count, 'total_value' => (float) $row->total_value])
            : null;

        $activities = Activity::query()->where('user_id', $user->id)->whereNull('completed_at');

        return response()->json([
            'open_value' => (float) $pipeline->sum('total_value'),
            'open_count' => $pipeline->sum('deals_count'),
            'weighted_value' => round($pipeline->sum(fn ($s) => $s['total_value'] * $s['probability'] / 100), 2),
            'won_this_month_value' => (float) (clone $wonThisMonth)->sum('value'),
            'won_this_month_count' => (clone $wonThisMonth)->count(),
            'win_rate_quarter' => $closedCount ? round($wonCount / $closedCount * 100, 1) : null,
            'pipeline' => $pipeline,
            'by_seller' => $bySeller,
            'my_overdue_activities' => (clone $activities)->where('due_at', '<', now())->count(),
            'my_today_activities' => (clone $activities)->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()])->count(),
            'closing_soon' => $deals()
                ->with('company')
                ->whereIn('pipeline_stage_id', $openStageIds)
                ->whereBetween('expected_close_date', [now()->toDateString(), now()->addDays(14)->toDateString()])
                ->orderBy('expected_close_date')
                ->limit(10)
                ->get()
                ->map(fn (Deal $d) => ['id' => $d->id, 'title' => $d->title, 'company' => $d->company->name, 'value' => (float) $d->value, 'expected_close_date' => $d->expected_close_date->toDateString()]),
        ]);
    }
}
