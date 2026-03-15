<?php

use App\Events\WorkspaceSwitched;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceContext;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->org = Organization::create(['name' => 'Context Org', 'timezone' => 'UTC']);
    $this->user->organizations()->attach($this->org);
    $this->user->switchOrganization($this->org);

    $this->defaultWs = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Default',
        'is_default' => true,
    ]);

    $this->otherWs = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Other',
        'is_default' => false,
    ]);

    $this->user->workspaces()->attach([$this->defaultWs->id, $this->otherWs->id]);

    $this->context = app(WorkspaceContext::class);
});

it('resolves workspace from pivot last_workspace_id', function () {
    $this->user->organizations()->updateExistingPivot($this->org->id, [
        'last_workspace_id' => $this->otherWs->id,
    ]);

    $resolved = $this->context->resolveWorkspace($this->user, $this->org);

    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($this->otherWs->id);
});

it('falls back to default workspace when pivot has no last_workspace_id', function () {
    $resolved = $this->context->resolveWorkspace($this->user, $this->org);

    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($this->defaultWs->id);
});

it('returns null when user has no accessible workspaces', function () {
    $this->user->workspaces()->detach();
    $this->context->reset();

    $resolved = $this->context->resolveWorkspace($this->user, $this->org);

    expect($resolved)->toBeNull();
});

it('falls back to default when pivot workspace is not accessible', function () {
    // Set last_workspace_id to a workspace the user doesn't have access to
    $inaccessibleWs = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'Inaccessible',
        'is_default' => false,
    ]);

    $this->user->organizations()->updateExistingPivot($this->org->id, [
        'last_workspace_id' => $inaccessibleWs->id,
    ]);

    $resolved = $this->context->resolveWorkspace($this->user, $this->org);

    expect($resolved)->not->toBeNull();
    expect($resolved->id)->toBe($this->defaultWs->id);
});

it('sets workspace in session and updates pivot', function () {
    $this->actingAs($this->user);
    Event::fake([WorkspaceSwitched::class]);

    $this->context->setWorkspace($this->otherWs);

    // Check session
    $sessionKey = "workspace:{$this->user->id}:{$this->org->id}";
    expect(Session::get($sessionKey))->toBe($this->otherWs->id);

    // Check pivot
    $this->user->refresh();
    $pivot = $this->user->organizations()->where('organizations.id', $this->org->id)->first()->pivot;
    expect($pivot->last_workspace_id)->toBe($this->otherWs->id);
});

it('dispatches WorkspaceSwitched event only on change', function () {
    $this->actingAs($this->user);
    Event::fake([WorkspaceSwitched::class]);

    // First set
    $this->context->setWorkspace($this->otherWs);
    Event::assertDispatched(WorkspaceSwitched::class, 1);

    // Set same workspace again — should NOT dispatch again
    $this->context->setWorkspace($this->otherWs);
    Event::assertDispatched(WorkspaceSwitched::class, 1); // Still 1
});

it('returns correct accessible workspace IDs', function () {
    $ids = $this->context->accessibleWorkspaceIds($this->user, $this->org);

    expect($ids)->toContain($this->defaultWs->id);
    expect($ids)->toContain($this->otherWs->id);
    expect($ids)->toHaveCount(2);
});

it('excludes soft-deleted workspaces from accessible IDs', function () {
    $this->otherWs->delete();
    $this->context->reset();

    $ids = $this->context->accessibleWorkspaceIds($this->user, $this->org);

    expect($ids)->toContain($this->defaultWs->id);
    expect($ids)->not->toContain($this->otherWs->id);
    expect($ids)->toHaveCount(1);
});

it('clears workspace from session but preserves pivot', function () {
    $this->actingAs($this->user);

    $this->context->setWorkspace($this->otherWs);

    $this->context->clearWorkspace();

    // Session should be cleared
    $sessionKey = "workspace:{$this->user->id}:{$this->org->id}";
    expect(Session::get($sessionKey))->toBeNull();

    // Pivot should still have last_workspace_id
    $this->user->refresh();
    $pivot = $this->user->organizations()->where('organizations.id', $this->org->id)->first()->pivot;
    expect($pivot->last_workspace_id)->toBe($this->otherWs->id);
});

it('resets caches', function () {
    // Populate cache
    $ids = $this->context->accessibleWorkspaceIds($this->user, $this->org);
    expect($ids)->toHaveCount(2);

    // Detach a workspace
    $this->user->workspaces()->detach($this->otherWs->id);

    // Cache still returns 2
    $ids = $this->context->accessibleWorkspaceIds($this->user, $this->org);
    expect($ids)->toHaveCount(2);

    // After reset, cache is cleared
    $this->context->reset();
    $ids = $this->context->accessibleWorkspaceIds($this->user, $this->org);
    expect($ids)->toHaveCount(1);
});
