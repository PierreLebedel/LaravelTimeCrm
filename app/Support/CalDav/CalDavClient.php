<?php

namespace App\Support\CalDav;

use App\Models\CalendarAccount;
use App\Models\CalendarEvent;
use Carbon\CarbonImmutable;
use DateTimeZone;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class CalDavClient
{
    /**
     * @return Collection<int, CalDavCalendar>
     */
    public function discoverCalendars(CalendarAccount $account): Collection
    {
        $response = $this->request($account)
            ->withHeaders([
                'Depth' => '1',
                'Content-Type' => 'application/xml; charset=utf-8',
            ])
            ->send('PROPFIND', $account->base_url, [
                'body' => <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:cal="urn:ietf:params:xml:ns:caldav">
    <d:prop>
        <d:displayname />
        <d:resourcetype />
        <cs:getctag />
        <cal:calendar-description />
        <cal:calendar-timezone />
        <cal:supported-calendar-component-set />
        <x1:calendar-color xmlns:x1="http://apple.com/ns/ical/" />
    </d:prop>
</d:propfind>
XML,
            ]);

        $response->throw();

        return collect($this->parseMultistatus($response->body()))
            ->filter(fn (array $resource): bool => $resource['is_calendar'])
            ->map(function (array $resource) use ($account): CalDavCalendar {
                return new CalDavCalendar(
                    externalId: $resource['href'],
                    url: $this->absoluteUrl($account->base_url, $resource['href']),
                    name: $resource['display_name'] ?: 'Agenda sans nom',
                    color: $resource['calendar_color'],
                    timezone: $this->normalizeTimezone($resource['calendar_timezone']),
                );
            })
            ->values();
    }

    /**
     * @return Collection<int, CalDavEvent>
     */
    public function events(CalendarAccount $account, CalDavCalendar $calendar): Collection
    {
        $window = $this->syncWindow();

        $response = $this->request($account)
            ->withHeaders([
                'Depth' => '1',
                'Content-Type' => 'application/xml; charset=utf-8',
            ])
            ->send('REPORT', $calendar->url, [
                'body' => str_replace(
                    ['__START__', '__END__'],
                    [$window['start'], $window['end']],
                    <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<c:calendar-query xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
    <d:prop>
        <d:getetag />
        <c:calendar-data />
    </d:prop>
    <c:filter>
        <c:comp-filter name="VCALENDAR">
            <c:comp-filter name="VEVENT">
                <c:time-range start="__START__" end="__END__" />
            </c:comp-filter>
        </c:comp-filter>
    </c:filter>
</c:calendar-query>
XML,
                ),
            ]);

        $response->throw();

        return collect($this->parseMultistatus($response->body()))
            ->filter(fn (array $resource): bool => filled($resource['calendar_data']))
            ->map(function (array $resource) use ($calendar): ?CalDavEvent {
                return $this->parseCalendarEvent(
                    externalId: $resource['href'],
                    etag: $resource['etag'],
                    calendarTimezone: $calendar->timezone,
                    calendarData: $resource['calendar_data'],
                );
            })
            ->filter()
            ->values();
    }

    protected function request(CalendarAccount $account): PendingRequest
    {
        return Http::accept('application/xml')
            ->withBasicAuth($account->username, $account->password)
            ->connectTimeout(10)
            ->timeout(30)
            ->retry([200, 500], throw: false);
    }

    /**
     * @return list<array{
     *     href: string,
     *     is_calendar: bool,
     *     display_name: ?string,
     *     calendar_color: ?string,
     *     calendar_timezone: ?string,
     *     etag: ?string,
     *     calendar_data: ?string
     * }>
     */
    protected function parseMultistatus(string $xml): array
    {
        $document = new DOMDocument;
        $document->loadXML($xml);

        $xpath = new DOMXPath($document);
        $xpath->registerNamespace('d', 'DAV:');
        $xpath->registerNamespace('cs', 'http://calendarserver.org/ns/');
        $xpath->registerNamespace('cal', 'urn:ietf:params:xml:ns:caldav');
        $xpath->registerNamespace('apple', 'http://apple.com/ns/ical/');

        $resources = [];

        foreach ($xpath->query('//d:response') ?: [] as $responseNode) {
            if (! $responseNode instanceof DOMElement) {
                continue;
            }

            $resources[] = [
                'href' => trim($xpath->evaluate('string(d:href)', $responseNode)),
                'is_calendar' => $xpath->evaluate('boolean(.//d:resourcetype/cal:calendar)', $responseNode),
                'display_name' => $this->nullableString($xpath->evaluate('string(.//d:displayname)', $responseNode)),
                'calendar_color' => $this->nullableString($xpath->evaluate('string(.//apple:calendar-color)', $responseNode)),
                'calendar_timezone' => $this->nullableString($xpath->evaluate('string(.//cal:calendar-timezone)', $responseNode)),
                'etag' => $this->nullableString($xpath->evaluate('string(.//d:getetag)', $responseNode)),
                'calendar_data' => $this->nullableString($xpath->evaluate('string(.//cal:calendar-data)', $responseNode)),
            ];
        }

        return $resources;
    }

    protected function parseCalendarEvent(string $externalId, ?string $etag, ?string $calendarTimezone, string $calendarData): ?CalDavEvent
    {
        $properties = $this->extractVeventProperties($calendarData);

        $startsAt = $this->parseDateTime($properties['DTSTART'] ?? null, $calendarTimezone);
        $endsAt = $this->parseDateTime($properties['DTEND'] ?? null, $calendarTimezone);
        $title = trim($properties['SUMMARY']['value'] ?? '');

        if ($startsAt === null || $endsAt === null || blank($title)) {
            return null;
        }

        $description = $properties['DESCRIPTION']['value'] ?? null;
        $sourceUpdatedAt = $this->parseDateTime($properties['LAST-MODIFIED'] ?? null, $calendarTimezone)
            ?? $this->parseDateTime($properties['DTSTAMP'] ?? null, $calendarTimezone);

        return new CalDavEvent(
            icalUid: $properties['UID']['value'] ?? null,
            externalId: $externalId,
            etag: $etag,
            startsAt: $startsAt,
            endsAt: $endsAt,
            timezone: $this->normalizeTimezone($properties['DTSTART']['tzid'] ?? null),
            title: $title,
            description: $description,
            sourceUpdatedAt: $sourceUpdatedAt,
        );
    }

    /**
     * @return array<string, array{value: string, tzid: ?string}>
     */
    protected function extractVeventProperties(string $calendarData): array
    {
        $properties = [];
        $inEvent = false;

        foreach ($this->unfoldLines($calendarData) as $line) {
            if ($line === 'BEGIN:VEVENT') {
                $inEvent = true;

                continue;
            }

            if ($line === 'END:VEVENT') {
                break;
            }

            if (! $inEvent || ! str_contains($line, ':')) {
                continue;
            }

            [$property, $value] = explode(':', $line, 2);
            $segments = explode(';', $property);
            $name = array_shift($segments);
            $parameters = $segments;

            $properties[$name] = [
                'value' => Str::replace('\\n', "\n", trim($value)),
                'tzid' => collect($parameters)
                    ->first(fn (string $parameter): bool => str_starts_with($parameter, 'TZID=')) ?:
                    null,
            ];

            if ($properties[$name]['tzid'] !== null) {
                $properties[$name]['tzid'] = Str::after($properties[$name]['tzid'], 'TZID=');
            }
        }

        return $properties;
    }

    /**
     * @return list<string>
     */
    protected function unfoldLines(string $calendarData): array
    {
        $lines = $this->normalizeLines($calendarData);
        $unfoldedLines = [];

        foreach ($lines as $line) {
            if (($line[0] ?? null) !== ' ' && ($line[0] ?? null) !== "\t") {
                $unfoldedLines[] = $line;

                continue;
            }

            $lastIndex = array_key_last($unfoldedLines);

            if ($lastIndex === null) {
                $unfoldedLines[] = ltrim($line);

                continue;
            }

            $unfoldedLines[$lastIndex] .= ltrim($line);
        }

        return $unfoldedLines;
    }

    /**
     * @return list<string>
     */
    protected function normalizeLines(string $calendarData): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $calendarData) ?: [];
        $nonEmptyLines = array_values(array_filter(
            $lines,
            fn (string $line): bool => trim($line) !== '',
        ));

        if ($nonEmptyLines === []) {
            return [];
        }

        $commonIndentation = min(array_map(function (string $line): int {
            preg_match('/^[ \t]*/', $line, $matches);

            return strlen($matches[0] ?? '');
        }, $nonEmptyLines));

        return array_map(
            fn (string $line): string => substr($line, min($commonIndentation, strlen($line))),
            $nonEmptyLines,
        );
    }

    /**
     * @param  array{value: string, tzid: ?string}|null  $property
     */
    protected function parseDateTime(?array $property, ?string $defaultTimezone = null): ?CarbonImmutable
    {
        if ($property === null || blank($property['value'])) {
            return null;
        }

        $value = trim($property['value']);
        $timezone = $this->normalizeTimezone($property['tzid'])
            ?? $this->normalizeTimezone($defaultTimezone)
            ?? config('app.timezone');

        if (preg_match('/^\d{8}$/', $value) === 1) {
            return CarbonImmutable::createFromFormat('Ymd', $value, $timezone)->startOfDay();
        }

        if (str_ends_with($value, 'Z')) {
            return CarbonImmutable::createFromFormat('Ymd\THis\Z', $value, 'UTC')
                ->setTimezone($timezone);
        }

        return CarbonImmutable::createFromFormat('Ymd\THis', $value, $timezone);
    }

    protected function normalizeTimezone(?string $timezone): ?string
    {
        $timezone = $this->nullableString((string) $timezone);

        if ($timezone === null) {
            return null;
        }

        if (str_contains($timezone, 'BEGIN:VTIMEZONE') && preg_match('/(?:^|\R)TZID:([^\r\n]+)/', $timezone, $matches) === 1) {
            $timezone = trim($matches[1]);
        }

        $timezone = trim($timezone, "\"'");
        $timezone = $this->windowsTimezoneMap()[$timezone] ?? $timezone;

        if (in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return $timezone;
        }

        return config('app.timezone');
    }

    /**
     * @return array<string, string>
     */
    protected function windowsTimezoneMap(): array
    {
        return [
            'UTC' => 'UTC',
            'Romance Standard Time' => 'Europe/Paris',
            'W. Europe Standard Time' => 'Europe/Berlin',
            'Central Europe Standard Time' => 'Europe/Budapest',
            'Central European Standard Time' => 'Europe/Warsaw',
            'GMT Standard Time' => 'Europe/London',
        ];
    }

    protected function absoluteUrl(string $baseUrl, string $href): string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://')) {
            return $href;
        }

        $parsedBaseUrl = parse_url($baseUrl);

        if (! is_array($parsedBaseUrl) || ! isset($parsedBaseUrl['scheme'], $parsedBaseUrl['host'])) {
            throw new RuntimeException('URL DAV invalide.');
        }

        $port = isset($parsedBaseUrl['port']) ? ':'.$parsedBaseUrl['port'] : '';

        return sprintf(
            '%s://%s%s%s',
            $parsedBaseUrl['scheme'],
            $parsedBaseUrl['host'],
            $port,
            Str::start($href, '/'),
        );
    }

    protected function nullableString(string $value): ?string
    {
        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array{start: string, end: string}
     */
    protected function syncWindow(): array
    {
        $pastMonths = (int) config('crm.sync_window.past_months', 3);
        $futureMonths = (int) config('crm.sync_window.future_months', 6);

        return [
            'start' => now('UTC')->subMonthsNoOverflow($pastMonths)->startOfDay()->format('Ymd\THis\Z'),
            'end' => now('UTC')->addMonthsNoOverflow($futureMonths)->endOfDay()->format('Ymd\THis\Z'),
        ];
    }

    public function updateEvent(CalendarEvent $event): ?string
    {
        $event->loadMissing('calendar.account', 'client', 'project');

        if ($event->calendar === null || $event->calendar->account === null) {
            throw new RuntimeException('Evenement DAV sans agenda ou compte distant.');
        }

        if (blank($event->ical_uid)) {
            throw new RuntimeException('Impossible de pousser un evenement distant sans UID iCal.');
        }

        $currentCalendarData = $this->fetchEventCalendarData($event);

        $response = $this->request($event->calendar->account)
            ->withHeaders(array_filter([
                'Content-Type' => 'text/calendar; charset=utf-8',
                'If-Match' => $event->external_etag,
            ]))
            ->send('PUT', $this->absoluteUrl($event->calendar->account->base_url, $event->external_id), [
                'body' => $currentCalendarData !== null
                    ? $this->updateCalendarData($currentCalendarData, $event)
                    : $this->buildCalendarData($event),
            ]);

        $response->throw();

        return $this->nullableString($response->header('ETag') ?? '');
    }

    public function deleteEvent(CalendarEvent $event): void
    {
        $event->loadMissing('calendar.account');

        if ($event->calendar === null || $event->calendar->account === null) {
            throw new RuntimeException('Evenement DAV sans agenda ou compte distant.');
        }

        if (blank($event->external_id)) {
            return;
        }

        $response = $this->request($event->calendar->account)
            ->withHeaders(array_filter([
                'If-Match' => $event->external_etag,
            ]))
            ->send('DELETE', $this->absoluteUrl($event->calendar->account->base_url, $event->external_id));

        if ($response->status() === 404) {
            return;
        }

        $response->throw();
    }

    protected function buildCalendarData(CalendarEvent $event): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//LaravelTimeCrm//FR',
            'BEGIN:VEVENT',
            'UID:'.$this->escapeIcalText($event->ical_uid),
            'DTSTAMP:'.now('UTC')->format('Ymd\THis\Z'),
            'LAST-MODIFIED:'.now('UTC')->format('Ymd\THis\Z'),
            'DTSTART:'.$event->starts_at->clone()->utc()->format('Ymd\THis\Z'),
            'DTEND:'.$event->ends_at->clone()->utc()->format('Ymd\THis\Z'),
            'SUMMARY:'.$this->escapeIcalText($event->title),
        ];

        if (filled($event->description)) {
            $lines[] = 'DESCRIPTION:'.$this->escapeIcalText($event->description);
        }

        $lines[] = 'END:VEVENT';
        $lines[] = 'END:VCALENDAR';

        return implode("\r\n", $lines)."\r\n";
    }

    protected function fetchEventCalendarData(CalendarEvent $event): ?string
    {
        $response = $this->request($event->calendar->account)
            ->accept('text/calendar')
            ->withHeaders(array_filter([
                'If-None-Match' => null,
            ]))
            ->send('GET', $this->absoluteUrl($event->calendar->account->base_url, $event->external_id));

        if ($response->failed()) {
            return null;
        }

        return $this->nullableString($response->body());
    }

    protected function updateCalendarData(string $calendarData, CalendarEvent $event): string
    {
        $lines = $this->unfoldLines($calendarData);
        $veventStartIndex = array_search('BEGIN:VEVENT', $lines, true);
        $veventEndIndex = array_search('END:VEVENT', $lines, true);

        if ($veventStartIndex === false || $veventEndIndex === false || $veventEndIndex <= $veventStartIndex) {
            return $this->buildCalendarData($event);
        }

        $eventLines = array_slice($lines, $veventStartIndex + 1, $veventEndIndex - $veventStartIndex - 1);

        $eventLines = $this->upsertProperty($eventLines, 'UID', $this->escapeIcalText((string) $event->ical_uid));
        $eventLines = $this->upsertProperty($eventLines, 'DTSTAMP', now('UTC')->format('Ymd\THis\Z'));
        $eventLines = $this->upsertProperty($eventLines, 'LAST-MODIFIED', now('UTC')->format('Ymd\THis\Z'));
        $eventLines = $this->upsertProperty($eventLines, 'DTSTART', $event->starts_at->clone()->utc()->format('Ymd\THis\Z'));
        $eventLines = $this->upsertProperty($eventLines, 'DTEND', $event->ends_at->clone()->utc()->format('Ymd\THis\Z'));
        $eventLines = $this->upsertProperty($eventLines, 'SUMMARY', $this->escapeIcalText($event->title));
        $eventLines = $this->upsertProperty(
            $eventLines,
            'DESCRIPTION',
            filled($event->description) ? $this->escapeIcalText($event->description) : null,
        );

        array_splice(
            $lines,
            $veventStartIndex + 1,
            $veventEndIndex - $veventStartIndex - 1,
            $eventLines,
        );

        return implode("\r\n", $lines)."\r\n";
    }

    /**
     * @param  list<string>  $lines
     * @return list<string>
     */
    protected function upsertProperty(array $lines, string $propertyName, ?string $value): array
    {
        $matchingIndexes = [];

        foreach ($lines as $index => $line) {
            $name = Str::before(Str::before($line, ':'), ';');

            if ($name === $propertyName) {
                $matchingIndexes[] = $index;
            }
        }

        foreach (array_reverse($matchingIndexes) as $index) {
            array_splice($lines, $index, 1);
        }

        if ($value === null) {
            return array_values($lines);
        }

        $insertIndex = count($lines);

        foreach ($lines as $index => $line) {
            if (str_starts_with($line, 'END:')) {
                $insertIndex = $index;
                break;
            }
        }

        array_splice($lines, $insertIndex, 0, [$propertyName.':'.$value]);

        return array_values($lines);
    }

    protected function escapeIcalText(string $value): string
    {
        return Str::of($value)
            ->replace('\\', '\\\\')
            ->replace(';', '\;')
            ->replace(',', '\,')
            ->replace("\r\n", '\n')
            ->replace("\n", '\n')
            ->replace("\r", '\n')
            ->value();
    }
}
