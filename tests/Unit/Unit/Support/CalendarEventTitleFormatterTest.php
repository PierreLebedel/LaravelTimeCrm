<?php

use App\Models\Client;
use App\Models\Project;
use App\Support\CalendarEventTitleFormatter;

test('it formats a title with a project', function () {
    $client = Client::factory()->make(['name' => 'ACME']);
    $project = Project::factory()->make(['name' => 'Mobile App']);

    expect(CalendarEventTitleFormatter::format($client, $project, 'offline sync'))
        ->toBe('ACME/Mobile App : offline sync');
});

test('it formats a title without a project', function () {
    $client = Client::factory()->make(['name' => 'ACME']);

    expect(CalendarEventTitleFormatter::format($client, null, 'weekly review'))
        ->toBe('ACME : weekly review');
});
