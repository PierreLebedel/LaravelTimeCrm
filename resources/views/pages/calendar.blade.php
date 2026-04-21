<?php

use App\Enums\CalendarEventFormatStatus;
use App\Jobs\PushCalendarEventToRemoteJob;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Support\CalendarEventEditor;
use App\Support\CalendarEventTitleFormatter;
use App\Support\QueueWorkerManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
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

    public string $client_id = '';

    public string $project_id = '';

    public string $feature_description = '';

    public string $description = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public function mount(): void
    {
        $this->week = $this->weekStart()->toDateString();
    }

    public function previousWeek(): void
    {
        $this->week = $this->weekStart()->subWeek()->toDateString();
    }

    public function nextWeek(): void
    {
        $this->week = $this->weekStart()->addWeek()->toDateString();
    }

    public function currentWeek(): void
    {
        $this->week = CarbonImmutable::now()->startOfWeek()->toDateString();
    }

    public function editEvent(int $eventId): void
    {
        $event = CalendarEvent::query()
            ->with(['client:id,name', 'project:id,name,client_id'])
            ->findOrFail($eventId);

        $this->editingEventId = $event->id;
        $this->client_id = (string) ($event->client_id ?? '');
        $this->project_id = (string) ($event->project_id ?? '');
        $this->feature_description = (string) ($event->feature_description ?? '');
        $this->description = (string) ($event->description ?? '');
        $this->starts_at = $event->starts_at->format('Y-m-d\TH:i');
        $this->ends_at = $event->ends_at->format('Y-m-d\TH:i');
        $this->drawer = true;
    }

    public function saveEvent(CalendarEventEditor $editor, QueueWorkerManager $queueWorkerManager): void
    {
        $validated = $this->validate([
            'editingEventId' => ['required', 'exists:calendar_events,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable'],
            'feature_description' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $event = CalendarEvent::query()->findOrFail($validated['editingEventId']);

        $updatedEvent = $editor->update($event, [
            'client_id' => (int) $validated['client_id'],
            'project_id' => $validated['project_id'] !== '' ? (int) $validated['project_id'] : null,
            'feature_description' => $validated['feature_description'],
            'description' => $validated['description'] ?: null,
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
        ]);

        PushCalendarEventToRemoteJob::dispatch($updatedEvent->id)->afterCommit();
        $queueWorkerManager->ensureRunning();

        unset($this->eventsByDay, $this->weeklyTotals, $this->currentEvent);

        $this->success('Evenement enregistre et synchronisation distante planifiee.');
        $this->resetEditor();
    }

    public function resetEditor(): void
    {
        $this->reset('drawer', 'editingEventId', 'client_id', 'project_id', 'feature_description', 'description', 'starts_at', 'ends_at');
        $this->resetErrorBag();
    }

    #[Computed]
    public function currentEvent(): ?CalendarEvent
    {
        if ($this->editingEventId === null) {
            return null;
        }

        return CalendarEvent::query()
            ->with(['calendar:id,name', 'client:id,name', 'project:id,name'])
            ->find($this->editingEventId);
    }

    #[Computed]
    public function clientOptions(): Collection
    {
        return Client::query()->orderBy('name')->get();
    }

    #[Computed]
    public function projectOptions(): Collection
    {
        if ($this->client_id === '') {
            return collect();
        }

        return Project::query()
            ->where('client_id', (int) $this->client_id)
            ->orderBy('name')
            ->get();
    }

    public function previewTitle(): string
    {
        $client = $this->clientOptions->firstWhere('id', (int) $this->client_id);

        if ($client === null) {
            return 'Selectionne un client pour generer le titre.';
        }

        $project = $this->projectOptions->firstWhere('id', (int) $this->project_id);

        return CalendarEventTitleFormatter::format($client, $project, $this->feature_description ?: 'feature description');
    }

    #[Computed]
    public function days(): array
    {
        return collect(range(0, 6))
            ->map(fn (int $offset) => $this->weekStart()->addDays($offset))
            ->all();
    }

    #[Computed]
    public function eventsByDay(): array
    {
        return CalendarEvent::query()
            ->with(['client:id,name', 'project:id,name', 'calendar:id,name,color'])
            ->forWeek($this->weekStart())
            ->orderBy('starts_at')
            ->get()
            ->groupBy(fn (CalendarEvent $event) => $event->starts_at->toDateString())
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

    public function weekStart(): CarbonImmutable
    {
        $date = $this->week !== '' ? CarbonImmutable::parse($this->week) : CarbonImmutable::now();

        return $date->locale('fr')->startOfWeek();
    }

    public function weekEnd(): CarbonImmutable
    {
        return $this->weekStart()->endOfWeek();
    }
};
?>

<div>
    <x-header
        title="Calendrier hebdomadaire"
        subtitle="Navigation semaine par semaine, revue des evenements et edition directe depuis la grille."
        separator
    >
        <x-slot:actions>
            <x-button label="Semaine precedente" icon="tabler.chevron-left" wire:click="previousWeek" />
            <x-button label="Semaine actuelle" icon="tabler.calendar-week" wire:click="currentWeek" />
            <x-button label="Semaine suivante" icon-right="tabler.chevron-right" wire:click="nextWeek" class="btn-primary" />
        </x-slot:actions>
    </x-header>

    <div class="mb-6 grid gap-4 md:grid-cols-3">
        <x-card title="Periode" subtitle="{{ $this->weekStart()->translatedFormat('d M Y') }} -> {{ $this->weekEnd()->translatedFormat('d M Y') }}">
            <p class="text-sm text-base-content/70">Les evenements sont cliquables pour edition locale puis push distant via job.</p>
        </x-card>

        <x-card title="Evenements">
            <p class="text-4xl font-bold">{{ $this->weeklyTotals['events'] }}</p>
        </x-card>

        <x-card title="Temps / Revue">
            <p class="text-2xl font-bold">{{ number_format($this->weeklyTotals['hours'], 2, ',', ' ') }} h</p>
            <p class="mt-2 text-sm text-warning">{{ $this->weeklyTotals['reviews'] }} evenement(s) a revoir</p>
        </x-card>
    </div>

    <div class="grid gap-4 xl:grid-cols-7">
        @foreach ($this->days as $day)
            <x-card class="bg-base-100/80 shadow-sm">
                <div class="mb-4">
                    <p class="text-xs uppercase tracking-[0.3em] text-base-content/40">{{ $day->translatedFormat('D') }}</p>
                    <p class="mt-1 text-lg font-semibold">{{ $day->translatedFormat('d M') }}</p>
                </div>

                <div class="space-y-3">
                    @forelse ($this->eventsByDay[$day->toDateString()] ?? [] as $event)
                        <button
                            type="button"
                            class="w-full rounded-box border border-base-300 bg-base-200/60 p-3 text-left transition hover:border-primary/40 hover:bg-base-200"
                            wire:key="calendar-event-{{ $event->id }}"
                            wire:click="editEvent({{ $event->id }})"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold">{{ $event->title }}</p>
                                    <p class="mt-1 text-xs text-base-content/60">{{ $event->starts_at->format('H:i') }} - {{ $event->ends_at->format('H:i') }}</p>
                                </div>

                                @if ($event->calendar?->color)
                                    <span class="mt-1 size-3 rounded-full border border-base-300" style="background-color: {{ $event->calendar->color }}"></span>
                                @endif
                            </div>

                            <div class="mt-3 space-y-1 text-xs text-base-content/70">
                                <p>{{ $event->client?->name ?? 'A revoir' }}</p>
                                @if($event->project)
                                <p>{{ $event->project->name }}</p>
                                @endif
                                <x-badge :value="$event->format_status->value" class="{{ $event->format_status === CalendarEventFormatStatus::NeedsReview ? 'badge-warning' : 'badge-primary' }}" />
                            </div>
                        </button>
                    @empty
                        <div class="rounded-box border border-dashed border-base-300 p-4 text-sm text-base-content/50">
                            Aucun evenement.
                        </div>
                    @endforelse
                </div>
            </x-card>
        @endforeach
    </div>

    <x-drawer wire:model="drawer" title="Editer l evenement" right separator with-close-button class="w-full lg:w-[32rem]">
        @if ($this->currentEvent)
            <div class="space-y-4">
                <div class="rounded-box bg-base-200 p-4">
                    <p class="text-xs uppercase tracking-[0.3em] text-base-content/40">{{ $this->currentEvent->calendar?->name ?? 'Agenda' }}</p>
                    <p class="mt-2 text-sm font-semibold">{{ $this->currentEvent->title }}</p>
                </div>

                <x-select label="Client" wire:model.change="client_id" :options="$this->clientOptions" required />
                <x-select label="Projet" wire:model.change="project_id" :options="$this->projectOptions" placeholder="Sans projet" />
                <x-input label="Description courte" wire:model.blur="feature_description" required />
                <x-textarea label="Description detaillee" wire:model.blur="description" rows="5" />

                <div class="grid gap-4 md:grid-cols-2">
                    <x-input type="datetime-local" label="Debut" wire:model.blur="starts_at" step="900" required />
                    <x-input type="datetime-local" label="Fin" wire:model.blur="ends_at" step="900" required />
                </div>

                <div class="rounded-box bg-primary/10 p-4 text-sm">
                    <p class="font-semibold text-primary">Titre distant</p>
                    <p class="mt-2 font-mono text-xs">{{ $this->previewTitle() }}</p>
                </div>
            </div>
        @endif

        <x-slot:actions>
            <x-button label="Annuler" wire:click="resetEditor" />
            <x-button label="Enregistrer" class="btn-primary" wire:click="saveEvent" spinner="saveEvent" />
        </x-slot:actions>
    </x-drawer>
</div>
