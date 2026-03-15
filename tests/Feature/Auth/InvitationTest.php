<?php

use App\Exceptions\InvalidInvitationException;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use App\Services\InvitationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->service = app(InvitationService::class);
    $this->org = Organization::create(['name' => 'Test Org']);
});

it('creates invitation with unique token and 7-day expiry', function () {
    $invitation = $this->service->create($this->org, 'user@example.com', 'editor');

    expect($invitation)->toBeInstanceOf(Invitation::class)
        ->and($invitation->email)->toBe('user@example.com')
        ->and($invitation->role)->toBe('editor')
        ->and($invitation->token_hash)->toHaveLength(64)
        ->and($invitation->plaintext_token)->toHaveLength(64)
        ->and(now()->diffInDays($invitation->expires_at, false))->toBeBetween(6, 7)
        ->and($invitation->isPending())->toBeTrue();
});

it('accepts invitation with new user and assigns role', function () {
    $invitation = $this->service->create($this->org, 'newuser@example.com', 'editor');

    $user = $this->service->accept($invitation->plaintext_token);

    expect($user)->toBeInstanceOf(User::class)
        ->and($user->email)->toBe('newuser@example.com')
        ->and($user->name)->toBe('newuser')
        ->and($user->organizations)->toHaveCount(1);

    // Check role is assigned scoped to org
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    expect($user->hasRole('editor'))->toBeTrue();

    // Invitation should be accepted
    $invitation->refresh();
    expect($invitation->isAccepted())->toBeTrue();
});

it('accepts invitation with existing user and adds to org', function () {
    $user = User::factory()->create(['email' => 'existing@example.com']);
    $invitation = $this->service->create($this->org, 'existing@example.com', 'admin');

    $result = $this->service->accept($invitation->plaintext_token);

    expect($result->id)->toBe($user->id)
        ->and($result->organizations)->toHaveCount(1);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    expect($result->hasRole('admin'))->toBeTrue();
});

it('scopes role assignment to the correct organization', function () {
    $org2 = Organization::create(['name' => 'Other Org']);
    $invitation = $this->service->create($this->org, 'scoped@example.com', 'admin');

    $user = $this->service->accept($invitation->plaintext_token);

    // Should have role in the invitation's org
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    expect($user->hasRole('admin'))->toBeTrue();

    // Should NOT have role in other org
    app(PermissionRegistrar::class)->setPermissionsTeamId($org2->id);
    $user->unsetRelation('roles');
    expect($user->hasRole('admin'))->toBeFalse();
});

it('cannot accept expired invitation', function () {
    $invitation = $this->service->create($this->org, 'expired@example.com', 'editor');
    $invitation->expires_at = now()->subDay();
    $invitation->save();

    $this->service->accept($invitation->plaintext_token);
})->throws(InvalidInvitationException::class, 'expired');

it('cannot accept revoked invitation', function () {
    $invitation = $this->service->create($this->org, 'revoked@example.com', 'editor');
    $this->service->revoke($invitation);

    $this->service->accept($invitation->plaintext_token);
})->throws(InvalidInvitationException::class, 'revoked');

it('cannot accept already accepted invitation', function () {
    $invitation = $this->service->create($this->org, 'accepted@example.com', 'editor');
    $this->service->accept($invitation->plaintext_token);

    // Try to accept again (need token since it was already used)
    $invitation->refresh();
    $invitation->accepted_at = null; // reset for retry scenario
    $invitation->save();

    // Re-create with same token to simulate double accept
    $invitation2 = $this->service->create($this->org, 'accepted2@example.com', 'editor');
    $this->service->accept($invitation2->plaintext_token);

    $invitation2->refresh();
    expect($invitation2->isAccepted())->toBeTrue();

    // Now try with a fresh invitation that was already accepted
    $invitation3 = $this->service->create($this->org, 'accepted3@example.com', 'editor');
    $token = $invitation3->plaintext_token;
    $this->service->accept($token);

    // Mark as accepted and try again
    $this->service->accept($token);
})->throws(InvalidInvitationException::class, 'already been accepted');

it('rejects admin inviting as owner', function () {
    $user = User::factory()->create();
    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $user->assignRole('admin');

    $this->service->create($this->org, 'promoted@example.com', 'owner', $user);
})->throws(AccessDeniedHttpException::class);

it('cannot create duplicate invitation for same org and email', function () {
    $this->service->create($this->org, 'dupe@example.com', 'editor');
    $this->service->create($this->org, 'dupe@example.com', 'admin');
})->throws(InvalidInvitationException::class, 'pending invitation already exists');

it('resends invitation with new token and extended expiry', function () {
    $invitation = $this->service->create($this->org, 'resend@example.com', 'editor');
    $originalHash = $invitation->token_hash;
    $originalExpiry = $invitation->expires_at;

    // Travel forward 3 days
    $this->travel(3)->days();

    $updated = $this->service->resend($invitation);

    expect($updated->token_hash)->not->toBe($originalHash)
        ->and($updated->plaintext_token)->toHaveLength(64)
        ->and($updated->expires_at->gt($originalExpiry))->toBeTrue();
});

it('returns only pending invitations with scopePending', function () {
    $pending = $this->service->create($this->org, 'pending@example.com', 'editor');

    $org2 = Organization::create(['name' => 'Org 2']);
    $expired = $org2->invitations()->create([
        'email' => 'expired@example.com',
        'role' => 'editor',
        'token_hash' => hash('sha256', 'expired-token'),
        'expires_at' => now()->subDay(),
    ]);

    $org3 = Organization::create(['name' => 'Org 3']);
    $revoked = $org3->invitations()->create([
        'email' => 'revoked@example.com',
        'role' => 'editor',
        'token_hash' => hash('sha256', 'revoked-token'),
        'expires_at' => now()->addDays(7),
        'revoked_at' => now(),
    ]);

    $pendingInvitations = Invitation::pending()->get();

    expect($pendingInvitations)->toHaveCount(1)
        ->and($pendingInvitations->first()->id)->toBe($pending->id);
});
