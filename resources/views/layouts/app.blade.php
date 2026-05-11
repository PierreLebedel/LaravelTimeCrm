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
            <x-app-brand class="px-5 pt-5 pb-2" />

            <x-menu activate-by-route>
                <x-menu-item title="Calendrier" icon="tabler.calendar-week" link="{{ route('calendar') }}" exact />
                <x-menu-item title="Clients" icon="tabler.building-skyscraper" link="{{ route('clients') }}" />
                <x-menu-item title="Projets" icon="tabler.briefcase" link="{{ route('projects') }}" />
                <x-menu-item title="Agendas DAV" icon="tabler.link" link="{{ route('calendars') }}" />
                <livewire:review-menu-item />
                <x-menu-item title="Analyse et facturation" icon="tabler.chart-area-line" link="{{ route('reports') }}" />
                <x-menu-item title="Synchronisation" icon="tabler.list-details" link="{{ route('queue') }}" />
            </x-menu>

            <div class="ps-5.5">
                <x-theme-toggle darkTheme="dark" lightTheme="" />
            </div>
        </x-slot:sidebar>

        <x-slot:content>
            {{ $slot }}
        </x-slot:content>
    </x-main>

    <x-toast position="toast-bottom toast-center" />
</body>
</html>
