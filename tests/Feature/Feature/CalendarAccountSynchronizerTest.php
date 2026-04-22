<?php

use App\Enums\CalendarEventFormatStatus;
use App\Enums\CalendarEventSyncStatus;
use App\Models\Calendar;
use App\Models\CalendarAccount;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Support\CalendarAccountSynchronizer;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

test('it discovers calendars and imports remote events into the review pipeline when needed', function () {
    CarbonImmutable::setTestNow('2026-04-21 10:00:00 UTC');

    $client = Client::factory()->create([
        'name' => 'Acme',
    ]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Plateforme',
    ]);

    $legacyClient = Client::factory()->create();
    $legacyProject = Project::factory()->create([
        'client_id' => $legacyClient->id,
    ]);

    $account = CalendarAccount::factory()->create([
        'base_url' => 'https://dav.example.test/principals/pierre/',
        'username' => 'pierre@example.test',
        'password' => 'secret-token',
    ]);

    $calendar = Calendar::factory()->create([
        'calendar_account_id' => $account->id,
        'external_id' => '/calendars/pierre/main/',
    ]);

    $existingEvent = CalendarEvent::factory()->create([
        'calendar_id' => $calendar->id,
        'client_id' => $legacyClient->id,
        'project_id' => $legacyProject->id,
        'external_id' => '/calendars/pierre/main/conflict.ics',
        'title' => 'Legacy title',
        'feature_description' => 'Legacy title',
        'sync_status' => CalendarEventSyncStatus::Synced,
        'format_status' => CalendarEventFormatStatus::Formatted,
    ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'PROPFIND' && $request->url() === 'https://dav.example.test/principals/pierre/') {
            return Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8" ?>
<d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav" xmlns:apple="http://apple.com/ns/ical/">
    <d:response>
        <d:href>/calendars/pierre/main/</d:href>
        <d:propstat>
            <d:prop>
                <d:displayname>Main</d:displayname>
                <d:resourcetype>
                    <d:collection />
                    <cal:calendar />
                </d:resourcetype>
                <apple:calendar-color>#0ea5e9</apple:calendar-color>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
</d:multistatus>
XML, 207);
        }

        if ($request->method() === 'REPORT' && $request->url() === 'https://dav.example.test/calendars/pierre/main/') {
            return Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8" ?>
<d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
    <d:response>
        <d:href>/calendars/pierre/main/formatted.ics</d:href>
        <d:propstat>
            <d:prop>
                <d:getetag>"etag-1"</d:getetag>
                <cal:calendar-data>BEGIN:VCALENDAR
BEGIN:VEVENT
UID:formatted-1
DTSTAMP:20260421T070000Z
DTSTART:20260421T080000Z
DTEND:20260421T100000Z
SUMMARY:Acme/Plateforme : Sprint planning
DESCRIPTION:Preparation\natelier
LAST-MODIFIED:20260421T100000Z
END:VEVENT
END:VCALENDAR</cal:calendar-data>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
    <d:response>
        <d:href>/calendars/pierre/main/conflict.ics</d:href>
        <d:propstat>
            <d:prop>
                <d:getetag>"etag-2"</d:getetag>
                <cal:calendar-data>BEGIN:VCALENDAR
BEGIN:VEVENT
UID:conflict-1
DTSTAMP:20260421T070000Z
DTSTART:20260422T120000Z
DTEND:20260422T130000Z
SUMMARY:Acme/Projet introuvable : Support
DESCRIPTION:Support client
LAST-MODIFIED:20260421T110000Z
END:VEVENT
END:VCALENDAR</cal:calendar-data>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
</d:multistatus>
XML, 207);
        }

        return Http::response('', 500);
    });

    $result = app(CalendarAccountSynchronizer::class)->sync($account->fresh());

    expect($result->calendarCount)->toBe(1)
        ->and($result->eventCount)->toBe(2);

    $formattedEvent = CalendarEvent::query()
        ->where('external_id', '/calendars/pierre/main/formatted.ics')
        ->first();

    expect($formattedEvent)
        ->not()->toBeNull()
        ->client_id->toBe($client->id)
        ->project_id->toBe($project->id)
        ->feature_description->toBe('Sprint planning')
        ->sync_status->toBe(CalendarEventSyncStatus::Synced)
        ->format_status->toBe(CalendarEventFormatStatus::Formatted);

    $existingEvent->refresh();

    expect($existingEvent)
        ->client_id->toBeNull()
        ->project_id->toBeNull()
        ->feature_description->toBe('Support')
        ->sync_status->toBe(CalendarEventSyncStatus::Conflict)
        ->format_status->toBe(CalendarEventFormatStatus::NeedsReview);

    expect($account->fresh()->last_synced_at)->not()->toBeNull();

    Http::assertSent(function (Request $request): bool {
        if ($request->method() !== 'REPORT') {
            return false;
        }

        return str_contains($request->body(), 'start="20260121T000000Z"')
            && str_contains($request->body(), 'end="20261021T235959Z"');
    });
});

