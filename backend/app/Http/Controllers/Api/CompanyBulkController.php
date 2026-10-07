<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Support\LeadSource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Azioni massive su leads e clienti selezionati nella griglia: modifica di alcuni campi
 * o eliminazione. Ogni record passa dalle stesse regole di permesso dell'azione singola.
 */
class CompanyBulkController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:1000'],
            'ids.*' => ['integer', 'distinct'],
            'action' => ['required', Rule::in(['update', 'delete'])],
            // Eliminazione: se vero elimina anche i record con opportunità, insieme alle opportunità.
            'with_deals' => ['sometimes', 'boolean'],
            'changes' => ['required_if:action,update', 'array'],
            'changes.segment' => ['sometimes', Rule::in(Company::SEGMENTS)],
            'changes.source' => ['sometimes', 'nullable', Rule::in(LeadSource::ALL)],
            'changes.type' => ['sometimes', 'nullable', Rule::in(Company::TYPES)],
            'changes.status' => ['sometimes', Rule::in(Company::STATUSES)],
            'changes.owner_id' => ['sometimes', 'integer', Rule::exists(User::class, 'id')->where('is_active', true)],
        ]);

        if ($data['action'] === 'delete' && ! $user->seesEverything()) {
            return response()->json(['message' => 'Solo amministratori e responsabili possono eliminare.'], 403);
        }
        if (isset($data['changes']['owner_id']) && ! Gate::allows('assign', Company::class)) {
            return response()->json(['message' => 'Solo amministratori e responsabili possono cambiare il venditore.'], 403);
        }

        // Solo i record visibili all'utente: gli ID di altri venditori vengono ignorati.
        $companies = Company::query()->visibleTo($user)->whereKey($data['ids'])->withCount('deals')->get();
        $result = ['processed' => 0, 'skipped' => count($data['ids']) - $companies->count(), 'skipped_with_deals' => 0, 'deals_deleted' => 0];

        DB::transaction(function () use ($companies, $data, &$result) {
            foreach ($companies as $company) {
                if ($data['action'] === 'delete') {
                    if ($company->deals_count > 0 && ! ($data['with_deals'] ?? false)) {
                        $result['skipped_with_deals']++;

                        continue;
                    }
                    $result['deals_deleted'] += $company->deleteWithRelated();
                } else {
                    $changes = $data['changes'];
                    $company->fill(array_intersect_key($changes, array_flip(['segment', 'source', 'type'])));
                    if (array_key_exists('owner_id', $changes)) {
                        $company->owner_id = $changes['owner_id'];
                    }
                    if (isset($changes['status']) && $changes['status'] !== $company->status) {
                        $company->status = $changes['status'];
                        $company->converted_at = $changes['status'] === 'customer' ? now() : null;
                    }
                    $company->save();
                }
                $result['processed']++;
            }
        });

        AuditLog::record('companies_bulk_'.$data['action'], null, [...$result, 'ids' => $companies->pluck('id')->all()]);

        return response()->json($result);
    }
}
