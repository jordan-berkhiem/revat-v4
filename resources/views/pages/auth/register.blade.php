<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $registered = false;

    public function register(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['required', 'confirmed'],
        ]);

        // Account enumeration prevention: if email exists, show success but don't create
        $existing = User::where('email', $this->email)->first();

        if ($existing) {
            $this->registered = true;

            return;
        }

        $user = User::create([
            'name' => $this->name,
            'email' => $this->email,
            'password' => $this->password,
        ]);

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<x-layouts.auth-split>
    <x-slot:title>Register</x-slot:title>

    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Create your account</h1>
        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
            Get started with Revat in minutes.
        </p>
    </div>

    @volt('auth.register')
    <div>
        @if ($registered)
            <div class="mt-8">
                <flux:callout variant="success">
                    <flux:callout.heading>Check your email</flux:callout.heading>
                    <flux:callout.text>We've sent a confirmation link to your email address. Please check your inbox to continue.</flux:callout.text>
                </flux:callout>
            </div>
        @else
            <form wire:submit="register" class="mt-8 space-y-6">
                <flux:input
                    wire:model="name"
                    label="Full name"
                    type="text"
                    placeholder="Your name"
                    required
                    autofocus
                />

                <flux:input
                    wire:model="email"
                    label="Email address"
                    type="email"
                    placeholder="you@example.com"
                    required
                />

                <flux:input
                    wire:model="password"
                    label="Password"
                    type="password"
                    placeholder="Enter a password"
                    required
                />

                <flux:input
                    wire:model="password_confirmation"
                    label="Confirm password"
                    type="password"
                    placeholder="Confirm your password"
                    required
                />

                <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                    Create account
                </flux:button>

                <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
                    Already have an account?
                    <a href="{{ route('login') }}" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">Log in</a>
                </p>
            </form>
        @endif
    </div>
    @endvolt
</x-layouts.auth-split>
