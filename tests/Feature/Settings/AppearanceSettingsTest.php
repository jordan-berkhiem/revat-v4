<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create(['name' => 'Test Org']);
    $workspace = new Workspace(['name' => 'Default']);
    $workspace->organization_id = $this->org->id;
    $workspace->is_default = true;
    $workspace->save();

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->organizations()->attach($this->org->id);
    $this->user->current_organization_id = $this->org->id;
    $this->user->save();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $this->user->assignRole('owner');
});

it('loads appearance settings page', function () {
    $this->actingAs($this->user)
        ->get(route('settings.appearance'))
        ->assertOk()
        ->assertSee('Appearance');
});

it('displays theme toggle buttons', function () {
    $this->actingAs($this->user)
        ->get(route('settings.appearance'))
        ->assertOk()
        ->assertSee('Light')
        ->assertSee('Dark')
        ->assertSee('System');
});
