<?php

use App\Models\User;
use App\Models\Workspace;
use Livewire\Volt\Component;
use Spatie\Permission\PermissionRegistrar;

new class extends Component
{
    public Workspace $workspace;

    public bool $showAddForm = false;

    public ?int $selectedUserId = null;

    public function mount(Workspace $workspace): void
    {
        $this->workspace = $workspace;

        // Verify workspace belongs to current org
        $org = auth()->user()->currentOrganization;
        if ($workspace->organization_id !== $org->id) {
            abort(403);
        }
    }

    public function getWorkspaceUsers(): \Illuminate\Database\Eloquent\Collection
    {
        return $this->workspace->users()->get();
    }

    public function getAvailableUsers(): \Illuminate\Support\Collection
    {
        $org = auth()->user()->currentOrganization;
        $existingIds = $this->workspace->users()->pluck('users.id');

        return $org->users()->whereNotIn('users.id', $existingIds)->get();
    }

    public function addUser(): void
    {
        if (! $this->selectedUserId) {
            return;
        }

        $org = auth()->user()->currentOrganization;
        $user = User::findOrFail($this->selectedUserId);

        // Validate user belongs to the organization
        if (! $org->users()->where('users.id', $user->id)->exists()) {
            $this->addError('selectedUserId', 'This user is not a member of the organization.');

            return;
        }

        $this->workspace->users()->attach($user->id);
        $this->selectedUserId = null;
        $this->showAddForm = false;
    }

    public function removeUser(int $userId): void
    {
        $this->workspace->users()->detach($userId);
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Workspace Users</x-slot:title>

    <div class="max-w-4xl mx-auto">
        <h1 class="text-xl font-bold text-zinc-900 dark:text-white mb-1">Settings</h1>
        <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">Manage your account settings and preferences.</p>

        <x-settings-tabs active="workspaces" />

        @volt('settings.workspaces.users')
        <div class="mt-6">
            {{-- Header --}}
            <div class="flex justify-between items-center mb-4">
                <div>
                    <h2 class="text-[17px] font-semibold text-zinc-900 dark:text-white">{{ $workspace->name }} — Users</h2>
                    <a href="{{ route('settings.workspaces') }}" class="text-sm text-blue-600 hover:text-blue-500">&larr; Back to workspaces</a>
                </div>
                <flux:button wire:click="$set('showAddForm', true)" variant="primary" size="sm" icon="plus">
                    Add User
                </flux:button>
            </div>

            {{-- Add User Form --}}
            @if ($showAddForm)
                <div class="mb-4 p-4 bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl">
                    <form wire:submit="addUser" class="flex items-end gap-3">
                        <div class="flex-1">
                            <flux:select wire:model="selectedUserId" label="Select user" placeholder="Choose a member...">
                                @foreach ($this->getAvailableUsers() as $user)
                                    <flux:select.option value="{{ $user->id }}">{{ $user->name }} ({{ $user->email }})</flux:select.option>
                                @endforeach
                            </flux:select>
                        </div>
                        <flux:button type="submit" variant="primary" size="sm">Add</flux:button>
                        <flux:button wire:click="$set('showAddForm', false)" variant="ghost" size="sm">Cancel</flux:button>
                    </form>
                </div>
            @endif

            {{-- Users Table --}}
            <div class="bg-white dark:bg-zinc-800 border border-zinc-200 dark:border-zinc-700 rounded-xl overflow-hidden">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>Name</flux:table.column>
                        <flux:table.column>Email</flux:table.column>
                        <flux:table.column>Role</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>

                    <flux:table.rows>
                        @foreach ($this->getWorkspaceUsers() as $user)
                            @php
                                app(\Spatie\Permission\PermissionRegistrar::class)->setPermissionsTeamId(auth()->user()->current_organization_id);
                                $user->unsetRelation('roles');
                                $userRole = $user->roles->first()?->name ?? 'viewer';
                            @endphp
                            <flux:table.row>
                                <flux:table.cell>
                                    <div class="flex items-center gap-2.5">
                                        <x-user-avatar :user="$user" />
                                        <span class="text-sm font-medium text-zinc-900 dark:text-white">{{ $user->name }}</span>
                                    </div>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <span class="text-sm text-zinc-500 dark:text-zinc-400">{{ $user->email }}</span>
                                </flux:table.cell>
                                <flux:table.cell>
                                    <x-role-badge :role="$userRole" />
                                </flux:table.cell>
                                <flux:table.cell>
                                    <flux:button wire:click="removeUser({{ $user->id }})" variant="ghost" size="xs" icon="x-mark" />
                                </flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </div>
        @endvolt
    </div>
</x-layouts.app>
