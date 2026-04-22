<?php

use App\Jobs\SyncCalendarAccountJob;
use App\Models\Calendar;
use App\Models\CalendarAccount;
use App\Models\Client;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Agendas')] class extends Component
{
    use Toast;

    public bool $drawer = false;

    public ?int $editingAccountId = null;

    public string $name = '';

    public string $base_url = '';

    public string $username = '';

    public string $password = '';

    public string $default_client_id = '';

    public bool $is_active = true;

    public array $sortBy = ['column' => 'name', 'direction' => 'asc'];

    public function save(): void
    {
        $validated = validator([
            'name' => $this->name,
            'base_url' => $this->base_url,
            'username' => $this->username,
            'password' => $this->password,
            'default_client_id' => $this->default_client_id !== '' ? $this->default_client_id : null,
            'is_active' => $this->is_active,
        ], [
            'name' => ['required', 'string', 'max:255'],
            'base_url' => ['required', 'url', 'max:255'],
            'username' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:3'],
            'default_client_id' => ['nullable', 'exists:clients,id'],
            'is_active' => ['required', 'bool'],
        ])->validate();

        $account = CalendarAccount::query()->find($this->editingAccountId) ?? new CalendarAccount();
        $account->fill($validated);
        $account->save();

        $this->resetForm();
        $this->success('Compte DAV enregistre.');
    }

    public function create(): void
    {
        $this->resetForm();
        $this->drawer = true;
    }

    public function edit(int $accountId): void
    {
        $account = CalendarAccount::query()->findOrFail($accountId);

        $this->editingAccountId = $account->id;
        $this->name = $account->name;
        $this->base_url = $account->base_url;
        $this->username = $account->username;
        $this->password = $account->password;
        $this->default_client_id = (string) ($account->default_client_id ?? '');
        $this->is_active = $account->is_active;
        $this->drawer = true;
    }

    public function syncAccount(int $accountId): void
    {
        CalendarAccount::query()->findOrFail($accountId);

        SyncCalendarAccountJob::dispatch($accountId);

        $this->success('Synchronisation planifiee dans la file de jobs.');
    }

    public function delete(int $accountId): void
    {
        try {
            CalendarAccount::query()->findOrFail($accountId)->delete();
            $this->success('Compte DAV supprime.');
        } catch (QueryException) {
            $this->error('Ce compte possede encore des agendas synchronises.');
        }
    }

    public function toggleCalendar(int $calendarId): void
    {
        $calendar = Calendar::query()->findOrFail($calendarId);
        $calendar->forceFill([
            'is_selected' => ! $calendar->is_selected,
        ])->save();

        unset($this->accounts);

        $this->success($calendar->is_selected ? 'Agenda active pour la synchronisation.' : 'Agenda exclu de la synchronisation.');
    }

    public function resetForm(): void
    {
        $this->reset('editingAccountId', 'name', 'base_url', 'username', 'password', 'default_client_id');
        $this->is_active = true;
        $this->drawer = false;
        $this->resetErrorBag();
    }

    public function headers(): array
    {
        return [
            ['key' => 'name', 'label' => 'Compte'],
            ['key' => 'default_client_name', 'label' => 'Client par defaut', 'sortable' => false],
            ['key' => 'base_url', 'label' => 'URL', 'sortable' => false],
            ['key' => 'calendars_count', 'label' => 'Agendas'],
            ['key' => 'last_synced_at', 'label' => 'Derniere synchro'],
        ];
    }

    #[Computed]
    public function rows(): Collection
    {
        return CalendarAccount::query()
            ->with('defaultClient:id,name,color')
            ->withCount('calendars')
            ->orderBy($this->sortBy['column'], $this->sortBy['direction'])
            ->get()
            ->map(fn (CalendarAccount $account) => [
                'id' => $account->id,
                'name' => $account->name,
                'default_client_name' => $account->defaultClient?->name ?? 'Aucun',
                'default_client_color' => $account->defaultClient?->color,
                'base_url' => $account->base_url,
                'calendars_count' => $account->calendars_count,
                'last_synced_at' => $account->last_synced_at?->format('d/m/Y H:i') ?? 'Jamais',
            ]);
    }

    #[Computed]
    public function accounts(): Collection
    {
        return CalendarAccount::query()
            ->with([
                'defaultClient:id,name,color',
                'calendars' => fn ($query) => $query->orderBy('name'),
            ])
            ->orderBy('name')
            ->get();
    }

    #[Computed]
    public function clientOptions(): Collection
    {
        return Client::query()->orderBy('name')->get(['id', 'name', 'color']);
    }

    public function with(): array
    {
        return [
            'headers' => $this->headers(),
            'rows' => $this->rows,
            'accounts' => $this->accounts,
        ];
    }
};
?>

