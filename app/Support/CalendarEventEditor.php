<?php

namespace App\Support;

use App\Enums\CalendarEventFormatStatus;
use App\Enums\CalendarEventSyncStatus;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CalendarEventEditor
{
    /**
     * @param  array{
     *     client_id: int,
     *     project_id: ?int,
     *     feature_description: string,
     *     description: ?string,
     *     is_billable: bool,
     *     starts_at: string,
     *     ends_at: string
     * }  $attributes
     */
    public function update(CalendarEvent $event, array $attributes): CalendarEvent
    {
        return $this->fill($event, $attributes);
    }

    /**
     * @param  array{
     *     calendar_id: int,
     *     client_id: int,
     *     project_id: ?int,
     *     feature_description: string,
     *     description: ?string,
     *     is_billable: bool,
     *     starts_at: string,
     *     ends_at: string
     * }  $attributes
     */
    public function create(array $attributes): CalendarEvent
    {
        $calendar = Calendar::query()
            ->with('account')
            ->findOrFail($attributes['calendar_id']);

        $event = new CalendarEvent;
        $uid = (string) Str::uuid();

        $event->calendar()->associate($calendar);
        $event->ical_uid = $uid;
        $event->external_id = rtrim($calendar->external_id, '/').'/'.$uid.'.ics';
        $event->timezone = $calendar->timezone ?? config('app.timezone');

        return $this->fill($event, $attributes);
    }

    public function reschedule(CalendarEvent $event, string $startsAt, string $endsAt): CalendarEvent
    {
        $event->starts_at = CarbonImmutable::parse($startsAt);
        $event->ends_at = CarbonImmutable::parse($endsAt);
        $event->sync_status = CalendarEventSyncStatus::Queued;
        $event->save();

        return $event->fresh(['calendar', 'client', 'project']);
    }

    /**
     * @param  array{
     *     client_id: int,
     *     project_id: ?int,
     *     feature_description: string,
     *     description: ?string,
     *     is_billable: bool,
     *     starts_at: string,
     *     ends_at: string
     * }  $attributes
     */
    protected function fill(CalendarEvent $event, array $attributes): CalendarEvent
    {
        $client = Client::query()->findOrFail($attributes['client_id']);
        $project = $attributes['project_id'] === null
            ? null
            : Project::query()->findOrFail($attributes['project_id']);

        if ($project !== null && $project->client_id !== $client->id) {
            throw ValidationException::withMessages([
                'project_id' => 'Le projet selectionne n appartient pas au client choisi.',
            ]);
        }

        $startsAt = CarbonImmutable::parse($attributes['starts_at']);
        $endsAt = CarbonImmutable::parse($attributes['ends_at']);

        $event->client()->associate($client);
        $event->project()->associate($project);
        $event->feature_description = trim($attributes['feature_description']);
        $event->description = filled($attributes['description'] ?? null)
            ? trim((string) $attributes['description'])
            : null;
        $event->is_billable = $attributes['is_billable'];
        $event->starts_at = $startsAt;
        $event->ends_at = $endsAt;
        $event->title = CalendarEventTitleFormatter::format($client, $project, $event->feature_description);
        $event->format_status = CalendarEventFormatStatus::Formatted;
        $event->sync_status = CalendarEventSyncStatus::Queued;
        $event->source_updated_at = $event->source_updated_at ?? now();
        $event->save();

        return $event->fresh(['calendar', 'client', 'project']);
    }
}
