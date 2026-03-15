<?php

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;

it('returns associated plan model', function () {
    $plan = Plan::factory()->starter()->create();
    $org = Organization::create(['name' => 'Test Org']);
    $org->plan_id = $plan->id;
    $org->save();

    $org->refresh();

    expect($org->plan)->not->toBeNull()
        ->and($org->plan->slug)->toBe('starter');
});

it('returns isOnFreePlan true when plan_id is null', function () {
    $org = Organization::create(['name' => 'No Plan Org']);

    expect($org->isOnFreePlan())->toBeTrue();
});

it('returns isOnFreePlan true when plan slug is free', function () {
    $plan = Plan::factory()->free()->create();
    $org = Organization::create(['name' => 'Free Org']);
    $org->plan_id = $plan->id;
    $org->save();

    expect($org->isOnFreePlan())->toBeTrue();
});

it('returns isOnFreePlan false when on paid plan', function () {
    $plan = Plan::factory()->growth()->create();
    $org = Organization::create(['name' => 'Paid Org']);
    $org->plan_id = $plan->id;
    $org->save();

    expect($org->isOnFreePlan())->toBeFalse();
});

it('returns none subscription status when no subscription exists', function () {
    $org = Organization::create(['name' => 'No Sub']);

    expect($org->subscriptionStatus())->toBe(SubscriptionStatus::None);
});

it('returns active subscription status for active subscription', function () {
    $org = Organization::create(['name' => 'Active Sub']);

    // Create a subscription record directly
    $org->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_active',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
    ]);

    expect($org->subscriptionStatus())->toBe(SubscriptionStatus::Active);
});

it('returns trialing subscription status for trial subscription', function () {
    $org = Organization::create(['name' => 'Trial Sub']);

    $org->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_trial',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_test',
        'trial_ends_at' => now()->addDays(14),
    ]);

    expect($org->subscriptionStatus())->toBe(SubscriptionStatus::Trialing);
});

it('returns past_due subscription status', function () {
    $org = Organization::create(['name' => 'Past Due Sub']);

    $org->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_past_due',
        'stripe_status' => 'past_due',
        'stripe_price' => 'price_test',
    ]);

    expect($org->subscriptionStatus())->toBe(SubscriptionStatus::PastDue);
});

it('returns grace_period subscription status when canceled with future ends_at', function () {
    $org = Organization::create(['name' => 'Grace Period Sub']);

    $org->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_grace',
        'stripe_status' => 'active',
        'stripe_price' => 'price_test',
        'ends_at' => now()->addDays(30),
    ]);

    expect($org->subscriptionStatus())->toBe(SubscriptionStatus::GracePeriod);
});

it('has cashier billing methods available', function () {
    $org = Organization::create(['name' => 'Cashier Org']);

    // Verify key Cashier methods exist on the Billable model
    expect(method_exists($org, 'subscription'))->toBeTrue()
        ->and(method_exists($org, 'subscriptions'))->toBeTrue()
        ->and(method_exists($org, 'subscribed'))->toBeTrue()
        ->and(method_exists($org, 'onTrial'))->toBeTrue()
        ->and(method_exists($org, 'createOrGetStripeCustomer'))->toBeTrue();
});
