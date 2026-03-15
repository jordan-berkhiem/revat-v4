<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;

class DeactivatedUserException extends AuthenticationException
{
    public function __construct()
    {
        parent::__construct('Your account has been deactivated. Contact your organization administrator.');
    }
}
