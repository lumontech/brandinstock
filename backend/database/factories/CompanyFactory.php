<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Company> */
class CompanyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company(),
            'segment' => fake()->randomElement(Company::SEGMENTS),
            'vat_number' => 'IT'.fake()->unique()->numerify('###########'),
            'type' => fake()->randomElement(Company::TYPES),
            'city' => fake()->city(),
            'country' => 'IT',
            'email' => fake()->companyEmail(),
            'phone' => fake()->phoneNumber(),
            'owner_id' => User::factory(),
        ];
    }
}
