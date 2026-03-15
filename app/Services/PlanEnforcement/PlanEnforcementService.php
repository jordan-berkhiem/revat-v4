<?php

namespace App\Services\PlanEnforcement;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\Workspace;

/**
 * Main facade for plan enforcement checks.
 *
 * Delegates to sub-services for each limit type.
 *
 * CONCURRENCY NOTE: Callers must wrap the enforcement check and resource
 * creation in a DB::transaction() with a pessimistic lock on the organization
 * row (SELECT FOR UPDATE) to prevent TOCTOU race conditions.
 */
class PlanEnforcementService
{
    public function __construct(
        private UserLimitService $userLimitService,
        private WorkspaceLimitService $workspaceLimitService,
        private IntegrationLimitService $integrationLimitService,
        private AgencyFeatureGate $agencyFeatureGate,
    ) {}

    public function canAddUser(Organization $organization): bool
    {
        return $this->userLimitService->canAdd($organization);
    }

    public function canAddWorkspace(Organization $organization): bool
    {
        return $this->workspaceLimitService->canAdd($organization);
    }

    public function canAddIntegration(Organization $organization, Workspace $workspace): bool
    {
        return $this->integrationLimitService->canAdd($organization, $workspace);
    }

    public function canDowngradeTo(Organization $organization, Plan $plan): bool
    {
        return $this->userLimitService->canDowngradeTo($organization, $plan)
            && $this->workspaceLimitService->canDowngradeTo($organization, $plan);
    }

    /**
     * Get a structured enforcement status for the organization.
     *
     * @return array<string, array{current: int, max: int, can_add: bool}>
     */
    public function getEnforcementStatus(Organization $organization): array
    {
        return [
            'users' => [
                'current' => $this->userLimitService->currentCount($organization),
                'max' => $this->userLimitService->maxAllowed($organization),
                'can_add' => $this->userLimitService->canAdd($organization),
            ],
            'workspaces' => [
                'current' => $this->workspaceLimitService->currentCount($organization),
                'max' => $this->workspaceLimitService->maxAllowed($organization),
                'can_add' => $this->workspaceLimitService->canAdd($organization),
            ],
            'agency_features' => [
                'available' => $this->agencyFeatureGate->canAccessAgencyFeatures($organization),
            ],
        ];
    }
}
