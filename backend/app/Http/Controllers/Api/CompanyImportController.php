<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Importazione clienti (es. da un CSV esportato da Airtable). Il file viene letto nel browser,
 * che invia le righe già abbinate ai campi del CRM a blocchi. Ogni riga è validata come un
 * inserimento manuale; con dry_run tutto avviene in una transazione annullata alla fine.
 */
class CompanyImportController extends Controller
{
    private const COMPANY_FIELDS = ['name', 'segment', 'type', 'vat_number', 'tax_code', 'city', 'province', 'country', 'address', 'email', 'phone', 'website', 'notes'];

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*' => ['array'],
            'duplicates' => ['required', Rule::in(['skip', 'update'])],
            'dry_run' => ['boolean'],
        ]);
        $user = $request->user();
        $dryRun = $request->boolean('dry_run');
        $owners = $user->seesEverything() ? User::where('is_active', true)->pluck('id', 'email') : collect();

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'contacts_created' => 0, 'errors' => []];

        DB::beginTransaction();
        try {
            foreach ($payload['rows'] as $index => $raw) {
                $row = $this->normalize($raw);
                $errors = $this->validateRow($row);
                if ($errors) {
                    $result['errors'][] = ['row' => $index, 'messages' => $errors];

                    continue;
                }

                $existing = $this->findExisting($row, $user);
                if ($existing === false) {
                    $result['errors'][] = ['row' => $index, 'messages' => ['Questa partita IVA è già presente nel CRM: contatta un responsabile.']];

                    continue;
                }

                if ($existing) {
                    if ($payload['duplicates'] === 'skip') {
                        $result['skipped']++;
                        $company = $existing;
                    } else {
                        // Aggiorna solo i campi valorizzati nel file, senza cancellare dati esistenti.
                        $existing->fill(array_filter(array_intersect_key($row, array_flip(self::COMPANY_FIELDS)), fn ($v) => $v !== null && $v !== ''));
                        $existing->save();
                        $result['updated']++;
                        $company = $existing;
                    }
                } else {
                    $company = new Company(array_intersect_key($row, array_flip(self::COMPANY_FIELDS)));
                    $company->owner_id = $owners->get($row['owner_email'] ?? '') ?? $user->id;
                    $company->save();
                    $result['created']++;
                }

                if ($this->createContact($company, $row, $user)) {
                    $result['contacts_created']++;
                }
            }

            if ($dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
                AuditLog::record('companies_imported', null, array_diff_key($result, ['errors' => true]));
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return response()->json([...$result, 'dry_run' => $dryRun]);
    }

    /** Pulisce i valori e converte le etichette leggibili (es. "Franchising", "Boutique") nei codici del CRM. */
    private function normalize(array $raw): array
    {
        $row = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                $value = is_string($value) ? trim(preg_replace('/\s+/u', ' ', $value)) : $value;
                $row[$key] = $value === '' ? null : $value;
            }
        }

        $row['segment'] = $this->matchOption($row['segment'] ?? null, [
            'b2b' => ['b2b', 'business', 'azienda', 'aziende'],
            'b2c' => ['b2c', 'privato', 'privati', 'consumer', 'cliente finale'],
            'franchising' => ['franchising', 'franchise', 'franchisee', 'affiliato'],
        ]) ?? 'b2b';
        $row['type'] = $this->matchOption($row['type'] ?? null, [
            'boutique' => ['boutique', 'negozio'],
            'outlet' => ['outlet'],
            'grossista' => ['grossista', 'ingrosso', 'wholesale'],
            'ecommerce' => ['ecommerce', 'e-commerce', 'online', 'shop online'],
            'catena' => ['catena', 'catena retail', 'retail'],
            'altro' => ['altro', 'other'],
        ]);
        if (! empty($row['vat_number'])) {
            $row['vat_number'] = strtoupper(str_replace([' ', '.', '-'], '', $row['vat_number']));
        }
        if (! empty($row['tax_code'])) {
            $row['tax_code'] = strtoupper(str_replace(' ', '', $row['tax_code']));
        }
        if (! empty($row['country'])) {
            $row['country'] = strlen($row['country']) === 2 ? strtoupper($row['country']) : (Str::contains(Str::lower($row['country']), 'ital') ? 'IT' : null);
        }
        if (! empty($row['website']) && ! preg_match('#^https?://#i', $row['website'])) {
            $row['website'] = 'https://'.$row['website'];
        }
        if (! empty($row['email'])) {
            $row['email'] = Str::lower($row['email']);
        }
        if (! empty($row['owner_email'])) {
            $row['owner_email'] = Str::lower($row['owner_email']);
        }
        // "Mario Rossi" in un'unica colonna referente → nome e cognome.
        if (! empty($row['contact_name']) && empty($row['contact_first_name'])) {
            [$first, $last] = array_pad(explode(' ', $row['contact_name'], 2), 2, null);
            $row['contact_first_name'] = $first;
            $row['contact_last_name'] ??= $last;
        }

        return $row;
    }

    /** @param  array<string, list<string>>  $options */
    private function matchOption(?string $value, array $options): ?string
    {
        if ($value === null) {
            return null;
        }
        $needle = Str::lower(trim($value));
        foreach ($options as $key => $aliases) {
            if ($needle === $key || in_array($needle, $aliases, true)) {
                return $key;
            }
        }

        return null;
    }

    /** @return list<string> */
    private function validateRow(array $row): array
    {
        return Validator::make($row, [
            'name' => ['required', 'string', 'max:255'],
            'segment' => [Rule::in(Company::SEGMENTS)],
            'type' => ['nullable', Rule::in(Company::TYPES)],
            'vat_number' => ['nullable', 'string', 'max:32'],
            'tax_code' => ['nullable', 'regex:/^[A-Za-z0-9]{11,16}$/'],
            'city' => ['nullable', 'string', 'max:100'],
            'province' => ['nullable', 'string', 'max:10'],
            'country' => ['nullable', 'string', 'size:2'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'website' => ['nullable', 'url:http,https', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'contact_first_name' => ['nullable', 'string', 'max:100'],
            'contact_last_name' => ['nullable', 'string', 'max:100'],
            'contact_job_title' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
        ], [
            'name.required' => 'Manca il nome del cliente.',
            'tax_code.regex' => 'Codice fiscale non valido.',
        ], [
            'email' => 'email',
            'website' => 'sito web',
            'contact_email' => 'email del referente',
        ])->errors()->all();
    }

    /**
     * Cliente già presente: stessa P.IVA, oppure stesso nome tra quelli visibili all'utente.
     * Restituisce false se la P.IVA appartiene a un cliente di un altro venditore (non va rivelato).
     */
    private function findExisting(array $row, User $user): Company|false|null
    {
        if (! empty($row['vat_number'])) {
            $match = Company::withTrashed()->where('vat_number', $row['vat_number'])->first();
            if ($match) {
                return ! $match->trashed() && ($user->seesEverything() || $match->owner_id === $user->id) ? $match : false;
            }
        }

        return Company::query()->visibleTo($user)->whereRaw('LOWER(name) = ?', [Str::lower($row['name'])])->first();
    }

    private function createContact(Company $company, array $row, User $user): bool
    {
        if (empty($row['contact_first_name']) && empty($row['contact_email'])) {
            return false;
        }
        $hash = Contact::hashEmail($row['contact_email'] ?? null);
        if ($hash && $company->contacts()->where('email_hash', $hash)->exists()) {
            return false;
        }

        $contact = new Contact([
            'company_id' => $company->id,
            'first_name' => $row['contact_first_name'] ?? Str::before((string) $row['contact_email'], '@'),
            'last_name' => $row['contact_last_name'] ?? null,
            'job_title' => $row['contact_job_title'] ?? null,
            'email' => $row['contact_email'] ?? null,
            'phone' => $row['contact_phone'] ?? null,
        ]);
        $contact->owner_id = $company->owner_id ?? $user->id;
        $contact->save();

        return true;
    }
}
