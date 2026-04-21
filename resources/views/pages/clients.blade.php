<?php

use App\Enums\BillingMode;
use App\Models\Client;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Clients')] class extends Component
{
    use Toast;

    public bool $drawer = false;

    public ?int $editingClientId = null;

    public string $name = '';

    public string $color = '#2563eb';

    public string $billing_mode = 'hourly';

    public ?string $hourly_rate = null;

    public ?string $daily_rate = null;

    public bool $is_active = true;

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public function save(): void
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('clients', 'name')->ignore($this->editingClientId)],
            'color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'billing_mode' => ['required', Rule::enum(BillingMode::class)],
            'hourly_rate' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($this->billing_mode === BillingMode::Hourly->value)],
            'daily_rate' => ['nullable', 'numeric', 'min:0', Rule::requiredIf($this->billing_mode === BillingMode::Daily->value)],
            'is_active' => ['required', 'bool'],
        ]);

        $client = Client::query()->find($this->editingClientId) ?? new Client();
        $client->fill($validated);
        $client->save();

        $this->resetForm();
        $this->success('Client enregistré.');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->drawer = true;
    }

    public function edit(int $clientId): void
    {
        $client = Client::query()->findOrFail($clientId);

        $this->editingClientId = $client->id;
        $this->name = $client->name;
        $this->color = $client->color ?? '#2563eb';
        $this->billing_mode = $client->billing_mode->value;
        $this->hourly_rate = $client->hourly_rate;
        $this->daily_rate = $client->daily_rate;
        $this->is_active = $client->is_active;
        $this->drawer = true;
    }

    public function delete(int $clientId): void
    {
        try {
            Client::query()->findOrFail($clientId)->delete();
            $this->success('Client supprimé.');
        } catch (QueryException) {
            $this->error('Ce client est encore lié à des projets ou à des événements.');
        }
    }

    public function resetForm(): void
    {
        $this->reset('editingClientId', 'name', 'hourly_rate', 'daily_rate');
        $this->color = '#2563eb';
        $this->billing_mode = BillingMode::Hourly->value;
        $this->is_active = true;
        $this->drawer = false;
        $this->resetErrorBag();
    }

    public function headers(): array
    {
        return [
            ['key' => 'color', 'label' => '', 'sortable' => false],
            ['key' => 'name', 'label' => 'Client'],
            ['key' => 'billing_label', 'label' => 'Facturation', 'sortable' => false],
            ['key' => 'projects_count', 'label' => 'Projets'],
            ['key' => 'calendar_events_count', 'label' => 'Événements'],
        ];
    }

    #[Computed]
    public function rows(): Collection
    {
        return Client::query()
            ->withCount(['projects', 'calendarEvents'])
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->get()
            ->map(fn (Client $client) => [
                'id' => $client->id,
                'color' => $client->color ?? '#2563eb',
                'name' => $client->name,
                'billing_label' => $client->billing_mode === BillingMode::Daily
                    ? number_format((float) $client->daily_rate, 2, ',', ' ').' € / jour'
                    : number_format((float) $client->hourly_rate, 2, ',', ' ').' € / h',
                'projects_count' => $client->projects_count,
                'calendar_events_count' => $client->calendar_events_count,
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
    <x-header title="Clients" subtitle="Référentiel de facturation pour piloter temps et coûts." separator>
        <x-slot:actions>
            <x-button label="Nouveau client" icon="tabler.plus" class="btn-primary" wire:click="create" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$rows" :sort-by="$sortBy">
            @scope('cell_color', $client)
                <span class="inline-flex size-4 rounded-full border border-base-300" style="background-color: {{ $client['color'] }}"></span>
            @endscope

            @scope('actions', $client)
                <div class="flex gap-2">
                    <x-button icon="tabler.pencil" class="btn-ghost btn-sm" wire:click="edit({{ $client['id'] }})" />
                    <x-button icon="tabler.trash" class="btn-ghost btn-sm text-error" wire:click="delete({{ $client['id'] }})" wire:confirm="Supprimer ce client ?" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <x-drawer wire:model="drawer" title="{{ $editingClientId ? 'Modifier le client' : 'Nouveau client' }}" right separator with-close-button class="w-full lg:w-1/3">
        <div class="space-y-4">
            <x-input label="Nom" wire:model.blur="name" required />
            <x-input label="Couleur" wire:model.live="color" type="color" required />
            <x-select
                label="Mode de facturation"
                wire:model.live="billing_mode"
                :options="collect([
                    ['id' => 'hourly', 'name' => 'Taux horaire'],
                    ['id' => 'daily', 'name' => 'Taux journalier'],
                ])"
            />

            @if ($billing_mode === 'hourly')
                <x-input label="Tarif horaire" wire:model.blur="hourly_rate" type="number" step="0.01" prefix="€" required />
            @else
                <x-input label="Tarif journalier" wire:model.blur="daily_rate" type="number" step="0.01" prefix="€" required />
            @endif

            <x-toggle label="Client actif" wire:model="is_active" />
        </div>

        <x-slot:actions>
            <x-button label="Annuler" wire:click="resetForm" />
            <x-button label="Enregistrer" class="btn-primary" wire:click="save" spinner="save" />
        </x-slot:actions>
    </x-drawer>
</div>
