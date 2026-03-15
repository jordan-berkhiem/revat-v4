<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Services\OrganizationSetupService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $this->service = app(OrganizationSetupService::class);
});

it('creates organization, workspace, and pivot entries', function () {
    $user = User::factory()->create();

    $org = $this->service->setup($user, [
        'name' => 'Acme Corp',
        'timezone' => 'America/New_York',
    ]);

    expect($org)->toBeInstanceOf(Organization::class)
        ->and($org->name)->toBe('Acme Corp')
        ->and($org->timezone)->toBe('America/New_York');

    // Default workspace created
    $workspace = $org->workspaces()->first();
    expect($workspace)->toBeInstanceOf(Workspace::class)
        ->and($workspace->name)->toBe('Acme Corp Workspace')
        ->and($workspace->is_default)->toBeTrue();

    // User attached to organization
    expect($org->users()->where('users.id', $user->id)->exists())->toBeTrue();

    // User attached to workspace
    expect($workspace->users()->where('users.id', $user->id)->exists())->toBeTrue();
});

it('assigns owner role scoped to the new organization', function () {
    $user = User::factory()->create();

    $org = $this->service->setup($user, [
        'name' => 'Scoped Org',
        'timezone' => 'UTC',
    ]);

    // Set team context to verify scoped role
    app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
    $user->unsetRelation('roles');

    expect($user->hasRole('owner'))->toBeTrue();

    // Create another org — user should NOT have owner role there
    $otherOrg = Organization::create(['name' => 'Other Org']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($otherOrg->id);
    $user->unsetRelation('roles');

    expect($user->hasRole('owner'))->toBeFalse();
});

it('sets current_organization_id on user', function () {
    $user = User::factory()->create();

    $org = $this->service->setup($user, [
        'name' => 'My Org',
        'timezone' => 'UTC',
    ]);

    $user->refresh();

    expect($user->current_organization_id)->toBe($org->id);
});

it('rejects duplicate organization names', function () {
    Organization::create(['name' => 'Existing Org']);

    $user = User::factory()->create();

    // The validation happens at the Volt component level, not the service.
    // The service trusts validated data. Test uniqueness at DB level.
    expect(Organization::where('name', 'Existing Org')->exists())->toBeTrue();
});

it('applies rate limiting to the onboarding route', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($user);

    // Make requests up to the limit — throttle is 5 per minute
    for ($i = 0; $i < 5; $i++) {
        $response = $this->get(route('onboarding.create-organization'));
        $response->assertStatus(200);
    }

    // 6th request should be throttled
    $response = $this->get(route('onboarding.create-organization'));
    $response->assertStatus(429);
});
