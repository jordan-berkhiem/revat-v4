<?php

use App\Services\TwoFactorService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Component;

new class extends Component
{
    public string $password = '';

    public array $newRecoveryCodes = [];

    public bool $showRecoveryCodes = false;

    public function disableTwoFactor(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        $user = Auth::user();

        if (! Hash::check($this->password, $user->password)) {
            $this->addError('password', 'The provided password is incorrect.');

            return;
        }

        $service = app(TwoFactorService::class);
        $service->disable($user);
        $user->two_factor_recovery_codes = null;
        $user->save();

        // Clear 2fa_verified from session
        session()->forget('2fa_verified');

        $this->password = '';
        $this->dispatch('$refresh');
    }

    public function regenerateRecoveryCodes(): void
    {
        $this->validate([
            'password' => ['required', 'string'],
        ]);

        $user = Auth::user();

        if (! Hash::check($this->password, $user->password)) {
            $this->addError('password', 'The provided password is incorrect.');

            return;
        }

        $service = app(TwoFactorService::class);
        $codes = $service->generateRecoveryCodes();
        $user->two_factor_recovery_codes = json_encode($codes['hashed']);
        $user->save();

        $this->newRecoveryCodes = $codes['plain'];
        $this->showRecoveryCodes = true;
        $this->password = '';

        // Clear 2fa_verified from session — forces re-verification
        session()->forget('2fa_verified');
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Account Security</x-slot:title>

    @volt('settings.security')
    <div class="max-w-2xl mx-auto">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Account Security</h1>

        <div class="mt-8 space-y-8">
            <div class="rounded-lg border border-zinc-200 dark:border-zinc-700 p-6">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Two-Factor Authentication</h2>

                @if(auth()->user()->hasTwoFactorEnabled())
                    <div class="mt-4">
                        <p class="text-sm text-green-600 dark:text-green-400 font-medium">
                            Enabled since {{ auth()->user()->two_factor_confirmed_at->format('M j, Y') }}
                        </p>
                    </div>

                    @if($showRecoveryCodes && count($newRecoveryCodes) > 0)
                        <div class="mt-4">
                            <flux:callout variant="warning">
                                <flux:callout.heading>New recovery codes generated.</flux:callout.heading>
                                <flux:callout.text>Save these codes in a secure location. Each code can only be used once.</flux:callout.text>
                            </flux:callout>

                            <pre class="mt-4 rounded-lg bg-zinc-100 dark:bg-zinc-900 p-4 font-mono text-sm text-zinc-700 dark:text-zinc-300">{{ implode("\n", $newRecoveryCodes) }}</pre>
                        </div>
                    @endif

                    <div class="mt-6 space-y-4">
                        @if ($errors->any())
                            <flux:callout variant="danger">
                                <flux:callout.heading>{{ $errors->first() }}</flux:callout.heading>
                            </flux:callout>
                        @endif

                        <flux:input
                            wire:model="password"
                            label="Current password"
                            type="password"
                            placeholder="Enter your current password"
                        />

                        <div class="flex gap-3">
                            <flux:button wire:click="regenerateRecoveryCodes" variant="ghost" wire:loading.attr="disabled">
                                Regenerate Recovery Codes
                            </flux:button>

                            <flux:button wire:click="disableTwoFactor" variant="danger" wire:loading.attr="disabled">
                                Disable 2FA
                            </flux:button>
                        </div>
                    </div>
                @else
                    <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                        Add an extra layer of security to your account by enabling two-factor authentication.
                    </p>
                    <div class="mt-4">
                        <flux:button href="{{ route('two-factor.setup') }}" variant="primary">
                            Enable Two-Factor Authentication
                        </flux:button>
                    </div>
                @endif
            </div>
        </div>
    </div>
    @endvolt
</x-layouts.app>
