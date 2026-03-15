<?php

use App\Models\Organization;
use App\Models\User;
use App\Models\Workspace;
use App\Notifications\EmailChangeNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
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

it('loads profile settings page', function () {
    $this->actingAs($this->user)
        ->get(route('settings.profile'))
        ->assertOk()
        ->assertSee('Profile');
});

it('updates name without password', function () {
    Volt::actingAs($this->user)
        ->test('settings.profile')
        ->set('name', 'New Name')
        ->set('email', $this->user->email)
        ->call('save');

    $this->user->refresh();
    expect($this->user->name)->toBe('New Name');
});

it('requires current password when changing email', function () {
    Volt::actingAs($this->user)
        ->test('settings.profile')
        ->set('name', $this->user->name)
        ->set('email', 'newemail@example.com')
        ->set('current_password', '')
        ->call('save')
        ->assertHasErrors('current_password');
});

it('updates email and sends verification', function () {
    Notification::fake();

    $oldEmail = $this->user->email;

    Volt::actingAs($this->user)
        ->test('settings.profile')
        ->set('name', $this->user->name)
        ->set('email', 'newemail@example.com')
        ->set('current_password', 'password1234')
        ->call('save')
        ->assertSet('emailChanged', true);

    $this->user->refresh();
    expect($this->user->email)->toBe('newemail@example.com')
        ->and($this->user->email_verified_at)->toBeNull();

    // Notification sent to old email
    Notification::assertSentOnDemand(
        EmailChangeNotification::class,
        function ($notification, $channels, $notifiable) use ($oldEmail) {
            return $notifiable->routes['mail'] === $oldEmail;
        }
    );
});

it('sends notification to old email on email change', function () {
    Notification::fake();

    $oldEmail = $this->user->email;

    Volt::actingAs($this->user)
        ->test('settings.profile')
        ->set('name', $this->user->name)
        ->set('email', 'changed@example.com')
        ->set('current_password', 'password1234')
        ->call('save');

    Notification::assertSentOnDemand(EmailChangeNotification::class);
});
