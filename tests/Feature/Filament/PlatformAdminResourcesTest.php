<?php

use App\Enums\SupportLevel;
use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\IntegrationResource;
use App\Filament\Resources\PlanResource;
use App\Models\Admin;
use App\Models\Integration;
use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    $this->superAdmin = Admin::factory()->create(['support_level' => SupportLevel::Super]);
    $this->agentAdmin = Admin::factory()->create(['support_level' => SupportLevel::Agent]);

    $this->org = Organization::create(['name' => 'Test Org']);
    $this->workspace = new Workspace(['name' => 'Default WS']);
    $this->workspace->organization_id = $this->org->id;
    $this->workspace->is_default = true;
    $this->workspace->save();

    $this->integration = new Integration([
        'name' => 'Test Integration',
        'platform' => 'activecampaign',
        'is_active' => true,
        'data_types' => ['campaign_emails'],
        'sync_interval_minutes' => 60,
    ]);
    $this->integration->workspace_id = $this->workspace->id;
    $this->integration->organization_id = $this->org->id;
    $this->integration->save();

    $this->plan = Plan::create([
        'name' => 'Starter',
        'slug' => 'starter',
        'max_workspaces' => 3,
        'max_users' => 5,
        'max_integrations_per_workspace' => 2,
        'is_visible' => true,
        'sort_order' => 1,
    ]);

    Filament::setCurrentPanel(Filament::getPanel('admin'));
});

// ── Integration Resource ──────────────────────────────────────────────

it('loads integration list page', function () {
    Livewire::actingAs($this->superAdmin, 'admin')
        ->test(IntegrationResource\Pages\ListIntegrations::class)
        ->assertSuccessful();
})->group('filament');

it('loads integration view page with correct data', function () {
    Livewire::actingAs($this->superAdmin, 'admin')
        ->test(IntegrationResource\Pages\ViewIntegration::class, [
            'record' => $this->integration->getRouteKey(),
        ])
        ->assertSuccessful();
})->group('filament');

// ── Plan Resource ─────────────────────────────────────────────────────

it('loads plan list page', function () {
    Livewire::actingAs($this->superAdmin, 'admin')
        ->test(PlanResource\Pages\ListPlans::class)
        ->assertSuccessful();
})->group('filament');

it('creates a plan as super admin', function () {
    Livewire::actingAs($this->superAdmin, 'admin')
        ->test(PlanResource\Pages\CreatePlan::class)
        ->fillForm([
            'name' => 'Enterprise',
            'slug' => 'enterprise',
            'max_workspaces' => 10,
            'max_users' => 50,
            'max_integrations_per_workspace' => 10,
            'is_visible' => true,
            'sort_order' => 2,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Plan::where('slug', 'enterprise')->exists())->toBeTrue();
})->group('filament');

it('edits a plan as super admin', function () {
    Livewire::actingAs($this->superAdmin, 'admin')
        ->test(PlanResource\Pages\EditPlan::class, [
            'record' => $this->plan->getRouteKey(),
        ])
        ->fillForm([
            'name' => 'Updated Plan',
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    $this->plan->refresh();
    expect($this->plan->name)->toBe('Updated Plan');
})->group('filament');

it('prevents agent admin from creating plans', function () {
    // Super admin can create
    Livewire::actingAs($this->superAdmin, 'admin');
    expect(PlanResource::canCreate())->toBeTrue();

    // Agent admin cannot create
    Livewire::actingAs($this->agentAdmin, 'admin');
    expect(PlanResource::canCreate())->toBeFalse();
})->group('filament');

it('prevents agent admin from editing plans', function () {
    Livewire::actingAs($this->agentAdmin, 'admin');
    expect(PlanResource::canEdit($this->plan))->toBeFalse();
})->group('filament');

it('prevents agent admin from deleting plans', function () {
    Livewire::actingAs($this->agentAdmin, 'admin');
    expect(PlanResource::canDelete($this->plan))->toBeFalse();
})->group('filament');

// ── Audit Log Resource ────────────────────────────────────────────────

it('loads audit log list page', function () {
    Livewire::actingAs($this->superAdmin, 'admin')
        ->test(AuditLogResource\Pages\ListAuditLogs::class)
        ->assertSuccessful();
})->group('filament');

// ── Access Control ────────────────────────────────────────────────────

it('denies non-admin users access to admin panel', function () {
    $regularUser = User::factory()->create(['email_verified_at' => now()]);

    $response = $this->actingAs($regularUser)
        ->get('/admin');

    $response->assertRedirect();
})->group('filament');
