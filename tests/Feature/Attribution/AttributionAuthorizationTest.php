<?php

use App\Models\Effort;
use App\Models\Initiative;
use App\Models\Organization;
use App\Models\Program;
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

    $this->viewer = User::factory()->create(['email_verified_at' => now()]);
    $this->viewer->organizations()->attach($this->org->id);
    $this->viewer->current_organization_id = $this->org->id;
    $this->viewer->save();

    $this->workspace->users()->attach($this->viewer->id);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $this->viewer->assignRole('viewer');
});

it('viewer can view programs page', function () {
    $this->actingAs($this->viewer)
        ->get(route('attribution.programs'))
        ->assertOk();
});

it('viewer cannot create a program', function () {
    $this->actingAs($this->viewer);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('attribution.programs')
        ->call('openCreateModal')
        ->assertForbidden();
});

it('viewer cannot edit a program', function () {
    $program = Program::create([
        'name' => 'Test Program',
        'code' => 'TEST',
        'status' => 'active',
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->viewer);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('attribution.programs')
        ->call('openEditModal', $program->id)
        ->assertForbidden();
});

it('viewer cannot delete a program', function () {
    $program = Program::create([
        'name' => 'Test Program',
        'code' => 'TEST',
        'status' => 'active',
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->viewer);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('attribution.programs')
        ->call('delete', $program->id)
        ->assertForbidden();

    $this->assertDatabaseHas('programs', ['id' => $program->id]);
});

it('viewer can view initiatives page', function () {
    $this->actingAs($this->viewer)
        ->get(route('attribution.initiatives'))
        ->assertOk();
});

it('viewer cannot create an initiative', function () {
    $this->actingAs($this->viewer);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('attribution.initiatives')
        ->call('openCreateModal')
        ->assertForbidden();
});

it('viewer cannot delete an initiative', function () {
    $program = Program::create([
        'name' => 'Test Program',
        'code' => 'TEST',
        'status' => 'active',
        'workspace_id' => $this->workspace->id,
    ]);
    $initiative = Initiative::create([
        'name' => 'Test Initiative',
        'code' => 'TEST-INIT',
        'status' => 'active',
        'program_id' => $program->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->viewer);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('attribution.initiatives')
        ->call('delete', $initiative->id)
        ->assertForbidden();

    $this->assertDatabaseHas('initiatives', ['id' => $initiative->id]);
});

it('viewer can view efforts page', function () {
    $this->actingAs($this->viewer)
        ->get(route('attribution.efforts'))
        ->assertOk();
});

it('viewer cannot create an effort', function () {
    $this->actingAs($this->viewer);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('attribution.efforts')
        ->call('openCreateModal')
        ->assertForbidden();
});

it('viewer cannot delete an effort', function () {
    $program = Program::create([
        'name' => 'Test Program',
        'code' => 'TEST',
        'status' => 'active',
        'workspace_id' => $this->workspace->id,
    ]);
    $initiative = Initiative::create([
        'name' => 'Test Initiative',
        'code' => 'TEST-INIT',
        'status' => 'active',
        'program_id' => $program->id,
        'workspace_id' => $this->workspace->id,
    ]);
    $effort = Effort::create([
        'name' => 'Test Effort',
        'code' => 'TEST-EFF',
        'status' => 'active',
        'initiative_id' => $initiative->id,
        'workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($this->viewer);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('attribution.efforts')
        ->call('delete', $effort->id)
        ->assertForbidden();

    $this->assertDatabaseHas('efforts', ['id' => $effort->id]);
});

it('viewer can view connectors page', function () {
    $this->actingAs($this->viewer)
        ->get(route('attribution.connectors'))
        ->assertOk();
});

it('viewer cannot create a connector', function () {
    $this->actingAs($this->viewer);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('attribution.connectors')
        ->call('openCreateModal')
        ->assertForbidden();
});

it('editor can create a program', function () {
    $editor = User::factory()->create(['email_verified_at' => now()]);
    $editor->organizations()->attach($this->org->id);
    $editor->current_organization_id = $this->org->id;
    $editor->save();
    $this->workspace->users()->attach($editor->id);

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $editor->assignRole('editor');

    $this->actingAs($editor);
    app(WorkspaceContext::class)->setWorkspace($this->workspace);

    Volt::test('attribution.programs')
        ->call('openCreateModal')
        ->assertSuccessful()
        ->set('name', 'New Program')
        ->set('code', 'NEW-PROG')
        ->call('save')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('programs', ['name' => 'New Program', 'code' => 'NEW-PROG']);
});