test('it skips event import for unselected calendars while keeping discovered calendars', function () {
    $account = CalendarAccount::factory()->create([
        'base_url' => 'https://dav.example.test/principals/pierre/',
        'username' => 'pierre@example.test',
        'password' => 'secret-token',
    ]);

    $calendar = Calendar::factory()->create([
        'calendar_account_id' => $account->id,
        'external_id' => '/calendars/pierre/main/',
        'is_selected' => false,
    ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'PROPFIND') {
            return Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8" ?>
<d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
    <d:response>
        <d:href>/calendars/pierre/main/</d:href>
        <d:propstat>
            <d:prop>
                <d:displayname>Main</d:displayname>
                <d:resourcetype>
                    <d:collection />
                    <cal:calendar />
                </d:resourcetype>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
</d:multistatus>
XML, 207);
        }

        return Http::response('', 500);
    });

    $result = app(CalendarAccountSynchronizer::class)->sync($account);

    expect($result->calendarCount)->toBe(1)
        ->and($result->eventCount)->toBe(0)
        ->and($calendar->fresh()->is_selected)->toBeFalse()
        ->and(CalendarEvent::query()->count())->toBe(0);

    Http::assertSentCount(1);
});

test('it falls back to europe paris for windows timezones returned by caldav', function () {
    $account = CalendarAccount::factory()->create([
        'base_url' => 'https://dav.example.test/principals/pierre/',
        'username' => 'pierre@example.test',
        'password' => 'secret-token',
    ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'PROPFIND') {
            return Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8" ?>
<d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
    <d:response>
        <d:href>/calendars/pierre/main/</d:href>
        <d:propstat>
            <d:prop>
                <d:displayname>Main</d:displayname>
                <d:resourcetype>
                    <d:collection />
                    <cal:calendar />
                </d:resourcetype>
                <cal:calendar-timezone>Romance Standard Time</cal:calendar-timezone>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
</d:multistatus>
XML, 207);
        }

        if ($request->method() === 'REPORT') {
            return Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8" ?>
<d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
    <d:response>
        <d:href>/calendars/pierre/main/windows-tz.ics</d:href>
        <d:propstat>
            <d:prop>
                <d:getetag>"etag-windows"</d:getetag>
                <cal:calendar-data>BEGIN:VCALENDAR
BEGIN:VEVENT
UID:windows-tz-1
DTSTAMP:20260421T070000Z
DTSTART;TZID=Romance Standard Time:20260421T100000
DTEND;TZID=Romance Standard Time:20260421T111500
SUMMARY:Client inconnu : Revue
END:VEVENT
END:VCALENDAR</cal:calendar-data>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
</d:multistatus>
XML, 207);
        }

        return Http::response('', 500);
    });

    app(CalendarAccountSynchronizer::class)->sync($account);

    $calendar = Calendar::query()->sole();
    $event = CalendarEvent::query()->sole();

    expect($calendar->timezone)->toBe('Europe/Paris')
        ->and($event->timezone)->toBe('Europe/Paris')
        ->and($event->starts_at->timezone->getName())->toBe('Europe/Paris')
        ->and($event->starts_at->format('Y-m-d H:i:s'))->toBe('2026-04-21 10:00:00')
        ->and($event->ends_at->format('Y-m-d H:i:s'))->toBe('2026-04-21 11:15:00');
});

test('it converts utc caldav datetimes to the calendar timezone for display in the app', function () {
    $account = CalendarAccount::factory()->create([
        'base_url' => 'https://dav.example.test/principals/pierre/',
        'username' => 'pierre@example.test',
        'password' => 'secret-token',
    ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'PROPFIND') {
            return Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8" ?>
<d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
    <d:response>
        <d:href>/calendars/pierre/main/</d:href>
        <d:propstat>
            <d:prop>
                <d:displayname>Main</d:displayname>
                <d:resourcetype>
                    <d:collection />
                    <cal:calendar />
                </d:resourcetype>
                <cal:calendar-timezone>Europe/Paris</cal:calendar-timezone>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
</d:multistatus>
XML, 207);
        }

        if ($request->method() === 'REPORT') {
            return Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8" ?>
<d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
    <d:response>
        <d:href>/calendars/pierre/main/utc-event.ics</d:href>
        <d:propstat>
            <d:prop>
                <d:getetag>"etag-utc"</d:getetag>
                <cal:calendar-data>BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Example Corp//Calendar//EN
BEGIN:VTIMEZONE
TZID:Romance Standard Time
BEGIN:STANDARD
DTSTART:16010101T030000
TZOFFSETFROM:+0200
TZOFFSETTO:+0100
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=10
END:STANDARD
BEGIN:DAYLIGHT
DTSTART:16010101T020000
TZOFFSETFROM:+0100
TZOFFSETTO:+0200
RRULE:FREQ=YEARLY;INTERVAL=1;BYDAY=-1SU;BYMONTH=3
END:DAYLIGHT
END:VTIMEZONE
BEGIN:VEVENT
PRIORITY:5
STATUS:CONFIRMED
ORGANIZER;CN=Example Organizer:mailto:organizer@example.test
ATTENDEE;RSVP=TRUE;CN=Example Attendee;PARTSTAT=ACCEPTED;ROLE=REQ-PARTICIPANT:mailto:attendee@example.test
CLASS:PUBLIC
TRANSP:OPAQUE
SEQUENCE:0
LOCATION;LANGUAGE=fr-FR:Visioconference
UID:utc-event-1
DTSTAMP:20260422T184902Z
LAST-MODIFIED:20260422T184902Z
DTSTART:20260421T100000Z
DTEND:20260421T110000Z
SUMMARY:Client test/Projet test : Revue sujet technique
DESCRIPTION:Bonjour\,\n\nPeux-tu confirmer ce point ?\n\nMerci.
END:VEVENT
END:VCALENDAR</cal:calendar-data>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
</d:multistatus>
XML, 207);
        }

        return Http::response('', 500);
    });

    app(CalendarAccountSynchronizer::class)->sync($account);

    $event = CalendarEvent::query()->sole();

    expect($event->timezone)->toBe('Europe/Paris')
        ->and($event->starts_at->timezone->getName())->toBe('Europe/Paris')
        ->and($event->starts_at->format('Y-m-d H:i:s'))->toBe('2026-04-21 12:00:00')
        ->and($event->ends_at->format('Y-m-d H:i:s'))->toBe('2026-04-21 13:00:00');
});

