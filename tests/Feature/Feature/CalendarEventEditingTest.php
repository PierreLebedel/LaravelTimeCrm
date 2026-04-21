<?php

use App\Enums\CalendarEventFormatStatus;
use App\Enums\CalendarEventSyncStatus;
use App\Jobs\PushCalendarEventToRemoteJob;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('it edits a calendar event from the weekly calendar and queues a remote push job', function () {
    Queue::fake();

    $sourceClient = Client::factory()->create();
    $targetClient = Client::factory()->create([
        'name' => 'Acme',
    ]);
    $project = Project::factory()->create([
        'client_id' => $targetClient->id,
        'name' => 'Plateforme',
    ]);

    $event = CalendarEvent::factory()->create([
        'client_id' => $sourceClient->id,
        'project_id' => null,
        'ical_uid' => 'event-123',
        'feature_description' => 'Ancienne tache',
        'title' => 'Ancien titre',
        'sync_status' => CalendarEventSyncStatus::Synced,
        'format_status' => CalendarEventFormatStatus::NeedsReview,
    ]);

    Livewire::test('pages::calendar')
        ->call('editEvent', $event->id)
        ->set('client_id', (string) $targetClient->id)
        ->set('project_id', (string) $project->id)
        ->set('feature_description', 'Sprint planning')
        ->set('description', 'Atelier equipe')
        ->set('starts_at', '2026-04-21T09:00')
        ->set('ends_at', '2026-04-21T10:30')
        ->call('saveEvent')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event)
        ->client_id->toBe($targetClient->id)
        ->project_id->toBe($project->id)
        ->feature_description->toBe('Sprint planning')
        ->description->toBe('Atelier equipe')
        ->title->toBe('Acme//Plateforme : Sprint planning')
        ->sync_status->toBe(CalendarEventSyncStatus::Queued)
        ->format_status->toBe(CalendarEventFormatStatus::Formatted);

    Queue::assertPushed(PushCalendarEventToRemoteJob::class, fn (PushCalendarEventToRemoteJob $job) => $job->calendarEventId === $event->id);
});

test('it edits a review event and queues a remote push job', function () {
    Queue::fake();

    $client = Client::factory()->create([
        'name' => 'Acme',
    ]);
    $project = Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Plateforme',
    ]);

    $event = CalendarEvent::factory()->create([
        'client_id' => null,
        'project_id' => null,
        'ical_uid' => 'event-456',
        'feature_description' => 'A revoir',
        'title' => 'Titre brut',
        'sync_status' => CalendarEventSyncStatus::Conflict,
        'format_status' => CalendarEventFormatStatus::NeedsReview,
    ]);

    Livewire::test('pages::review')
        ->set('event_id', $event->id)
        ->set('client_id', (string) $client->id)
        ->set('project_id', (string) $project->id)
        ->set('feature_description', 'Support')
        ->set('description', 'Resolution incident')
        ->set('starts_at', '2026-04-22T14:00')
        ->set('ends_at', '2026-04-22T15:15')
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event)
        ->client_id->toBe($client->id)
        ->project_id->toBe($project->id)
        ->title->toBe('Acme//Plateforme : Support')
        ->sync_status->toBe(CalendarEventSyncStatus::Queued)
        ->format_status->toBe(CalendarEventFormatStatus::Formatted);

    Queue::assertPushed(PushCalendarEventToRemoteJob::class, fn (PushCalendarEventToRemoteJob $job) => $job->calendarEventId === $event->id);
});
