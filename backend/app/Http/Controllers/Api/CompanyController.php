<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOwner;
use App\Http\Controllers\Controller;
use App\Http\Resources\CompanyResource;
use App\Models\Company;
use App\Models\PipelineStage;
use App\Models\User;
use App\Support\LeadSource;
use App\Support\LeadStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    use ResolvesOwner;

    /** Colonne ordinabili della vista a griglia (whitelist: mai ordinare su input libero). */
    private const SORTABLE = ['name', 'segment', 'source', 'lead_status', 'status', 'converted_at', 'won_value', 'billing_city', 'sdi_code', 'payment_terms', 'type', 'city', 'province', 'vat_number', 'created_at', 'deals_count', 'contacts_count', 'open_deals_value', 'last_activity_at', 'owner'];

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'segment' => ['nullable', Rule::in(Company::SEGMENTS)],
            'source' => ['nullable', Rule::in(LeadSource::ALL)],
            'lead_status' => ['nullable', Rule::in(LeadStatus::ALL)],
            'status' => ['nullable', Rule::in(Company::STATUSES)],
            'type' => ['nullable', Rule::in(Company::TYPES)],
            'owner_id' => ['nullable', 'integer'],
            'sort' => ['nullable', Rule::in(self::SORTABLE)],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:200'],
        ]);
        $user = $request->user();
        $openStageIds = PipelineStage::where('is_won', false)->where('is_lost', false)->pluck('id');
        $sort = $filters['sort'] ?? 'name';
        $direction = $filters['direction'] ?? 'asc';

        $companies = Company::query()
            ->visibleTo($user)
            ->with('owner')
            // I conteggi rispettano i permessi: un venditore non vede i numeri dei colleghi.
            ->withCount([
                'deals' => fn ($q) => $q->visibleTo($user),
                'contacts' => fn ($q) => $q->visibleTo($user),
            ])
            ->withSum(['deals as open_deals_value' => fn ($q) => $q->visibleTo($user)->whereIn('pipeline_stage_id', $openStageIds)], 'value')
            ->withSum(['deals as won_value' => fn ($q) => $q->visibleTo($user)->whereIn('pipeline_stage_id', PipelineStage::where('is_won', true)->pluck('id'))], 'value')
            ->withMax(['activities as last_activity_at' => fn ($q) => $q->visibleTo($user)], 'created_at')
            ->when($filters['q'] ?? null, fn ($q, $term) => $q->where(fn ($w) => $w
                ->whereLike('name', "%{$term}%")
                ->orWhereLike('vat_number', "%{$term}%")
                ->orWhereLike('city', "%{$term}%")))
            ->when($filters['segment'] ?? null, fn ($q, $segment) => $q->where('segment', $segment))
            ->when($filters['source'] ?? null, fn ($q, $source) => $q->where('source', $source))
            ->when($filters['lead_status'] ?? null, fn ($q, $leadStatus) => $q->where('lead_status', $leadStatus))
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('type', $type))
            ->when($filters['owner_id'] ?? null, fn ($q, $owner) => $q->where('owner_id', $owner))
            ->when(
                $sort === 'owner',
                fn ($q) => $q->orderBy(User::select('name')->whereColumn('users.id', 'companies.owner_id'), $direction),
                fn ($q) => $q->orderBy($sort, $direction),
            )
            ->orderBy('companies.id')
            ->paginate($filters['per_page'] ?? 25);

        return CompanyResource::collection($companies);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->validated($request);
        $company = new Company(Arr::except($data, ['status']));
        $company->owner_id = $this->resolveOwner($request, Company::class);
        if (($data['status'] ?? 'lead') === 'customer') {
            $company->status = 'customer';
            $company->converted_at = now();
        }
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
        $data = $this->validated($request, $company);
        $company->fill(Arr::except($data, ['status']));
        if ($request->has('owner_id') && Gate::allows('assign', Company::class)) {
            $company->owner_id = $this->resolveOwner($request, Company::class);
        }
        // Passaggio manuale lead ↔ cliente (quello automatico avviene vincendo un'opportunità).
        if (isset($data['status']) && $data['status'] !== $company->status) {
            $company->status = $data['status'];
            $company->converted_at = $data['status'] === 'customer' ? now() : null;
        }
        $company->save();

        return new CompanyResource($company->load('owner'));
    }

    public function destroy(Company $company): JsonResponse
    {
        Gate::authorize('delete', $company);
        DB::transaction(fn () => $company->deleteWithRelated());

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Company $company = null): array
    {
        $data = $request->validate([
            'name' => [$company ? 'sometimes' : 'required', 'string', 'max:255'],
            'segment' => ['sometimes', Rule::in(Company::SEGMENTS)],
            'source' => ['nullable', Rule::in(LeadSource::ALL)],
            'lead_status' => ['nullable', Rule::in(LeadStatus::ALL)],
            'status' => ['sometimes', Rule::in(Company::STATUSES)],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_zip' => ['nullable', 'string', 'max:10'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_province' => ['nullable', 'string', 'max:10'],
            'billing_country' => ['nullable', 'string', 'size:2'],
            'sdi_code' => ['nullable', 'regex:/^[A-Za-z0-9]{6,7}$/'],
            'pec' => ['nullable', 'email', 'max:255'],
            'iban' => ['nullable', 'regex:/^[A-Za-z]{2}\d{2}[A-Za-z0-9 ]{11,34}$/'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'billing_notes' => ['nullable', 'string', 'max:2000'],
            'vat_number' => ['nullable', 'string', 'max:32', Rule::unique('companies')->ignore($company)],
            'tax_code' => ['nullable', 'string', 'regex:/^[A-Za-z0-9]{11,16}$/'],
            'type' => ['nullable', Rule::in(Company::TYPES)],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'vat_number.unique' => 'Questa partita IVA è già presente nel CRM: contatta un responsabile.',
            'tax_code.regex' => 'Il codice fiscale non è valido.',
            'sdi_code.regex' => 'Il codice destinatario SDI ha 6 o 7 caratteri.',
            'iban.regex' => "L'IBAN non è valido.",
        ]);

        // Normalizzato qui (non con un mutator, che scavalcherebbe la cifratura del campo).
        if (! empty($data['iban'])) {
            $data['iban'] = strtoupper(str_replace(' ', '', $data['iban']));
        }

        return $data;
    }
}
