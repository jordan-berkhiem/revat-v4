<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Revat') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white dark:bg-zinc-900">
    <div class="flex flex-col min-h-screen" data-testid="onboarding-layout">
        {{-- Header --}}
        <header class="flex items-center justify-center p-6 border-b border-zinc-200 dark:border-zinc-700">
            <x-logo height="h-7" />
        </header>

        {{-- Content --}}
        <main class="flex-1 flex items-center justify-center p-6 md:p-12">
            <div class="w-full max-w-lg">
                {{ $slot }}
            </div>
        </main>
    </div>

    @fluxScripts
</body>
</html>
