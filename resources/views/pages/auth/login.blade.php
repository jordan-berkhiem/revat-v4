<?php

use App\Exceptions\DeactivatedUserException;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $email = '';

    public string $password = '';

    public bool $remember = false;

    public function login(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        try {
            $result = Auth::attempt(
                ['email' => $this->email, 'password' => $this->password],
                $this->remember
            );
        } catch (DeactivatedUserException $e) {
            $this->addError('email', $e->getMessage());

            return;
        }

        if (! $result) {
            $this->addError('email', __('These credentials do not match our records.'));

            return;
        }

        session()->regenerate();

        $user = Auth::user();
        if ($user->hasTwoFactorEnabled()) {
            $this->redirect(route('two-factor.challenge', absolute: false), navigate: true);

            return;
        }

        $this->redirect(route('dashboard', absolute: false), navigate: true);
    }
}; ?>

<x-layouts.auth-split>
    <x-slot:title>Log In</x-slot:title>

    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Welcome back</h1>
        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
            Log in to your account to continue.
        </p>
    </div>

    @volt('auth.login')
    <div>
        <form wire:submit="login" class="mt-8 space-y-6">
            @if ($errors->has('email'))
                <flux:callout variant="danger">
                    <flux:callout.heading>{{ $errors->first('email') }}</flux:callout.heading>
                </flux:callout>
            @endif

            <flux:input
                wire:model="email"
                label="Email address"
                type="email"
                placeholder="you@example.com"
                required
                autofocus
            />

            <flux:input
                wire:model="password"
                label="Password"
                type="password"
                placeholder="Enter your password"
                required
            />

            <div class="flex items-center justify-between">
                <flux:checkbox wire:model="remember" label="Remember me" />
                <a href="{{ route('password.request') }}" class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400">
                    Forgot password?
                </a>
            </div>

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                Log in
            </flux:button>

            <p class="text-center text-sm text-zinc-500 dark:text-zinc-400">
                Don't have an account?
                <a href="{{ route('register') }}" class="text-blue-600 hover:text-blue-500 dark:text-blue-400">Register</a>
            </p>
        </form>
    </div>
    @endvolt
</x-layouts.auth-split>
