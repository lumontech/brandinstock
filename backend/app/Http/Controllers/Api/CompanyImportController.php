<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use App\Support\ItalianCity;
use App\Support\LeadSource;
use App\Support\LeadStatus;
use Illuminate\Database\QueryException;
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
    private const COMPANY_FIELDS = ['name', 'segment', 'source', 'lead_status', 'contact_person', 'type', 'vat_number', 'tax_code', 'city', 'province', 'country', 'address', 'email', 'phone', 'website', 'notes',
        'billing_name', 'billing_address', 'billing_zip', 'billing_city', 'billing_province', 'sdi_code', 'pec', 'iban', 'payment_terms'];

    public function __invoke(Request $request): JsonResponse
    {
        $payload = $request->validate([
            'rows' => ['required', 'array', 'min:1', 'max:500'],
            'rows.*' => ['array'],
            'duplicates' => ['required', Rule::in(['skip', 'update'])],
            'status' => ['nullable', Rule::in(Company::STATUSES)],
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

                // Ogni riga in un savepoint: se il database rifiuta una riga, si annulla solo
                // quella e l'importazione prosegue con le altre.
                try {
                    $outcome = DB::transaction(fn () => $this->importRow($row, $user, $owners, $payload['duplicates'], $payload['status'] ?? 'lead'));
                } catch (QueryException $e) {
                    report($e);
                    $outcome = ['error' => $this->describeDatabaseError($e)];
                }

                if (isset($outcome['error'])) {
                    $result['errors'][] = ['row' => $index, 'messages' => [$outcome['error']]];

                    continue;
                }
                $result[$outcome['status']]++;
                $result['contacts_created'] += $outcome['contact'] ? 1 : 0;
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

    /**
     * Motivo leggibile dell'errore del database, con il codice SQLSTATE per l'assistenza.
     * Si usa solo la prima riga del messaggio (senza il DETAIL, che può contenere i dati).
     */
    private function describeDatabaseError(QueryException $e): string
    {
        $state = (string) ($e->errorInfo[0] ?? $e->getCode());
        $message = Str::of((string) ($e->errorInfo[2] ?? ''))->before("\n")->after('ERROR:')->trim()->limit(160)->value();
        $reason = match ($state) {
            '23505', '23000' => 'esiste già un record con lo stesso valore',
            '22001' => 'un valore è troppo lungo',
            '23502' => 'manca un valore obbligatorio',
            '42501' => 'permesso negato dal database',
            '42703', '42P01' => 'la struttura del database non è aggiornata',
            default => 'dato non accettato dal database',
        };

        return "Riga non salvata: {$reason} (codice {$state}".($message !== '' ? ": {$message}" : '').').';
    }

    /** @return array{status?: string, contact?: bool, error?: string} */
    private function importRow(array $row, User $user, $owners, string $duplicates, string $status): array
    {
        $existing = $this->findExisting($row, $user);
        if ($existing === false) {
            return ['error' => 'Questa partita IVA è già presente nel CRM: contatta un responsabile.'];
        }

        if ($existing && $duplicates === 'skip') {
            $result = 'skipped';
            $company = $existing;
        } elseif ($existing) {
            // Aggiorna solo i campi valorizzati nel file, senza cancellare dati esistenti.
            $existing->fill(array_filter(array_intersect_key($row, array_flip(self::COMPANY_FIELDS)), fn ($v) => $v !== null && $v !== ''));
            $existing->save();
            $result = 'updated';
            $company = $existing;
        } else {
            $company = new Company(array_intersect_key($row, array_flip(self::COMPANY_FIELDS)));
            $company->owner_id = $owners->get($row['owner_email'] ?? '') ?? $user->id;
            $company->status = $status;
            $company->converted_at = $status === 'customer' ? now() : null;
            $company->save();
        }

        return ['status' => $result ?? 'created', 'contact' => $this->createContact($company, $row, $user)];
    }

    /** Pulisce i valori e converte le etichette leggibili (es. "Franchising", "Boutique") nei codici del CRM. */
    private function normalize(array $raw): array
    {
        $row = [];
        foreach ($raw as $key => $value) {
            if (is_string($key) && (is_scalar($value) || $value === null)) {
                if (is_string($value)) {
                    // Le note mantengono gli a capo (una informazione per riga), gli altri campi no.
                    $value = in_array($key, ['notes', 'billing_notes'], true)
                        ? trim(preg_replace(['/[^\S\n]+/u', '/ *\n */u', '/\n{3,}/u'], [' ', "\n", "\n\n"], str_replace("\r", '', $value)))
                        : trim(preg_replace('/\s+/u', ' ', $value));
                }
                // Celle vuote o segnaposto tipici degli export ("-", "N/A", "n.d.", "?", "0").
                $row[$key] = ($value === '' || (is_string($value) && preg_match('/^(-+|n\/?a|n\.?d\.?|none|null|nessun[oa]?|\?+|0)$/iu', $value))) ? null : $value;
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
        if (! empty($row['source'])) {
            $original = $row['source'];
            $row['source'] = LeadSource::match($original) ?? 'altro';
            // Provenienza non riconosciuta: resta leggibile nelle note.
            if ($row['source'] === 'altro' && LeadSource::match($original) === null) {
                $row['notes'] = trim(($row['notes'] ?? '')."\nProvenienza: {$original}");
            }
        }
        if (! empty($row['lead_status'])) {
            $original = $row['lead_status'];
            $row['lead_status'] = LeadStatus::match($original);
            // Stato non riconosciuto: resta leggibile nelle note.
            if ($row['lead_status'] === null) {
                $row['notes'] = trim(($row['notes'] ?? '')."\nStato: {$original}");
            }
        }
        if (array_key_exists('lead_status', $row) && empty($row['lead_status'])) {
            // Stato vuoto nel file: resta vuoto, come in Airtable.
            $row['lead_status'] = null;
        }
        if (! empty($row['sdi_code'])) {
            $sdi = strtoupper(preg_replace('/\s+/', '', (string) $row['sdi_code']));
            $row['sdi_code'] = preg_match('/^[A-Z0-9]{6,7}$/', $sdi) ? $sdi : null;
        }
        if (! empty($row['iban'])) {
            $row['iban'] = strtoupper(str_replace(' ', '', (string) $row['iban']));
        }
        if (! empty($row['pec'])) {
            $row['pec'] = Str::lower($row['pec']);
        }
        if (! empty($row['vat_number'])) {
            $vat = strtoupper(preg_replace('/[\s.\-\/]/', '', (string) $row['vat_number']));
            // Una P.IVA ha almeno 8 cifre: altri valori non sono P.IVA e non vanno usati per riconoscere i duplicati.
            $row['vat_number'] = preg_match('/^[A-Z]{0,2}\d{8,15}$/', $vat) ? $vat : null;
        }
        if (! empty($row['tax_code'])) {
            $cf = strtoupper(str_replace(' ', '', (string) $row['tax_code']));
            $row['tax_code'] = preg_match('/^[A-Z0-9]{11,16}$/', $cf) ? $cf : null;
        }
        if (! empty($row['country'])) {
            $row['country'] = strlen($row['country']) === 2 ? strtoupper($row['country']) : (Str::contains(Str::lower($row['country']), 'ital') ? 'IT' : null);
        }
        if (! empty($row['website']) && ! preg_match('#^https?://#i', $row['website'])) {
            $row['website'] = 'https://'.$row['website'];
        }
        if (! empty($row['email'])) {
            $row['email'] = Str::lower(trim($row['email']));
            // Email non valida: non blocca la riga, resta leggibile nelle note.
            if (! filter_var($row['email'], FILTER_VALIDATE_EMAIL)) {
                $row['notes'] = trim(($row['notes'] ?? '')."\nEmail (non valida): {$row['email']}");
                $row['email'] = null;
            }
        }
        // Più città (es. record collegati di Airtable): tengo la prima, l'elenco completo va nelle note.
        if (! empty($row['city']) && mb_strlen($row['city']) > 100) {
            $row['notes'] = trim(($row['notes'] ?? '')."\nCittà: {$row['city']}");
            $row['city'] = Str::limit(trim(explode(',', $row['city'])[0]), 100, '');
        }
        if (! empty($row['city'])) {
            $city = ItalianCity::normalize($row['city']);
            $row['city'] = $city['city'];
            if ($city['province'] && empty($row['province'])) {
                $row['province'] = $city['province'];
            }
        }
        if (! empty($row['phone']) && mb_strlen($row['phone']) > 40) {
            $row['notes'] = trim(($row['notes'] ?? '')."\nTelefono: {$row['phone']}");
            $row['phone'] = null;
        }
        if (! empty($row['notes'])) {
            $row['notes'] = Str::limit($row['notes'], 5000, '');
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
            'source' => ['nullable', Rule::in(LeadSource::ALL)],
            'lead_status' => ['nullable', Rule::in(LeadStatus::ALL)],
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
            'contact_person' => ['nullable', 'string', 'max:150'],
            'billing_name' => ['nullable', 'string', 'max:255'],
            'billing_address' => ['nullable', 'string', 'max:255'],
            'billing_zip' => ['nullable', 'string', 'max:10'],
            'billing_city' => ['nullable', 'string', 'max:100'],
            'billing_province' => ['nullable', 'string', 'max:10'],
            'pec' => ['nullable', 'email', 'max:255'],
            'iban' => ['nullable', 'regex:/^[A-Z]{2}\d{2}[A-Z0-9]{11,30}$/'],
            'payment_terms' => ['nullable', 'string', 'max:100'],
            'contact_first_name' => ['nullable', 'string', 'max:100'],
            'contact_last_name' => ['nullable', 'string', 'max:100'],
            'contact_job_title' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
        ], [
            'name.required' => 'Manca il nome del cliente.',
            'tax_code.regex' => 'Codice fiscale non valido.',
            'iban.regex' => 'IBAN non valido.',
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
            'first_name' => Str::limit($row['contact_first_name'] ?? Str::before((string) $row['contact_email'], '@'), 100, ''),
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
