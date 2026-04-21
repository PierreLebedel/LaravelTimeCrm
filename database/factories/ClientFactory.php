<?php

namespace Database\Factories;

use App\Enums\BillingMode;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'billing_mode' => BillingMode::Hourly,
            'hourly_rate' => fake()->randomFloat(2, 60, 1400),
            'daily_rate' => fake()->randomFloat(2, 350, 5000),
            'is_active' => true,
        ];
    }

    public function daily(): static
    {
        return $this->state(fn () => [
            'billing_mode' => BillingMode::Daily,
        ]);
    }
}
