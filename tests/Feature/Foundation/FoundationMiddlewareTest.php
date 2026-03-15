<?php

use App\Events\OrganizationSwitched;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceContext;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->org = Organization::create(['name' => 'MW Org', 'timezone' => 'UTC']);
    $this->user->organizations()->attach($this->org);
    $this->user->switchOrganization($this->org);

    $this->ws = Workspace::forceCreate([
        'organization_id' => $this->org->id,
        'name' => 'MW Workspace',
        'is_default' => true,
    ]);
    $this->user->workspaces()->attach($this->ws);
});

// ── EnsureOrganization Tests ───────────────────────────────────────

it('resolves organization from current_organization_id', function () {
    // Create a test route with the middleware
    Route::middleware(['web', 'auth', 'ensure-organization'])->get('/test-org', function () {
        return response()->json([
            'org_id' => auth()->user()->current_organization_id,
        ]);
    });

    $response = $this->actingAs($this->user)->get('/test-org');

    $response->assertOk();
    $response->assertJson(['org_id' => $this->org->id]);
});

it('falls back to most recent org when current is invalid', function () {
    Event::fake([OrganizationSwitched::class]);

    // Set current org to one the user is NOT a member of
    $otherOrg = Organization::create(['name' => 'Other Org', 'timezone' => 'UTC']);
    $this->user->current_organization_id = $otherOrg->id;
    $this->user->save();

    Route::middleware(['web', 'auth', 'ensure-organization'])->get('/test-fallback', function () {
        return response()->json([
            'org_id' => auth()->user()->current_organization_id,
        ]);
    });

    $response = $this->actingAs($this->user)->get('/test-fallback');

    $response->assertOk();
    $this->user->refresh();
    expect($this->user->current_organization_id)->toBe($this->org->id);
});

it('redirects to organization.select when no org available', function () {
    // Remove all org memberships
    $this->user->organizations()->detach();
    $this->user->current_organization_id = null;
    $this->user->save();

    Route::middleware(['web', 'auth', 'ensure-organization'])->get('/test-no-org', function () {
        return 'should not reach here';
    });

    $response = $this->actingAs($this->user)->get('/test-no-org');

    $response->assertRedirect(route('organization.select'));
});

it('logs out deactivated users', function () {
    $this->user->deactivate();

    Route::middleware(['web', 'auth', 'ensure-organization'])->get('/test-deactivated', function () {
        return 'should not reach here';
    });

    $response = $this->actingAs($this->user)->get('/test-deactivated');

    $response->assertRedirect(route('login'));
});

it('sets Spatie team ID', function () {
    Route::middleware(['web', 'auth', 'ensure-organization'])->get('/test-spatie', function () {
        return response()->json([
            'team_id' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
        ]);
    });

    $response = $this->actingAs($this->user)->get('/test-spatie');

    $response->assertOk();
    $response->assertJson(['team_id' => $this->org->id]);
});

// ── EnsureWorkspace Tests ──────────────────────────────────────────

it('resolves workspace via WorkspaceContext', function () {
    Route::middleware(['web', 'auth', 'ensure-organization', 'ensure-workspace'])->get('/test-ws', function () {
        $ws = app(WorkspaceContext::class)->getWorkspace();

        return response()->json([
            'workspace_id' => $ws?->id,
        ]);
    });

    $response = $this->actingAs($this->user)->get('/test-ws');

    $response->assertOk();
    $response->assertJson(['workspace_id' => $this->ws->id]);
});

it('redirects when zero accessible workspaces', function () {
    $this->user->workspaces()->detach();

    Route::middleware(['web', 'auth', 'ensure-organization', 'ensure-workspace'])->get('/test-no-ws', function () {
        return 'should not reach here';
    });

    $response = $this->actingAs($this->user)->get('/test-no-ws');

    $response->assertRedirect(route('workspace.none'));
});

it('throws RuntimeException if EnsureOrganization has not run', function () {
    // User has no current_organization_id
    $this->user->current_organization_id = null;
    $this->user->save();

    // Only apply ensure-workspace, NOT ensure-organization
    Route::middleware(['web', 'auth', 'ensure-workspace'])->get('/test-no-org-mw', function () {
        return 'should not reach here';
    });

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('EnsureWorkspace middleware requires EnsureOrganization to run first.');

    $this->withoutExceptionHandling()
        ->actingAs($this->user)
        ->get('/test-no-org-mw');
});
