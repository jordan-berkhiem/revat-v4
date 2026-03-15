<?php

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('rejects unauthenticated requests', function () {
    Route::middleware(['web', 'auth'])->get('/_test/auth-required', function () {
        return 'protected';
    });

    $this->get('/_test/auth-required')
        ->assertRedirect('/login');
});

it('sets permissions team id in EnsureOrganization middleware', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Test Org']);
    $user->organizations()->attach($org->id);
    $user->current_organization_id = $org->id;
    $user->save();

    Route::middleware(['web', 'auth', 'organization'])->get('/_test/org-team-id', function () {
        return response()->json([
            'team_id' => app(PermissionRegistrar::class)->getPermissionsTeamId(),
        ]);
    });

    $response = $this->actingAs($user)
        ->get('/_test/org-team-id')
        ->assertOk();

    expect($response->json('team_id'))->toBe($org->id);
});

it('returns correct hasRole after middleware runs', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Test Org']);
    $user->organizations()->attach($org->id);
    $user->current_organization_id = $org->id;
    $user->save();

    // Assign editor role scoped to org
    app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
    $user->assignRole('editor');

    Route::middleware(['web', 'auth', 'organization'])->get('/_test/check-role', function () {
        $user = auth()->user();

        return response()->json([
            'has_editor' => $user->hasRole('editor'),
            'has_owner' => $user->hasRole('owner'),
        ]);
    });

    $response = $this->actingAs($user)
        ->get('/_test/check-role')
        ->assertOk();

    expect($response->json('has_editor'))->toBeTrue()
        ->and($response->json('has_owner'))->toBeFalse();
});

it('scopes roles to the active organization only', function () {
    $user = User::factory()->create();
    $orgA = Organization::create(['name' => 'Org A']);
    $orgB = Organization::create(['name' => 'Org B']);
    $user->organizations()->attach([$orgA->id, $orgB->id]);

    // Assign editor in Org A
    app(PermissionRegistrar::class)->setPermissionsTeamId($orgA->id);
    $user->assignRole('editor');

    // Assign viewer in Org B
    app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
    $user->unsetRelation('roles');
    $user->assignRole('viewer');

    Route::middleware(['web', 'auth', 'organization'])->get('/_test/scoped-role', function () {
        $user = auth()->user();

        return response()->json([
            'has_editor' => $user->hasRole('editor'),
            'has_viewer' => $user->hasRole('viewer'),
        ]);
    });

    // Set current org to A
    $user->current_organization_id = $orgA->id;
    $user->save();

    $response = $this->actingAs($user)
        ->get('/_test/scoped-role')
        ->assertOk();

    expect($response->json('has_editor'))->toBeTrue()
        ->and($response->json('has_viewer'))->toBeFalse();
});

it('allows different roles in different organizations', function () {
    $user = User::factory()->create();
    $orgA = Organization::create(['name' => 'Org A']);
    $orgB = Organization::create(['name' => 'Org B']);
    $user->organizations()->attach([$orgA->id, $orgB->id]);

    // Owner in A, viewer in B
    app(PermissionRegistrar::class)->setPermissionsTeamId($orgA->id);
    $user->assignRole('owner');

    app(PermissionRegistrar::class)->setPermissionsTeamId($orgB->id);
    $user->unsetRelation('roles');
    $user->assignRole('viewer');

    Route::middleware(['web', 'auth', 'organization'])->get('/_test/diff-roles', function () {
        $user = auth()->user();

        return response()->json([
            'has_owner' => $user->hasRole('owner'),
            'has_billing' => $user->hasPermissionTo('billing'),
        ]);
    });

    // Check Org A
    $user->current_organization_id = $orgA->id;
    $user->save();

    $response = $this->actingAs($user)->get('/_test/diff-roles')->assertOk();
    expect($response->json('has_owner'))->toBeTrue()
        ->and($response->json('has_billing'))->toBeTrue();

    // Switch to Org B
    $user->current_organization_id = $orgB->id;
    $user->save();

    $response = $this->actingAs($user)->get('/_test/diff-roles')->assertOk();
    expect($response->json('has_owner'))->toBeFalse()
        ->and($response->json('has_billing'))->toBeFalse();
});

it('scopes hasPermissionTo to the active organization', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Test Org']);
    $user->organizations()->attach($org->id);
    $user->current_organization_id = $org->id;
    $user->save();

    app(PermissionRegistrar::class)->setPermissionsTeamId($org->id);
    $user->assignRole('admin');

    Route::middleware(['web', 'auth', 'organization'])->get('/_test/check-perm', function () {
        $user = auth()->user();

        return response()->json([
            'can_manage' => $user->hasPermissionTo('manage'),
            'can_billing' => $user->hasPermissionTo('billing'),
            'can_view' => $user->hasPermissionTo('view'),
        ]);
    });

    $response = $this->actingAs($user)->get('/_test/check-perm')->assertOk();

    expect($response->json('can_manage'))->toBeTrue()
        ->and($response->json('can_billing'))->toBeFalse()
        ->and($response->json('can_view'))->toBeTrue();
});

it('processes full middleware stack end-to-end', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);
    $org = Organization::create(['name' => 'Full Stack Org']);
    $user->organizations()->attach($org->id);
    $user->current_organization_id = $org->id;
    $user->save();

    $workspace = $org->workspaces()->create([
        'name' => 'Default',
        'is_default' => true,
    ]);
    $user->workspaces()->attach($workspace->id);

    Route::middleware(['web', 'auth', 'organization', 'workspace'])->get('/_test/full-stack', function () {
        return response()->json([
            'user' => auth()->user()->email,
            'org' => auth()->user()->current_organization_id,
            'status' => 'ok',
        ]);
    });

    $response = $this->actingAs($user)->get('/_test/full-stack')->assertOk();

    expect($response->json('status'))->toBe('ok')
        ->and($response->json('org'))->toBe($org->id);
});
