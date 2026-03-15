<?php

use App\Filament\Resources\OrganizationResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WorkspaceResource;
use App\Models\Admin;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = Admin::factory()->create();

    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default WS']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->organizations()->attach($this->org->id);
    $this->user->current_organization_id = $this->org->id;
    $this->user->save();
    $this->workspace->users()->attach($this->user->id);

    // Set the admin panel as current
    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ── Organization Resource ─────────────────────────────────────────────

it('loads organization list page', function () {
    Livewire::actingAs($this->admin, 'admin')
        ->test(OrganizationResource\Pages\ListOrganizations::class)
        ->assertSuccessful();
})->group('filament');

it('loads organization view page with correct data', function () {
    Livewire::actingAs($this->admin, 'admin')
        ->test(OrganizationResource\Pages\ViewOrganization::class, [
            'record' => $this->org->getRouteKey(),
        ])
        ->assertSuccessful();
})->group('filament');

it('saves organization edit', function () {
    Livewire::actingAs($this->admin, 'admin')
        ->test(OrganizationResource\Pages\EditOrganization::class, [
            'record' => $this->org->getRouteKey(),
        ])
        ->fillForm([
            'name' => 'Updated Org Name',
            'support_access_enabled' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->org->refresh();
    expect($this->org->name)->toBe('Updated Org Name');
    expect($this->org->support_access_enabled)->toBeTrue();
})->group('filament');

// ── User Resource ─────────────────────────────────────────────────────

it('loads user list page', function () {
    Livewire::actingAs($this->admin, 'admin')
        ->test(UserResource\Pages\ListUsers::class)
        ->assertSuccessful();
})->group('filament');

it('loads user view page with correct data', function () {
    Livewire::actingAs($this->admin, 'admin')
        ->test(UserResource\Pages\ViewUser::class, [
            'record' => $this->user->getRouteKey(),
        ])
        ->assertSuccessful();
})->group('filament');

it('saves user edit', function () {
    Livewire::actingAs($this->admin, 'admin')
        ->test(UserResource\Pages\EditUser::class, [
            'record' => $this->user->getRouteKey(),
        ])
        ->fillForm([
            'name' => 'Updated User',
            'email' => 'updated@example.com',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->user->refresh();
    expect($this->user->name)->toBe('Updated User');
    expect($this->user->email)->toBe('updated@example.com');
})->group('filament');

// ── Workspace Resource ────────────────────────────────────────────────

it('loads workspace list page', function () {
    Livewire::actingAs($this->admin, 'admin')
        ->test(WorkspaceResource\Pages\ListWorkspaces::class)
        ->assertSuccessful();
})->group('filament');

it('loads workspace view page with correct data', function () {
    Livewire::actingAs($this->admin, 'admin')
        ->test(WorkspaceResource\Pages\ViewWorkspace::class, [
            'record' => $this->workspace->getRouteKey(),
        ])
        ->assertSuccessful();
})->group('filament');

// ── Access Control ────────────────────────────────────────────────────

it('denies non-admin users access to admin panel', function () {
    $regularUser = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($regularUser)
        ->get('/admin');

    $response->assertRedirect();
})->group('filament');
