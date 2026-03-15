<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('creates all required tables', function () {
    expect(Schema::hasTable('organizations'))->toBeTrue();
    expect(Schema::hasTable('workspaces'))->toBeTrue();
    expect(Schema::hasTable('organization_user'))->toBeTrue();
    expect(Schema::hasTable('workspace_user'))->toBeTrue();
});

it('has correct columns on organizations table', function () {
    $columns = Schema::getColumnListing('organizations');
    expect($columns)->toContain('name');
    expect($columns)->toContain('timezone');
    expect($columns)->toContain('support_access_enabled');
    expect($columns)->toContain('deleted_at');
    expect($columns)->toContain('name_uniqueness_guard');
});

it('has correct columns on workspaces table', function () {
    $columns = Schema::getColumnListing('workspaces');
    expect($columns)->toContain('organization_id');
    expect($columns)->toContain('name');
    expect($columns)->toContain('is_default');
    expect($columns)->toContain('default_uniqueness_guard');
    expect($columns)->toContain('name_uniqueness_guard');
    expect($columns)->toContain('deleted_at');
});

it('has correct columns on users table', function () {
    $columns = Schema::getColumnListing('users');
    expect($columns)->toContain('current_organization_id');
    expect($columns)->toContain('deactivated_at');
});

it('enforces organization name uniqueness for active records', function () {
    DB::table('organizations')->insert([
        'name' => 'Acme Corp',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('organizations')->insert([
        'name' => 'Acme Corp',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows reuse of organization name after soft delete', function () {
    DB::table('organizations')->insert([
        'name' => 'Reusable Corp',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Soft-delete the org
    DB::table('organizations')->where('name', 'Reusable Corp')->update([
        'deleted_at' => now(),
    ]);

    // Should be able to create a new org with the same name
    DB::table('organizations')->insert([
        'name' => 'Reusable Corp',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('organizations')->where('name', 'Reusable Corp')->count())->toBe(2);
});

it('enforces one default workspace per org', function () {
    $orgId = DB::table('organizations')->insertGetId([
        'name' => 'Default Workspace Org',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('workspaces')->insert([
        'organization_id' => $orgId,
        'name' => 'Default WS',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('workspaces')->insert([
        'organization_id' => $orgId,
        'name' => 'Another Default WS',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows default workspaces in different orgs', function () {
    $orgId1 = DB::table('organizations')->insertGetId([
        'name' => 'Org One',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $orgId2 = DB::table('organizations')->insertGetId([
        'name' => 'Org Two',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('workspaces')->insert([
        'organization_id' => $orgId1,
        'name' => 'Default WS',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('workspaces')->insert([
        'organization_id' => $orgId2,
        'name' => 'Default WS',
        'is_default' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('workspaces')->where('is_default', true)->count())->toBe(2);
});

it('enforces workspace name uniqueness per org for active records', function () {
    $orgId = DB::table('organizations')->insertGetId([
        'name' => 'Workspace Name Org',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('workspaces')->insert([
        'organization_id' => $orgId,
        'name' => 'Marketing',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(fn () => DB::table('workspaces')->insert([
        'organization_id' => $orgId,
        'name' => 'Marketing',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('allows workspace name reuse after soft delete', function () {
    $orgId = DB::table('organizations')->insertGetId([
        'name' => 'WS Reuse Org',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('workspaces')->insert([
        'organization_id' => $orgId,
        'name' => 'Old Workspace',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Soft-delete the workspace
    DB::table('workspaces')->where('name', 'Old Workspace')->update([
        'deleted_at' => now(),
    ]);

    // Should be able to create a new workspace with the same name
    DB::table('workspaces')->insert([
        'organization_id' => $orgId,
        'name' => 'Old Workspace',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('workspaces')->where('name', 'Old Workspace')->count())->toBe(2);
});

it('cascades organization deletion to workspaces via FK', function () {
    $orgId = DB::table('organizations')->insertGetId([
        'name' => 'Cascade Org',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('workspaces')->insert([
        'organization_id' => $orgId,
        'name' => 'WS1',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('workspaces')->insert([
        'organization_id' => $orgId,
        'name' => 'WS2',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Hard delete the org — workspaces should cascade
    DB::table('organizations')->where('id', $orgId)->delete();

    expect(DB::table('workspaces')->where('organization_id', $orgId)->count())->toBe(0);
});

it('cascades user deletion to pivot tables', function () {
    $orgId = DB::table('organizations')->insertGetId([
        'name' => 'Pivot Cascade Org',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $wsId = DB::table('workspaces')->insertGetId([
        'organization_id' => $orgId,
        'name' => 'Pivot WS',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::factory()->create();

    DB::table('organization_user')->insert([
        'organization_id' => $orgId,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('workspace_user')->insert([
        'workspace_id' => $wsId,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Delete user — pivot rows should cascade
    DB::table('users')->where('id', $user->id)->delete();

    expect(DB::table('organization_user')->where('user_id', $user->id)->count())->toBe(0);
    expect(DB::table('workspace_user')->where('user_id', $user->id)->count())->toBe(0);
});
