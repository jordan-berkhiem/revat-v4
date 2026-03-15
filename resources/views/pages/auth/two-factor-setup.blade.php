<?php

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $code = '';

    public string $secret = '';

    public string $qrCodeSvg = '';

    public array $recoveryCodes = [];

    public int $step = 1;

    public function mount(): void
    {
        $user = Auth::user();
        $service = app(TwoFactorService::class);

        // Generate a new secret if the user doesn't have one or hasn't confirmed
        if (! $user->hasTwoFactorEnabled()) {
            $this->secret = $service->generateSecret();
            $user->setTwoFactorSecret($this->secret);
            $this->qrCodeSvg = $service->getQrCodeSvg($user, config('app.name', 'Revat'));
        }
    }

    public function confirm(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = Auth::user();
        $service = app(TwoFactorService::class);

        if (! $service->enable($user, $this->code)) {
            $this->addError('code', 'The provided code is invalid. Please try again.');

            return;
        }

        // Generate recovery codes
        $codes = $service->generateRecoveryCodes();
        $user->two_factor_recovery_codes = json_encode($codes['hashed']);
        $user->save();

        $this->recoveryCodes = $codes['plain'];
        session()->put('2fa_verified', true);
        $this->step = 3;
    }

    public function finish(): void
    {
        $this->redirect(session()->pull('url.intended', route('dashboard', absolute: false)), navigate: true);
    }
}; ?>

<x-layouts.auth-card>
    <x-slot:title>Set Up Two-Factor Authentication</x-slot:title>

    @volt('auth.two-factor-setup')
    <div>
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Set Up Two-Factor Authentication</h1>

        @if(session('2fa_enforcement'))
            <flux:callout variant="warning" class="mt-4">
                <flux:callout.heading>{{ session('2fa_enforcement') }}</flux:callout.heading>
            </flux:callout>
        @endif

        @if($step === 1)
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                Scan this QR code with your authenticator app (e.g., Google Authenticator, Authy).
            </p>

            <div class="mt-6 flex justify-center">
                <div class="rounded-lg bg-white p-4">
                    {!! $qrCodeSvg !!}
                </div>
            </div>

            <div class="mt-4">
                <p class="text-xs text-zinc-500 dark:text-zinc-400 text-center">
                    Or enter this key manually:
                </p>
                <p class="mt-1 text-center font-mono text-sm text-zinc-700 dark:text-zinc-300 select-all break-all">
                    {{ $secret }}
                </p>
            </div>

            <div class="mt-6">
                <flux:button wire:click="$set('step', 2)" variant="primary" class="w-full">
                    Continue
                </flux:button>
            </div>
        @elseif($step === 2)
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                Enter the 6-digit code from your authenticator app to confirm setup.
            </p>

            <form wire:submit="confirm" class="mt-6 space-y-6">
                @if ($errors->has('code'))
                    <flux:callout variant="danger">
                        <flux:callout.heading>{{ $errors->first('code') }}</flux:callout.heading>
                    </flux:callout>
                @endif

                <flux:input
                    wire:model="code"
                    label="Verification code"
                    type="text"
                    maxlength="6"
                    placeholder="000000"
                    required
                    autofocus
                    autocomplete="one-time-code"
                    inputmode="numeric"
                />

                <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                    Verify and Enable
                </flux:button>
            </form>
        @elseif($step === 3)
            <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                Two-factor authentication has been enabled. Save your recovery codes below.
            </p>

            <flux:callout variant="warning" class="mt-4">
                <flux:callout.heading>Save these codes in a secure location.</flux:callout.heading>
                <flux:callout.text>Each code can only be used once. If you lose access to your authenticator app, you can use one of these codes to sign in.</flux:callout.text>
            </flux:callout>

            <pre class="mt-4 rounded-lg bg-zinc-100 dark:bg-zinc-900 p-4 font-mono text-sm text-zinc-700 dark:text-zinc-300">{{ implode("\n", $recoveryCodes) }}</pre>

            <div class="mt-4 flex gap-2">
                <flux:button
                    variant="ghost"
                    class="flex-1"
                    x-on:click="navigator.clipboard.writeText('{{ implode("\n", $recoveryCodes) }}').then(() => $flux.toast('Copied to clipboard'))"
                >
                    Copy to Clipboard
                </flux:button>
                <flux:button
                    variant="ghost"
                    class="flex-1"
                    x-on:click="
                        const blob = new Blob(['{{ implode("\\n", $recoveryCodes) }}'], { type: 'text/plain' });
                        const url = URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.href = url;
                        a.download = 'recovery-codes.txt';
                        a.click();
                        URL.revokeObjectURL(url);
                    "
                >
                    Download
                </flux:button>
            </div>

            <div class="mt-6">
                <flux:button wire:click="finish" variant="primary" class="w-full">
                    Done
                </flux:button>
            </div>
        @endif
    </div>
    @endvolt
</x-layouts.auth-card>
