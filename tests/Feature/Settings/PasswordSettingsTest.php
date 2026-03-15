<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create(['name' => 'Test Org']);
    $workspace = new Workspace(['name' => 'Default']);
    $workspace->organization_id = $this->org->id;
    $workspace->is_default = true;
    $workspace->save();

    $this->user = User::factory()->create([
        'email_verified_at' => now(),
        'password' => 'password1234',
    ]);
    $this->user->organizations()->attach($this->org->id);
    $this->user->current_organization_id = $this->org->id;
    $this->user->save();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $this->user->assignRole('owner');
});

it('loads password settings page', function () {
    $this->actingAs($this->user)
        ->get(route('settings.password'))
        ->assertOk()
        ->assertSee('Password');
});

it('requires correct current password to update', function () {
    Volt::actingAs($this->user)
        ->test('settings.password')
        ->set('current_password', 'wrong-password')
        ->set('password', 'newpassword1234')
        ->set('password_confirmation', 'newpassword1234')
        ->call('save')
        ->assertHasErrors('current_password');
});

it('updates password with correct current password', function () {
    Volt::actingAs($this->user)
        ->test('settings.password')
        ->set('current_password', 'password1234')
        ->set('password', 'newpassword1234')
        ->set('password_confirmation', 'newpassword1234')
        ->call('save')
        ->assertHasNoErrors();

    $this->user->refresh();
    expect(Hash::check('newpassword1234', $this->user->password))->toBeTrue();
});

it('requires password confirmation to match', function () {
    Volt::actingAs($this->user)
        ->test('settings.password')
        ->set('current_password', 'password1234')
        ->set('password', 'newpassword1234')
        ->set('password_confirmation', 'different-password')
        ->call('save')
        ->assertHasErrors('password');
});
