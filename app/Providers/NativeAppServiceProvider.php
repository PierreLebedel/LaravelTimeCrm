<?php

namespace App\Providers;

use App\Jobs\SyncCalendarAccountJob;
use App\Models\CalendarAccount;
use App\Support\QueueWorkerManager;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\MenuBar;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        Window::open()
            ->maximized();

        MenuBar::hide();

        if (app()->runningUnitTests()) {
            return;
        }

        CalendarAccount::query()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $accountId) => SyncCalendarAccountJob::dispatch($accountId));

        //app(QueueWorkerManager::class)->ensureRunning();
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
