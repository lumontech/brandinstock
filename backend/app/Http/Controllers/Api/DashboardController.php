<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Deal;
use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class DashboardController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'owner_id' => ['nullable', 'integer'],
            'segment' => ['nullable', Rule::in(Company::SEGMENTS)],
        ]);
        $user = $request->user();

        $deals = fn () => Deal::query()
            ->visibleTo($user)
            ->when($filters['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', $id))
            ->when($filters['segment'] ?? null, fn ($q, $segment) => $q->whereHas('company', fn ($c) => $c->where('segment', $segment)));

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

        // Pipeline aperta e vinto nell'anno per categoria cliente (B2B, B2C, Franchising).
        $segmentRows = $deals()
            ->join('companies', 'companies.id', '=', 'deals.company_id')
            ->selectRaw('companies.segment as segment, deals.pipeline_stage_id as stage_id, COUNT(*) as deals_count, COALESCE(SUM(deals.value), 0) as total_value')
            ->where(fn ($q) => $q->whereIn('deals.pipeline_stage_id', $openStageIds)
                ->orWhere(fn ($w) => $w->whereIn('deals.pipeline_stage_id', $wonStageIds)->where('deals.closed_at', '>=', now()->startOfYear())))
            ->groupBy('companies.segment', 'deals.pipeline_stage_id')
            ->get();
        $bySegment = collect(Company::SEGMENTS)->map(function (string $segment) use ($segmentRows, $openStageIds) {
            $rows = $segmentRows->where('segment', $segment);
            $open = $rows->filter(fn ($r) => $openStageIds->contains($r->stage_id));
            $won = $rows->reject(fn ($r) => $openStageIds->contains($r->stage_id));

            return [
                'segment' => $segment,
                'open_count' => (int) $open->sum('deals_count'),
                'open_value' => (float) $open->sum('total_value'),
                'won_year_count' => (int) $won->sum('deals_count'),
                'won_year_value' => (float) $won->sum('total_value'),
            ];
        })->values();

        // Andamento del vinto negli ultimi 12 mesi (raggruppato in PHP: indipendente dal database).
        $wonLastYear = $deals()
            ->whereIn('pipeline_stage_id', $wonStageIds)
            ->where('closed_at', '>=', now()->startOfMonth()->subMonths(11))
            ->get(['value', 'closed_at']);
        $monthly = collect(range(11, 0))->map(function (int $ago) use ($wonLastYear) {
            $month = now()->startOfMonth()->subMonths($ago);
            $inMonth = $wonLastYear->filter(fn ($d) => Carbon::parse($d->closed_at)->isSameMonth($month));

            return ['month' => $month->format('Y-m'), 'won_count' => $inMonth->count(), 'won_value' => (float) $inMonth->sum('value')];
        })->values();

        $lostReasons = $deals()
            ->whereIn('pipeline_stage_id', $lostStageIds)
            ->where('closed_at', '>=', now()->startOfYear())
            ->selectRaw("COALESCE(NULLIF(lost_reason, ''), 'Non indicato') as reason, COUNT(*) as deals_count, COALESCE(SUM(value), 0) as total_value")
            ->groupBy('reason')
            ->orderByDesc('deals_count')
            ->limit(6)
            ->get()
            ->map(fn ($r) => ['reason' => $r->reason, 'deals_count' => (int) $r->deals_count, 'total_value' => (float) $r->total_value]);

        $bySource = $deals()
            ->where('created_at', '>=', now()->startOfYear())
            ->selectRaw("COALESCE(source, 'altro') as source, COUNT(*) as deals_count, COALESCE(SUM(CASE WHEN pipeline_stage_id IN (".($wonStageIds->implode(',') ?: '0').') THEN value ELSE 0 END), 0) as won_value')
            ->groupBy('source')
            ->orderByDesc('deals_count')
            ->get()
            ->map(fn ($r) => ['source' => $r->source, 'deals_count' => (int) $r->deals_count, 'won_value' => (float) $r->won_value]);

        return response()->json([
            'by_segment' => $bySegment,
            'monthly' => $monthly,
            'lost_reasons' => $lostReasons,
            'by_source' => $bySource,
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
