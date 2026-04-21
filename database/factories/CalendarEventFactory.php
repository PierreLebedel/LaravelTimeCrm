<?php

namespace Database\Factories;

use App\Enums\CalendarEventFormatStatus;
use App\Enums\CalendarEventSyncStatus;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CalendarEvent>
 */
class CalendarEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('-10 days', '+10 days');
        $endsAt = (clone $startsAt)->modify('+'.fake()->numberBetween(30, 210).' minutes');

        return [
            'calendar_id' => Calendar::factory(),
            'client_id' => Client::factory(),
            'project_id' => null,
            'ical_uid' => fake()->uuid(),
            'external_id' => fake()->uuid(),
            'external_etag' => fake()->sha1(),
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'timezone' => 'Europe/Paris',
            'title' => fake()->sentence(4),
            'description' => fake()->paragraph(),
            'feature_description' => fake()->sentence(3),
            'sync_status' => CalendarEventSyncStatus::Synced,
            'format_status' => CalendarEventFormatStatus::NeedsReview,
            'source_updated_at' => now()->subHour(),
            'last_synced_at' => now()->subMinutes(20),
        ];
    }
}
