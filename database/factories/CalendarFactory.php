<?php

namespace Database\Factories;

use App\Models\CalendarAccount;
use App\Models\Calendar;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Calendar>
 */
class CalendarFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'calendar_account_id' => CalendarAccount::factory(),
            'external_id' => fake()->uuid(),
            'name' => fake()->words(2, true),
            'color' => fake()->hexColor(),
            'timezone' => 'Europe/Paris',
            'is_selected' => true,
        ];
    }
}
