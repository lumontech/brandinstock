<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Toglie dal "Nome e cognome" il testo "Creato da …" arrivato da Airtable. */
    public function up(): void
    {
        DB::table('companies')->whereNotNull('contact_person')->select(['id', 'contact_person'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $person = Crypt::decryptString($row->contact_person);
                    } catch (Throwable) {
                        continue;
                    }
                    $clean = trim(preg_replace('/[\s,;:\-–|]*\b(creato|creata|created)\b.*$/isu', '', $person), " \t\n\r,;:-–|");
                    if ($clean !== $person) {
                        DB::table('companies')->where('id', $row->id)
                            ->update(['contact_person' => $clean === '' ? null : Crypt::encryptString($clean)]);
                    }
                }
            });
    }

    public function down(): void
    {
        // Pulizia dei dati: niente da annullare.
    }
};
