<?php

namespace Database\Factories;

use App\Models\CalendarAccount;
use App\Models\Client;
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
            'default_client_id' => null,
            'is_active' => true,
            'last_synced_at' => now()->subDay(),
        ];
    }

    public function withDefaultClient(?Client $client = null): static
    {
        return $this->state(fn (): array => [
            'default_client_id' => $client?->id ?? Client::factory(),
        ]);
    }
}
