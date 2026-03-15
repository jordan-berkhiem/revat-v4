<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $sent = false;

    public function resend(): void
    {
        Auth::user()->sendEmailVerificationNotification();

        $this->sent = true;
    }
}; ?>

<x-layouts.auth-simple>
    <x-slot:title>Verify Email</x-slot:title>

    @volt('auth.verify-email')
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Verify your email</h1>
        <p class="mt-4 text-sm text-zinc-500 dark:text-zinc-400">
            We've sent a verification link to your email address. Please check your inbox and click the link to verify your account.
        </p>

        @if ($sent)
            <div class="mt-6">
                <flux:callout variant="success">
                    <flux:callout.heading>A new verification link has been sent to your email address.</flux:callout.heading>
                </flux:callout>
            </div>
        @endif

        <div class="mt-6">
            <flux:button wire:click="resend" variant="primary" class="w-full" wire:loading.attr="disabled">
                Resend verification email
            </flux:button>
        </div>
    </div>
    @endvolt
</x-layouts.auth-simple>
