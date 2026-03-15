<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->plan = Plan::create([
        'name' => 'Starter',
        'slug' => 'starter',
        'max_users' => 5,
        'max_workspaces' => 3,
        'max_integrations_per_workspace' => 5,
        'is_visible' => true,
        'sort_order' => 1,
    ]);

    $this->org = Organization::create(['name' => 'Test Org']);
    $this->org->plan_id = $this->plan->id;
    $this->org->save();

    $workspace = new Workspace(['name' => 'Default']);
    $workspace->organization_id = $this->org->id;
    $workspace->is_default = true;
    $workspace->save();

    $this->user = User::factory()->create(['email_verified_at' => now()]);
    $this->user->organizations()->attach($this->org->id);
    $this->user->current_organization_id = $this->org->id;
    $this->user->save();
});

it('loads billing index page for authenticated user', function () {
    $this->actingAs($this->user)
        ->get('/billing')
        ->assertOk();
});

it('shows current plan on billing index', function () {
    $this->actingAs($this->user)
        ->get('/billing')
        ->assertOk()
        ->assertSee('Starter');
});

it('displays all visible plans on subscribe page', function () {
    Plan::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'max_users' => 20,
        'max_workspaces' => 10,
        'max_integrations_per_workspace' => 20,
        'is_visible' => true,
        'sort_order' => 2,
    ]);

    Plan::create([
        'name' => 'Hidden Plan',
        'slug' => 'hidden',
        'max_users' => 100,
        'max_workspaces' => 50,
        'max_integrations_per_workspace' => 50,
        'is_visible' => false,
        'sort_order' => 3,
    ]);

    $this->actingAs($this->user)
        ->get('/billing/subscribe')
        ->assertOk()
        ->assertSee('Starter')
        ->assertSee('Pro')
        ->assertDontSee('Hidden Plan');
});

it('shows usage summary on billing index', function () {
    $this->actingAs($this->user)
        ->get('/billing')
        ->assertOk()
        ->assertSee('Usage');
});
