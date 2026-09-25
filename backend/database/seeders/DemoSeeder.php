<?php

namespace Database\Seeders;

use App\Enums\ActivityType;
use App\Enums\UserRole;
use App\Models\Activity;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

/** Dati dimostrativi: SOLO per ambienti locali (php artisan db:seed --class=DemoSeeder). */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder non può essere eseguito in produzione.');
        }

        $this->call(DatabaseSeeder::class);

        $admin = User::factory()->create(['name' => 'Admin Demo', 'email' => 'admin@brandinstock.test', 'role' => UserRole::Admin]);
        $sellers = collect([
            User::factory()->create(['name' => 'Giulia Rossi', 'email' => 'giulia@brandinstock.test', 'role' => UserRole::Sales]),
            User::factory()->create(['name' => 'Marco Bianchi', 'email' => 'marco@brandinstock.test', 'role' => UserRole::Sales]),
        ]);
        $stages = PipelineStage::orderBy('position')->get();

        foreach ($sellers as $seller) {
            Company::factory(6)->for($seller, 'owner')->create()->each(function (Company $company) use ($seller, $stages) {
                $contact = Contact::factory()->for($company)->for($seller, 'owner')->create();
                Deal::factory(rand(1, 2))->for($company)->for($seller, 'owner')->create([
                    'contact_id' => $contact->id,
                    'pipeline_stage_id' => $stages->random()->id,
                ])->each(fn (Deal $deal) => Activity::factory()->create([
                    'user_id' => $seller->id,
                    'deal_id' => $deal->id,
                    'company_id' => $company->id,
                    'type' => ActivityType::Call,
                ]));
            });
        }

        $this->command?->info("Utenti demo creati (password: 'password'): {$admin->email}, ".$sellers->pluck('email')->implode(', '));
    }
}
