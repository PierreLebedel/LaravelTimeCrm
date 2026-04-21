<?php

use App\Enums\CalendarEventFormatStatus;
use App\Models\CalendarEvent;

test('it shows the review badge in the sidebar when events need review', function () {
    CalendarEvent::factory()->create([
        'format_status' => CalendarEventFormatStatus::NeedsReview,
    ]);

    $this->get('/')
        ->assertOk()
        ->assertSee('Revue')
        ->assertSee('1');
});
