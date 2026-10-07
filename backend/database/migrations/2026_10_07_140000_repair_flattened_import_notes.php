<?php

use App\Support\FlattenedNotes;
use App\Support\LeadStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Lead importati da Airtable con le note su un'unica riga: le note tornano una informazione
     * per riga e da lì si recuperano lo Stato e il Nome e cognome (senza il "Creato" di Airtable).
     */
    public function up(): void
    {
        DB::table('companies')->whereNotNull('notes')->select(['id', 'notes', 'lead_status'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $repaired = FlattenedNotes::split(Crypt::decryptString($row->notes));
                    } catch (Throwable) {
                        continue;
                    }
                    if ($repaired === null) {
                        continue;
                    }
                    $changes = ['notes' => $repaired['notes'] === null ? null : Crypt::encryptString($repaired['notes'])];
                    $status = LeadStatus::match($repaired['fields']['Stato'] ?? null);
                    if ($status && ! $row->lead_status) {
                        $changes['lead_status'] = $status;
                    }
                    if (! empty($repaired['fields']['Nome e Cognome'])) {
                        $changes['contact_person'] = Crypt::encryptString(mb_substr($repaired['fields']['Nome e Cognome'], 0, 150));
                    }
                    DB::table('companies')->where('id', $row->id)->update($changes);
                }
            });
    }

    public function down(): void
    {
        // Riparazione dei dati: niente da annullare.
    }
};
