<?php

use App\Exceptions\InvalidInvitationException;
use App\Models\Invitation;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $invitationToken = '';

    public ?string $error = null;

    public ?string $organizationName = null;

    public ?string $roleName = null;

    public ?string $inviterName = null;

    public ?string $invitedEmail = null;

    public bool $isNewUser = false;

    public bool $isLoggedInUser = false;

    public bool $needsLogin = false;

    // Registration fields for new users
    public string $name = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function mount(string $token): void
    {
        $this->invitationToken = $token;
        $this->loadInvitation();
    }

    private function loadInvitation(): void
    {
        $tokenHash = hash('sha256', $this->invitationToken);
        $invitation = Invitation::where('token_hash', $tokenHash)->first();

        if (! $invitation) {
            $this->error = 'Invitation not found.';

            return;
        }

        if ($invitation->isAccepted()) {
            $this->error = 'This invitation has already been accepted.';

            return;
        }

        if ($invitation->isRevoked()) {
            $this->error = 'This invitation has been revoked.';

            return;
        }

        if ($invitation->isExpired()) {
            $this->error = 'This invitation has expired. Please request a new invitation.';

            return;
        }

        $this->organizationName = $invitation->organization->name;
        $this->roleName = $invitation->role;
        $this->inviterName = $invitation->invitedBy?->name;
        $this->invitedEmail = $invitation->email;

        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            if (Auth::check() && Auth::id() === $existingUser->id) {
                // Check if user already belongs to org
                if ($existingUser->organizations()->where('organizations.id', $invitation->organization_id)->exists()) {
                    $this->error = 'You already belong to this organization.';

                    return;
                }
                $this->isLoggedInUser = true;
            } else {
                $this->needsLogin = true;
            }
        } else {
            $this->isNewUser = true;
        }
    }

    public function acceptInvitation(): void
    {
        try {
            $service = app(InvitationService::class);
            $user = $service->accept($this->invitationToken);

            // Set the new organization as active
            $user->switchOrganization($user->organizations()->latest('organization_user.created_at')->first());

            $this->redirect(route('dashboard'), navigate: true);
        } catch (InvalidInvitationException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function registerAndAccept(): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed'],
        ]);

        // Create the user first
        $user = User::create([
            'name' => $this->name,
            'email' => $this->invitedEmail,
            'password' => $this->password,
        ]);

        // Now accept the invitation (will find the user we just created)
        try {
            $service = app(InvitationService::class);
            $service->accept($this->invitationToken);
        } catch (InvalidInvitationException $e) {
            $this->error = $e->getMessage();

            return;
        }

        Auth::login($user);
        session()->regenerate();

        // Set the new organization as active
        $user->switchOrganization($user->organizations()->latest('organization_user.created_at')->first());

        $this->redirect(route('dashboard'), navigate: true);
    }
}; ?>

<x-layouts.auth-card>
    <x-slot:title>Accept Invitation</x-slot:title>

    @volt('auth.accept-invitation')
    <div>
        @if ($error)
            <flux:callout variant="danger">
                <flux:callout.heading>{{ $error }}</flux:callout.heading>
            </flux:callout>
        @elseif ($organizationName)
            <div>
                <h2 class="text-xl font-bold text-zinc-900 dark:text-white">You've been invited</h2>
                <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
                    @if ($inviterName)
                        {{ $inviterName }} has invited you to join
                    @else
                        You've been invited to join
                    @endif
                    <strong>{{ $organizationName }}</strong> as a <strong>{{ $roleName }}</strong>.
                </p>
            </div>

            @if ($isLoggedInUser)
                <div class="mt-6">
                    <flux:button wire:click="acceptInvitation" variant="primary" class="w-full" wire:loading.attr="disabled">
                        Accept Invitation
                    </flux:button>
                </div>
            @elseif ($needsLogin)
                <div class="mt-6">
                    <p class="text-sm text-zinc-500 dark:text-zinc-400 mb-4">
                        You already have an account. Please log in to accept this invitation.
                    </p>
                    <a href="{{ route('login', ['redirect' => url()->current()]) }}" class="block">
                        <flux:button variant="primary" class="w-full">
                            Log in to accept
                        </flux:button>
                    </a>
                </div>
            @elseif ($isNewUser)
                <form wire:submit="registerAndAccept" class="mt-6 space-y-6">
                    <flux:input
                        wire:model="name"
                        label="Full name"
                        type="text"
                        placeholder="Your name"
                        required
                        autofocus
                    />

                    <flux:input
                        label="Email address"
                        type="email"
                        :value="$invitedEmail"
                        disabled
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
                        Create Account & Join
                    </flux:button>
                </form>
            @endif
        @endif
    </div>
    @endvolt
</x-layouts.auth-card>
