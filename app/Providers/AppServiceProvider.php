<?php

namespace App\Providers;

use App\Support\QueueDashboard;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $view->with('queueSummary', app(QueueDashboard::class)->summary());
        });

        Queue::before(function (JobProcessing $event): void {
            $runningJobs = Cache::get('queue:running_jobs', []);
            $runningJobs[$event->job->uuid()] = [
                'uuid' => $event->job->uuid(),
                'name' => (string) ($event->job->payload()['displayName'] ?? $event->job->resolveName()),
                'queue' => $event->job->getQueue(),
                'started_at' => now()->toIso8601String(),
            ];

            Cache::forever('queue:running_jobs', $runningJobs);
        });

        Queue::after(function (JobProcessed $event): void {
            $this->forgetRunningJob($event->job->uuid());
        });

        Queue::failing(function (JobFailed $event): void {
            $this->forgetRunningJob($event->job->uuid());
        });
    }

    protected function forgetRunningJob(?string $uuid): void
    {
        if ($uuid === null) {
            return;
        }

        $runningJobs = Cache::get('queue:running_jobs', []);
        unset($runningJobs[$uuid]);

        Cache::forever('queue:running_jobs', $runningJobs);
    }
}
