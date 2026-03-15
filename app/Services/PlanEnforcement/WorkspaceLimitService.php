<?php

namespace App\Services\PlanEnforcement;

use App\Models\Organization;
use App\Models\Plan;

class WorkspaceLimitService
{
    /**
     * Free-tier default for max workspaces.
     */
    private const FREE_TIER_MAX_WORKSPACES = 1;

    public function currentCount(Organization $organization): int
    {
        return $organization->workspaces()->count();
    }

    public function maxAllowed(Organization $organization): int
    {
        $plan = $this->resolvePlan($organization);

        return $plan ? $plan->max_workspaces : self::FREE_TIER_MAX_WORKSPACES;
    }

    public function canAdd(Organization $organization): bool
    {
        return $this->currentCount($organization) < $this->maxAllowed($organization);
    }

    public function canDowngradeTo(Organization $organization, Plan $plan): bool
    {
        return $this->currentCount($organization) <= $plan->max_workspaces;
    }

    private function resolvePlan(Organization $organization): ?Plan
    {
        return PlanResolver::resolve($organization);
    }
}
