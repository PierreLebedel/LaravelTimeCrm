<?php

use App\Models\Calendar;
use App\Models\CalendarAccount;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Support\CalDav\CalDavClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('it preserves unknown vevent properties when pushing a remote update', function () {
    $account = CalendarAccount::factory()->create([
        'base_url' => 'https://dav.example.test/principals/pierre/',
        'username' => 'pierre@example.test',
        'password' => 'secret-token',
    ]);

    $calendar = Calendar::factory()->create([
        'calendar_account_id' => $account->id,
        'external_id' => '/calendars/pierre/main/',
    ]);

    $client = Client::factory()->create([
        'name' => 'Acme',
    ]);

    $event = CalendarEvent::factory()->create([
        'calendar_id' => $calendar->id,
        'client_id' => $client->id,
        'project_id' => null,
        'ical_uid' => 'event-123',
        'external_id' => '/calendars/pierre/main/event-123.ics',
        'external_etag' => '"etag-1"',
        'title' => 'Acme : Support',
        'description' => 'Support client',
        'starts_at' => '2026-04-21 09:00:00',
        'ends_at' => '2026-04-21 10:15:00',
    ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:event-123
DTSTAMP:20260420T090000Z
DTSTART:20260420T090000Z
DTEND:20260420T100000Z
SUMMARY:Legacy
LOCATION:Chez le client
X-CUSTOM-FIELD:keep-me
END:VEVENT
END:VCALENDAR
ICS, 200, ['ETag' => '"etag-1"']);
        }

        if ($request->method() === 'PUT') {
            return Http::response('', 204, ['ETag' => '"etag-2"']);
        }

        return Http::response('', 500);
    });

    $etag = app(CalDavClient::class)->updateEvent($event);

    expect($etag)->toBe('"etag-2"');

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PUT') {
            return false;
        }

        return str_contains($request->body(), 'LOCATION:Chez le client')
            && str_contains($request->body(), 'X-CUSTOM-FIELD:keep-me')
            && str_contains($request->body(), 'SUMMARY:Acme : Support')
            && str_contains($request->body(), 'DTSTART:')
            && str_contains($request->body(), 'DTEND:');
    });
});

test('it creates a remote event when no existing ics resource is found', function () {
    $account = CalendarAccount::factory()->create([
        'base_url' => 'https://dav.example.test/principals/pierre/',
        'username' => 'pierre@example.test',
        'password' => 'secret-token',
    ]);

    $calendar = Calendar::factory()->create([
        'calendar_account_id' => $account->id,
        'external_id' => '/calendars/pierre/main/',
    ]);

    $client = Client::factory()->create([
        'name' => 'Acme',
    ]);

    $event = CalendarEvent::factory()->create([
        'calendar_id' => $calendar->id,
        'client_id' => $client->id,
        'project_id' => null,
        'ical_uid' => 'new-event-123',
        'external_id' => '/calendars/pierre/main/new-event-123.ics',
        'external_etag' => null,
        'title' => 'Acme : Support',
        'description' => 'Support client',
        'starts_at' => '2026-04-21 09:00:00',
        'ends_at' => '2026-04-21 10:15:00',
    ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response('', 404);
        }

        if ($request->method() === 'PUT') {
            return Http::response('', 201, ['ETag' => '"etag-created"']);
        }

        return Http::response('', 500);
    });

    $etag = app(CalDavClient::class)->updateEvent($event);

    expect($etag)->toBe('"etag-created"');

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PUT') {
            return false;
        }

        return $request->url() === 'https://dav.example.test/calendars/pierre/main/new-event-123.ics'
            && str_contains($request->body(), 'UID:new-event-123')
            && str_contains($request->body(), 'SUMMARY:Acme : Support');
    });
});
