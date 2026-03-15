<?php

use App\Events\OrganizationWorkspacesCascadedSoftDelete;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Illuminate\Support\Facades\Event;

beforeEach(function () {
    $this->org = Organization::create(['name' => 'Test Org', 'timezone' => 'UTC']);
});

it('has users relationship', function () {
    $user = User::factory()->create();
    $this->org->users()->attach($user);

    expect($this->org->users)->toHaveCount(1);
    expect($this->org->users->first()->id)->toBe($user->id);
});

it('has workspaces relationship', function () {
    Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'WS1',
        'is_default' => false,
    ]);

    expect($this->org->workspaces)->toHaveCount(1);
});

it('has defaultWorkspace relationship', function () {
    Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Non-Default',
        'is_default' => false,
    ]);
    $defaultWs = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Default WS',
        'is_default' => true,
    ]);

    expect($this->org->defaultWorkspace)->not->toBeNull();
    expect($this->org->defaultWorkspace->id)->toBe($defaultWs->id);
});

it('soft deletes cascading to workspaces', function () {
    Event::fake([OrganizationWorkspacesCascadedSoftDelete::class]);

    Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Cascade WS 1',
        'is_default' => false,
    ]);
    Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Cascade WS 2',
        'is_default' => false,
    ]);

    $this->org->delete();

    expect($this->org->trashed())->toBeTrue();
    expect(Workspace::where('organization_id', $this->org->id)->count())->toBe(0);
    expect(Workspace::withTrashed()->where('organization_id', $this->org->id)->count())->toBe(2);

    Event::assertDispatched(OrganizationWorkspacesCascadedSoftDelete::class);
});

it('restores cascading to workspaces', function () {
    Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Restore WS',
        'is_default' => false,
    ]);

    $this->org->delete();

    expect(Workspace::where('organization_id', $this->org->id)->count())->toBe(0);

    $this->org->restore();

    expect(Workspace::where('organization_id', $this->org->id)->count())->toBe(1);
});

it('toggles support access', function () {
    expect($this->org->support_access_enabled)->toBeFalse();

    $this->org->toggleSupportAccess(true);
    $this->org->refresh();

    expect($this->org->support_access_enabled)->toBeTrue();

    $this->org->toggleSupportAccess(false);
    $this->org->refresh();

    expect($this->org->support_access_enabled)->toBeFalse();
});

it('hides Cashier fields in serialization', function () {
    $array = $this->org->toArray();

    expect($array)->not->toHaveKey('stripe_id');
    expect($array)->not->toHaveKey('pm_type');
    expect($array)->not->toHaveKey('pm_last_four');
    expect($array)->not->toHaveKey('trial_ends_at');
    expect($array)->toHaveKey('name');
});

it('does not include support_access_enabled in fillable', function () {
    $org = Organization::create([
        'name' => 'Fillable Test Org',
        'timezone' => 'UTC',
        'support_access_enabled' => true, // should be ignored by mass assignment
    ]);

    $org->refresh();
    expect($org->support_access_enabled)->toBeFalse();
});
