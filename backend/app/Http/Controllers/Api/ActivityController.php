<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityType;
use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityResource;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Rules\VisibleRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ActivityController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'overdue', 'today', 'upcoming', 'done'])],
            'deal_id' => ['nullable', 'integer'],
            'company_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
        ]);

        $activities = Activity::query()
            ->visibleTo($request->user())
            ->with('deal', 'company', 'user')
            ->when($filters['deal_id'] ?? null, fn ($q, $id) => $q->where('deal_id', $id))
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($filters['user_id'] ?? null, fn ($q, $id) => $q->where('user_id', $id))
            ->when($filters['status'] ?? null, fn ($q, $status) => match ($status) {
                'open' => $q->whereNull('completed_at'),
                'overdue' => $q->whereNull('completed_at')->where('due_at', '<', now()),
                'today' => $q->whereNull('completed_at')->whereBetween('due_at', [now()->startOfDay(), now()->endOfDay()]),
                'upcoming' => $q->whereNull('completed_at')->where('due_at', '>', now()->endOfDay()),
                'done' => $q->whereNotNull('completed_at'),
            })
            ->orderByRaw('completed_at IS NOT NULL, due_at IS NULL, due_at')
            ->paginate(50);

        return ActivityResource::collection($activities);
    }

    public function store(Request $request): JsonResponse
    {
        $activity = new Activity($this->validated($request));
        $activity->user_id = $request->user()->id;
        $this->inheritCompany($activity);
        if ($activity->type === ActivityType::Note) {
            $activity->completed_at = now();
        }
        $activity->save();

        return (new ActivityResource($activity->load('deal', 'company', 'user')))->response()->setStatusCode(201);
    }

    public function update(Request $request, Activity $activity): ActivityResource
    {
        Gate::authorize('update', $activity);
        $activity->fill($this->validated($request, $activity));
        $this->inheritCompany($activity);
        $activity->save();

        return new ActivityResource($activity->load('deal', 'company', 'user'));
    }

    public function toggleComplete(Activity $activity): ActivityResource
    {
        Gate::authorize('update', $activity);
        $activity->completed_at = $activity->completed_at ? null : now();
        $activity->save();

        return new ActivityResource($activity->load('deal', 'company', 'user'));
    }

    public function destroy(Activity $activity): JsonResponse
    {
        Gate::authorize('delete', $activity);
        $activity->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Activity $activity = null): array
    {
        $user = $request->user();
        $required = $activity ? 'sometimes' : 'required';

        return $request->validate([
            'type' => [$required, Rule::enum(ActivityType::class)],
            'subject' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'deal_id' => ['nullable', new VisibleRecord(Deal::class, $user)],
            'company_id' => ['nullable', new VisibleRecord(Company::class, $user)],
            'contact_id' => ['nullable', new VisibleRecord(Contact::class, $user)],
            'due_at' => ['nullable', 'date'],
        ]);
    }

    /** Un'attività legata a un'opportunità viene collegata anche alla sua azienda. */
    private function inheritCompany(Activity $activity): void
    {
        if ($activity->deal_id && ! $activity->company_id) {
            $activity->company_id = Deal::whereKey($activity->deal_id)->value('company_id');
        }
    }
}
