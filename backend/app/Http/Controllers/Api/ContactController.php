<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ResolvesOwner;
use App\Http\Controllers\Controller;
use App\Http\Resources\ContactResource;
use App\Models\Company;
use App\Models\Contact;
use App\Rules\VisibleRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ContactController extends Controller
{
    use ResolvesOwner;

    public function index(Request $request): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'company_id' => ['nullable', 'integer'],
        ]);
        $term = $filters['q'] ?? null;

        $contacts = Contact::query()
            ->visibleTo($request->user())
            ->with('company', 'owner')
            ->when($filters['company_id'] ?? null, fn ($q, $id) => $q->where('company_id', $id))
            ->when($term, fn ($q) => $q->where(fn ($w) => $w
                ->whereLike('first_name', "%{$term}%")
                ->orWhereLike('last_name', "%{$term}%")
                // L'email è cifrata: si può cercare solo per corrispondenza esatta tramite indice cieco.
                ->orWhere('email_hash', Contact::hashEmail($term))))
            ->orderBy('first_name')
            ->paginate(25);

        return ContactResource::collection($contacts);
    }

    public function store(Request $request): JsonResponse
    {
        $contact = new Contact($this->validated($request));
        $contact->owner_id = $this->resolveOwner($request, Contact::class);
        $contact->save();

        return (new ContactResource($contact->load('company', 'owner')))->response()->setStatusCode(201);
    }

    public function show(Contact $contact): ContactResource
    {
        Gate::authorize('view', $contact);

        return new ContactResource($contact->load('company', 'owner'));
    }

    public function update(Request $request, Contact $contact): ContactResource
    {
        Gate::authorize('update', $contact);
        $contact->fill($this->validated($request, $contact));
        if ($request->has('owner_id') && Gate::allows('assign', Contact::class)) {
            $contact->owner_id = $this->resolveOwner($request, Contact::class);
        }
        $contact->save();

        return new ContactResource($contact->load('company', 'owner'));
    }

    public function destroy(Contact $contact): JsonResponse
    {
        Gate::authorize('delete', $contact);
        $contact->delete();

        return response()->json(null, 204);
    }

    private function validated(Request $request, ?Contact $contact = null): array
    {
        return $request->validate([
            'company_id' => ['nullable', new VisibleRecord(Company::class, $request->user())],
            'first_name' => [$contact ? 'sometimes' : 'required', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'job_title' => ['nullable', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'marketing_consent' => ['boolean'],
        ]);
    }
}
