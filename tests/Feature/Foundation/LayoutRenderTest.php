<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;

it('renders auth-split layout without errors', function () {
    $response = $this->get('/_layouts/auth-split');
    $response->assertOk();
    $response->assertSee('Sign in to your account');
});

it('renders auth-card layout without errors', function () {
    $response = $this->get('/_layouts/auth-card');
    $response->assertOk();
    $response->assertSee('Reset your password');
});

it('renders auth-simple layout without errors', function () {
    $response = $this->get('/_layouts/auth-simple');
    $response->assertOk();
    $response->assertSee('Verify your email');
});

it('renders onboarding layout without errors', function () {
    $response = $this->get('/_layouts/onboarding');
    $response->assertOk();
    $response->assertSee('Set up your organization');
});

it('renders app layout without errors', function () {
    $response = $this->get('/_layouts/app');
    $response->assertOk();
    $response->assertSee('Dashboard');
});

it('renders app layout with authenticated user and org/workspace data', function () {
    $user = User::factory()->create();
    $org = Organization::create(['name' => 'Layout Test Org', 'timezone' => 'UTC']);
    $user->organizations()->attach($org);
    $user->switchOrganization($org);

    $ws = Workspace::forceCreate([
        'organization_id' => $org->id,
        'name' => 'Layout WS',
        'is_default' => true,
    ]);
    $user->workspaces()->attach($ws);

    $response = $this->actingAs($user)->get('/_layouts/app');

    $response->assertOk();
    $response->assertSee('Dashboard');
    $response->assertSee('Layout Test Org');
});

it('includes logo images in auth layouts', function () {
    $response = $this->get('/_layouts/auth-split');
    $response->assertOk();
    $response->assertSee('Logo-Light.svg');

    $response = $this->get('/_layouts/auth-card');
    $response->assertOk();
    $response->assertSee('Logo-Clear.svg');
});

it('includes favicon link', function () {
    $response = $this->get('/_layouts/auth-card');
    $response->assertOk();
    $response->assertSee('favicon.png');
});
