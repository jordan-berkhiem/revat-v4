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
<body class="min-h-screen bg-zinc-50 dark:bg-zinc-900 flex items-center justify-center p-6">
    <div class="w-full max-w-md" data-testid="auth-card-layout">
        <div class="flex justify-center mb-8">
            <x-logo height="h-8" />
        </div>

        <div class="bg-white dark:bg-zinc-800 rounded-xl shadow-sm border border-zinc-200 dark:border-zinc-700 p-8">
            {{ $slot }}
        </div>
    </div>

    @fluxScripts
</body>
</html>
