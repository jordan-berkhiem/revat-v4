<?php

use App\Models\Organization;
use App\Models\Plan;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    $this->plan = Plan::create([
        'name' => 'Pro',
        'slug' => 'pro',
        'stripe_price_monthly' => 'price_pro_monthly',
        'stripe_price_yearly' => 'price_pro_yearly',
        'max_users' => 10,
        'max_workspaces' => 5,
        'max_integrations_per_workspace' => 20,
        'is_visible' => true,
        'sort_order' => 1,
    ]);

    $this->starterPlan = Plan::create([
        'name' => 'Starter',
        'slug' => 'starter',
        'stripe_price_monthly' => 'price_starter_monthly',
        'stripe_price_yearly' => 'price_starter_yearly',
        'max_users' => 3,
        'max_workspaces' => 2,
        'max_integrations_per_workspace' => 5,
        'is_visible' => true,
        'sort_order' => 0,
    ]);

    $this->org = Organization::create(['name' => 'Test Org']);
    $this->org->stripe_id = 'cus_test_123';
    $this->org->save();

    // Disable webhook signature verification for testing
    config(['cashier.webhook.secret' => null]);
});

function subscriptionItem(string $priceId, string $itemId = 'si_test_1', string $product = 'prod_test'): array
{
    return [
        'id' => $itemId,
        'price' => ['id' => $priceId, 'product' => $product],
        'quantity' => 1,
    ];
}

it('sets plan_id on customer.subscription.created', function () {
    DB::table('subscriptions')->insert([
        'organization_id' => $this->org->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro_monthly',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'id' => 'sub_test_123',
                'customer' => 'cus_test_123',
                'status' => 'active',
                'items' => ['data' => [subscriptionItem('price_pro_monthly')]],
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
            ],
        ],
    ];

    $response = $this->postJson('/stripe/webhook', $payload);

    $response->assertSuccessful();
    expect($this->org->fresh()->plan_id)->toBe($this->plan->id);
});

it('updates plan_id on customer.subscription.updated with price change', function () {
    $this->org->plan_id = $this->plan->id;
    $this->org->save();

    $subId = DB::table('subscriptions')->insertGetId([
        'organization_id' => $this->org->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro_monthly',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    // Insert subscription item for Cashier
    DB::table('subscription_items')->insert([
        'subscription_id' => $subId,
        'stripe_id' => 'si_test_1',
        'stripe_product' => 'prod_pro',
        'stripe_price' => 'price_pro_monthly',
        'quantity' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_test_123',
                'customer' => 'cus_test_123',
                'status' => 'active',
                'items' => ['data' => [subscriptionItem('price_starter_monthly', 'si_test_1', 'prod_starter')]],
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
            ],
        ],
    ];

    $response = $this->postJson('/stripe/webhook', $payload);

    $response->assertSuccessful();
    expect($this->org->fresh()->plan_id)->toBe($this->starterPlan->id);
});

it('resets plan_id on customer.subscription.deleted', function () {
    $this->org->plan_id = $this->plan->id;
    $this->org->save();

    DB::table('subscriptions')->insert([
        'organization_id' => $this->org->id,
        'type' => 'default',
        'stripe_id' => 'sub_test_123',
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_pro_monthly',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_test_123',
                'customer' => 'cus_test_123',
                'status' => 'canceled',
                'items' => ['data' => [subscriptionItem('price_pro_monthly')]],
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
            ],
        ],
    ];

    $response = $this->postJson('/stripe/webhook', $payload);

    $response->assertSuccessful();
    expect($this->org->fresh()->plan_id)->toBeNull();
});

it('sets plan_id on checkout.session.completed when payment is paid', function () {
    DB::table('subscriptions')->insert([
        'organization_id' => $this->org->id,
        'type' => 'default',
        'stripe_id' => 'sub_checkout_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro_monthly',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_123',
                'customer' => 'cus_test_123',
                'payment_status' => 'paid',
                'subscription' => 'sub_checkout_123',
            ],
        ],
    ];

    $response = $this->postJson('/stripe/webhook', $payload);

    $response->assertSuccessful();
    expect($this->org->fresh()->plan_id)->toBe($this->plan->id);
});

it('logs invoice.payment_failed webhook', function () {
    $payload = [
        'type' => 'invoice.payment_failed',
        'data' => [
            'object' => [
                'id' => 'in_test_123',
                'customer' => 'cus_test_123',
            ],
        ],
    ];

    $this->postJson('/stripe/webhook', $payload)->assertSuccessful();
});

it('logs invoice.payment_action_required webhook', function () {
    $payload = [
        'type' => 'invoice.payment_action_required',
        'data' => [
            'object' => [
                'id' => 'in_test_456',
                'customer' => 'cus_test_123',
                'payment_intent' => 'pi_test_123',
            ],
        ],
    ];

    $this->postJson('/stripe/webhook', $payload)->assertSuccessful();
});

it('handles portal-initiated plan swap via subscription.updated webhook', function () {
    $this->org->plan_id = $this->starterPlan->id;
    $this->org->save();

    $subId = DB::table('subscriptions')->insertGetId([
        'organization_id' => $this->org->id,
        'type' => 'default',
        'stripe_id' => 'sub_portal_123',
        'stripe_status' => 'active',
        'stripe_price' => 'price_starter_monthly',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('subscription_items')->insert([
        'subscription_id' => $subId,
        'stripe_id' => 'si_portal_1',
        'stripe_product' => 'prod_starter',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'type' => 'customer.subscription.updated',
        'data' => [
            'object' => [
                'id' => 'sub_portal_123',
                'customer' => 'cus_test_123',
                'status' => 'active',
                'items' => ['data' => [subscriptionItem('price_pro_monthly', 'si_portal_1', 'prod_pro')]],
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
            ],
        ],
    ];

    $response = $this->postJson('/stripe/webhook', $payload);

    $response->assertSuccessful();
    expect($this->org->fresh()->plan_id)->toBe($this->plan->id);
});

it('handles duplicate webhooks idempotently', function () {
    DB::table('subscriptions')->insert([
        'organization_id' => $this->org->id,
        'type' => 'default',
        'stripe_id' => 'sub_idempotent',
        'stripe_status' => 'active',
        'stripe_price' => 'price_pro_monthly',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $payload = [
        'type' => 'customer.subscription.created',
        'data' => [
            'object' => [
                'id' => 'sub_idempotent',
                'customer' => 'cus_test_123',
                'status' => 'active',
                'items' => ['data' => [subscriptionItem('price_pro_monthly')]],
                'current_period_start' => now()->timestamp,
                'current_period_end' => now()->addMonth()->timestamp,
            ],
        ],
    ];

    $this->postJson('/stripe/webhook', $payload)->assertSuccessful();
    $this->postJson('/stripe/webhook', $payload)->assertSuccessful();

    expect($this->org->fresh()->plan_id)->toBe($this->plan->id);
});
