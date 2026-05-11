<?php

use App\Enums\CalendarEventFormatStatus;
use App\Models\CalendarEvent;
use App\Support\DurationFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

new #[Title('Analyse')] class extends Component
{
    #[Url(history: true)]
    public string $from = '';

    #[Url(history: true)]
    public string $to = '';

    #[Url(as: 'group', history: true)]
    public string $groupBy = 'client';

    public array $sortBy = ['column' => 'label', 'direction' => 'asc'];

    public function mount(): void
    {
        $this->from = $this->from ?: CarbonImmutable::now()->startOfMonth()->toDateString();
        $this->to = $this->to ?: CarbonImmutable::now()->endOfMonth()->toDateString();
    }

    public function headers(): array
    {
        return [
            ['key' => 'label', 'label' => 'Libellé'],
            ['key' => 'events', 'label' => 'Événements'],
            ['key' => 'hours', 'label' => 'Temps'],
            ['key' => 'cost', 'label' => 'Coût (€)'],
        ];
    }

    #[Computed]
    public function rows(): Collection
    {
        $events = CalendarEvent::query()
            ->with(['client:id,name,billing_mode,hourly_rate,daily_rate', 'project:id,name'])
            ->whereBetween('starts_at', [
                CarbonImmutable::parse($this->from)->startOfDay(),
                CarbonImmutable::parse($this->to)->endOfDay(),
            ])
            ->where('format_status', CalendarEventFormatStatus::Formatted)
            ->where('is_billable', true)
            ->orderBy('starts_at')
            ->get();

        return (match ($this->groupBy) {
            'project' => $events->groupBy(fn ($event) => $event->project?->name ?? null),
            'client_project' => $events->groupBy(fn ($event) => $event->client->name.($event->project ? '/'.$event->project->name : '')),
            default => $events->groupBy(fn ($event) => $event->client->name),
        })->map(function ($group, string $label) {
            $minutes = $group->sum(fn ($event) => $event->durationInMinutes());
            $cost = $group->sum(fn ($event) => $event->client->calculateCostInEuros($event->durationInMinutes()));
            $firstEvent = $group->first();

            return [
                'label' => $label,
                'color' => $firstEvent?->client?->color,
                'events' => $group->count(),
                'minutes' => $minutes,
                'hours' => round($minutes / 60, 2),
                'cost' => round($cost, 2),
            ];
        })->sortBy([
            [$this->sortBy['column'], $this->sortBy['direction']],
        ])->values();
    }

    #[Computed]
    public function totals(): array
    {
        return [
            'events' => $this->rows->sum('events'),
            'minutes' => $this->rows->sum('minutes'),
            'cost' => round($this->rows->sum('cost'), 2),
        ];
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
    <x-header title="Analyse et facturation" subtitle="" />

    <div class="mb-6 grid gap-4">
        <x-card shadow class="pt-2">
            <div class="grid gap-4 md:grid-cols-3">
                <x-input label="Du" type="date" wire:model.live="from" />
                <x-input label="Au" type="date" wire:model.live="to" />
                <x-select
                    label="Regroupement"
                    wire:model.live="groupBy"
                    :options="collect([
                        ['id' => 'client', 'name' => 'Par client'],
                        ['id' => 'project', 'name' => 'Par projet'],
                        ['id' => 'client_project', 'name' => 'Client / projet'],
                    ])"
                />
            </div>
        </x-card>

    </div>

    <div class="grid gap-6 md:grid-cols-3 mb-6">

        <x-stat
            title="Evénements"
            value="{{ $this->totals['events'] }}"
            icon="tabler.calendar"
            color="text-primary" />

        <x-stat
            title="Temps"
            value="{{ DurationFormatter::formatMinutes($this->totals['minutes']) }}"
            icon="tabler.clock"
            color="text-primary" />

        <x-stat
            title="Facturation"
            value="{{ number_format($this->totals['cost'], 2, ',', ' ') }} €"
            icon="tabler.receipt-euro"
            color="text-primary" />
    </div>

    <x-card shadow class="p-0!">
        <x-table :headers="$headers" :rows="$rows" :sort-by="$sortBy">
            @scope('cell_label', $row)
                <x-client-indicator :name="$row['label']" :color="$row['color']" />
            @endscope
            @scope('cell_hours', $row)
                {{ DurationFormatter::formatMinutes($row['minutes']) }}
            @endscope
        </x-table>
    </x-card>
</div>
