<?php

namespace Database\Seeders;

use App\Models\PipelineStage;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /** Dati di base indispensabili (fasi della pipeline). Gli utenti si creano con `php artisan crm:create-admin`. */
    public function run(): void
    {
        if (PipelineStage::exists()) {
            return;
        }

        $stages = [
            ['name' => 'Nuovo lead', 'probability' => 10, 'color' => '#64748b'],
            ['name' => 'Contattato', 'probability' => 20, 'color' => '#0ea5e9'],
            ['name' => 'Qualificato', 'probability' => 40, 'color' => '#6366f1'],
            ['name' => 'Offerta inviata', 'probability' => 60, 'color' => '#f59e0b'],
            ['name' => 'Trattativa', 'probability' => 80, 'color' => '#f97316'],
            ['name' => 'Vinto', 'probability' => 100, 'color' => '#16a34a', 'is_won' => true],
            ['name' => 'Perso', 'probability' => 0, 'color' => '#dc2626', 'is_lost' => true],
        ];

        foreach ($stages as $position => $stage) {
            PipelineStage::create([...$stage, 'position' => $position]);
        }
    }
}
