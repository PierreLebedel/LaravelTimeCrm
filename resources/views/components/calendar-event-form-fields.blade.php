@props([
    'showCalendarSelect' => false,
    'calendarOptions' => collect(),
    'clientOptions' => collect(),
    'projectOptions' => collect(),
    'projectWireKey' => 'calendar-event-project-select',
    'projectRequired' => false,
    'projectDisabled' => false,
    'projectPlaceholder' => null,
    'titlePreview' => null,
])

<div class="space-y-4">
    @if ($showCalendarSelect)
        <x-select
            label="Agenda"
            wire:model.live="calendar_id"
            :options="$calendarOptions"
            placeholder="{{ config('crm.select_placeholder') }}"
            required
        />
    @endif

    <div class="grid gap-4 md:grid-cols-2">
        <x-select
            label="Client"
            wire:model.live="client_id"
            :options="$clientOptions"
            placeholder="{{ config('crm.select_placeholder') }}"
            required
        />

        <div wire:key="{{ $projectWireKey }}">
            <x-select
                label="Projet"
                wire:model.live="project_id"
                :options="$projectOptions"
                placeholder="{{ $projectPlaceholder ?? config('crm.select_placeholder') }}"
                :required="$projectRequired"
                :disabled="$projectDisabled"
                wire:loading.attr="disabled"
                wire:target="client_id,project_id"
            />
        </div>
    </div>

    <x-input label="Titre" wire:model.live.blur="feature_description" required />
    <x-textarea label="Description détaillée" wire:model.live.blur="description" rows="5" />
    <x-checkbox label="Facturable" wire:model="is_billable" />

    <div class="grid gap-4 md:grid-cols-2">
        <x-input type="datetime-local" label="Début" wire:model.live.blur="starts_at" step="900" required />
        <x-input type="datetime-local" label="Fin" wire:model.live.blur="ends_at" step="900" required />
    </div>

    <x-alert icon="tabler.calendar-event" class="alert-info">
        <div>
            <div class="font-bold">{{ $titlePreview }}</div>
            @if($this->currentEvent)
            <div class="text-xs">
                @if ($this->currentEvent->client)
                Client : {{ $this->currentEvent->client->name }}<br />
                @endif

                {{ $this->currentEvent->starts_at->translatedFormat('d M Y H:i') }} -> {{ $this->currentEvent->ends_at->translatedFormat('H:i') }}<br />

                {{ $this->currentEvent->description }}
            </div>
            @endif
        </div>
    </x-alert>
</div>
