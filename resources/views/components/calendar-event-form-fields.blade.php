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
    'titlePreviewLabel' => 'Titre distant',
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

    <x-input label="Titre" wire:model.blur="feature_description" required />
    <x-textarea label="Description detaillee" wire:model.blur="description" rows="5" />
    <x-checkbox label="Facturable" wire:model="is_billable" />

    <div class="grid gap-4 md:grid-cols-2">
        <x-input type="datetime-local" label="Debut" wire:model.blur="starts_at" step="900" required />
        <x-input type="datetime-local" label="Fin" wire:model.blur="ends_at" step="900" required />
    </div>

    <div class="rounded-box bg-primary/10 p-4 text-sm">
        <p class="font-semibold text-primary">{{ $titlePreviewLabel }}</p>
        <p class="mt-2 font-mono text-xs">{{ $titlePreview }}</p>
    </div>
</div>
