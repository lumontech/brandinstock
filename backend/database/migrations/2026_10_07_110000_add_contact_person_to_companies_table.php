<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            // Nome e cognome della persona di riferimento del lead (cifrato come gli altri dati personali).
            $table->text('contact_person')->nullable();
        });

        // Lead importati da Airtable: il nome era finito nelle note ("Nome e Cognome: Mario Rossi").
        DB::table('companies')->whereNotNull('notes')->select(['id', 'notes'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $notes = Crypt::decryptString($row->notes);
                    } catch (Throwable) {
                        continue;
                    }
                    if (preg_match('/^(?:Nome e Cognome|Nome e cognome|Referente): *(.+)$/mu', $notes, $m)) {
                        $person = mb_substr(trim($m[1]), 0, 150);
                        DB::table('companies')->where('id', $row->id)->update(['contact_person' => Crypt::encryptString($person)]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('contact_person');
        });
    }
};
