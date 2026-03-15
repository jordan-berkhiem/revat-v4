<?php

use App\Models\Integration;
use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Services\WorkspaceContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Livewire\Volt\Volt;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->organizations()->attach($this->org->id);
    $this->user->current_organization_id = $this->org->id;
    $this->user->save();

    $this->workspace->users()->attach($this->user->id);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $this->user->assignRole('owner');
});

it('renders integrations page successfully', function () {
    $this->actingAs($this->user)
        ->get(route('integrations'))
        ->assertOk()
        ->assertSee('Integrations')
        ->assertSee('Manage your data source connections');
});

it('shows empty state when no integrations', function () {
    $this->actingAs($this->user)
        ->get(route('integrations'))
        ->assertOk()
        ->assertSee('No integrations configured');
});

it('displays integration details', function () {
    $integration = new Integration([
        'name' => 'My ActiveCampaign',
        'platform' => 'activecampaign',
        'data_types' => ['campaign_emails', 'campaign_email_clicks'],
        'is_active' => true,
        'sync_interval_minutes' => 60,
    ]);
    $integration->workspace_id = $this->workspace->id;
    $integration->organization_id = $this->org->id;
    $integration->last_sync_status = 'completed';
    $integration->last_synced_at = now()->subMinutes(30);
    $integration->save();

    $this->actingAs($this->user)
        ->get(route('integrations'))
        ->assertOk()
        ->assertSee('My ActiveCampaign')
        ->assertSee('ActiveCampaign')
        ->assertSee('Active')
        ->assertSee('Completed');
});

it('shows inactive badge for inactive integration', function () {
    $integration = new Integration([
        'name' => 'Disabled Integration',
        'platform' => 'voluum',
        'data_types' => ['conversion_sales'],
        'is_active' => false,
        'sync_interval_minutes' => 60,
    ]);
    $integration->workspace_id = $this->workspace->id;
    $integration->organization_id = $this->org->id;
    $integration->save();

    $this->actingAs($this->user)
        ->get(route('integrations'))
        ->assertOk()
        ->assertSee('Disabled Integration')
        ->assertSee('Inactive');
});

it('requires authentication', function () {
    $this->get(route('integrations'))
        ->assertRedirect(route('login'));
});

it('shows add integration button', function () {
    $this->actingAs($this->user)
        ->get(route('integrations'))
        ->assertOk()
        ->assertSee('Add Integration');
});

it('can create an integration via the modal', function () {
    $this->actingAs($this->user);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('integrations.index')
        ->call('openCreateModal')
        ->assertSet('showCreateModal', true)
        ->set('platform', 'activecampaign')
        ->set('name', 'Test AC Integration')
        ->set('credentials.api_url', 'https://test.api-us1.com')
        ->set('credentials.api_key', 'test-key-123')
        ->set('selectedDataTypes', ['campaign_emails'])
        ->set('syncInterval', 120)
        ->call('createIntegration')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('integrations', [
        'name' => 'Test AC Integration',
        'platform' => 'activecampaign',
        'workspace_id' => $this->workspace->id,
        'organization_id' => $this->org->id,
        'sync_interval_minutes' => 120,
        'is_active' => true,
    ]);
});

it('validates required fields when creating integration', function () {
    $this->actingAs($this->user);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('integrations.index')
        ->call('openCreateModal')
        ->set('platform', '')
        ->set('name', '')
        ->call('createIntegration')
        ->assertHasErrors(['name', 'platform']);
});

it('validates credential fields for selected platform', function () {
    $this->actingAs($this->user);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('integrations.index')
        ->call('openCreateModal')
        ->set('platform', 'activecampaign')
        ->set('name', 'Test Integration')
        ->set('selectedDataTypes', ['campaign_emails'])
        ->call('createIntegration')
        ->assertHasErrors(['credentials.api_url', 'credentials.api_key']);
});

it('requires at least one data type', function () {
    $this->actingAs($this->user);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('integrations.index')
        ->call('openCreateModal')
        ->set('platform', 'activecampaign')
        ->set('name', 'Test Integration')
        ->set('credentials.api_url', 'https://test.api-us1.com')
        ->set('credentials.api_key', 'test-key-123')
        ->set('selectedDataTypes', [])
        ->call('createIntegration')
        ->assertHasErrors(['selectedDataTypes']);
});

it('resets form fields when opening create modal', function () {
    $this->actingAs($this->user);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('integrations.index')
        ->set('name', 'leftover name')
        ->set('platform', 'voluum')
        ->call('openCreateModal')
        ->assertSet('name', '')
        ->assertSet('platform', '')
        ->assertSet('credentials', [])
        ->assertSet('selectedDataTypes', [])
        ->assertSet('syncInterval', 60);
});

it('updates available data types when platform changes', function () {
    $this->actingAs($this->user);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    $component = Volt::test('integrations.index')
        ->call('openCreateModal')
        ->set('platform', 'voluum');

    expect($component->get('selectedDataTypes'))->toBe(['conversion_sales']);
});

it('enforces integrate permission', function () {
    $viewer = User::factory()->create(['email_verified_at' => now()]);
    $viewer->organizations()->attach($this->org->id);
    $viewer->current_organization_id = $this->org->id;
    $viewer->save();

    $this->workspace->users()->attach($viewer->id);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $viewer->assignRole('viewer');

    $this->actingAs($viewer)
        ->get(route('integrations'))
        ->assertForbidden();
});
