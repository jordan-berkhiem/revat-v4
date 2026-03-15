<?php

namespace App\Services\PlanEnforcement;

use App\Models\Organization;
use App\Models\Plan;

class UserLimitService
{
    /**
     * Free-tier default for max users.
     */
    private const FREE_TIER_MAX_USERS = 1;

    public function currentCount(Organization $organization): int
    {
        return $organization->users()->count();
    }

    public function maxAllowed(Organization $organization): int
    {
        $plan = $this->resolvePlan($organization);

        return $plan ? $plan->max_users : self::FREE_TIER_MAX_USERS;
    }

    public function canAdd(Organization $organization): bool
    {
        return $this->currentCount($organization) < $this->maxAllowed($organization);
    }

    public function canDowngradeTo(Organization $organization, Plan $plan): bool
    {
        return $this->currentCount($organization) <= $plan->max_users;
    }

    private function resolvePlan(Organization $organization): ?Plan
    {
        return PlanResolver::resolve($organization);
    }
}
