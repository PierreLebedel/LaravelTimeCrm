<?php

use App\Enums\CalendarEventFormatStatus;
use App\Enums\CalendarEventSyncStatus;
use App\Models\Calendar;
use App\Models\CalendarAccount;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\QueryException;

test('a client with linked calendar events can not be deleted', function () {
    $client = Client::factory()->create();
    $calendar = Calendar::factory()->create();

    CalendarEvent::factory()->create([
        'calendar_id' => $calendar->id,
        'client_id' => $client->id,
        'format_status' => CalendarEventFormatStatus::Formatted,
        'sync_status' => CalendarEventSyncStatus::Synced,
    ]);

    expect(fn () => $client->delete())->toThrow(QueryException::class);
});

test('a project with linked calendar events can not be deleted', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create(['client_id' => $client->id]);
    $calendar = Calendar::factory()->create();

    CalendarEvent::factory()->create([
        'calendar_id' => $calendar->id,
        'client_id' => $client->id,
        'project_id' => $project->id,
        'format_status' => CalendarEventFormatStatus::Formatted,
        'sync_status' => CalendarEventSyncStatus::Synced,
    ]);

    expect(fn () => $project->delete())->toThrow(QueryException::class);
});
