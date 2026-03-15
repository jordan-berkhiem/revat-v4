<?php

use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create(['name' => 'Test Org', 'timezone' => 'UTC']);
    $workspace = new Workspace(['name' => 'Default']);
    $workspace->organization_id = $this->org->id;
    $workspace->is_default = true;
    $workspace->save();

    $this->owner = User::factory()->create(['email_verified_at' => now()]);
    $this->owner->organizations()->attach($this->org->id);
    $this->owner->current_organization_id = $this->org->id;
    $this->owner->save();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $this->owner->assignRole('owner');
});

it('loads organization settings page for owner', function () {
    $this->actingAs($this->owner)
        ->get(route('settings.organization'))
        ->assertOk()
        ->assertSee('Organization');
});

it('updates organization name and timezone', function () {
    Volt::actingAs($this->owner)
        ->test('settings.organization')
        ->set('name', 'Updated Org')
        ->set('timezone', 'America/New_York')
        ->call('save')
        ->assertHasNoErrors();

    $this->org->refresh();
    expect($this->org->name)->toBe('Updated Org')
        ->and($this->org->timezone)->toBe('America/New_York');
});

it('denies access to non-admin users', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->organizations()->attach($this->org->id);
    $viewer->current_organization_id = $this->org->id;
    $viewer->save();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->get(route('settings.organization'))
        ->assertForbidden();
});

it('denies support-access page to non-billing users', function () {
    $editor = User::factory()->create(['email_verified_at' => now()]);
    $editor->organizations()->attach($this->org->id);
    $editor->current_organization_id = $this->org->id;
    $editor->save();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $editor->assignRole('editor');

    $this->actingAs($editor)
        ->get(route('settings.support-access'))
        ->assertForbidden();
});

it('loads support-access page for owner', function () {
    $this->actingAs($this->owner)
        ->get(route('settings.support-access'))
        ->assertOk()
        ->assertSee('Support Access');
});

it('toggles support access and updates the organization', function () {
    expect($this->org->support_access_enabled)->toBeFalse();

    Volt::actingAs($this->owner)
        ->test('settings.support-access')
        ->call('toggleSupportAccess');

    $this->org->refresh();
    expect($this->org->support_access_enabled)->toBeTrue();
});

it('logs support access toggle via audit service', function () {
    Volt::actingAs($this->owner)
        ->test('settings.support-access')
        ->call('toggleSupportAccess');

    $log = AuditLog::where('action', 'organization.support_access_toggled')->first();

    expect($log)->not->toBeNull()
        ->and($log->organization_id)->toBe($this->org->id)
        ->and($log->metadata['enabled'])->toBeTrue()
        ->and($log->metadata['old_value'])->toBeFalse()
        ->and($log->metadata['new_value'])->toBeTrue();
});