test('it assigns the default client locally without requiring a remote title rewrite', function () {
    $defaultClient = Client::factory()->create([
        'name' => 'Acme',
    ]);

    $project = Project::factory()->create([
        'client_id' => $defaultClient->id,
        'name' => 'Plateforme',
    ]);

    $account = CalendarAccount::factory()
        ->withDefaultClient($defaultClient)
        ->create([
            'base_url' => 'https://dav.example.test/principals/pierre/',
            'username' => 'pierre@example.test',
            'password' => 'secret-token',
        ]);

    Http::fake(function (Request $request) {
        if ($request->method() === 'PROPFIND') {
            return Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8" ?>
<d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
    <d:response>
        <d:href>/calendars/pierre/main/</d:href>
        <d:propstat>
            <d:prop>
                <d:displayname>Main</d:displayname>
                <d:resourcetype>
                    <d:collection />
                    <cal:calendar />
                </d:resourcetype>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
</d:multistatus>
XML, 207);
        }

        if ($request->method() === 'REPORT') {
            return Http::response(<<<'XML'
<?xml version="1.0" encoding="utf-8" ?>
<d:multistatus xmlns:d="DAV:" xmlns:cal="urn:ietf:params:xml:ns:caldav">
    <d:response>
        <d:href>/calendars/pierre/main/default-client.ics</d:href>
        <d:propstat>
            <d:prop>
                <d:getetag>"etag-default-client"</d:getetag>
                <cal:calendar-data>BEGIN:VCALENDAR
BEGIN:VEVENT
UID:default-client-1
DTSTAMP:20260421T070000Z
DTSTART:20260423T090000Z
DTEND:20260423T101500Z
SUMMARY:Acme/Plateforme : Atelier
END:VEVENT
END:VCALENDAR</cal:calendar-data>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
    <d:response>
        <d:href>/calendars/pierre/main/raw-title.ics</d:href>
        <d:propstat>
            <d:prop>
                <d:getetag>"etag-raw-title"</d:getetag>
                <cal:calendar-data>BEGIN:VCALENDAR
BEGIN:VEVENT
UID:raw-title-1
DTSTAMP:20260421T070000Z
DTSTART:20260423T130000Z
DTEND:20260423T140000Z
SUMMARY:Support production
END:VEVENT
END:VCALENDAR</cal:calendar-data>
            </d:prop>
            <d:status>HTTP/1.1 200 OK</d:status>
        </d:propstat>
    </d:response>
</d:multistatus>
XML, 207);
        }

        return Http::response('', 500);
    });

    app(CalendarAccountSynchronizer::class)->sync($account);

    $formattedEvent = CalendarEvent::query()
        ->where('external_id', '/calendars/pierre/main/default-client.ics')
        ->sole();

    $rawEvent = CalendarEvent::query()
        ->where('external_id', '/calendars/pierre/main/raw-title.ics')
        ->sole();

    expect($formattedEvent)
        ->client_id->toBe($defaultClient->id)
        ->project_id->toBe($project->id)
        ->feature_description->toBe('Atelier')
        ->sync_status->toBe(CalendarEventSyncStatus::Synced)
        ->format_status->toBe(CalendarEventFormatStatus::Formatted);

    expect($rawEvent)
        ->client_id->toBe($defaultClient->id)
        ->project_id->toBeNull()
        ->feature_description->toBe('Support production')
        ->sync_status->toBe(CalendarEventSyncStatus::Synced)
        ->format_status->toBe(CalendarEventFormatStatus::Formatted);
});
