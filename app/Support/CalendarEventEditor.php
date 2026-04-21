<?php

namespace App\Support;

use App\Enums\CalendarEventFormatStatus;
use App\Enums\CalendarEventSyncStatus;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class CalendarEventEditor
{
    /**
     * @param  array{
     *     client_id: int,
     *     project_id: ?int,
     *     feature_description: string,
     *     description: ?string,
     *     starts_at: string,
     *     ends_at: string
     * }  $attributes
     */
    public function update(CalendarEvent $event, array $attributes): CalendarEvent
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
        $event->starts_at = $startsAt;
        $event->ends_at = $endsAt;
        $event->title = CalendarEventTitleFormatter::format($client, $project, $event->feature_description);
        $event->format_status = CalendarEventFormatStatus::Formatted;
        $event->sync_status = CalendarEventSyncStatus::Queued;
        $event->save();

        return $event->fresh(['calendar', 'client', 'project']);
    }
}
