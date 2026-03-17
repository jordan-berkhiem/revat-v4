<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\InvitationService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Volt\Volt;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    Http::fake([
        'api.pwnedpasswords.com/*' => Http::response('', 200),
    ]);

    foreach (['owner', 'admin', 'editor', 'viewer'] as $role) {
        Role::findOrCreate($role, 'web');
    }

    // Clear rate limiter to prevent throttle state leaking between tests
    Cache::flush();
});

function createTestInvitation(
    ?Organization $org = null,
    string $email = 'invited@example.com',
    string $role = 'editor',
    ?User $invitedBy = null,
): array {
    $org = $org ?? Organization::create(['name' => 'Test Organization']);
    $service = app(InvitationService::class);

    if ($invitedBy) {
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        if ($role === 'owner') {
            $invitedBy->assignRole('owner');
        }
    }

    $invitation = $service->create($org, $email, $role, $invitedBy);

    return [$invitation, $invitation->plaintext_token, $org];
}

it('renders invitation accept page for new user', function () {
    [$invitation, $token, $org] = createTestInvitation();

    $this->get(route('invitations.accept', $token))
        ->assertOk()
        ->assertSee("You've been invited", false)
        ->assertSee($org->name);
});

it('allows new user to register and join org via invitation', function () {
    [$invitation, $token, $org] = createTestInvitation();

    Volt::test('auth.accept-invitation', ['token' => $token])
        ->set('name', 'New User')
        ->set('password', 'securepassword123')
        ->set('password_confirmation', 'securepassword123')
        ->call('registerAndAccept')
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'invited@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->organizations->pluck('id'))->toContain($org->id);

    app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
    expect($user->hasRole('editor'))->toBeTrue();
});

it('allows existing logged-in user to accept with one click', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    [$invitation, $token, $org] = createTestInvitation(email: 'existing@example.com');

    $this->actingAs($user);

    Volt::test('auth.accept-invitation', ['token' => $token])
        ->assertSet('isLoggedInUser', true)
        ->call('acceptInvitation')
        ->assertRedirect(route('dashboard'));

    expect($user->fresh()->organizations->pluck('id'))->toContain($org->id);
});

it('shows login link for existing user who is not logged in', function () {
    User::factory()->create(['email' => 'existing@example.com']);
    [$invitation, $token, $org] = createTestInvitation(email: 'existing@example.com');

    Volt::test('auth.accept-invitation', ['token' => $token])
        ->assertSet('needsLogin', true);
});

it('shows error for expired invitation token', function () {
    $org = Organization::create(['name' => 'Test Org']);
    $service = app(InvitationService::class);
    $invitation = $service->create($org, 'test@example.com', 'editor');
    $token = $invitation->plaintext_token;

    $invitation->expires_at = now()->subDay();
    $invitation->save();

    Volt::test('auth.accept-invitation', ['token' => $token])
        ->assertSet('error', 'This invitation has expired. Please request a new invitation.');
});

it('shows error for revoked invitation token', function () {
    [$invitation, $token, $org] = createTestInvitation();

    app(InvitationService::class)->revoke($invitation);

    Volt::test('auth.accept-invitation', ['token' => $token])
        ->assertSet('error', 'This invitation has been revoked.');
});

it('shows error for already accepted invitation token', function () {
    [$invitation, $token, $org] = createTestInvitation();

    app(InvitationService::class)->accept($token);

    Volt::test('auth.accept-invitation', ['token' => $token])
        ->assertSet('error', 'This invitation has already been accepted.');
});

it('assigns correct Spatie role scoped to org on accept', function () {
    [$invitation, $token, $org] = createTestInvitation(role: 'admin');

    Volt::test('auth.accept-invitation', ['token' => $token])
        ->set('name', 'Admin User')
        ->set('password', 'securepassword123')
        ->set('password_confirmation', 'securepassword123')
        ->call('registerAndAccept');

    $user = User::where('email', 'invited@example.com')->first();
    app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
    expect($user->hasRole('admin'))->toBeTrue();
});

it('redirects to dashboard with new org active after acceptance', function () {
    [$invitation, $token, $org] = createTestInvitation();

    Volt::test('auth.accept-invitation', ['token' => $token])
        ->set('name', 'Test User')
        ->set('password', 'securepassword123')
        ->set('password_confirmation', 'securepassword123')
        ->call('registerAndAccept')
        ->assertRedirect(route('dashboard'));

    $user = User::where('email', 'invited@example.com')->first();
    expect($user->current_organization_id)->toBe($org->id);
});

it('regenerates session after auto-login of new user', function () {
    [$invitation, $token, $org] = createTestInvitation();

    $this->get(route('invitations.accept', $token));
    $oldSessionId = session()->getId();

    Volt::test('auth.accept-invitation', ['token' => $token])
        ->set('name', 'Test User')
        ->set('password', 'securepassword123')
        ->set('password_confirmation', 'securepassword123')
        ->call('registerAndAccept');

    expect(session()->getId())->not->toBe($oldSessionId);
});

it('rate limits invitation accept route', function () {
    for ($i = 0; $i < 10; $i++) {
        $this->get(route('invitations.accept', 'test-token-'.$i));
    }

    $this->get(route('invitations.accept', 'test-token-extra'))
        ->assertStatus(429);
});

it('shows inviter name when available', function () {
    $inviter = User::factory()->create(['name' => 'Jane Smith']);
    $org = Organization::create(['name' => 'Inviter Org']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
    $inviter->assignRole('owner');

    $service = app(InvitationService::class);
    $invitation = $service->create($org, 'guest@example.com', 'editor', $inviter);

    $this->get(route('invitations.accept', $invitation->plaintext_token))
        ->assertSee('Jane Smith');
});
