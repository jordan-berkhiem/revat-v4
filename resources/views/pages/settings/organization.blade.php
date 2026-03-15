<?php

use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $timezone = 'UTC';

    public function mount(): void
    {
        $org = auth()->user()->currentOrganization;
        $this->name = $org->name;
        $this->timezone = $org->timezone ?? 'UTC';
    }

    public function save(): void
    {
        $org = auth()->user()->currentOrganization;

        $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:organizations,name,' . $org->id],
            'timezone' => ['required', 'string', 'timezone:all'],
        ]);

        $org->update([
            'name' => $this->name,
            'timezone' => $this->timezone,
        ]);

        session()->flash('settings-saved', true);
    }

    public function getTimezonesProperty(): array
    {
        $timezones = timezone_identifiers_list();
        $result = ['UTC'];

        foreach ($timezones as $tz) {
            if ($tz === 'UTC') {
                continue;
            }
            $parts = explode('/', $tz, 2);
            $region = $parts[0];
            if (in_array($region, ['Africa', 'America', 'Antarctica', 'Arctic', 'Asia', 'Atlantic', 'Australia', 'Europe', 'Indian', 'Pacific'])) {
                $result[] = $tz;
            }
        }

        return $result;
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Organization Settings</x-slot:title>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-white mb-1">Settings</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Manage your account settings and preferences.</p>

        <x-settings-tabs active="organization" />

        @volt('settings.organization')
        <div class="mt-6 max-w-lg">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">Organization</h2>

            @if (session('settings-saved'))
                <flux:callout variant="success" class="mb-4">
                    <flux:callout.text>Organization settings have been updated.</flux:callout.text>
                </flux:callout>
            @endif

            <form wire:submit="save" class="space-y-6">
                <flux:input
                    wire:model="name"
                    label="Organization name"
                    type="text"
                    required
                />

                <flux:select wire:model="timezone" label="Timezone" searchable>
                    @foreach ($this->timezones as $tz)
                        <flux:select.option value="{{ $tz }}">{{ str_replace('_', ' ', $tz) }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:button type="submit" variant="primary" wire:loading.attr="disabled">
                    Save changes
                </flux:button>
            </form>
        </div>
        @endvolt
    </div>
</x-layouts.app>
