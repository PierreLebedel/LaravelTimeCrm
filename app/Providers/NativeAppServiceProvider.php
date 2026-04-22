<?php

namespace App\Providers;

use App\Jobs\SyncCalendarAccountJob;
use App\Models\CalendarAccount;
use App\Support\NativeAppEnvBootstrapper;
use Native\Desktop\Contracts\ProvidesPhpIni;
use Native\Desktop\Facades\Menu;
use Native\Desktop\Facades\Window;

class NativeAppServiceProvider implements ProvidesPhpIni
{
    /**
     * Executed once the native application has been booted.
     * Use this method to open windows, register global shortcuts, etc.
     */
    public function boot(): void
    {
        app(NativeAppEnvBootstrapper::class)->bootstrap();

        if (app()->runningUnitTests()) {
            return;
        }

        Menu::create();

        Window::open()
            ->maximized();

        CalendarAccount::query()
            ->where('is_active', true)
            ->pluck('id')
            ->each(fn (int $accountId) => SyncCalendarAccountJob::dispatch($accountId));
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
