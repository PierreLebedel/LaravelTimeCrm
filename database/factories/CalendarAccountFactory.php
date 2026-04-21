<?php

namespace Database\Factories;

use App\Models\CalendarAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarAccount>
 */
class CalendarAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company().' CalDAV',
            'base_url' => fake()->url(),
            'username' => fake()->safeEmail(),
            'password' => fake()->password(18),
            'is_active' => true,
            'last_synced_at' => now()->subDay(),
        ];
    }
}
