<?php

namespace App\Auth;

use App\Exceptions\DeactivatedUserException;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class DeactivationCheckUserProvider extends EloquentUserProvider
{
    /**
     * Validate a user against the given credentials.
     *
     * Checks deactivation status before password validation to block
     * deactivated users at login time without using a global query scope.
     */
    public function validateCredentials(Authenticatable $user, array $credentials): bool
    {
        if (method_exists($user, 'isDeactivated') && $user->isDeactivated()) {
            throw new DeactivatedUserException;
        }

        return parent::validateCredentials($user, $credentials);
    }
}
