<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title ?? config('app.name', 'Revat') }}</title>
    <link rel="icon" href="{{ asset('favicon.png') }}" type="image/png">
    <link rel="preload" href="{{ Vite::asset('resources/fonts/plus-jakarta-sans-latin.woff2') }}" as="font" type="font/woff2" crossorigin>
    <link rel="preload" href="{{ Vite::asset('resources/fonts/ibm-plex-mono-400.woff2') }}" as="font" type="font/woff2" crossorigin>
    @fluxAppearance
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-white dark:bg-zinc-800">
    <x-support.impersonation-banner />
    <flux:sidebar collapsible="mobile" sticky class="bg-zinc-50 dark:bg-zinc-900 border-e border-zinc-200 dark:border-zinc-700">
        <flux:sidebar.toggle class="lg:hidden" icon="x-mark" />

        <flux:sidebar.brand>
            <x-logo height="h-7" />
        </flux:sidebar.brand>

        <flux:sidebar.nav>
            <flux:sidebar.item icon="home" href="{{ route('dashboard') }}" :current="request()->routeIs('dashboard')" data-testid="nav-dashboard">Dashboard</flux:sidebar.item>
            <flux:sidebar.item icon="chart-bar" href="{{ route('reports') }}" :current="request()->routeIs('reports')" data-testid="nav-reports">Reports</flux:sidebar.item>
            <flux:sidebar.item icon="megaphone" href="{{ route('campaigns') }}" :current="request()->routeIs('campaigns')" data-testid="nav-campaigns">Campaigns</flux:sidebar.item>
            <flux:sidebar.item icon="arrow-path" href="{{ route('attribution') }}" :current="request()->routeIs('attribution')" data-testid="nav-attribution">Attribution</flux:sidebar.item>
            <flux:sidebar.item icon="puzzle-piece" href="{{ route('integrations') }}" :current="request()->routeIs('integrations')" data-testid="nav-integrations">Integrations</flux:sidebar.item>
            <flux:sidebar.item icon="cog-6-tooth" href="{{ route('settings.profile') }}" :current="request()->routeIs('settings.*')" data-testid="nav-settings">Settings</flux:sidebar.item>
        </flux:sidebar.nav>

        <flux:spacer />

        @auth
        <flux:sidebar.profile>
            <flux:sidebar.item icon="user-circle" href="#">
                {{ auth()->user()->name }}
            </flux:sidebar.item>
        </flux:sidebar.profile>
        @endauth
    </flux:sidebar>

    <flux:header class="lg:hidden">
        <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

        <flux:spacer />

        <flux:profile avatar="https://unavatar.io/github/placeholder" />
    </flux:header>

    {{-- Desktop Header --}}
    <flux:header class="hidden lg:flex items-center border-b border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 px-6 h-14" container>
        @auth
            @php
                $user = auth()->user();
                $currentOrg = $user->currentOrganization;
                $orgs = $user->organizations;
            @endphp

            {{-- Organization Switcher --}}
            <flux:dropdown data-testid="org-switcher">
                <flux:button variant="ghost" class="flex items-center gap-2">
                    <div class="size-6 rounded bg-gradient-to-br from-blue-500 to-violet-500 flex items-center justify-center text-white text-xs font-bold">
                        {{ $currentOrg ? strtoupper(substr($currentOrg->name, 0, 1)) : '?' }}
                    </div>
                    <span class="text-sm font-medium">{{ $currentOrg?->name ?? 'Select Organization' }}</span>
                    <flux:icon.chevron-down class="size-4" />
                </flux:button>

                <flux:menu>
                    @foreach ($orgs as $org)
                        <form method="POST" action="{{ route('switch-organization', $org) }}">
                            @csrf
                            <flux:menu.item type="submit">{{ $org->name }}</flux:menu.item>
                        </form>
                    @endforeach
                </flux:menu>
            </flux:dropdown>

            <span class="text-zinc-300 dark:text-zinc-600 mx-2">/</span>

            {{-- Workspace Switcher --}}
            @php
                $workspace = app(\App\Services\WorkspaceContext::class)->getWorkspace();
                $accessibleWorkspaceIds = $currentOrg ? $user->accessibleWorkspaceIds($currentOrg) : collect();
                $workspaces = $accessibleWorkspaceIds->isNotEmpty()
                    ? \App\Models\Workspace::whereIn('id', $accessibleWorkspaceIds)->get()
                    : collect();
            @endphp

            <flux:dropdown data-testid="workspace-switcher">
                <flux:button variant="ghost" class="flex items-center gap-2">
                    <span class="text-sm font-medium">{{ $workspace?->name ?? 'Select Workspace' }}</span>
                    <flux:icon.chevron-down class="size-4" />
                </flux:button>

                <flux:menu>
                    @foreach ($workspaces as $ws)
                        <form method="POST" action="{{ route('switch-workspace', $ws) }}">
                            @csrf
                            <flux:menu.item type="submit">{{ $ws->name }}</flux:menu.item>
                        </form>
                    @endforeach
                </flux:menu>
            </flux:dropdown>
        @endauth

        <flux:spacer />

        <div class="flex items-center gap-2" data-testid="header-actions">
            <div x-data class="flex items-center">
                <flux:button variant="ghost" icon="sun" class="dark:hidden" data-testid="appearance-toggle" x-on:click="$flux.appearance = 'dark'" />
                <flux:button variant="ghost" icon="moon" class="hidden dark:flex" data-testid="appearance-toggle-dark" x-on:click="$flux.appearance = 'light'" />
            </div>
            <flux:button variant="ghost" icon="bell" data-testid="notifications" />
            @auth
                <flux:dropdown data-testid="user-menu">
                    <flux:button variant="ghost" icon="user-circle" />
                    <flux:menu>
                        <flux:menu.item href="{{ route('settings.profile') }}">Profile</flux:menu.item>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <flux:menu.item type="submit">Sign out</flux:menu.item>
                        </form>
                    </flux:menu>
                </flux:dropdown>
            @endauth
        </div>
    </flux:header>

    <flux:main>
        {{ $slot }}
    </flux:main>

    @fluxScripts
</body>
</html>
