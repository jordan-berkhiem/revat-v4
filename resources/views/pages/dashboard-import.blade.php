<x-layouts.app>
    <x-slot:title>Import Dashboard</x-slot:title>

    <div class="max-w-xl mx-auto py-12">
        <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 rounded-xl p-6">
            <h1 class="text-xl font-bold text-slate-900 dark:text-white mb-2">Import Dashboard</h1>

            <div class="mb-4">
                <p class="text-sm text-slate-600 dark:text-slate-300">
                    <span class="font-medium text-slate-900 dark:text-white">{{ $export->name }}</span>
                </p>
                @if ($export->description)
                    <p class="text-sm text-slate-500 dark:text-slate-400 mt-1">{{ $export->description }}</p>
                @endif
                <p class="text-xs text-slate-400 mt-2">{{ $export->widget_count }} widget(s)</p>
            </div>

            @auth
                @can('integrate')
                    <form method="POST" action="{{ route('dashboard.import', $export->token) }}">
                        @csrf
                        <flux:button type="submit" variant="primary">Import to My Workspace</flux:button>
                    </form>
                @else
                    <p class="text-sm text-slate-500">You need the integrate permission to import dashboards.</p>
                @endcan
            @else
                <p class="text-sm text-slate-500">
                    <a href="{{ route('login') }}" class="text-blue-600 hover:underline">Log in</a> to import this dashboard.
                </p>
            @endauth
        </div>
    </div>
</x-layouts.app>
