<?php

use App\Enums\CalendarEventFormatStatus;
use App\Livewire\ReviewMenuItem;
use App\Models\CalendarEvent;
use Livewire\Livewire;

test('it refreshes the badge count when the review queue update event is dispatched', function () {
    CalendarEvent::factory()->create([
        'format_status' => CalendarEventFormatStatus::NeedsReview,
    ]);

    $component = Livewire::test(ReviewMenuItem::class)
        ->assertSee('Revue')
        ->assertSee('1');

    CalendarEvent::factory()->create([
        'format_status' => CalendarEventFormatStatus::NeedsReview,
    ]);

    $component
        ->dispatch('review-queue-updated')
        ->assertSee('2');
});