<div>
    <x-header title="Agendas DAV" subtitle="Connexion, decouverte des calendriers et synchronisation CalDAV locale." separator>
        <x-slot:actions>
            <x-button label="Nouveau compte" icon="tabler.plus" class="btn-primary" wire:click="create" />
        </x-slot:actions>
    </x-header>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$rows" :sort-by="$sortBy">
            @scope('cell_default_client_name', $account)
                @if ($account['default_client_name'] === 'Aucun')
                    <span>{{ $account['default_client_name'] }}</span>
                @else
                    <x-client-indicator :name="$account['default_client_name']" :color="$account['default_client_color']" />
                @endif
            @endscope

            @scope('actions', $account)
                <div class="flex gap-2">
                    <x-button
                        icon="tabler.refresh"
                        class="btn-ghost btn-sm"
                        wire:click="syncAccount({{ $account['id'] }})"
                        wire:loading.attr="disabled"
                        wire:target="syncAccount({{ $account['id'] }})"
                    />
                    <x-button icon="tabler.pencil" class="btn-ghost btn-sm" wire:click="edit({{ $account['id'] }})" />
                    <x-button icon="tabler.trash" class="btn-ghost btn-sm text-error" wire:click="delete({{ $account['id'] }})" wire:confirm="Supprimer ce compte ?" />
                </div>
            @endscope
        </x-table>
    </x-card>

    <div class="mt-6 space-y-4">
        @foreach ($accounts as $account)
            <x-card title="{{ $account->name }}" subtitle="Selection agenda par agenda pour la synchro." shadow>
                @if ($account->defaultClient)
                    <div class="mb-4 flex items-center gap-3 rounded-box bg-base-200 p-3 text-sm">
                        <span>Client par defaut :</span>
                        <x-client-indicator :name="$account->defaultClient->name" :color="$account->defaultClient->color" />
                    </div>
                @endif

                <div class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                    @forelse ($account->calendars as $calendar)
                        <button
                            type="button"
                            class="rounded-box border p-4 text-left transition {{ $calendar->is_selected ? 'border-success/40 bg-success/5' : 'border-base-300 bg-base-200/60' }}"
                            wire:key="calendar-toggle-{{ $calendar->id }}"
                            wire:click="toggleCalendar({{ $calendar->id }})"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <p class="text-sm font-semibold">{{ $calendar->name }}</p>
                                    <p class="mt-1 text-xs text-base-content/60">{{ $calendar->timezone ?: 'Fuseau non renseigne' }}</p>
                                </div>

                                <x-badge :value="$calendar->is_selected ? 'actif' : 'ignore'" class="{{ $calendar->is_selected ? 'badge-success' : 'badge-ghost' }}" />
                            </div>
                        </button>
                    @empty
                        <div class="rounded-box border border-dashed border-base-300 p-4 text-sm text-base-content/50">
                            Aucun agenda decouvert pour ce compte.
                        </div>
                    @endforelse
                </div>
            </x-card>
        @endforeach
    </div>

    <x-drawer wire:model="drawer" title="{{ $editingAccountId ? 'Modifier le compte DAV' : 'Nouveau compte DAV' }}" right separator with-close-button class="w-full lg:w-1/3">
        <div class="space-y-4">
            <x-input label="Nom" wire:model.blur="name" required />
            <x-input label="URL DAV" wire:model.blur="base_url" required />
            <x-input label="Identifiant" wire:model.blur="username" required />
            <x-password label="Mot de passe / token" wire:model.blur="password" clearable required />
            <x-select
                label="Client par defaut"
                wire:model.live="default_client_id"
                :options="$this->clientOptions"
                placeholder="{{ config('crm.select_placeholder') }}"
            />
            <x-toggle label="Compte actif" wire:model="is_active" />
        </div>

        <x-slot:actions>
            <x-button label="Annuler" wire:click="resetForm" />
            <x-button label="Enregistrer" class="btn-primary" wire:click="save" spinner="save" />
        </x-slot:actions>
    </x-drawer>
</div>
