<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Legge una tabella di Airtable e la restituisce come righe "colonna → valore", pronte per
 * l'importazione (stesso abbinamento colonne del CSV). Il token Airtable arriva con la richiesta,
 * viene usato solo per questa lettura e non viene salvato né registrato nei log.
 */
class AirtableRecordsController extends Controller
{
    private const MAX_RECORDS = 20000;

    private const SYSTEM_FIELDS = '/^\s*(creato|creata|created|modificato|modificata|last modified|ultima modifica)(\s+(da|by|il|on|time))?\s*$/iu';

    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255', 'regex:/^pat[A-Za-z0-9.]+$/'],
            'base_id' => ['required', 'string', 'regex:/^app[A-Za-z0-9]{14}$/'],
            'table_id' => ['required', 'string', 'regex:/^tbl[A-Za-z0-9]{14}$/'],
            'view_id' => ['nullable', 'string', 'regex:/^viw[A-Za-z0-9]{14}$/'],
        ], [
            'token.regex' => 'Il token Airtable deve iniziare con "pat".',
            'base_id.regex' => 'Link di Airtable non valido: copia il link della tabella dal browser.',
            'table_id.regex' => 'Link di Airtable non valido: copia il link della tabella dal browser.',
        ]);

        $headers = [];
        $rows = [];
        $offset = null;
        do {
            $query = array_filter([
                'pageSize' => 100,
                'offset' => $offset,
                'view' => $data['view_id'] ?? null,
                // Valori già come testo: i record collegati diventano i loro nomi.
                'cellFormat' => 'string',
                'timeZone' => 'Europe/Rome',
                'userLocale' => 'it',
            ]);
            try {
                $response = Http::withToken($data['token'])
                    ->acceptJson()
                    ->timeout(30)
                    ->retry(3, 1000, fn ($e) => $e instanceof ConnectionException || $e->response?->status() === 429, throw: false)
                    ->get("https://api.airtable.com/v0/{$data['base_id']}/{$data['table_id']}", $query);
            } catch (ConnectionException) {
                return response()->json(['message' => 'Airtable non risponde: riprova tra qualche minuto.'], 502);
            }

            if (! $response->successful()) {
                $message = match ($response->status()) {
                    401 => 'Token Airtable non valido o scaduto.',
                    403 => 'Il token non ha accesso a questa base: in Airtable aggiungi la base al token, con il permesso data.records:read.',
                    404 => 'Base o tabella non trovata: controlla il link e che il token abbia accesso alla base.',
                    429 => 'Airtable sta limitando le richieste: riprova tra un minuto.',
                    default => 'Airtable ha risposto con un errore ('.$response->status().').',
                };

                return response()->json(['message' => $message], 422);
            }

            foreach ($response->json('records', []) as $record) {
                $row = [];
                foreach ($record['fields'] ?? [] as $name => $value) {
                    // Campi di sistema di Airtable (chi ha creato o modificato il record): non sono dati del lead.
                    if (preg_match(self::SYSTEM_FIELDS, (string) $name)) {
                        continue;
                    }
                    $text = trim(is_array($value) ? implode(', ', array_map(fn ($v) => is_scalar($v) ? (string) $v : json_encode($v), $value)) : (string) $value);
                    if ($text === '') {
                        continue;
                    }
                    $headers[$name] = true;
                    $row[$name] = $text;
                }
                if ($row) {
                    $rows[] = $row;
                }
            }
            $offset = $response->json('offset');
            if ($offset) {
                // Airtable accetta al massimo 5 richieste al secondo per base.
                usleep(250_000);
            }
        } while ($offset && count($rows) < self::MAX_RECORDS);

        return response()->json(['headers' => array_keys($headers), 'rows' => $rows]);
    }
}
