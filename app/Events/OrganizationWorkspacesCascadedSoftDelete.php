<?php

namespace App\Events;

use App\Models\Organization;
use Illuminate\Foundation\Events\Dispatchable;

class OrganizationWorkspacesCascadedSoftDelete
{
    use Dispatchable;

    public function __construct(
        public readonly Organization $organization,
    ) {}
}
