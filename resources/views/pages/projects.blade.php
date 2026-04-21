<?php

use App\Models\Client;
use App\Models\Project;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Projets')] class extends Component
{
    use Toast;

    public bool $drawer = false;

    public ?int $editingProjectId = null;

    public string $client_id = '';

    public string $name = '';

    public string $description = '';

    public bool $is_active = true;

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public function mount(): void
    {
        $this->client_id = '';
    }

    public function save(): void
    {
        $validated = $this->validate([
            'client_id' => ['required', 'exists:clients,id'],
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('projects', 'name')
                    ->where(fn ($query) => $query->where('client_id', $this->client_id))
                    ->ignore($this->editingProjectId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['required', 'bool'],
        ]);

        $project = Project::query()->find($this->editingProjectId) ?? new Project();
        $project->fill($validated);
        $project->save();

        $this->resetForm();
        $this->success('Projet enregistré.');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->drawer = true;
    }

    public function edit(int $projectId): void
    {
        $project = Project::query()->findOrFail($projectId);

        $this->editingProjectId = $project->id;
        $this->client_id = (string) $project->client_id;
        $this->name = $project->name;
        $this->description = (string) $project->description;
        $this->is_active = $project->is_active;
        $this->drawer = true;
    }

    public function delete(int $projectId): void
    {
        try {
            Project::query()->findOrFail($projectId)->delete();
            $this->success('Projet supprimé.');
        } catch (QueryException) {
            $this->error('Ce projet est encore lié à des événements.');
        }
    }

    public function resetForm(): void
    {
        $this->reset('editingProjectId', 'name', 'description');
        $this->client_id = '';
        $this->is_active = true;
        $this->drawer = false;
        $this->resetErrorBag();
    }

    public function headers(): array
    {
        return [
            ['key' => 'client_name', 'label' => 'Client', 'sortable' => false],
            ['key' => 'name', 'label' => 'Projet'],
            ['key' => 'calendar_events_count', 'label' => 'Événements'],
        ];
    }

    #[Computed]
    public function clientOptions(): Collection
    {
        return Client::query()->orderBy('name')->get();
    }

    #[Computed]
    public function rows(): Collection
    {
        return Project::query()
            ->with('client:id,name,color')
            ->withCount('calendarEvents')
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->get()
            ->map(fn (Project $project) => [
                'id' => $project->id,
                'client_name' => $project->client->name,
                'client_color' => $project->client->color,
                'name' => $project->name,
                'calendar_events_count' => $project->calendar_events_count,
            ]);
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'rows' => $this->rows,
        ];
    }
};
?>

<div>
    <x-header title="Projets" subtitle="Un projet appartient à un client, mais reste facultatif sur un événement." separator>
        <x-slot:actions>
            <x-button label="Nouveau projet" icon="tabler.plus" class="btn-primary" wire:click="create" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$rows" :sort-by="$sortBy">
            @scope('cell_client_name', $project)
                <x-client-indicator :name="$project['client_name']" :color="$project['client_color']" />
            @endscope

            @scope('actions', $project)
                <div class="flex gap-2">
                    <x-button icon="tabler.pencil" class="btn-ghost btn-sm" wire:click="edit({{ $project['id'] }})" />
                    <x-button icon="tabler.trash" class="btn-ghost btn-sm text-error" wire:click="delete({{ $project['id'] }})" wire:confirm="Supprimer ce projet ?" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="drawer" title="{{ $editingProjectId ? 'Modifier le projet' : 'Nouveau projet' }}" right separator with-close-button class="w-full lg:w-1/3">
        <div class="space-y-4">
            <x-select
                label="Client"
                wire:model.live="client_id"
                :options="$this->clientOptions"
                placeholder="{{ config('crm.select_placeholder') }}"
            />
            <x-input label="Nom" wire:model.blur="name" required />
            <x-input label="Description" wire:model.blur="description" />
            <x-toggle label="Projet actif" wire:model="is_active" />
        </div>

        <x-slot:actions>
            <x-button label="Annuler" wire:click="resetForm" />
            <x-button label="Enregistrer" class="btn-primary" wire:click="save" spinner="save" />
        </x-slot:actions>
    </x-drawer>
</div>
