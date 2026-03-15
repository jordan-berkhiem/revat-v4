<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use App\Services\PlanEnforcement\AgencyFeatureGate;
use App\Services\PlanEnforcement\PlanEnforcementService;

beforeEach(function () {
    $this->plan = Plan::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'max_users' => 5,
        'max_workspaces' => 3,
        'max_integrations_per_workspace' => 10,
        'is_visible' => true,
        'sort_order' => 1,
    ]);

    $this->org = Organization::create(['name' => 'Test Org']);
    $this->org->plan_id = $this->plan->id;
    $this->org->save();

    $this->service = app(PlanEnforcementService::class);
});

// ── User Limit Tests ────────────────────────────────────────────────────

it('canAddUser returns true when under limit', function () {
    // Org has 0 users, limit is 5
    expect($this->service->canAddUser($this->org))->toBeTrue();
});

it('canAddUser returns false when at limit', function () {
    // Add 5 users to hit the limit
    for ($i = 0; $i < 5; $i++) {
        $user = User::factory()->create();
        $this->org->users()->attach($user->id);
    }

    expect($this->service->canAddUser($this->org))->toBeFalse();
});

// ── Workspace Limit Tests ───────────────────────────────────────────────

it('canAddWorkspace returns true when under limit', function () {
    expect($this->service->canAddWorkspace($this->org))->toBeTrue();
});

it('canAddWorkspace returns false when at limit', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->org->workspaces()->create([
            'name' => "Workspace {$i}",
        ]);
    }

    expect($this->service->canAddWorkspace($this->org))->toBeFalse();
});

// ── Integration Limit Tests ─────────────────────────────────────────────

it('canAddIntegration returns true when integrations table does not exist', function () {
    $workspace = $this->org->workspaces()->create([
        'name' => 'Test Workspace',
    ]);

    // Integrations table doesn't exist yet, should return true
    expect($this->service->canAddIntegration($this->org, $workspace))->toBeTrue();
});

// ── Downgrade Tests ─────────────────────────────────────────────────────

it('canDowngradeTo returns true when usage fits target plan', function () {
    $freePlan = Plan::create([
        'name' => 'Free',
        'slug' => 'free',
        'max_users' => 2,
        'max_workspaces' => 1,
        'max_integrations_per_workspace' => 2,
        'is_visible' => true,
        'sort_order' => 0,
    ]);

    // Org has 1 user and 1 workspace
    $user = User::factory()->create();
    $this->org->users()->attach($user->id);

    $this->org->workspaces()->create([
        'name' => 'WS',
    ]);

    expect($this->service->canDowngradeTo($this->org, $freePlan))->toBeTrue();
});

it('canDowngradeTo returns false when usage exceeds target plan', function () {
    $freePlan = Plan::create([
        'name' => 'Free',
        'slug' => 'free',
        'max_users' => 1,
        'max_workspaces' => 1,
        'max_integrations_per_workspace' => 2,
        'is_visible' => true,
        'sort_order' => 0,
    ]);

    // Add 3 users - exceeds free plan limit of 1
    for ($i = 0; $i < 3; $i++) {
        $user = User::factory()->create();
        $this->org->users()->attach($user->id);
    }

    expect($this->service->canDowngradeTo($this->org, $freePlan))->toBeFalse();
});

// ── Free Tier Fallback Tests ────────────────────────────────────────────

it('uses free-tier defaults when organization has no plan', function () {
    $orgNoPlan = Organization::create(['name' => 'No Plan Org']);

    // Free tier defaults: max_users = 1, max_workspaces = 1
    expect($this->service->canAddUser($orgNoPlan))->toBeTrue();

    // Add one user, should be at limit
    $user = User::factory()->create();
    $orgNoPlan->users()->attach($user->id);

    expect($this->service->canAddUser($orgNoPlan))->toBeFalse();
});

// ── Grace Period Tests ──────────────────────────────────────────────────

it('retains plan limits during grace period', function () {
    // Create a subscription in grace period
    $this->org->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_grace',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'ends_at' => now()->addDays(14), // Grace period - will end in 14 days
    ]);

    // Should still use the plan's limits (5 users)
    for ($i = 0; $i < 4; $i++) {
        $user = User::factory()->create();
        $this->org->users()->attach($user->id);
    }

    expect($this->service->canAddUser($this->org))->toBeTrue();
});

// ── Agency Feature Gate Tests ───────────────────────────────────────────

it('identifies agency plan correctly', function () {
    $agencyPlan = Plan::create([
        'name' => 'Agency',
        'slug' => 'agency',
        'max_users' => 50,
        'max_workspaces' => 25,
        'max_integrations_per_workspace' => 50,
        'is_visible' => true,
        'sort_order' => 3,
    ]);

    $agencyOrg = Organization::create(['name' => 'Agency Org']);
    $agencyOrg->plan_id = $agencyPlan->id;
    $agencyOrg->save();

    $gate = app(AgencyFeatureGate::class);

    expect($gate->isAgencyPlan($agencyOrg))->toBeTrue();
    expect($gate->canAccessAgencyFeatures($agencyOrg))->toBeTrue();
    expect($gate->isAgencyPlan($this->org))->toBeFalse();
});

// ── Enforcement Status Tests ────────────────────────────────────────────

it('returns structured enforcement status', function () {
    $user = User::factory()->create();
    $this->org->users()->attach($user->id);

    $this->org->workspaces()->create([
        'name' => 'WS',
    ]);

    $status = $this->service->getEnforcementStatus($this->org);

    expect($status)->toHaveKeys(['users', 'workspaces', 'agency_features']);
    expect($status['users']['current'])->toBe(1);
    expect($status['users']['max'])->toBe(5);
    expect($status['users']['can_add'])->toBeTrue();
    expect($status['workspaces']['current'])->toBe(1);
    expect($status['workspaces']['max'])->toBe(3);
    expect($status['workspaces']['can_add'])->toBeTrue();
    expect($status['agency_features']['available'])->toBeFalse();
});
