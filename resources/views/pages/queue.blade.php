<?php

use App\Support\QueueDashboard;
use App\Support\QueueWorkerManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;
use Mary\Traits\Toast;

new #[Title('Queue')] class extends Component
{
    use Toast;

    public function processQueue(QueueWorkerManager $queueWorkerManager): void
    {
        $queueWorkerManager->ensureRunning();

        $this->success('Worker de queue lance.');
    }

    public function retryFailed(string $uuid, QueueWorkerManager $queueWorkerManager): void
    {
        Artisan::call('queue:retry', ['id' => [$uuid]]);
        $queueWorkerManager->ensureRunning();

        unset($this->failedJobs, $this->pendingJobs, $this->summary);

        $this->success('Job relance.');
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
    <x-header title="Queue de jobs" subtitle="Suivi des synchronisations et reecritures distantes executees en arriere-plan." separator>
        <x-slot:actions>
            <x-button label="Traiter la file" icon="tabler.player-play" class="btn-primary" wire:click="processQueue" spinner="processQueue" />
        </x-slot:actions>
    </x-header>

    <div class="mb-6 grid gap-4 md:grid-cols-4">
        <x-card title="En attente">
            <p class="text-3xl font-bold">{{ $this->summary['pending_count'] }}</p>
        </x-card>

        <x-card title="Reserves">
            <p class="text-3xl font-bold">{{ $this->summary['reserved_count'] }}</p>
        </x-card>

        <x-card title="En cours">
            <p class="text-3xl font-bold">{{ $this->summary['running_count'] }}</p>
        </x-card>

        <x-card title="Echecs">
            <p class="text-3xl font-bold">{{ $this->summary['failed_count'] }}</p>
        </x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <x-card title="Jobs en cours">
            <div class="space-y-3">
                @forelse ($this->runningJobs as $job)
                    <div class="rounded-box bg-base-200 p-4" wire:key="running-job-{{ $job['uuid'] }}">
                        <p class="text-sm font-semibold">{{ $job['name'] }}</p>
                        <p class="mt-1 text-xs text-base-content/60">{{ $job['queue'] }} · demarre a {{ $job['started_at'] }}</p>
                    </div>
                @empty
                    <div class="rounded-box border border-dashed border-base-300 p-4 text-sm text-base-content/50">
                        Aucun job actif.
                    </div>
                @endforelse
            </div>
        </x-card>

        <x-card title="Jobs en attente">
            <div class="space-y-3">
                @forelse ($this->pendingJobs as $job)
                    <div class="rounded-box bg-base-200 p-4" wire:key="pending-job-{{ $job['id'] }}">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-semibold">{{ $job['name'] }}</p>
                                <p class="mt-1 text-xs text-base-content/60">{{ $job['queue'] }} · dispo a {{ $job['available_at'] }}</p>
                            </div>

                            @if ($job['reserved'])
                                <x-badge value="reserve" class="badge-info" />
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="rounded-box border border-dashed border-base-300 p-4 text-sm text-base-content/50">
                        Aucun job en attente.
                    </div>
                @endforelse
            </div>
        </x-card>

        <x-card title="Jobs echoues">
            <div class="space-y-3">
                @forelse ($this->failedJobs as $job)
                    <div class="rounded-box bg-base-200 p-4" wire:key="failed-job-{{ $job['uuid'] }}">
                        <p class="text-sm font-semibold">{{ $job['name'] }}</p>
                        <p class="mt-1 text-xs text-base-content/60">{{ $job['queue'] }} · {{ $job['failed_at'] }}</p>
                        <p class="mt-2 text-xs text-error">{{ $job['exception'] }}</p>

                        <div class="mt-3 flex gap-2">
                            <x-button label="Relancer" class="btn-sm btn-primary" wire:click="retryFailed('{{ $job['uuid'] }}')" />
                            <x-button label="Oublier" class="btn-sm" wire:click="forgetFailed('{{ $job['uuid'] }}')" wire:confirm="Supprimer ce job echoue ?" />
                        </div>
                    </div>
                @empty
                    <div class="rounded-box border border-dashed border-base-300 p-4 text-sm text-base-content/50">
                        Aucun job echoue.
                    </div>
                @endforelse
            </div>
        </x-card>
    </div>
</div>
