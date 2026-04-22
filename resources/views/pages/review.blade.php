<?php

use App\Jobs\PushCalendarEventToRemoteJob;
use App\Models\CalendarEvent;
use App\Models\Client;
use App\Models\Project;
use App\Support\CalendarEventEditor;
use App\Support\CalendarEventTitleFormatter;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
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

    public bool $is_billable = true;

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
            ->with(['calendar.account.defaultClient:id,name,color', 'client:id,name', 'project:id,name,client_id'])
            ->orderByDesc('starts_at')
            ->first();

        if ($event === null) {
            $this->reset('event_id', 'client_id', 'project_id', 'feature_description', 'description', 'starts_at', 'ends_at');
            $this->is_billable = true;

            return;
        }

        $this->event_id = $event->id;
        $this->client_id = (string) ($event->client_id ?? $event->calendar?->account?->default_client_id ?? '');
        $this->project_id = (string) ($event->project_id ?? '');
        $this->feature_description = (string) ($event->feature_description ?? '');
        $this->description = (string) ($event->description ?? '');
        $this->is_billable = $event->is_billable;
        $this->starts_at = $event->starts_at->format('Y-m-d\TH:i');
        $this->ends_at = $event->ends_at->format('Y-m-d\TH:i');
        $this->syncProjectSelection();
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

    public function save(CalendarEventEditor $editor): void
    {
        $validator = Validator::make([
            'event_id' => $this->event_id,
            'client_id' => $this->client_id,
            'project_id' => $this->project_id,
            'feature_description' => $this->feature_description,
            'description' => $this->description,
            'is_billable' => $this->is_billable,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
        ], [
            'event_id' => ['required', 'exists:calendar_events,id'],
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

        $event = CalendarEvent::query()->findOrFail($validated['event_id']);

        $updatedEvent = $editor->update($event, [
            'client_id' => (int) $validated['client_id'],
            'project_id' => $validated['project_id'] !== '' ? (int) $validated['project_id'] : null,
            'feature_description' => $validated['feature_description'],
            'description' => $validated['description'] ?: null,
            'is_billable' => $validated['is_billable'],
            'starts_at' => $validated['starts_at'],
            'ends_at' => $validated['ends_at'],
        ]);

        PushCalendarEventToRemoteJob::dispatch($updatedEvent->id)->afterCommit();

        $this->success('Evenement reclasse et synchronisation distante planifiee.');
        $this->loadNextEvent();
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

        return CalendarEventTitleFormatter::format($client, $project, $this->feature_description ?: 'feature description');
    }

    #[Computed]
    public function currentEvent(): ?CalendarEvent
    {
        if ($this->event_id === null) {
            return null;
        }

        return CalendarEvent::query()
            ->with(['calendar.account.defaultClient:id,name,color', 'calendar:id,name,calendar_account_id', 'client:id,name,color', 'project:id,name'])
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
    public function queueCount(): int
    {
        return CalendarEvent::query()->needsReview()->count();
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

<div>
    <x-header title="File de revue" subtitle="Traitement unitaire des evenements mal formates detectes pendant la synchronisation." separator>
        <x-slot:actions>
            <x-badge :value="$this->queueCount.' restant(s)'" class="badge-warning" />
        </x-slot:actions>
    </x-header>

    <div class="grid gap-6 xl:grid-cols-[1.1fr_0.9fr]">
        <x-card title="Evenement courant" subtitle="Client obligatoire, projet conditionnel selon le client.">
            @if (! $this->currentEvent)
                <div class="rounded-box border border-dashed border-base-300 p-6 text-base-content/60">
                    Plus aucun evenement a traiter.
                </div>
            @else
                <div class="space-y-4">
                    <div class="rounded-box bg-base-200 p-4">
                        <div
                            class="-m-4 mb-0 rounded-box bg-base-200 p-4"
                            style="border-left: 4px solid {{ $this->currentEvent->client?->color ?? $this->currentEvent->calendar?->account?->defaultClient?->color ?? 'transparent' }};"
                        >
                        <p class="text-sm font-semibold">{{ $this->currentEvent->title }}</p>
                        @if ($this->currentEvent->client?->color)
                            <div class="mt-2 text-xs text-base-content/60">
                                <x-client-indicator :name="$this->currentEvent->client->name" :color="$this->currentEvent->client->color" />
                            </div>
                        @endif
                        @unless ($this->currentEvent->is_billable)
                            <div class="mt-2">
                                <x-badge value="non facturable" class="badge-ghost" />
                            </div>
                        @endunless
                        <p class="mt-1 text-sm text-base-content/60">
                            {{ $this->currentEvent->starts_at->translatedFormat('d M Y H:i') }} -> {{ $this->currentEvent->ends_at->translatedFormat('H:i') }}
                        </p>
                        @if ($this->currentEvent->description)
                            <p class="mt-3 text-sm leading-6 text-base-content/70">{{ $this->currentEvent->description }}</p>
                        @endif
                        </div>
                    </div>

                    <x-calendar-event-form-fields
                        :client-options="$this->clientOptions"
                        :project-options="$this->projectOptions"
                        :project-wire-key="'review-project-select-' . ($client_id ?: 'none')"
                        :project-required="$this->projectSelectionIsRequired()"
                        :project-disabled="$this->projectSelectionIsDisabled()"
                        :project-placeholder="$this->projectPlaceholder()"
                        :title-preview="$this->previewTitle()"
                        title-preview-label="Titre final"
                    />

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
