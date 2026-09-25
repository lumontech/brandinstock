<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Deal> */
class DealFactory extends Factory
{
    public function definition(): array
    {
        $brand = fake()->randomElement(['Guess', 'Liu Jo', 'Calvin Klein', 'Tommy Hilfiger', 'Levi\'s', 'Nike', 'Adidas', 'Diesel']);

        return [
            'title' => "Stock {$brand} ".fake()->randomElement(['PE', 'AI']).' '.fake()->numberBetween(24, 26),
            'company_id' => Company::factory(),
            'owner_id' => User::factory(),
            'pipeline_stage_id' => fn () => PipelineStage::query()->orderBy('position')->value('id') ?? PipelineStage::factory(),
            'value' => fake()->randomFloat(2, 1000, 80000),
            'currency' => 'EUR',
            'brand' => $brand,
            'product_category' => fake()->randomElement(['Abbigliamento donna', 'Abbigliamento uomo', 'Calzature', 'Accessori', 'Kids']),
            'quantity' => fake()->numberBetween(100, 5000),
            'expected_close_date' => fake()->dateTimeBetween('-10 days', '+60 days'),
            'source' => fake()->randomElement(Deal::SOURCES),
        ];
    }
}
