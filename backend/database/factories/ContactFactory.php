<?php

namespace Database\Factories;

use App\Models\Company;
use App\Models\Contact;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Contact> */
class ContactFactory extends Factory
{
    public function definition(): array
    {
        return [
            'company_id' => Company::factory(),
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'job_title' => fake()->randomElement(['Buyer', 'Titolare', 'Responsabile acquisti', 'Store manager']),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'owner_id' => User::factory(),
        ];
    }
}
