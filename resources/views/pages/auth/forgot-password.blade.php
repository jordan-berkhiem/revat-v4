<?php

use Illuminate\Support\Facades\Password;
use Livewire\Volt\Component;

new class extends Component
{
    public string $email = '';

    public ?string $status = null;

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
        ]);

        Password::sendResetLink(['email' => $this->email]);

        // Account enumeration prevention: always show the same success message
        $this->status = __('If an account with that email exists, we\'ve sent a password reset link.');
    }
}; ?>

<x-layouts.auth-card>
    <x-slot:title>Forgot Password</x-slot:title>

    @volt('auth.forgot-password')
    <div>
        <div>
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">Forgot your password?</h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                Enter your email address and we'll send you a reset link.
            </p>
        </div>

        @if ($status)
            <div class="mt-6">
                <flux:callout variant="success">
                    <flux:callout.heading>{{ $status }}</flux:callout.heading>
                </flux:callout>
            </div>
        @endif

        <form wire:submit="sendResetLink" class="mt-6 space-y-6">
            <flux:input
                wire:model="email"
                label="Email address"
                type="email"
                placeholder="you@example.com"
                required
                autofocus
            />

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                Send reset link
            </flux:button>

            <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
                <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">Back to login</a>
            </p>
        </form>
    </div>
    @endvolt
</x-layouts.auth-card>
