<?php

use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;

new class extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function save(): void
    {
        $this->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed'],
        ]);

        auth()->user()->update([
            'password' => $this->password,
        ]);

        $this->reset('current_password', 'password', 'password_confirmation');

        session()->flash('settings-saved', true);
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Password Settings</x-slot:title>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-white mb-1">Settings</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Manage your account settings and preferences.</p>

        <x-settings-tabs active="password" />

        @volt('settings.password')
        <div class="mt-6 max-w-lg">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">Password</h2>

            @if (session('settings-saved'))
                <flux:callout variant="success" class="mb-4">
                    <flux:callout.text>Your password has been updated.</flux:callout.text>
                </flux:callout>
            @endif

            <form wire:submit="save" class="space-y-6">
                <flux:input
                    wire:model="current_password"
                    label="Current password"
                    type="password"
                    required
                />

                <flux:input
                    wire:model="password"
                    label="New password"
                    type="password"
                    placeholder="Enter a new password"
                    required
                />

                <flux:input
                    wire:model="password_confirmation"
                    label="Confirm new password"
                    type="password"
                    required
                />

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    Update password
                </flux:button>
            </form>
        </div>
        @endvolt
    </div>
</x-layouts.app>
