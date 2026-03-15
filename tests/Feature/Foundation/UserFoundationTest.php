<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->org = Organization::create(['name' => 'User Test Org', 'timezone' => 'UTC']);
});

it('has currentOrganization relationship', function () {
    $this->user->current_organization_id = $this->org->id;
    $this->user->save();

    $this->user->refresh();

    expect($this->user->currentOrganization)->not->toBeNull();
    expect($this->user->currentOrganization->id)->toBe($this->org->id);
});

it('has organizations relationship', function () {
    $this->user->organizations()->attach($this->org);

    expect($this->user->organizations)->toHaveCount(1);
    expect($this->user->organizations->first()->id)->toBe($this->org->id);
});

it('has workspaces relationship', function () {
    $ws = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'User WS',
        'is_default' => false,
    ]);

    $this->user->workspaces()->attach($ws);

    expect($this->user->workspaces)->toHaveCount(1);
    expect($this->user->workspaces->first()->id)->toBe($ws->id);
});

it('switches organization and sets Spatie team', function () {
    $org2 = Organization::create(['name' => 'Switch Org', 'timezone' => 'UTC']);

    $this->user->switchOrganization($org2);

    expect($this->user->current_organization_id)->toBe($org2->id);
    expect(app(PermissionRegistrar::class)->getPermissionsTeamId())->toBe($org2->id);
});

it('deactivates and reactivates', function () {
    expect($this->user->isDeactivated())->toBeFalse();

    $this->user->deactivate();
    expect($this->user->isDeactivated())->toBeTrue();
    expect($this->user->deactivated_at)->not->toBeNull();

    $this->user->reactivate();
    expect($this->user->isDeactivated())->toBeFalse();
    expect($this->user->deactivated_at)->toBeNull();
});

it('uses active and deactivated scopes', function () {
    $deactivatedUser = User::factory()->create();
    $deactivatedUser->deactivate();

    $activeUsers = User::active()->get();
    $deactivatedUsers = User::deactivated()->get();

    expect($activeUsers->pluck('id'))->toContain($this->user->id);
    expect($activeUsers->pluck('id'))->not->toContain($deactivatedUser->id);

    expect($deactivatedUsers->pluck('id'))->toContain($deactivatedUser->id);
    expect($deactivatedUsers->pluck('id'))->not->toContain($this->user->id);
});

it('hides deactivated_at in serialization', function () {
    $array = $this->user->toArray();
    expect($array)->not->toHaveKey('deactivated_at');
});

it('does not mass assign current_organization_id or deactivated_at', function () {
    // Mass assignment via fill() should not set these fields
    $user = new User;
    $user->fill([
        'name' => 'Test User',
        'email' => 'fill-test@example.com',
        'password' => 'password',
        'current_organization_id' => $this->org->id,
        'deactivated_at' => now(),
    ]);

    expect($user->current_organization_id)->toBeNull();
    expect($user->deactivated_at)->toBeNull();
});
