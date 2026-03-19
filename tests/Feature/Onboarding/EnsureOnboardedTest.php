<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('redirects unonboarded user to onboarding page', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('onboarding'));
});

it('allows onboarded user to pass through', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $org = Organization::create(['name' => 'Test Org']);
    $org->users()->attach($user->id);
    $user->current_organization_id = $org->id;
    $user->save();

    $workspace = new Workspace(['name' => 'Default']);
    $workspace->organization_id = $org->id;
    $workspace->is_default = true;
    $workspace->save();
    $workspace->users()->attach($user->id);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk();
});

it('allows unonboarded user to access onboarding route', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user)
        ->get(route('onboarding'))
        ->assertOk();
});

it('returns true from isOnboarded when user has an organization', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Test Org']);
    $org->users()->attach($user->id);

    expect($user->isOnboarded())->toBeTrue();
});

it('returns false from isOnboarded when user has no organizations', function () {
    $user = User::factory()->create();

    expect($user->isOnboarded())->toBeFalse();
});
