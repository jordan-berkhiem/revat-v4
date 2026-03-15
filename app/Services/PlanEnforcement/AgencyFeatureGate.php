<?php

namespace App\Services\PlanEnforcement;

use App\Models\Organization;

class AgencyFeatureGate
{
    public function isAgencyPlan(Organization $organization): bool
    {
        $plan = PlanResolver::resolve($organization);

        return $plan !== null && $plan->slug === 'agency';
    }

    public function canAccessAgencyFeatures(Organization $organization): bool
    {
        return $this->isAgencyPlan($organization);
    }
}
