<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Activity> */
class ActivityFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement([ActivityType::Call, ActivityType::Email, ActivityType::Meeting, ActivityType::Task]),
            'subject' => fake()->randomElement(['Richiamare per offerta', 'Inviare listino stock', 'Visita showroom', 'Follow-up campionatura']),
            'user_id' => User::factory(),
            'due_at' => fake()->dateTimeBetween('-3 days', '+10 days'),
        ];
    }
}
