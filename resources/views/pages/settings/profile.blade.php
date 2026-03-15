<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $current_password = '';

    public bool $emailChanged = false;

    public function mount(): void
    {
        $user = auth()->user();
        $this->name = $user->name;
        $this->email = $user->email;
    }

    public function save(): void
    {
        $user = auth()->user();
        $emailChanging = $this->email !== $user->email;

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ];

        if ($emailChanging) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $this->validate($rules);

        $user->name = $this->name;

        if ($emailChanging) {
            $oldEmail = $user->email;
            $user->email = $this->email;
            $user->email_verified_at = null;
            $user->save();

            $user->sendEmailVerificationNotification();

            // Notify old email about the change
            Notification::route('mail', $oldEmail)
                ->notify(new \App\Notifications\EmailChangeNotification($oldEmail, $this->email));

            $this->emailChanged = true;
        } else {
            $user->save();
        }

        $this->current_password = '';

        if (! $emailChanging) {
            session()->flash('settings-saved', true);
        }
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Profile Settings</x-slot:title>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-white mb-1">Settings</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Manage your account settings and preferences.</p>

        <x-settings-tabs active="profile" />

        @volt('settings.profile')
        <div class="mt-6 max-w-lg">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">Profile</h2>

            @if (session('settings-saved'))
                <flux:callout variant="success" class="mb-4">
                    <flux:callout.text>Your profile has been updated.</flux:callout.text>
                </flux:callout>
            @endif

            @if ($emailChanged)
                <flux:callout variant="warning" class="mb-4">
                    <flux:callout.text>A verification link has been sent to your new email address. Your old email will remain active until the new one is verified.</flux:callout.text>
                </flux:callout>
            @endif

            <form wire:submit="save" class="space-y-6">
                <flux:input
                    wire:model="name"
                    label="Name"
                    type="text"
                    required
                />

                <flux:input
                    wire:model="email"
                    label="Email address"
                    type="email"
                    required
                />

                <flux:input
                    wire:model="current_password"
                    label="Current password"
                    type="password"
                    description="Required when changing email address."
                />

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    Save changes
                </flux:button>
            </form>
        </div>
        @endvolt
    </div>
</x-layouts.app>
