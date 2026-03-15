<?php

use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);
});

it('creates all four roles', function () {
    expect(Role::where('guard_name', 'web')->pluck('name')->sort()->values()->all())
        ->toBe(['admin', 'editor', 'owner', 'viewer']);
});

it('creates all four permissions', function () {
    expect(Permission::where('guard_name', 'web')->pluck('name')->sort()->values()->all())
        ->toBe(['billing', 'integrate', 'manage', 'view']);
});

it('gives owner all permissions', function () {
    $role = Role::findByName('owner', 'web');

    expect($role->hasPermissionTo('billing'))->toBeTrue()
        ->and($role->hasPermissionTo('manage'))->toBeTrue()
        ->and($role->hasPermissionTo('integrate'))->toBeTrue()
        ->and($role->hasPermissionTo('view'))->toBeTrue();
});

it('gives admin all permissions except billing', function () {
    $role = Role::findByName('admin', 'web');

    expect($role->hasPermissionTo('billing'))->toBeFalse()
        ->and($role->hasPermissionTo('manage'))->toBeTrue()
        ->and($role->hasPermissionTo('integrate'))->toBeTrue()
        ->and($role->hasPermissionTo('view'))->toBeTrue();
});

it('gives editor integrate and view permissions', function () {
    $role = Role::findByName('editor', 'web');

    expect($role->hasPermissionTo('billing'))->toBeFalse()
        ->and($role->hasPermissionTo('manage'))->toBeFalse()
        ->and($role->hasPermissionTo('integrate'))->toBeTrue()
        ->and($role->hasPermissionTo('view'))->toBeTrue();
});

it('gives viewer only view permission', function () {
    $role = Role::findByName('viewer', 'web');

    expect($role->hasPermissionTo('billing'))->toBeFalse()
        ->and($role->hasPermissionTo('manage'))->toBeFalse()
        ->and($role->hasPermissionTo('integrate'))->toBeFalse()
        ->and($role->hasPermissionTo('view'))->toBeTrue();
});

it('is idempotent when run twice', function () {
    // Seeder already ran in beforeEach, run it again
    $this->seed(RolesAndPermissionsSeeder::class);

    expect(Role::where('guard_name', 'web')->count())->toBe(4)
        ->and(Permission::where('guard_name', 'web')->count())->toBe(4);
});
