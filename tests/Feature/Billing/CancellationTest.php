<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Services\PlanEnforcement\PlanEnforcementService;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $billingPerm = Permission::findOrCreate('billing', 'web');
    $ownerRole = Role::findOrCreate('owner', 'web');
    $ownerRole->givePermissionTo($billingPerm);

    $this->plan = Plan::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'stripe_price_monthly' => 'price_pro_monthly',
        'stripe_price_yearly' => 'price_pro_yearly',
        'max_users' => 10,
        'max_workspaces' => 5,
        'max_integrations_per_workspace' => 20,
        'is_visible' => true,
        'sort_order' => 1,
    ]);

    $this->org = Organization::create(['name' => 'Test Org']);
    $this->org->plan_id = $this->plan->id;
    $this->org->save();

    $this->user = User::factory()->create(['current_organization_id' => $this->org->id]);
    $this->org->users()->attach($this->user->id);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $this->user->assignRole('owner');
});

it('retains plan limits during grace period', function () {
    // Create a subscription in grace period
    DB::table('subscriptions')->insert([
        'organization_id' => $this->org->id,
        'type' => 'default',
        'stripe_id' => 'sub_grace',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro_monthly',
        'ends_at' => now()->addDays(14),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $enforcement = app(PlanEnforcementService::class);
    $status = $enforcement->getEnforcementStatus($this->org);

    expect($status['users']['max'])->toBe(10);
    expect($status['workspaces']['max'])->toBe(5);
});

it('falls back to free-tier limits after subscription ends', function () {
    // Create an ended subscription
    DB::table('subscriptions')->insert([
        'organization_id' => $this->org->id,
        'type' => 'default',
        'stripe_id' => 'sub_ended',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_pro_monthly',
        'ends_at' => now()->subDay(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $enforcement = app(PlanEnforcementService::class);
    $status = $enforcement->getEnforcementStatus($this->org);

    // Should fall back to free-tier defaults (1 user, 1 workspace)
    expect($status['users']['max'])->toBe(1);
    expect($status['workspaces']['max'])->toBe(1);
});
