<?php

namespace App\Providers;

use App\Jobs\SyncCalendarAccountJob;
use App\Models\CalendarAccount;
use App\Support\QueueWorkerManager;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(QueueWorkerManager $queueWorkerManager): void
    {
        Window::open();

        if (app()->runningUnitTests()) {
            return;
        }

        CalendarAccount::query()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $accountId) => SyncCalendarAccountJob::dispatch($accountId));

        $queueWorkerManager->ensureRunning();
    }

    /**
     * Return an array of php.ini directives to be set.
     */
    public function phpIni(): array
    {
        return [
        ];
    }
}
