<?php

use App\Support\QueueDashboard;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Queue')] class extends Component
{
    use Toast;

    public function retryFailed(string $uuid): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);

        unset($this->failedJobs, $this->pendingJobs, $this->summary);

        $this->success('Job relance dans la queue.');
    }

    public function forgetFailed(string $uuid): void
    {
        Artisan::call('queue:forget', ['id' => $uuid]);

        unset($this->failedJobs, $this->summary);

        $this->success('Job supprime de la liste des echecs.');
    }

    #[Computed]
    public function summary(): array
    {
        return app(QueueDashboard::class)->summary();
    }

    #[Computed]
    public function pendingJobs(): Collection
    {
        return app(QueueDashboard::class)->pendingJobs();
    }

    #[Computed]
    public function runningJobs(): Collection
    {
        return app(QueueDashboard::class)->runningJobs();
    }

    #[Computed]
    public function failedJobs(): Collection
    {
        return app(QueueDashboard::class)->failedJobs();
    }
};
?>

<div>
    <x-header title="Synchronisations" subtitle="Suivez le traitement des tâches de synchronisation avec vos agendas" separator />

    <div class="mb-6 grid gap-6 md:grid-cols-3">
        <x-stat
            title="En attente"
            value="{{ $this->summary['pending_count'] }}"
            icon="tabler.progress-help"
            color="text-info" />

        <x-stat
            title="En cours"
            value="{{ $this->summary['running_count'] }}"
            icon="tabler.progress-bolt"
            color="text-warning" />

        <x-stat
            title="Echecs"
            value="{{ $this->summary['failed_count'] }}"
            icon="tabler.progress-x"
            color="text-error" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3 items-start">
        <x-card title="Tâches en cours">
            <div class="space-y-3">
                @forelse ($this->runningJobs as $job)
                    <div class="rounded-box bg-base-200 p-4" wire:key="running-job-{{ $job['uuid'] }}">
                        <p class="text-sm font-semibold">{{ $job['name'] }}</p>
                        <p class="mt-1 text-xs text-base-content/60">Démarre à {{ $job['started_at'] }}</p>
                    </div>
                @empty
                    <x-alert title="Aucune tâche active" icon="tabler.ban" class="" />
                @endforelse
            </div>
        </x-card>

        <x-card title="Tâches en attente">
            <div class="space-y-3">
                @forelse ($this->pendingJobs as $job)
                    <div class="rounded-box bg-base-200 p-4" wire:key="pending-job-{{ $job['id'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold">{{ $job['name'] }}</p>
                                <p class="mt-1 text-xs text-base-content/60">Disponible à {{ $job['available_at'] }}</p>
                            </div>

                            @if ($job['reserved'])
                                <x-badge value="reserve" class="badge-info" />
                            @endif
                        </div>
                    </div>
                @empty
                    <x-alert title="Aucune tâche en attente" icon="tabler.ban" class="" />
                @endforelse
            </div>
        </x-card>

        <x-card title="Tâches échouées">
            <div class="space-y-3">
                @forelse ($this->failedJobs as $job)
                    <div class="rounded-box bg-base-200 p-4 overflow-hidden" wire:key="failed-job-{{ $job['uuid'] }}">
                        <p class="text-sm font-semibold">{{ $job['name'] }}</p>
                        <p class="mt-1 text-xs text-base-content/60">Echouée à {{ $job['failed_at'] }}</p>
                        <p class="mt-2 text-xs text-error">{{ $job['exception'] }}</p>

                        <div class="mt-3 flex gap-2">
                            <x-button label="Relancer" class="btn-sm btn-primary" wire:click="retryFailed('{{ $job['uuid'] }}')" />
                            <x-button label="Oublier" class="btn-sm" wire:click="forgetFailed('{{ $job['uuid'] }}')" wire:confirm="Supprimer ce job echoue ?" />
                        </div>
                    </div>
                @empty
                    <x-alert title="Aucune tâche échouée" icon="tabler.ban" class="" />
                @endforelse
            </div>
        </x-card>
    </div>
</div>
