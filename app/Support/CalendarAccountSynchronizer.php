<?php

namespace App\Support;

use App\Enums\CalendarEventFormatStatus;
use App\Enums\CalendarEventSyncStatus;
use App\Models\Calendar;
use App\Models\CalendarAccount;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Support\CalDav\CalDavCalendar;
use App\Support\CalDav\CalDavClient;
use App\Support\CalDav\CalDavEvent;
use App\Support\CalDav\CalDavSyncResult;
use Illuminate\Support\Facades\DB;

class CalendarAccountSynchronizer
{
    public function __construct(
        protected CalDavClient $client,
    ) {}

    public function sync(CalendarAccount $account): CalDavSyncResult
    {
        $calendarCount = 0;
        $eventCount = 0;

        DB::transaction(function () use ($account, &$calendarCount, &$eventCount): void {
            $account->loadMissing('defaultClient');
            $remoteCalendars = $this->client->discoverCalendars($account);

            $calendarCount = $remoteCalendars->count();

            foreach ($remoteCalendars as $remoteCalendar) {
                $calendar = $this->storeCalendar($account, $remoteCalendar);

                if (! $calendar->is_selected) {
                    continue;
                }

                $events = $this->client->events($account, $remoteCalendar);
                $eventCount += $events->count();

                foreach ($events as $event) {
                    $this->storeEvent($calendar, $account, $event);
                }
            }

            $account->forceFill([
                'last_synced_at' => now(),
            ])->save();
        });

        return new CalDavSyncResult(
            calendarCount: $calendarCount,
            eventCount: $eventCount,
        );
    }

    public function syncActiveAccounts(): void
    {
        CalendarAccount::query()
            ->where('is_active', true)
            ->each(fn (CalendarAccount $account) => rescue(
                fn () => $this->sync($account),
                report: true,
            ));
    }

    protected function storeCalendar(CalendarAccount $account, CalDavCalendar $remoteCalendar): Calendar
    {
        $calendar = Calendar::query()->firstOrNew([
            'calendar_account_id' => $account->id,
            'external_id' => $remoteCalendar->externalId,
        ]);

        $calendar->fill([
            'name' => $remoteCalendar->name,
            'color' => $remoteCalendar->color,
            'timezone' => $remoteCalendar->timezone,
        ]);

        if (! $calendar->exists) {
            $calendar->is_selected = true;
        }

        $calendar->save();

        return $calendar;
    }

    protected function storeEvent(Calendar $calendar, CalendarAccount $account, CalDavEvent $remoteEvent): CalendarEvent
    {
        $event = CalendarEvent::query()->firstOrNew([
            'calendar_id' => $calendar->id,
            'external_id' => $remoteEvent->externalId,
        ]);

        $assignment = $this->resolveAssignment(
            title: $remoteEvent->title,
            calendarEvent: $event,
            defaultClient: $account->defaultClient,
        );

        $event->fill([
            'client_id' => $assignment['client_id'],
            'project_id' => $assignment['project_id'],
            'ical_uid' => $remoteEvent->icalUid,
            'external_etag' => $remoteEvent->etag,
            'starts_at' => $remoteEvent->startsAt,
            'ends_at' => $remoteEvent->endsAt,
            'timezone' => $remoteEvent->timezone ?? $calendar->timezone ?? config('app.timezone'),
            'title' => $remoteEvent->title,
            'description' => $remoteEvent->description,
            'feature_description' => $assignment['feature_description'],
            'sync_status' => $assignment['sync_status'],
            'format_status' => $assignment['format_status'],
            'source_updated_at' => $remoteEvent->sourceUpdatedAt,
            'last_synced_at' => now(),
        ]);
        $event->calendar()->associate($calendar);
        $event->save();

        return $event;
    }

    /**
     * @return array{
     *     client_id: ?int,
     *     project_id: ?int,
     *     feature_description: string,
     *     sync_status: CalendarEventSyncStatus,
     *     format_status: CalendarEventFormatStatus
     * }
     */
    protected function resolveAssignment(string $title, CalendarEvent $calendarEvent, ?Client $defaultClient = null): array
    {
        $parsedTitle = CalendarEventTitleParser::parse($title);

        if ($defaultClient !== null) {
            $project = $parsedTitle === null || $parsedTitle['project_name'] === null
                ? null
                : Project::query()
                    ->whereBelongsTo($defaultClient)
                    ->where('name', $parsedTitle['project_name'])
                    ->first();

            return [
                'client_id' => $defaultClient->id,
                'project_id' => $project?->id,
                'feature_description' => $parsedTitle['feature_description'] ?? $title,
                'sync_status' => CalendarEventSyncStatus::Synced,
                'format_status' => CalendarEventFormatStatus::Formatted,
            ];
        }

        if ($parsedTitle === null) {
            return [
                'client_id' => null,
                'project_id' => null,
                'feature_description' => $title,
                'sync_status' => CalendarEventSyncStatus::Orphaned,
                'format_status' => CalendarEventFormatStatus::NeedsReview,
            ];
        }

        $client = Client::query()
            ->where('name', $parsedTitle['client_name'])
            ->first();

        $project = $client === null || $parsedTitle['project_name'] === null
            ? null
            : Project::query()
                ->whereBelongsTo($client)
                ->where('name', $parsedTitle['project_name'])
                ->first();

        if ($client === null || ($parsedTitle['project_name'] !== null && $project === null)) {
            return [
                'client_id' => null,
                'project_id' => null,
                'feature_description' => $parsedTitle['feature_description'],
                'sync_status' => $calendarEvent->exists ? CalendarEventSyncStatus::Conflict : CalendarEventSyncStatus::Orphaned,
                'format_status' => CalendarEventFormatStatus::NeedsReview,
            ];
        }

        return [
            'client_id' => $client->id,
            'project_id' => $project?->id,
            'feature_description' => $parsedTitle['feature_description'],
            'sync_status' => CalendarEventSyncStatus::Synced,
            'format_status' => CalendarEventFormatStatus::Formatted,
        ];
    }
}
