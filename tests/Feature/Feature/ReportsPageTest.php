<?php

use App\Enums\CalendarEventFormatStatus;
use App\Models\CalendarEvent;
use App\Models\Client;

test('it excludes non billable events from analysis', function () {
    $billableClient = Client::factory()->create([
        'name' => 'Acme',
    ]);

    $nonBillableClient = Client::factory()->create([
        'name' => 'Interne',
    ]);

    CalendarEvent::factory()->create([
        'client_id' => $billableClient->id,
        'starts_at' => '2026-04-15 09:00:00',
        'ends_at' => '2026-04-15 10:00:00',
        'is_billable' => true,
        'format_status' => CalendarEventFormatStatus::Formatted,
    ]);

    CalendarEvent::factory()->create([
        'client_id' => $nonBillableClient->id,
        'starts_at' => '2026-04-15 11:00:00',
        'ends_at' => '2026-04-15 12:00:00',
        'is_billable' => false,
        'format_status' => CalendarEventFormatStatus::Formatted,
    ]);

    $this->get('/analyse?from=2026-04-01&to=2026-04-30&group=client')
        ->assertOk()
        ->assertSee('Acme')
        ->assertDontSee('Interne');
});
