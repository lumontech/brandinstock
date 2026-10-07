<?php

use App\Support\LeadStatus;
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
            // Stato di lavorazione del lead (vedi App\Support\LeadStatus).
            $table->string('lead_status', 40)->nullable()->index();
        });

        // Lead già importati da Airtable: lo stato era finito nelle note ("Stato: Prospect").
        // Le note sono cifrate, quindi vanno lette e decifrate qui.
        DB::table('companies')->whereNotNull('notes')->orderBy('id')->select(['id', 'notes'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    try {
                        $notes = Crypt::decryptString($row->notes);
                    } catch (Throwable) {
                        continue;
                    }
                    if (preg_match('/^Stato(?: Airtable)?: *(.+)$/mu', $notes, $m) && ($status = LeadStatus::match($m[1]))) {
                        DB::table('companies')->where('id', $row->id)->update(['lead_status' => $status]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex(['lead_status']);
            $table->dropColumn('lead_status');
        });
    }
};
