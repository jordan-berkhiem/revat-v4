<?php

namespace App\Services;

use App\Exceptions\InvalidInvitationException;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class InvitationService
{
    private const VALID_ROLES = ['owner', 'admin', 'editor', 'viewer'];

    private const EXPIRY_DAYS = 7;

    /**
     * Create a new invitation.
     *
     * The plaintext token is available as `$invitation->plaintext_token` after creation.
     */
    public function create(Organization $org, string $email, string $role, ?User $invitedBy = null): Invitation
    {
        $this->validateRole($role);

        // Only owners can invite as owner
        if ($role === 'owner' && $invitedBy !== null) {
            app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
            if (! $invitedBy->hasRole('owner')) {
                throw new AccessDeniedHttpException('Only owners can invite as owner.');
            }
        }

        // Check for existing pending invitation
        $existingPending = $org->invitations()
            ->where('email', $email)
            ->pending()
            ->exists();

        if ($existingPending) {
            throw InvalidInvitationException::duplicatePending();
        }

        // Delete non-pending invitations for same org + email (allows re-inviting)
        $org->invitations()
            ->where('email', $email)
            ->where(function ($query) {
                $query->whereNotNull('accepted_at')
                    ->orWhereNotNull('revoked_at')
                    ->orWhere('expires_at', '<=', now());
            })
            ->delete();

        $plaintextToken = Str::random(64);
        $tokenHash = hash('sha256', $plaintextToken);

        $invitation = $org->invitations()->create([
            'email' => $email,
            'role' => $role,
            'invited_by' => $invitedBy?->id,
            'token_hash' => $tokenHash,
            'expires_at' => now()->addDays(self::EXPIRY_DAYS),
        ]);

        // Store plaintext token transiently for email URL usage
        $invitation->plaintext_token = $plaintextToken;

        return $invitation;
    }

    /**
     * Accept an invitation by token.
     */
    public function accept(string $token): User
    {
        $tokenHash = hash('sha256', $token);

        $invitation = Invitation::where('token_hash', $tokenHash)->first();

        if (! $invitation) {
            throw InvalidInvitationException::notFound();
        }

        if ($invitation->isAccepted()) {
            throw InvalidInvitationException::alreadyAccepted();
        }

        if ($invitation->isRevoked()) {
            throw InvalidInvitationException::revoked();
        }

        if ($invitation->isExpired()) {
            throw InvalidInvitationException::expired();
        }

        $user = User::where('email', $invitation->email)->first();

        if (! $user) {
            $user = User::create([
                'name' => Str::before($invitation->email, '@'),
                'email' => $invitation->email,
                'password' => Str::random(32),
            ]);
        }

        // Attach user to organization
        $org = $invitation->organization;
        if (! $user->organizations()->where('organizations.id', $org->id)->exists()) {
            $user->organizations()->attach($org->id);
        }

        // Assign Spatie role scoped to organization
        app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
        $user->assignRole($invitation->role);

        // Mark as accepted
        $invitation->accepted_at = now();
        $invitation->save();

        return $user;
    }

    /**
     * Revoke an invitation.
     */
    public function revoke(Invitation $invitation): void
    {
        $invitation->revoked_at = now();
        $invitation->save();
    }

    /**
     * Resend an invitation with a new token and extended expiry.
     *
     * The new plaintext token is available as `$invitation->plaintext_token`.
     */
    public function resend(Invitation $invitation): Invitation
    {
        $plaintextToken = Str::random(64);
        $tokenHash = hash('sha256', $plaintextToken);

        $invitation->token_hash = $tokenHash;
        $invitation->expires_at = now()->addDays(self::EXPIRY_DAYS);
        $invitation->save();

        $invitation->plaintext_token = $plaintextToken;

        return $invitation;
    }

    private function validateRole(string $role): void
    {
        if (! in_array($role, self::VALID_ROLES, true)) {
            throw InvalidInvitationException::invalidRole($role);
        }
    }
}
