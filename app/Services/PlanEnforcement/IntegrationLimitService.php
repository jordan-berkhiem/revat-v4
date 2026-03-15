<?php

namespace App\Services\PlanEnforcement;

use App\Models\Organization;
use App\Models\Workspace;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class IntegrationLimitService
{
    /**
     * Free-tier default for max integrations per workspace.
     */
    private const FREE_TIER_MAX_INTEGRATIONS = 2;

    public function currentCount(Organization $organization, Workspace $workspace): int
    {
        if (! Schema::hasTable('integrations')) {
            return 0;
        }

        return DB::table('integrations')
            ->where('workspace_id', $workspace->id)
            ->count();
    }

    public function maxAllowed(Organization $organization): int
    {
        $plan = PlanResolver::resolve($organization);

        return $plan ? $plan->max_integrations_per_workspace : self::FREE_TIER_MAX_INTEGRATIONS;
    }

    public function canAdd(Organization $organization, Workspace $workspace): bool
    {
        return $this->currentCount($organization, $workspace) < $this->maxAllowed($organization);
    }
}
