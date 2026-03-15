<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'WS Test Org', 'timezone' => 'UTC']);
});

it('has organization relationship', function () {
    $ws = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Test WS',
        'is_default' => false,
    ]);

    expect($ws->organization->id)->toBe($this->org->id);
});

it('has users relationship', function () {
    $ws = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'User WS',
        'is_default' => false,
    ]);

    $user = User::factory()->create();
    $ws->users()->attach($user);

    expect($ws->users)->toHaveCount(1);
    expect($ws->users->first()->id)->toBe($user->id);
});

it('sets as default clearing other defaults', function () {
    $ws1 = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'WS1',
        'is_default' => true,
    ]);

    $ws2 = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'WS2',
        'is_default' => false,
    ]);

    $ws2->setAsDefault();

    $ws1->refresh();
    $ws2->refresh();

    expect($ws2->is_default)->toBeTrue();
    expect($ws1->is_default)->toBeFalse();
});

it('uses default scope', function () {
    Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Non-Default',
        'is_default' => false,
    ]);
    Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Default',
        'is_default' => true,
    ]);

    $defaults = Workspace::default()->get();
    expect($defaults)->toHaveCount(1);
    expect($defaults->first()->name)->toBe('Default');
});

it('uses forOrganization scope', function () {
    $org2 = Organization::create(['name' => 'Other Org', 'timezone' => 'UTC']);

    Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Org1 WS',
        'is_default' => false,
    ]);
    Workspace::forceCreate([
        'organization_id' => $org2->id,
        'name' => 'Org2 WS',
        'is_default' => false,
    ]);

    $org1Workspaces = Workspace::forOrganization($this->org)->get();
    expect($org1Workspaces)->toHaveCount(1);
    expect($org1Workspaces->first()->name)->toBe('Org1 WS');

    // Also accepts int
    $org1Workspaces2 = Workspace::forOrganization($this->org->id)->get();
    expect($org1Workspaces2)->toHaveCount(1);
});

it('supports soft delete', function () {
    $ws = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Soft Delete WS',
        'is_default' => false,
    ]);

    $ws->delete();

    expect(Workspace::find($ws->id))->toBeNull();
    expect(Workspace::withTrashed()->find($ws->id))->not->toBeNull();
    expect(Workspace::withTrashed()->find($ws->id)->trashed())->toBeTrue();
});

it('hides deleted_at in serialization', function () {
    $ws = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Hidden WS',
        'is_default' => false,
    ]);

    $array = $ws->toArray();
    expect($array)->not->toHaveKey('deleted_at');
});
