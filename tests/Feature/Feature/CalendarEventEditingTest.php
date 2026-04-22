<?php

use App\Enums\CalendarEventFormatStatus;
use App\Enums\CalendarEventSyncStatus;
use App\Jobs\PushCalendarEventToRemoteJob;
use App\Models\Calendar;
use App\Models\CalendarAccount;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Support\CalDav\CalDavClient;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;

test('it edits a calendar event from the main calendar and queues a remote push job', function () {
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
        ->set('is_billable', false)
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
        ->is_billable->toBeFalse()
        ->title->toBe('Acme/Plateforme : Sprint planning')
        ->sync_status->toBe(CalendarEventSyncStatus::Queued)
        ->format_status->toBe(CalendarEventFormatStatus::Formatted);

    Queue::assertPushed(PushCalendarEventToRemoteJob::class, fn (PushCalendarEventToRemoteJob $job) => $job->calendarEventId === $event->id);
});

test('it creates a calendar event from the main calendar and queues a remote push job', function () {
    Queue::fake();

    $client = Client::factory()->create([
        'name' => 'Acme',
        'color' => '#0f766e',
    ]);

    $project = Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Plateforme',
    ]);

    $calendar = Calendar::factory()->create([
        'is_selected' => true,
    ]);

    Livewire::test('pages::calendar')
        ->call('createEventForSelection', '2026-04-23T09:00:00+02:00', '2026-04-23T10:00:00+02:00')
        ->set('calendar_id', (string) $calendar->id)
        ->set('client_id', (string) $client->id)
        ->set('project_id', (string) $project->id)
        ->set('feature_description', 'Atelier de cadrage')
        ->set('description', 'Preparation du sprint')
        ->set('is_billable', false)
        ->set('starts_at', '2026-04-23T09:00')
        ->set('ends_at', '2026-04-23T10:15')
        ->call('saveEvent')
        ->assertHasNoErrors();

    $event = CalendarEvent::query()->latest('id')->firstOrFail();

    expect($event)
        ->calendar_id->toBe($calendar->id)
        ->client_id->toBe($client->id)
        ->project_id->toBe($project->id)
        ->is_billable->toBeFalse()
        ->title->toBe('Acme/Plateforme : Atelier de cadrage')
        ->sync_status->toBe(CalendarEventSyncStatus::Queued)
        ->format_status->toBe(CalendarEventFormatStatus::Formatted);

    expect($event->ical_uid)->not->toBeNull();
    expect($event->external_id)->toEndWith('.ics');

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
        ->set('is_billable', false)
        ->set('starts_at', '2026-04-22T14:00')
        ->set('ends_at', '2026-04-22T15:15')
        ->call('save')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event)
        ->client_id->toBe($client->id)
        ->project_id->toBe($project->id)
        ->is_billable->toBeFalse()
        ->title->toBe('Acme/Plateforme : Support')
        ->sync_status->toBe(CalendarEventSyncStatus::Queued)
        ->format_status->toBe(CalendarEventFormatStatus::Formatted);

    Queue::assertPushed(PushCalendarEventToRemoteJob::class, fn (PushCalendarEventToRemoteJob $job) => $job->calendarEventId === $event->id);
});

test('it preselects the default dav client in review when the event has no local client yet', function () {
    $defaultClient = Client::factory()->create([
        'name' => 'Acme',
    ]);

    $account = CalendarAccount::factory()
        ->withDefaultClient($defaultClient)
        ->create();

    $calendar = Calendar::factory()->create([
        'calendar_account_id' => $account->id,
    ]);

    $event = CalendarEvent::factory()->create([
        'calendar_id' => $calendar->id,
        'client_id' => null,
        'project_id' => null,
        'sync_status' => CalendarEventSyncStatus::Conflict,
        'format_status' => CalendarEventFormatStatus::NeedsReview,
    ]);

    Livewire::test('pages::review')
        ->assertSet('event_id', $event->id)
        ->assertSet('client_id', (string) $defaultClient->id);
});

test('it loads the most recent review event first', function () {
    $olderEvent = CalendarEvent::factory()->create([
        'starts_at' => '2026-04-20 09:00:00',
        'ends_at' => '2026-04-20 10:00:00',
        'format_status' => CalendarEventFormatStatus::NeedsReview,
    ]);

    $newerEvent = CalendarEvent::factory()->create([
        'starts_at' => '2026-04-22 14:00:00',
        'ends_at' => '2026-04-22 15:00:00',
        'format_status' => CalendarEventFormatStatus::NeedsReview,
    ]);

    Livewire::test('pages::review')
        ->assertSet('event_id', $newerEvent->id);

    expect($olderEvent->id)->not->toBe($newerEvent->id);
});

