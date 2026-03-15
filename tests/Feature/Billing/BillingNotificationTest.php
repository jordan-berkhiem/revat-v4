<?php

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\PaymentActionRequiredNotification;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionExpiringNotification;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->plan = Plan::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'max_users' => 10,
        'max_workspaces' => 5,
        'max_integrations_per_workspace' => 20,
        'is_visible' => true,
        'sort_order' => 1,
    ]);

    $this->org = Organization::create(['name' => 'Test Org']);
    $this->org->plan_id = $this->plan->id;
    $this->org->save();

    $this->owner = User::factory()->create(['email_verified_at' => now()]);
    $this->org->users()->attach($this->owner->id);
    $this->owner->current_organization_id = $this->org->id;
    $this->owner->save();

    app(PermissionRegistrar::class)->setPermissionsTeamId($this->org->id);
    $this->owner->assignRole('owner');
});

it('sends SubscriptionExpiringNotification with correct content', function () {
    Notification::fake();

    $this->owner->notify(new SubscriptionExpiringNotification($this->org));

    Notification::assertSentTo($this->owner, SubscriptionExpiringNotification::class, function ($notification) {
        return $notification->organization->id === $this->org->id;
    });
});

it('sends PaymentFailedNotification with correct content', function () {
    Notification::fake();

    $this->owner->notify(new PaymentFailedNotification($this->org));

    Notification::assertSentTo($this->owner, PaymentFailedNotification::class, function ($notification) {
        return $notification->organization->id === $this->org->id;
    });
});

it('sends PaymentActionRequiredNotification with payment link', function () {
    Notification::fake();

    $this->owner->notify(new PaymentActionRequiredNotification($this->org, 'pi_test_123'));

    Notification::assertSentTo($this->owner, PaymentActionRequiredNotification::class, function ($notification) {
        return $notification->organization->id === $this->org->id
            && $notification->paymentId === 'pi_test_123';
    });
});

it('SubscriptionExpiringNotification renders mail', function () {
    $notification = new SubscriptionExpiringNotification($this->org);
    $mail = $notification->toMail($this->owner);

    expect($mail->subject)->toBe('Your subscription is expiring soon');
});

it('PaymentFailedNotification renders mail', function () {
    $notification = new PaymentFailedNotification($this->org);
    $mail = $notification->toMail($this->owner);

    expect($mail->subject)->toBe('Payment failed for your subscription');
});
