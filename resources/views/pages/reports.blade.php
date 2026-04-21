<?php

use App\Enums\CalendarEventFormatStatus;
use App\Models\CalendarEvent;
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
            ['key' => 'hours', 'label' => 'Temps (h)'],
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
            'hours' => round($this->rows->sum('hours'), 2),
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
    <x-header title="Analyse temps et coûts" subtitle="Synthèse sur période avec regroupement par client, projet ou client/projet." separator />

    <div class="mb-6 grid gap-4 xl:grid-cols-[1.1fr_0.9fr]">
        <x-card title="Filtres">
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

        <div class="grid gap-4 md:grid-cols-3">
            <x-card title="Événements">
                <p class="text-3xl font-bold">{{ $this->totals['events'] }}</p>
            </x-card>
            <x-card title="Temps">
                <p class="text-3xl font-bold">{{ number_format($this->totals['hours'], 2, ',', ' ') }} h</p>
            </x-card>
            <x-card title="Coût">
                <p class="text-3xl font-bold text-primary">{{ number_format($this->totals['cost'], 2, ',', ' ') }} €</p>
            </x-card>
        </div>
    </div>

    <x-card shadow>
        <x-table :headers="$headers" :rows="$rows" :sort-by="$sortBy">
            @scope('cell_label', $row)
                <x-client-indicator :name="$row['label']" :color="$row['color']" />
            @endscope
        </x-table>
    </x-card>
</div>