test('it assigns the matching client when a project is selected in the calendar drawer', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create([
        'client_id' => $client->id,
    ]);

    Livewire::test('pages::calendar')
        ->set('project_id', (string) $project->id)
        ->assertSet('client_id', (string) $client->id);
});

test('it preselects the only project available for the selected client in the main calendar drawer', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create([
        'client_id' => $client->id,
    ]);

    Livewire::test('pages::calendar')
        ->set('client_id', (string) $client->id)
        ->assertSet('project_id', (string) $project->id);
});

test('it requires a project in the main calendar drawer when the selected client has projects', function () {
    Queue::fake();

    $client = Client::factory()->create([
        'name' => 'Acme',
    ]);
    Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Plateforme',
    ]);
    Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Support',
    ]);
    $calendar = Calendar::factory()->create([
        'is_selected' => true,
    ]);

    Livewire::test('pages::calendar')
        ->call('createEventForSelection', '2026-04-23T09:00:00+02:00', '2026-04-23T10:00:00+02:00')
        ->set('calendar_id', (string) $calendar->id)
        ->set('client_id', (string) $client->id)
        ->set('project_id', '')
        ->set('feature_description', 'Atelier de cadrage')
        ->set('starts_at', '2026-04-23T09:00')
        ->set('ends_at', '2026-04-23T10:15')
        ->call('saveEvent')
        ->assertHasErrors(['project_id']);
});

test('it reschedules a calendar event from the main calendar and queues a remote push job', function () {
    Queue::fake();

    $event = CalendarEvent::factory()->create([
        'starts_at' => '2026-04-21 09:00:00',
        'ends_at' => '2026-04-21 10:00:00',
        'sync_status' => CalendarEventSyncStatus::Synced,
        'format_status' => CalendarEventFormatStatus::Formatted,
    ]);

    $component = Livewire::test('pages::calendar')
        ->call('rescheduleEvent', $event->id, '2026-04-22T13:15:00+02:00', '2026-04-22T14:45:00+02:00')
        ->assertHasNoErrors();

    $event->refresh();

    expect($event->starts_at->format('Y-m-d H:i'))->toBe('2026-04-22 13:15');
    expect($event->ends_at->format('Y-m-d H:i'))->toBe('2026-04-22 14:45');
    expect($event->sync_status)->toBe(CalendarEventSyncStatus::Queued);
    expect(data_get($component->effects, 'xjs.0.expression'))->toContain('Synchronisation distante planifiee.');

    Queue::assertPushed(PushCalendarEventToRemoteJob::class, fn (PushCalendarEventToRemoteJob $job) => $job->calendarEventId === $event->id);
});

test('it deletes a calendar event from the fullcalendar drawer after remote deletion', function () {
    $event = CalendarEvent::factory()->create();

    $client = Mockery::mock(CalDavClient::class);
    $client->shouldReceive('deleteEvent')
        ->once()
        ->with(Mockery::on(fn (CalendarEvent $passedEvent) => $passedEvent->is($event)));

    app()->instance(CalDavClient::class, $client);

    Livewire::test('pages::calendar')
        ->call('editEvent', $event->id)
        ->call('deleteEvent')
        ->assertHasNoErrors();

    expect(CalendarEvent::query()->find($event->id))->toBeNull();
});

test('it preselects the only project available in review when the selected client has a single project', function () {
    $client = Client::factory()->create();
    $project = Project::factory()->create([
        'client_id' => $client->id,
    ]);

    Livewire::test('pages::review')
        ->set('client_id', (string) $client->id)
        ->assertSet('project_id', (string) $project->id);
});

test('it requires a project in review when the selected client has projects', function () {
    Queue::fake();

    $client = Client::factory()->create();
    Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Plateforme',
    ]);
    Project::factory()->create([
        'client_id' => $client->id,
        'name' => 'Support',
    ]);

    $event = CalendarEvent::factory()->create([
        'client_id' => null,
        'project_id' => null,
        'sync_status' => CalendarEventSyncStatus::Conflict,
        'format_status' => CalendarEventFormatStatus::NeedsReview,
    ]);

    Livewire::test('pages::review')
        ->assertSet('event_id', $event->id)
        ->set('client_id', (string) $client->id)
        ->set('project_id', '')
        ->set('feature_description', 'Support')
        ->set('starts_at', '2026-04-22T14:00')
        ->set('ends_at', '2026-04-22T15:15')
        ->call('save')
        ->assertHasErrors(['project_id']);
});
