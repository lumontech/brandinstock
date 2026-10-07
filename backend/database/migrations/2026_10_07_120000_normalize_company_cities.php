<?php

use App\Support\ItalianCity;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** Città già inserite scritte in modo uniforme ("ct" → Catania) e provincia ricavata dove manca. */
    public function up(): void
    {
        DB::table('companies')->whereNotNull('city')->select(['id', 'city', 'province'])
            ->chunkById(500, function ($rows) {
                foreach ($rows as $row) {
                    $city = ItalianCity::normalize($row->city);
                    $changes = array_filter([
                        'city' => $city['city'] !== $row->city ? $city['city'] : null,
                        'province' => $city['province'] && empty($row->province) ? $city['province'] : null,
                    ]);
                    if ($changes) {
                        DB::table('companies')->where('id', $row->id)->update($changes);
                    }
                }
            });
    }

    public function down(): void
    {
        // I valori originali non sono recuperabili (e non servono).
    }
};
