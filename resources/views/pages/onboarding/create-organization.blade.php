<?php

use App\Services\OrganizationSetupService;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $timezone = 'UTC';

    public function createOrganization(OrganizationSetupService $service): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255', 'unique:organizations,name'],
            'timezone' => ['required', 'string', 'timezone:all'],
        ]);

        $service->setup(auth()->user(), [
            'name' => $this->name,
            'timezone' => $this->timezone,
        ]);

        $this->redirect(route('dashboard', absolute: false), navigate: true);
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

<x-layouts.onboarding>
    <x-slot:title>Create Organization</x-slot:title>

    <div class="text-center mb-8">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Create your organization</h1>
        <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
            Set up your organization to get started with Revat.
        </p>
    </div>

    @volt('onboarding.create-organization')
    <div>
        <form wire:submit="createOrganization" class="space-y-6">
            <flux:input
                wire:model="name"
                label="Organization name"
                type="text"
                placeholder="Your company or team name"
                required
                autofocus
            />

            <flux:select wire:model="timezone" label="Timezone" searchable>
                @foreach ($this->timezones as $tz)
                    <flux:select.option value="{{ $tz }}">{{ str_replace('_', ' ', $tz) }}</flux:select.option>
                @endforeach
            </flux:select>

            <flux:button type="submit" variant="primary" class="w-full" wire:loading.attr="disabled">
                Create Organization
            </flux:button>
        </form>
    </div>
    @endvolt
</x-layouts.onboarding>
