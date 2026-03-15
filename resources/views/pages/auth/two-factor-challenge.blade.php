<?php

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $code = '';

    public string $recoveryCode = '';

    public bool $useRecovery = false;

    public function verify(): void
    {
        if ($this->useRecovery) {
            $this->verifyRecoveryCode();

            return;
        }

        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = Auth::user();
        $service = app(TwoFactorService::class);

        if (! $service->verifyCode($user, $this->code)) {
            $this->addError('code', 'The provided two-factor code is invalid.');

            return;
        }

        session()->put('2fa_verified', true);
        session()->regenerate();

        $this->redirect(session()->pull('url.intended', route('dashboard', absolute: false)), navigate: true);
    }

    public function verifyRecoveryCode(): void
    {
        $this->validate([
            'recoveryCode' => ['required', 'string'],
        ]);

        $user = Auth::user();
        $service = app(TwoFactorService::class);

        if (! $service->verifyRecoveryCode($user, $this->recoveryCode)) {
            $this->addError('recoveryCode', 'The provided recovery code is invalid.');

            return;
        }

        session()->put('2fa_verified', true);
        session()->regenerate();

        $this->redirect(session()->pull('url.intended', route('dashboard', absolute: false)), navigate: true);
    }

    public function toggleRecovery(): void
    {
        $this->useRecovery = ! $this->useRecovery;
        $this->resetErrorBag();
    }
}; ?>

<x-layouts.auth-card>
    <x-slot:title>Two-Factor Authentication</x-slot:title>

    @volt('auth.two-factor-challenge')
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Two-Factor Authentication</h1>
        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
            @if($useRecovery)
                Enter one of your recovery codes to continue.
            @else
                Enter the 6-digit code from your authenticator app.
            @endif
        </p>

        <form wire:submit="verify" class="mt-6 space-y-6">
            @if(!$useRecovery)
                @if ($errors->has('code'))
                    <flux:callout variant="danger">
                        <flux:callout.heading>{{ $errors->first('code') }}</flux:callout.heading>
                    </flux:callout>
                @endif

                <flux:input
                    wire:model="code"
                    label="Authentication code"
                    type="text"
                    maxlength="6"
                    placeholder="000000"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    inputmode="numeric"
                />
            @else
                @if ($errors->has('recoveryCode'))
                    <flux:callout variant="danger">
                        <flux:callout.heading>{{ $errors->first('recoveryCode') }}</flux:callout.heading>
                    </flux:callout>
                @endif

                <flux:input
                    wire:model="recoveryCode"
                    label="Recovery code"
                    type="text"
                    placeholder="XXXX-XXXX"
                    required
                    autofocus
                />
            @endif

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                Verify
            </flux:button>

            <div class="text-center">
                <button type="button" wire:click="toggleRecovery" class="text-sm text-blue-600 hover:text-blue-500 dark:text-blue-400">
                    @if($useRecovery)
                        Use authenticator code instead
                    @else
                        Use a recovery code instead
                    @endif
                </button>
            </div>
        </form>
    </div>
    @endvolt
</x-layouts.auth-card>
