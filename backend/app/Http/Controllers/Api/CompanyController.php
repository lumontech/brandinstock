<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOwner;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    use ResolvesOwner;

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::in(Company::TYPES)],
            'owner_id' => ['nullable', 'integer'],
        ]);

        $companies = Company::query()
            ->visibleTo($request->user())
            ->with('owner')
            ->withCount('deals')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('vat_number', "%{$term}%")
                ->orWhereLike('city', "%{$term}%")))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['owner_id'] ?? null, fn ($q, $owner) => $q->where('owner_id', $owner))
            ->orderBy('name')
            ->paginate(25);

        return CompanyResource::collection($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $company = new Company($this->validated($request));
        $company->owner_id = $this->resolveOwner($request, Company::class);
        $company->save();

        return (new CompanyResource($company->load('owner')))->response()->setStatusCode(201);
    }

    public function show(Request $request, Company $company): CompanyResource
    {
        Gate::authorize('view', $company);
        $user = $request->user();

        return new CompanyResource($company->load([
            'owner',
            'contacts' => fn ($q) => $q->visibleTo($user)->orderBy('first_name'),
            'deals' => fn ($q) => $q->visibleTo($user)->with('stage', 'owner')->latest(),
            'activities' => fn ($q) => $q->visibleTo($user)->with('user')->latest('due_at')->limit(50),
        ]));
    }

    public function update(Request $request, Company $company): CompanyResource
    {
        Gate::authorize('update', $company);
        $company->fill($this->validated($request, $company));
        if ($request->has('owner_id') && Gate::allows('assign', Company::class)) {
            $company->owner_id = $this->resolveOwner($request, Company::class);
        }
        $company->save();

        return new CompanyResource($company->load('owner'));
    }

    public function destroy(Company $company): JsonResponse
    {
        Gate::authorize('delete', $company);
        if ($company->deals()->exists()) {
            return response()->json(['message' => "Impossibile eliminare un'azienda con opportunità collegate."], 422);
        }
        $company->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        return $request->validate([
            'name' => [$company ? 'sometimes' : 'required', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:32', Rule::unique('companies')->ignore($company)],
            'type' => ['nullable', Rule::in(Company::TYPES)],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], ['vat_number.unique' => 'Questa partita IVA è già presente nel CRM: contatta un responsabile.']);
    }
}
