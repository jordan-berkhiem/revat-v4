<?php

use App\Services\AuditService;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $supportAccessEnabled = false;

    public function mount(): void
    {
        $this->supportAccessEnabled = auth()->user()->currentOrganization->support_access_enabled;
    }

    public function toggleSupportAccess(): void
    {
        $org = auth()->user()->currentOrganization;
        $oldValue = $org->support_access_enabled;
        $newValue = ! $oldValue;

        $org->toggleSupportAccess($newValue);
        $this->supportAccessEnabled = $newValue;

        AuditService::log(
            action: 'organization.support_access_toggled',
            organizationId: $org->id,
            resourceType: 'organization',
            resourceId: $org->id,
            metadata: [
                'enabled' => $newValue,
                'old_value' => $oldValue,
                'new_value' => $newValue,
            ],
        );
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Support Access</x-slot:title>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-white mb-1">Settings</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Manage your account settings and preferences.</p>

        <x-settings-tabs active="support-access" />

        @volt('settings.support-access')
        <div class="mt-6 max-w-lg">
            <h2 class="text-lg font-semibold text-zinc-900 dark:text-white mb-4">Support Access</h2>

            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 p-6">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-sm font-semibold text-zinc-900 dark:text-white">Allow support access</h3>
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            When enabled, Revat support agents can view your organization data to help resolve issues.
                            They cannot modify your data without your explicit permission.
                        </p>

                        <div class="mt-3 flex items-center gap-2">
                            <div class="size-2 rounded-full {{ $supportAccessEnabled ? 'bg-green-500' : 'bg-zinc-400' }}"></div>
                            <span class="text-sm {{ $supportAccessEnabled ? 'text-green-600 dark:text-green-400' : 'text-zinc-500 dark:text-zinc-400' }}">
                                {{ $supportAccessEnabled ? 'Enabled' : 'Disabled' }}
                            </span>
                        </div>
                    </div>

                    <flux:switch
                        wire:model.live="supportAccessEnabled"
                        wire:click="toggleSupportAccess"
                    />
                </div>
            </div>
        </div>
        @endvolt
    </div>
</x-layouts.app>
