<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOwner;
use App\Http\Controllers\Controller;
use App\Http\Resources\DealResource;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Rules\VisibleRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class DealController extends Controller
{
    use ResolvesOwner;

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['open', 'won', 'lost'])],
            'pipeline_stage_id' => ['nullable', 'integer'],
            'owner_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
        ]);

        $deals = Deal::query()
            ->visibleTo($request->user())
            ->with('stage', 'company', 'owner')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereLike('title', "%{$term}%")
                ->orWhereLike('brand', "%{$term}%")
                ->orWhereHas('company', fn ($c) => $c->whereLike('name', "%{$term}%"))))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->whereHas('stage', fn ($s) => match ($status) {
                'won' => $s->where('is_won', true),
                'lost' => $s->where('is_lost', true),
                'open' => $s->where('is_won', false)->where('is_lost', false),
            }))
            ->when($filters['pipeline_stage_id'] ?? null, fn ($q, $id) => $q->where('pipeline_stage_id', $id))
            ->when($filters['owner_id'] ?? null, fn ($q, $id) => $q->where('owner_id', $id))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->latest('updated_at')
            ->paginate(25);

        return DealResource::collection($deals);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $deal = new Deal($data);
        $deal->pipeline_stage_id ??= PipelineStage::orderBy('position')->value('id');
        $deal->currency ??= config('crm.currency');
        // Se non indicata, la provenienza dell'opportunità è quella del cliente.
        $deal->source ??= Company::whereKey($deal->company_id)->value('source');
        $deal->owner_id = $this->resolveOwner($request, Deal::class);
        $deal->save();

        return (new DealResource($deal->load('stage', 'company', 'contact', 'owner')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Deal $deal): DealResource
    {
        Gate::authorize('view', $deal);

        return new DealResource($deal->load([
            'stage', 'company', 'contact', 'owner',
            'activities' => fn ($q) => $q->visibleTo($request->user())->with('user')->latest('due_at'),
        ]));
    }

    public function update(Request $request, Deal $deal): DealResource
    {
        Gate::authorize('update', $deal);
        $deal->fill($this->validated($request, $deal));
        if ($request->has('owner_id') && Gate::allows('assign', Deal::class)) {
            $deal->owner_id = $this->resolveOwner($request, Deal::class);
        }
        $deal->save();

        return new DealResource($deal->load('stage', 'company', 'contact', 'owner'));
    }

    /** Spostamento di fase dalla vista Kanban. */
    public function move(Request $request, Deal $deal): DealResource
    {
        Gate::authorize('update', $deal);
        $data = $request->validate([
            'pipeline_stage_id' => ['required', 'integer', Rule::exists(PipelineStage::class, 'id')],
            'lost_reason' => ['nullable', 'string', 'max:255'],
        ]);

        $deal->pipeline_stage_id = $data['pipeline_stage_id'];
        if (array_key_exists('lost_reason', $data)) {
            $deal->lost_reason = $data['lost_reason'];
        }
        $deal->save();

        return new DealResource($deal->load('stage', 'company', 'owner'));
    }

    public function destroy(Deal $deal): JsonResponse
    {
        Gate::authorize('delete', $deal);
        $deal->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Deal $deal = null): array
    {
        $user = $request->user();
        $required = $deal ? 'sometimes' : 'required';

        return $request->validate([
            'title' => [$required, 'string', 'max:255'],
            'company_id' => [$required, new VisibleRecord(Company::class, $user)],
            'contact_id' => ['nullable', new VisibleRecord(Contact::class, $user)],
            'pipeline_stage_id' => ['nullable', 'integer', Rule::exists(PipelineStage::class, 'id')],
            'value' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'currency' => ['nullable', 'string', 'size:3'],
            'brand' => ['nullable', 'string', 'max:100'],
            'product_category' => ['nullable', 'string', 'max:100'],
            'quantity' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'expected_close_date' => ['nullable', 'date'],
            'source' => ['nullable', Rule::in(Deal::SOURCES)],
            'lost_reason' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }
}
