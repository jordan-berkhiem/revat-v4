<?php

namespace App\Exceptions;

use RuntimeException;

class InvalidInvitationException extends RuntimeException
{
    public static function notFound(): self
    {
        return new self('Invitation not found.');
    }

    public static function expired(): self
    {
        return new self('This invitation has expired.');
    }

    public static function revoked(): self
    {
        return new self('This invitation has been revoked.');
    }

    public static function alreadyAccepted(): self
    {
        return new self('This invitation has already been accepted.');
    }

    public static function duplicatePending(): self
    {
        return new self('A pending invitation already exists for this email and organization.');
    }

    public static function invalidRole(string $role): self
    {
        return new self("Invalid role: {$role}.");
    }
}
