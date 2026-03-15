<?php

use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Livewire\Volt\Component;

new class extends Component
{
    public string $token = '';

    public string $email = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token = ''): void
    {
        $this->token = $token ?: request()->route('token', '');
        $this->email = request()->query('email', '');
    }

    public function resetPassword(): void
    {
        $this->validate([
            'token' => ['required'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed'],
        ]);

        $status = Password::reset(
            [
                'email' => $this->email,
                'password' => $this->password,
                'password_confirmation' => $this->password_confirmation,
                'token' => $this->token,
            ],
            function ($user, string $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            session()->flash('status', __($status));
            $this->redirect(route('login'), navigate: true);

            return;
        }

        $this->addError('email', __($status));
    }
}; ?>

<x-layouts.auth-card>
    <x-slot:title>Reset Password</x-slot:title>

    @volt('auth.reset-password')
    <div>
        <div>
            <h2 class="text-xl font-bold text-zinc-900 dark:text-white">Reset your password</h2>
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                Enter your new password below.
            </p>
        </div>

        <form wire:submit="resetPassword" class="mt-6 space-y-6">
            @if ($errors->has('email'))
                <flux:callout variant="danger">
                    <flux:callout.heading>{{ $errors->first('email') }}</flux:callout.heading>
                </flux:callout>
            @endif

            <flux:input
                wire:model="email"
                label="Email address"
                type="email"
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
                placeholder="Confirm your password"
                required
            />

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                Reset password
            </flux:button>
        </form>
    </div>
    @endvolt
</x-layouts.auth-card>
