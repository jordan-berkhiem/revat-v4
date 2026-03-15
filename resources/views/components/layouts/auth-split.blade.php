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
    <div class="flex min-h-screen" data-testid="auth-split-layout">
        {{-- Brand Panel --}}
        <div class="hidden md:flex w-[45%] relative overflow-hidden bg-gradient-to-b from-[#0f2042] via-[#091428] to-[#060d1a]" data-testid="brand-panel">
            {{-- Radial blue overlay --}}
            <div class="absolute inset-0 bg-[radial-gradient(ellipse_at_top_left,rgba(37,99,235,0.15),transparent_60%)]"></div>

            <div class="relative flex flex-col items-center justify-center w-full p-12">
                <img class="h-10 w-auto mb-8" src="{{ asset('svg/Logo-Light.svg') }}" alt="Revat">

                <p class="text-white/70 text-lg text-center max-w-sm mb-12">
                    Marketing attribution that drives real results.
                </p>

                {{-- Decorative bar chart --}}
                <div class="flex items-end gap-2 h-32" data-testid="decorative-chart">
                    @php
                        $heights = [40, 55, 35, 70, 50, 85, 65, 95, 75];
                    @endphp
                    @foreach ($heights as $h)
                        <div class="w-4 rounded-t bg-blue-500/30" style="height: {{ $h }}%"></div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Form Panel --}}
        <div class="flex-1 flex items-center justify-center p-6 md:p-12" data-testid="form-panel">
            <div class="w-full max-w-[400px]">
                {{-- Mobile logo --}}
                <div class="md:hidden mb-8 flex justify-center">
                    <x-logo height="h-8" />
                </div>

                {{ $slot }}
            </div>
        </div>
    </div>

    @fluxScripts
</body>
</html>
