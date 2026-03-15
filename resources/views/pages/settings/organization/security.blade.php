<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $require2fa = false;

    public function mount(): void
    {
        $org = Auth::user()->currentOrganization;
        $this->require2fa = $org?->require_2fa ?? false;
    }

    public function toggleRequire2fa(): void
    {
        $user = Auth::user();
        $org = $user->currentOrganization;

        // Gate check: only owner and admin roles
        if (! $user->hasRole(['owner', 'admin'])) {
            abort(403, 'You do not have permission to manage organization security settings.');
        }

        $org->require_2fa = $this->require2fa;
        $org->save();
    }

    public function getTwoFactorStatsProperty(): array
    {
        $org = Auth::user()->currentOrganization;
        if (! $org) {
            return ['enabled' => 0, 'total' => 0];
        }

        $users = $org->users;
        $total = $users->count();
        $enabled = $users->filter(fn ($u) => $u->hasTwoFactorEnabled())->count();

        return ['enabled' => $enabled, 'total' => $total];
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Organization Security</x-slot:title>

    @volt('settings.organization.security')
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Organization Security</h1>

        <div class="mt-8 rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Two-Factor Authentication</h2>

            <div class="mt-4 flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-zinc-700 dark:text-zinc-300">
                        Require two-factor authentication for all members
                    </p>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                        {{ $this->twoFactorStats['enabled'] }} of {{ $this->twoFactorStats['total'] }} members have 2FA enabled
                    </p>
                </div>

                <flux:switch
                    wire:model.live="require2fa"
                    wire:change="toggleRequire2fa"
                />
            </div>
        </div>
    </div>
    @endvolt
</x-layouts.app>
