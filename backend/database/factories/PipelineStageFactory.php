<?php

namespace Database\Factories;

use App\Models\PipelineStage;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PipelineStage> */
class PipelineStageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'position' => fake()->numberBetween(0, 10),
            'probability' => 20,
            'color' => '#64748b',
        ];
    }
}
