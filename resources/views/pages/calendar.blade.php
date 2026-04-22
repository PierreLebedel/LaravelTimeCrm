<?php

use App\Enums\CalendarEventFormatStatus;
use App\Jobs\PushCalendarEventToRemoteJob;
use App\Support\CalDav\CalDavClient;
use App\Models\Calendar;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Support\CalendarEventEditor;
use App\Support\CalendarEventTitleFormatter;
use App\Support\QueueWorkerManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Calendrier')] class extends Component
{
    use Toast;

    #[Url(as: 'week', history: true)]
    public string $week = '';

    public bool $drawer = false;

    public ?int $editingEventId = null;

    public string $calendar_id = '';

    public string $client_id = '';

    public string $project_id = '';

    public string $feature_description = '';

    public string $description = '';

    public bool $is_billable = true;

    public string $starts_at = '';

    public string $ends_at = '';

    public function mount(): void
    {
        $this->week = $this->weekStart()->toDateString();
    }

    public function updatedClientId(): void
    {
        $this->syncProjectSelection();
    }

    public function updatedProjectId(): void
    {
        if ($this->project_id === '') {
            $this->syncProjectSelection();

            return;
        }

        $project = Project::query()->find((int) $this->project_id);

        if ($project === null) {
            return;
        }

        $this->client_id = (string) $project->client_id;
        $this->syncProjectSelection();
    }

    public function updatedCalendarId(): void
    {
        $this->applyCalendarDefaults();
    }

    public function syncVisibleWeek(string $startDate): void
    {
        $week = CarbonImmutable::parse($startDate)->startOfWeek()->toDateString();

        if ($this->week === $week) {
            return;
        }

        $this->week = $week;
        unset($this->fullCalendarEvents, $this->weeklyTotals);
        $this->dispatchFullCalendarRefresh();
    }

    public function createEventForSelection(string $startDateTime, string $endDateTime): void
    {
        $this->resetEditor();

        $defaultCalendarId = $this->calendarOptions->first()['id'] ?? '';

        $this->calendar_id = (string) $defaultCalendarId;
        $this->applyCalendarDefaults();
        $this->starts_at = CarbonImmutable::parse($startDateTime)->format('Y-m-d\TH:i');
        $this->ends_at = CarbonImmutable::parse($endDateTime)->format('Y-m-d\TH:i');
        $this->is_billable = true;
        $this->drawer = true;
    }

    public function editEvent(int $eventId): void
    {
        $event = CalendarEvent::query()
            ->with(['calendar:id,name', 'client:id,name,color', 'project:id,name,client_id'])
            ->findOrFail($eventId);

        $this->editingEventId = $event->id;
        $this->calendar_id = (string) $event->calendar_id;
        $this->client_id = (string) ($event->client_id ?? '');
        $this->project_id = (string) ($event->project_id ?? '');
        $this->feature_description = (string) ($event->feature_description ?? '');
        $this->description = (string) ($event->description ?? '');
        $this->is_billable = $event->is_billable;
        $this->starts_at = $event->starts_at->format('Y-m-d\TH:i');
        $this->ends_at = $event->ends_at->format('Y-m-d\TH:i');
        $this->syncProjectSelection();
        $this->drawer = true;
    }

    public function saveEvent(CalendarEventEditor $editor, QueueWorkerManager $queueWorkerManager): void
    {
        $validator = Validator::make([
            'calendar_id' => $this->calendar_id,
            'editingEventId' => $this->editingEventId,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'feature_description' => $this->feature_description,
            'description' => $this->description,
            'is_billable' => $this->is_billable,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
        ], [
            'calendar_id' => ['required_without:editingEventId', 'exists:calendars,id'],
            'editingEventId' => ['nullable', 'exists:calendar_events,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable'],
            'feature_description' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'is_billable' => ['required', 'bool'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $validator->after(function ($validator): void {
            if ($this->projectSelectionIsRequired() && blank($this->project_id)) {
                $validator->errors()->add('project_id', 'Le projet est requis pour ce client.');

                return;
            }

            if (filled($this->project_id) && ! Project::query()->whereKey((int) $this->project_id)->exists()) {
                $validator->errors()->add('project_id', 'Le projet selectionne est invalide.');
            }
        });

        $validated = $validator->validate();

        $payload = [
            'calendar_id' => $validated['calendar_id'] !== '' ? (int) $validated['calendar_id'] : null,
            'client_id' => (int) $validated['client_id'],
            'project_id' => $validated['project_id'] !== '' ? (int) $validated['project_id'] : null,
            'feature_description' => $validated['feature_description'],
            'description' => $validated['description'] ?: null,
            'is_billable' => $validated['is_billable'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
        ];

        $updatedEvent = $validated['editingEventId']
            ? $editor->update(CalendarEvent::query()->findOrFail($validated['editingEventId']), $payload)
            : $editor->create($payload);

        PushCalendarEventToRemoteJob::dispatch($updatedEvent->id)->afterCommit();
        $queueWorkerManager->ensureRunning();

        unset($this->fullCalendarEvents, $this->weeklyTotals, $this->currentEvent);
        $this->dispatchFullCalendarRefresh();

        $this->success('Evenement enregistre et synchronisation distante planifiee.');
        $this->resetEditor();
    }

    public function deleteEvent(CalDavClient $client): void
    {
        if ($this->editingEventId === null) {
            return;
        }

        $event = CalendarEvent::query()
            ->with('calendar.account')
            ->findOrFail($this->editingEventId);

        $client->deleteEvent($event);
        $event->delete();

        unset($this->fullCalendarEvents, $this->weeklyTotals, $this->currentEvent);
        $this->dispatchFullCalendarRefresh();

        $this->success('Evenement supprime.');
        $this->resetEditor();
    }

    public function rescheduleEvent(
        int $eventId,
        string $startDateTime,
        string $endDateTime,
        CalendarEventEditor $editor,
        QueueWorkerManager $queueWorkerManager,
    ): void {
        $event = CalendarEvent::query()->findOrFail($eventId);

        $editor->reschedule($event, $startDateTime, $endDateTime);

        PushCalendarEventToRemoteJob::dispatch($event->id)->afterCommit();
        $queueWorkerManager->ensureRunning();

        unset($this->fullCalendarEvents, $this->weeklyTotals);
        $this->dispatchFullCalendarRefresh();
    }

    public function resetEditor(): void
    {
        $this->reset('drawer', 'editingEventId', 'calendar_id', 'client_id', 'project_id', 'feature_description', 'description', 'starts_at', 'ends_at');
        $this->is_billable = true;
        $this->resetErrorBag();
    }

    #[Computed]
    public function currentEvent(): ?CalendarEvent
    {
        if ($this->editingEventId === null) {
            return null;
        }

        return CalendarEvent::query()
            ->with(['calendar:id,name,color', 'client:id,name,color', 'project:id,name'])
            ->find($this->editingEventId);
    }

    #[Computed]
    public function currentCalendar(): ?Calendar
    {
        if ($this->calendar_id === '') {
            return null;
        }

        return Calendar::query()
            ->with('account')
            ->find((int) $this->calendar_id);
    }

    #[Computed]
    public function clientOptions(): Collection
    {
        return Client::query()->orderBy('name')->get();
    }

    #[Computed]
    public function projectOptions(): Collection
    {
        return Project::query()
            ->with('client:id,name')
            ->when($this->client_id !== '', fn ($query) => $query->where('client_id', (int) $this->client_id))
            ->join('clients', 'clients.id', '=', 'projects.client_id')
            ->orderBy('clients.name')
            ->orderBy('projects.name')
            ->select('projects.*')
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'name' => $this->client_id !== ''
                    ? $project->name
                    : $project->name.' ('.$project->client->name.')',
            ])
            ->sortBy('name');
    }

    public function projectSelectionIsRequired(): bool
    {
        return $this->selectedClientProjectCount() > 0;
    }

    public function projectSelectionIsDisabled(): bool
    {
        return $this->client_id !== '' && $this->selectedClientProjectCount() === 0;
    }

    public function projectPlaceholder(): string
    {
        return $this->projectSelectionIsDisabled()
            ? 'Aucun projet disponible'
            : config('crm.select_placeholder');
    }

    #[Computed]
    public function calendarOptions(): Collection
    {
        return Calendar::query()
            ->with('account:id,name')
            ->where('is_selected', true)
            ->orderBy('name')
            ->get()
            ->map(fn (Calendar $calendar) => [
                'id' => $calendar->id,
                'name' => ($calendar->account?->name ? $calendar->account->name.' - ' : '').$calendar->name,
            ]);
    }

    #[Computed]
    public function fullCalendarEvents(): array
    {
        return CalendarEvent::query()
            ->with(['client:id,name,color', 'project:id,name', 'calendar:id,name,color'])
            ->forWeek($this->weekStart())
            ->orderBy('starts_at')
            ->get()
            ->map(function (CalendarEvent $event): array {
                $color = $event->client?->color ?? $event->calendar?->color ?? '#64748b';

                return [
                    'id' => (string) $event->id,
                    'title' => $event->title,
                    'start' => $event->starts_at->toIso8601String(),
                    'end' => $event->ends_at->toIso8601String(),
                    'backgroundColor' => $color,
                    'borderColor' => $color,
                    'textColor' => '#111827',
                    'extendedProps' => [
                        'client' => $event->client?->name,
                        'project' => $event->project?->name,
                        'isBillable' => $event->is_billable,
                        'needsReview' => $event->format_status === CalendarEventFormatStatus::NeedsReview,
                    ],
                ];
            })
            ->all();
    }

    #[Computed]
    public function weeklyTotals(): array
    {
        $events = CalendarEvent::query()
            ->forWeek($this->weekStart())
            ->get();

        $minutes = $events->sum(fn (CalendarEvent $event) => $event->durationInMinutes());

        return [
            'events' => $events->count(),
            'hours' => round($minutes / 60, 2),
            'reviews' => $events->where('format_status', CalendarEventFormatStatus::NeedsReview)->count(),
        ];
    }

    public function previewTitle(): string
    {
        $client = $this->clientOptions->firstWhere('id', (int) $this->client_id);

        if ($client === null) {
            return 'Selectionne un client pour generer le titre.';
        }

        $project = $this->project_id !== ''
            ? Project::query()->find((int) $this->project_id)
            : null;

        return CalendarEventTitleFormatter::format($client, $project, $this->feature_description ?: 'Title');
    }

    public function drawerTitle(): string
    {
        return $this->editingEventId === null ? 'Nouvel evenement' : 'Editer l evenement';
    }

    public function weekStart(): CarbonImmutable
    {
        $date = $this->week !== '' ? CarbonImmutable::parse($this->week) : CarbonImmutable::now();

        return $date->locale('fr')->startOfWeek();
    }

    public function weekEnd(): CarbonImmutable
    {
        return $this->weekStart()->endOfWeek();
    }

    protected function applyCalendarDefaults(): void
    {
        $this->project_id = '';

        if ($this->calendar_id === '') {
            return;
        }

        $calendar = Calendar::query()
            ->with('account')
            ->find((int) $this->calendar_id);

        $this->client_id = (string) ($calendar?->account?->default_client_id ?? '');
        $this->syncProjectSelection();
    }

    protected function dispatchFullCalendarRefresh(): void
    {
        $this->dispatch('fullcalendar-refresh');
    }

    protected function syncProjectSelection(): void
    {
        if ($this->project_id !== '') {
            $project = Project::query()->find((int) $this->project_id);

            if ($project === null || ($this->client_id !== '' && $project->client_id !== (int) $this->client_id)) {
                $this->project_id = '';
            }
        }

        if ($this->client_id === '') {
            return;
        }

        $projectIds = Project::query()
            ->where('client_id', (int) $this->client_id)
            ->orderBy('name')
            ->pluck('id');

        if ($projectIds->count() === 1) {
            $this->project_id = (string) $projectIds->first();

            return;
        }

        if ($projectIds->isEmpty()) {
            $this->project_id = '';
        }
    }

    protected function selectedClientProjectCount(): int
    {
        if ($this->client_id === '') {
            return 0;
        }

        return Project::query()
            ->where('client_id', (int) $this->client_id)
            ->count();
    }
};
?>

<div class="space-y-6">
    <x-header
        title="Calendrier"
        subtitle="Vue hebdomadaire interactive avec drag and drop, resize et creation par selection de plage."
        separator
    />

    <div class="grid gap-4 md:grid-cols-3">
        <x-card title="Periode" subtitle="{{ $this->weekStart()->translatedFormat('d M Y') }} -> {{ $this->weekEnd()->translatedFormat('d M Y') }}">
            <p class="text-sm text-base-content/70">Glisse un evenement pour le deplacer ou tire sa bordure pour ajuster la duree.</p>
        </x-card>

        <x-card title="Evenements">
            <p class="text-4xl font-bold">{{ $this->weeklyTotals['events'] }}</p>
        </x-card>

        <x-card title="Temps / Revue">
            <p class="text-2xl font-bold">{{ number_format($this->weeklyTotals['hours'], 2, ',', ' ') }} h</p>
            <p class="mt-2 text-sm text-warning">{{ $this->weeklyTotals['reviews'] }} evenement(s) a revoir</p>
        </x-card>
    </div>

    <x-card class="overflow-hidden p-0">

        <script type="application/json" data-fullcalendar-events>@json($this->fullCalendarEvents)</script>

        <div wire:ignore>
            <div
                data-fullcalendar
                data-initial-date="{{ $this->weekStart()->toDateString() }}"
                data-timezone="{{ config('app.timezone') }}"
            ></div>
        </div>
    </x-card>

    <x-drawer wire:model="drawer" title="{{ $this->drawerTitle() }}" right separator with-close-button class="w-full lg:w-[32rem]">
        @if ($this->editingEventId === null || $this->currentEvent)
            <div class="space-y-4">
                <div class="rounded-box bg-base-200 p-4">
                    <div
                        class="-m-4 mb-0 rounded-box bg-base-200 p-4"
                        style="border-left: 4px solid {{ $this->currentEvent?->client?->color ?? $this->currentCalendar?->color ?? 'transparent' }};"
                    >
                        <p class="text-xs uppercase tracking-[0.3em] text-base-content/40">{{ $this->currentCalendar?->name ?? $this->currentEvent?->calendar?->name ?? 'Agenda' }}</p>
                        <p class="mt-2 text-sm font-semibold">{{ $this->currentEvent?->title ?? 'Creation d un nouvel evenement local' }}</p>
                        @if ($this->currentEvent?->client?->color)
                            <div class="mt-2 text-xs text-base-content/60">
                                <x-client-indicator :name="$this->currentEvent->client->name" :color="$this->currentEvent->client->color" />
                            </div>
                        @endif
                        @if (($this->currentEvent?->is_billable ?? $this->is_billable) === false)
                            <div class="mt-2">
                                <x-badge value="non facturable" class="badge-ghost" />
                            </div>
                        @endif
                    </div>
                </div>

                <x-calendar-event-form-fields
                    :show-calendar-select="$this->editingEventId === null"
                    :calendar-options="$this->calendarOptions"
                    :client-options="$this->clientOptions"
                    :project-options="$this->projectOptions"
                    :project-wire-key="'fullcalendar-project-select-' . ($client_id ?: 'none')"
                    :project-required="$this->projectSelectionIsRequired()"
                    :project-disabled="$this->projectSelectionIsDisabled()"
                    :project-placeholder="$this->projectPlaceholder()"
                    :title-preview="$this->previewTitle()"
                    title-preview-label="Titre distant"
                />
            </div>
        @endif

        <x-slot:actions>
            @if ($this->editingEventId !== null)
                <x-button
                    label="Supprimer"
                    class="btn-error btn-outline"
                    wire:click="deleteEvent"
                    wire:confirm="Supprimer cet evenement ?"
                />
            @endif
            <x-button label="Annuler" wire:click="resetEditor" />
            <x-button label="Enregistrer" class="btn-primary" wire:click="saveEvent" spinner="saveEvent" />
        </x-slot:actions>
    </x-drawer>
</div>

@assets
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.20/index.global.min.js"></script>
@endassets

<script>
    const calendarElement = $wire.$el.querySelector('[data-fullcalendar]');
    let calendar = null;

    const escapeHtml = (value) => {
        return String(value)
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    };

    const renderEvents = (events) => {
        if (! calendar) {
            return;
        }

        calendar.removeAllEvents();
        calendar.addEventSource(events);
    };

    const readEventsFromDom = () => {
        const fullCalendarEventsElement = $wire.$el.querySelector('[data-fullcalendar-events]');

        return JSON.parse(fullCalendarEventsElement?.textContent ?? '[]');
    };

    const persistEventMove = async (info) => {
        const end = info.event.end ?? info.event.start;

        try {
            await $wire.rescheduleEvent(
                Number(info.event.id),
                info.event.start.toISOString(),
                end.toISOString(),
            );
        } catch (error) {
            info.revert();
        }
    };

    if (calendarElement) {
        const fullCalendarInitialDate = calendarElement.dataset.initialDate;
        const fullCalendarTimezone = calendarElement.dataset.timezone;
        const fullCalendarEvents = readEventsFromDom();

        calendar = new FullCalendar.Calendar(calendarElement, {
            initialView: 'timeGridWeek',
            initialDate: fullCalendarInitialDate,
            locale: 'fr',
            timeZone: fullCalendarTimezone,
            firstDay: 1,
            allDaySlot: false,
            nowIndicator: true,
            editable: true,
            selectable: true,
            selectMirror: true,
            height: '528px',
            slotDuration: '00:30:00',
            slotMinTime: '00:00:00',
            slotMaxTime: '24:00:00',
            scrollTime: '08:50:00',
            scrollTimeReset: false,
            snapDuration: '00:15:00',
            slotLabelFormat: {
                hour: '2-digit',
                minute: '2-digit',
                hour12: false,
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'timeGridWeek,timeGridDay',
            },
            events: fullCalendarEvents,
            eventClick: (info) => {
                $wire.editEvent(Number(info.event.id));
            },
            select: (info) => {
                $wire.createEventForSelection(info.startStr, info.endStr);
                calendar.unselect();
            },
            datesSet: (info) => {
                $wire.syncVisibleWeek(info.startStr);
            },
            eventDrop: persistEventMove,
            eventResize: persistEventMove,
            eventContent: (arg) => {
                const client = arg.event.extendedProps.client;
                const project = arg.event.extendedProps.project;
                const needsReview = arg.event.extendedProps.needsReview;
                const isBillable = arg.event.extendedProps.isBillable;
                const lines = [
                    `<div class="fc-event-title font-semibold">${escapeHtml(arg.event.title)}</div>`,
                ];

                if (client || project || needsReview || ! isBillable) {
                    const meta = [];

                    if (client) {
                        meta.push(escapeHtml(client));
                    }

                    if (project) {
                        meta.push(escapeHtml(project));
                    }

                    if (needsReview) {
                        meta.push('A revoir');
                    }

                    if (! isBillable) {
                        meta.push('Non facturable');
                    }

                    lines.push(`<div class="fc-event-subtitle text-[11px] opacity-80">${meta.join(' - ')}</div>`);
                }

                return {
                    html: `<div class="">${lines.join('')}</div>`,
                };
            },
        });

        calendar.render();
    }

    this.$on('fullcalendar-refresh', () => {
        queueMicrotask(() => {
            renderEvents(readEventsFromDom());
        });
    });
</script>
