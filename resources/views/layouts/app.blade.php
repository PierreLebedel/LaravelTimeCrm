<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' - '.config('app.name') : config('app.name') }}</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen font-sans antialiased bg-base-200">
    <x-nav sticky class="lg:hidden">
        <x-slot:brand>
            <x-app-brand />
        </x-slot:brand>
        <x-slot:actions>
            <x-theme-toggle class="btn btn-circle btn-ghost btn-sm" />
            <label for="main-drawer" class="lg:hidden me-3">
                <x-svg name="tabler-menu-2" class="h-6 w-6 cursor-pointer" />
            </label>
        </x-slot:actions>
    </x-nav>

    <x-main>
        <x-slot:sidebar drawer="main-drawer" collapsible class="bg-base-100 lg:bg-inherit" collapse-text="Masquer">
            <x-app-brand class="px-5 pt-4" />

            <x-menu activate-by-route>
                <x-menu-item title="Calendrier" icon="tabler.calendar-week" link="{{ route('calendar') }}" exact />
                <x-menu-item title="Calendrier FC" icon="tabler.layout-grid" link="{{ route('calendar.fullcalendar') }}" />
                <x-menu-item title="Clients" icon="tabler.building-skyscraper" link="{{ route('clients') }}" />
                <x-menu-item title="Projets" icon="tabler.briefcase" link="{{ route('projects') }}" />
                <x-menu-item title="Agendas" icon="tabler.link" link="{{ route('calendars') }}" />
                <x-menu-item
                    title="Revue"
                    icon="tabler.alert-circle"
                    link="{{ route('review') }}"
                    :badge="($reviewCount ?? 0) > 0 ? (string) $reviewCount : null"
                    badge-classes="badge-warning"
                />
                <x-menu-item title="Analyse" icon="tabler.chart-bar" link="{{ route('reports') }}" />
                <x-menu-item title="Queue" icon="tabler.list-details" link="{{ route('queue') }}" />
            </x-menu>
        </x-slot:sidebar>

        <x-slot:content>
            @if (($queueSummary['running_count'] ?? 0) > 0 || ($queueSummary['pending_count'] ?? 0) > 0)
                <div class="mb-4">
                    <a href="{{ route('queue') }}" class="alert alert-info flex items-center justify-between gap-4">
                        <div class="flex items-center gap-3">
                            <x-svg name="tabler-loader-2" class="h-5 w-5" />
                            <span class="text-sm">
                                {{ $queueSummary['running_count'] }} job(s) en cours, {{ $queueSummary['pending_count'] }} en attente.
                            </span>
                        </div>
                        <span class="text-xs uppercase tracking-[0.2em]">Voir la file</span>
                    </a>
                </div>
            @endif

            {{ $slot }}
        </x-slot:content>
    </x-main>

    <x-toast position="toast-bottom toast-center" />
</body>
</html>
