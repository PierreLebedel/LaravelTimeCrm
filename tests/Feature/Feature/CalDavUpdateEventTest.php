<?php

use App\Models\Calendar;
use App\Models\CalendarAccount;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Support\CalDav\CalDavClient;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Acme',
    ]);

    $event = CalendarEvent::factory()->create([
        'calendar_id' => $calendar->id,
        'client_id' => $client->id,
        'project_id' => $project->id,
        'ical_uid' => 'event-123',
        'external_id' => '/calendars/pierre/main/event-123.ics',
        'external_etag' => '"etag-1"',
        'title' => 'Acme : Support',
        'feature_description' => 'Support',
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

test('it updates root vevent properties without moving them into nested components', function () {
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
        'name' => 'HS2',
    ]);
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'HS2',
    ]);

    $event = CalendarEvent::factory()->create([
        'calendar_id' => $calendar->id,
        'client_id' => $client->id,
        'project_id' => $project->id,
        'ical_uid' => 'event-106',
        'external_id' => '/calendars/pierre/main/event-106.ics',
        'external_etag' => '"etag-106"',
        'title' => 'HS2 : IA',
        'feature_description' => 'IA',
        'starts_at' => '2026-04-12 17:30:00',
        'ends_at' => '2026-04-12 19:30:00',
    ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
BEGIN:VEVENT
UID:event-106
DTSTAMP:20260412T140000Z
DTSTART:20260412T150000Z
DTEND:20260412T170000Z
SUMMARY:Ancien titre
BEGIN:VALARM
ACTION:DISPLAY
TRIGGER:-PT15M
DESCRIPTION:Rappel
END:VALARM
END:VEVENT
END:VCALENDAR
ICS, 200, ['ETag' => '"etag-106"']);
        }

        if ($request->method() === 'PUT') {
            return Http::response('', 204, ['ETag' => '"etag-107"']);
        }

        return Http::response('', 500);
    });

    $etag = app(CalDavClient::class)->updateEvent($event);

    expect($etag)->toBe('"etag-107"');

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'PUT') {
            return false;
        }

        $body = $request->body();
        $dtstartPosition = strpos($body, 'DTSTART:');
        $valarmPosition = strpos($body, 'BEGIN:VALARM');

        return $dtstartPosition !== false
            && $valarmPosition !== false
            && $dtstartPosition < $valarmPosition
            && substr_count($body, 'DTSTART:') === 1
            && str_contains($body, 'BEGIN:VALARM')
            && str_contains($body, 'TRIGGER:-PT15M');
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
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Acme',
    ]);

    $event = CalendarEvent::factory()->create([
        'calendar_id' => $calendar->id,
        'client_id' => $client->id,
        'project_id' => $project->id,
        'ical_uid' => 'new-event-123',
        'external_id' => '/calendars/pierre/main/new-event-123.ics',
        'external_etag' => null,
        'title' => 'Acme : Support',
        'feature_description' => 'Support',
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

test('it logs the precise remote error body when a caldav update fails', function () {
    Log::spy();

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
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Acme',
    ]);

    $event = CalendarEvent::factory()->create([
        'calendar_id' => $calendar->id,
        'client_id' => $client->id,
        'project_id' => $project->id,
        'ical_uid' => 'failed-event-123',
        'external_id' => '/calendars/pierre/main/failed-event-123.ics',
        'external_etag' => '"etag-failed"',
        'title' => 'Acme : Support',
        'feature_description' => 'Support',
        'description' => 'Support client',
        'starts_at' => '2026-04-21 09:00:00',
        'ends_at' => '2026-04-21 10:15:00',
    ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'GET') {
            return Http::response('', 404);
        }

        if ($request->method() === 'PUT') {
            return Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8"?>
<d:error xmlns:d="DAV:" xmlns:s="http://sabredav.org/ns">
  <s:message>Unsupported media type</s:message>
</d:error>
XML, 415);
        }

        return Http::response('', 500);
    });

    expect(fn () => app(CalDavClient::class)->updateEvent($event))
        ->toThrow(RequestException::class);

    Log::shouldHaveReceived('error')->once()->with(
        'CalDAV remote update failed.',
        Mockery::on(fn (array $context): bool => $context['status'] === 415
            && $context['method'] === 'PUT'
            && $context['external_id'] === '/calendars/pierre/main/failed-event-123.ics'
            && $context['ical_uid'] === 'failed-event-123'
            && str_contains($context['request_body'], 'UID:failed-event-123')
            && str_contains($context['response_body'], 'Unsupported media type'))
    );
});
