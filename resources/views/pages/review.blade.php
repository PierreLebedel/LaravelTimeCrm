<?php

use App\Jobs\PushCalendarEventToRemoteJob;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Support\CalendarEventEditor;
use App\Support\CalendarEventTitleFormatter;
use App\Support\QueueWorkerManager;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Revue')] class extends Component
{
    use Toast;

    public ?int $event_id = null;

    public string $client_id = '';

    public string $project_id = '';

    public string $feature_description = '';

    public string $description = '';

    public string $starts_at = '';

    public string $ends_at = '';

    public function mount(): void
    {
        $this->loadNextEvent();
    }

    public function loadNextEvent(): void
    {
        $event = CalendarEvent::query()
            ->needsReview()
            ->with(['client:id,name', 'project:id,name,client_id'])
            ->orderBy('starts_at')
            ->first();

        if ($event === null) {
            $this->reset('event_id', 'client_id', 'project_id', 'feature_description', 'description', 'starts_at', 'ends_at');

            return;
        }

        $this->event_id = $event->id;
        $this->client_id = (string) ($event->client_id ?? '');
        $this->project_id = (string) ($event->project_id ?? '');
        $this->feature_description = (string) ($event->feature_description ?? '');
        $this->description = (string) ($event->description ?? '');
        $this->starts_at = $event->starts_at->format('Y-m-d\TH:i');
        $this->ends_at = $event->ends_at->format('Y-m-d\TH:i');
    }

    public function save(CalendarEventEditor $editor, QueueWorkerManager $queueWorkerManager): void
    {
        $validated = $this->validate([
            'event_id' => ['required', 'exists:calendar_events,id'],
            'client_id' => ['required', 'exists:clients,id'],
            'project_id' => ['nullable'],
            'feature_description' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
        ]);

        $event = CalendarEvent::query()->findOrFail($validated['event_id']);

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

        $this->success('Evenement reclasse et synchronisation distante planifiee.');
        $this->loadNextEvent();
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
    public function currentEvent(): ?CalendarEvent
    {
        if ($this->event_id === null) {
            return null;
        }

        return CalendarEvent::query()
            ->with(['calendar:id,name', 'client:id,name', 'project:id,name'])
            ->find($this->event_id);
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

    #[Computed]
    public function queueCount(): int
    {
        return CalendarEvent::query()->needsReview()->count();
    }
};
?>

<div>
    <x-header title="File de revue" subtitle="Traitement unitaire des evenements mal formates detectes pendant la synchronisation." separator>
        <x-slot:actions>
            <x-badge :value="$this->queueCount.' restant(s)'" class="badge-warning" />
        </x-slot:actions>
    </x-header>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <x-card title="Evenement courant" subtitle="Client obligatoire, projet facultatif.">
            @if (! $this->currentEvent)
                <div class="rounded-box border border-dashed border-base-300 p-6 text-base-content/60">
                    Plus aucun evenement a traiter.
                </div>
            @else
                <div class="space-y-4">
                    <div class="rounded-box bg-base-200 p-4">
                        <p class="text-sm font-semibold">{{ $this->currentEvent->title }}</p>
                        <p class="mt-1 text-sm text-base-content/60">
                            {{ $this->currentEvent->starts_at->translatedFormat('d M Y H:i') }} -> {{ $this->currentEvent->ends_at->translatedFormat('H:i') }}
                        </p>
                        @if ($this->currentEvent->description)
                            <p class="mt-3 text-sm leading-6 text-base-content/70">{{ $this->currentEvent->description }}</p>
                        @endif
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
                        <p class="font-semibold text-primary">Titre final</p>
                        <p class="mt-2 font-mono text-xs">{{ $this->previewTitle() }}</p>
                    </div>

                    <div class="flex gap-3">
                        <x-button label="Valider" class="btn-primary" wire:click="save" spinner="save" />
                        <x-button label="Rafraichir" wire:click="loadNextEvent" />
                    </div>
                </div>
            @endif
        </x-card>

        <x-card title="Regle metier" subtitle="La source distante gagne toujours.">
            <p class="text-sm leading-6 text-base-content/70">
                Quand un evenement est reclassifie ou edite, son titre distant est reecrit via un job Laravel afin de rester coherent avec le referentiel client/projet.
            </p>

            <div class="mt-4 rounded-box bg-base-200 p-4">
                <p class="text-xs uppercase tracking-[0.3em] text-base-content/40">Format cible</p>
                <p class="mt-2 font-mono text-sm">{client}{?/projet} : titre</p>
            </div>
        </x-card>
    </div>
</div>
