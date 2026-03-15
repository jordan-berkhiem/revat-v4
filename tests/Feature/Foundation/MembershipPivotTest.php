<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('has composite primary key on organization_user', function () {
    $orgId = DB::table('organizations')->insertGetId([
        'name' => 'Pivot PK Org',
        'timezone' => 'UTC',
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

    // Inserting duplicate should fail
    expect(fn () => DB::table('organization_user')->insert([
        'organization_id' => $orgId,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('has composite primary key on workspace_user', function () {
    $orgId = DB::table('organizations')->insertGetId([
        'name' => 'WS Pivot PK Org',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $wsId = DB::table('workspaces')->insertGetId([
        'organization_id' => $orgId,
        'name' => 'WS Pivot PK WS',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::factory()->create();

    DB::table('workspace_user')->insert([
        'workspace_id' => $wsId,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Inserting duplicate should fail
    expect(fn () => DB::table('workspace_user')->insert([
        'workspace_id' => $wsId,
        'user_id' => $user->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('nullifies last_workspace_id when workspace is deleted', function () {
    $orgId = DB::table('organizations')->insertGetId([
        'name' => 'Null WS Org',
        'timezone' => 'UTC',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $wsId = DB::table('workspaces')->insertGetId([
        'organization_id' => $orgId,
        'name' => 'Null WS Test',
        'is_default' => false,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::factory()->create();

    DB::table('organization_user')->insert([
        'organization_id' => $orgId,
        'user_id' => $user->id,
        'last_workspace_id' => $wsId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Delete workspace — last_workspace_id should be nullified
    DB::table('workspaces')->where('id', $wsId)->delete();

    $pivot = DB::table('organization_user')
        ->where('organization_id', $orgId)
        ->where('user_id', $user->id)
        ->first();

    expect($pivot->last_workspace_id)->toBeNull();
});
