<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\DealResource;
use App\Http\Resources\StageResource;
use App\Models\Deal;
use App\Models\PipelineStage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PipelineController extends Controller
{
    /** Vista Kanban: fasi con le rispettive opportunità visibili all'utente. */
    public function __invoke(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'owner_id' => ['nullable', 'integer'],
            'q' => ['nullable', 'string', 'max:100'],
            // Le colonne chiuse mostrano solo le opportunità degli ultimi N giorni.
            'closed_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);
        $closedSince = now()->subDays($filters['closed_days'] ?? 30);

        $stages = PipelineStage::orderBy('position')->get();
        $deals = Deal::query()
            ->visibleTo($request->user())
            ->with('company', 'owner')
            ->when($filters['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', $id))
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereLike('title', "%{$term}%")
                ->orWhereLike('brand', "%{$term}%")
                ->orWhereHas('company', fn ($c) => $c->whereLike('name', "%{$term}%"))))
            ->where(fn ($q) => $q->whereNull('closed_at')->orWhere('closed_at', '>=', $closedSince))
            ->orderByRaw('expected_close_date IS NULL, expected_close_date')
            ->latest('updated_at')
            ->get()
            ->groupBy('pipeline_stage_id');

        return response()->json([
            'data' => $stages->map(function (PipelineStage $stage) use ($deals, $request) {
                $stageDeals = $deals->get($stage->id, collect());

                return [
                    ...(new StageResource($stage))->toArray($request),
                    'total_value' => (float) $stageDeals->sum('value'),
                    'deals' => DealResource::collection($stageDeals)->toArray($request),
                ];
            }),
        ]);
    }
}
