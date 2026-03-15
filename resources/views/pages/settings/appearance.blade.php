<?php

use Livewire\Volt\Component;

new class extends Component
{
    //
}; ?>

<x-layouts.app>
    <x-slot:title>Appearance Settings</x-slot:title>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-white mb-1">Settings</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Manage your account settings and preferences.</p>

        <x-settings-tabs active="appearance" />

        @volt('settings.appearance')
        <div class="mt-6 max-w-lg">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">Appearance</h2>
            <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-6">Choose how Revat looks to you. Select a theme below.</p>

            <div x-data class="grid grid-cols-3 gap-4">
                {{-- Light --}}
                <button
                    x-on:click="$flux.appearance = 'light'"
                    class="group relative flex flex-col items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 hover:border-blue-500 transition-colors"
                    data-testid="theme-light"
                >
                    <div class="w-full aspect-[4/3] rounded-lg bg-white border border-zinc-200 flex items-center justify-center">
                        <flux:icon.sun class="size-6 text-yellow-500" />
                    </div>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Light</span>
                </button>

                {{-- Dark --}}
                <button
                    x-on:click="$flux.appearance = 'dark'"
                    class="group relative flex flex-col items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 hover:border-blue-500 transition-colors"
                    data-testid="theme-dark"
                >
                    <div class="w-full aspect-[4/3] rounded-lg bg-zinc-800 border border-zinc-700 flex items-center justify-center">
                        <flux:icon.moon class="size-6 text-blue-400" />
                    </div>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Dark</span>
                </button>

                {{-- System --}}
                <button
                    x-on:click="$flux.appearance = 'system'"
                    class="group relative flex flex-col items-center gap-3 rounded-xl border border-zinc-200 dark:border-zinc-700 p-4 hover:border-blue-500 transition-colors"
                    data-testid="theme-system"
                >
                    <div class="w-full aspect-[4/3] rounded-lg bg-gradient-to-r from-white to-zinc-800 border border-zinc-200 dark:border-zinc-700 flex items-center justify-center">
                        <flux:icon.computer-desktop class="size-6 text-zinc-500" />
                    </div>
                    <span class="text-sm font-medium text-zinc-700 dark:text-zinc-300">System</span>
                </button>
            </div>
        </div>
        @endvolt
    </div>
</x-layouts.app>
