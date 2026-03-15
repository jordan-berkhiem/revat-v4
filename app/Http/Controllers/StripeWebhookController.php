<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Plan;
use App\Models\User;
use App\Notifications\PaymentActionRequiredNotification;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Support\Facades\Log;
use Laravel\Cashier\Http\Controllers\WebhookController;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class StripeWebhookController extends WebhookController
{
    /**
     * Handle checkout session completed.
     */
    protected function handleCheckoutSessionCompleted(array $payload): Response
    {
        Log::info('Stripe webhook: checkout.session.completed', [
            'session_id' => $payload['data']['object']['id'] ?? null,
        ]);

        $session = $payload['data']['object'];

        if (($session['payment_status'] ?? null) !== 'paid') {
            return $this->successMethod();
        }

        $customerId = $session['customer'] ?? null;

        if (! $customerId) {
            return $this->successMethod();
        }

        $organization = Organization::where('stripe_id', $customerId)->first();

        if (! $organization) {
            return $this->successMethod();
        }

        // Only set plan_id if not already set by subscription.created
        if ($organization->plan_id === null) {
            $subscription = $session['subscription'] ?? null;

            if ($subscription) {
                $this->syncPlanFromSubscriptionId($organization, $subscription);
            }
        }

        return $this->successMethod();
    }

    /**
     * Handle customer subscription created.
     */
    protected function handleCustomerSubscriptionCreated(array $payload): Response
    {
        Log::info('Stripe webhook: customer.subscription.created', [
            'subscription_id' => $payload['data']['object']['id'] ?? null,
        ]);

        // Let Cashier handle the base subscription creation first
        $response = parent::handleCustomerSubscriptionCreated($payload);

        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'] ?? null;

        if (! $customerId) {
            return $response;
        }

        $organization = Organization::where('stripe_id', $customerId)->first();

        if (! $organization) {
            return $response;
        }

        $priceId = $this->extractPriceId($subscription);

        if ($priceId) {
            $plan = $this->resolvePlanFromStripePrice($priceId);

            if ($plan && $organization->plan_id !== $plan->id) {
                $organization->plan_id = $plan->id;
                $organization->save();
            }
        }

        return $response;
    }

    /**
     * Handle customer subscription updated.
     */
    protected function handleCustomerSubscriptionUpdated(array $payload): Response
    {
        Log::info('Stripe webhook: customer.subscription.updated', [
            'subscription_id' => $payload['data']['object']['id'] ?? null,
        ]);

        $response = parent::handleCustomerSubscriptionUpdated($payload);

        $subscription = $payload['data']['object'];
        $customerId = $subscription['customer'] ?? null;

        if (! $customerId) {
            return $response;
        }

        $organization = Organization::where('stripe_id', $customerId)->first();

        if (! $organization) {
            return $response;
        }

        // Handle price change (plan swap, including portal-initiated)
        $priceId = $this->extractPriceId($subscription);

        if ($priceId) {
            $plan = $this->resolvePlanFromStripePrice($priceId);

            if ($plan && $organization->plan_id !== $plan->id) {
                $organization->plan_id = $plan->id;
                $organization->save();
            }
        }

        // Handle cancellation without grace period
        $status = $subscription['status'] ?? null;

        if ($status === 'canceled' && ! isset($subscription['cancel_at'])) {
            $organization->plan_id = null;
            $organization->save();
        }

        return $response;
    }

    /**
     * Handle customer subscription deleted.
     */
    protected function handleCustomerSubscriptionDeleted(array $payload): Response
    {
        Log::info('Stripe webhook: customer.subscription.deleted', [
            'subscription_id' => $payload['data']['object']['id'] ?? null,
        ]);

        $response = parent::handleCustomerSubscriptionDeleted($payload);

        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! $customerId) {
            return $response;
        }

        $organization = Organization::where('stripe_id', $customerId)->first();

        if ($organization && $organization->plan_id !== null) {
            $organization->plan_id = null;
            $organization->save();
        }

        return $response;
    }

    /**
     * Handle invoice payment failed.
     */
    protected function handleInvoicePaymentFailed(array $payload): Response
    {
        Log::info('Stripe webhook: invoice.payment_failed', [
            'invoice_id' => $payload['data']['object']['id'] ?? null,
        ]);

        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! $customerId) {
            return $this->successMethod();
        }

        $organization = Organization::where('stripe_id', $customerId)->first();

        if (! $organization) {
            return $this->successMethod();
        }

        Log::warning('Payment failed for organization', [
            'organization_id' => $organization->id,
            'invoice_id' => $payload['data']['object']['id'] ?? null,
        ]);

        // Notify organization owner
        $owner = $this->resolveOrganizationOwner($organization);
        if ($owner) {
            $owner->notify(new PaymentFailedNotification($organization));
        }

        return $this->successMethod();
    }

    /**
     * Handle invoice payment action required (SCA/3DS).
     */
    protected function handleInvoicePaymentActionRequired(array $payload): Response
    {
        Log::info('Stripe webhook: invoice.payment_action_required', [
            'invoice_id' => $payload['data']['object']['id'] ?? null,
        ]);

        $customerId = $payload['data']['object']['customer'] ?? null;

        if (! $customerId) {
            return $this->successMethod();
        }

        $organization = Organization::where('stripe_id', $customerId)->first();

        if (! $organization) {
            return $this->successMethod();
        }

        Log::warning('Payment action required for organization', [
            'organization_id' => $organization->id,
            'payment_intent' => $payload['data']['object']['payment_intent'] ?? null,
        ]);

        // Notify organization owner
        $paymentId = $payload['data']['object']['payment_intent'] ?? '';
        $owner = $this->resolveOrganizationOwner($organization);
        if ($owner && $paymentId) {
            $owner->notify(new PaymentActionRequiredNotification($organization, $paymentId));
        }

        return $this->successMethod();
    }

    /**
     * Resolve the organization owner (user with 'owner' role, fallback to first user).
     */
    private function resolveOrganizationOwner(Organization $organization): ?User
    {
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);

        try {
            $owner = $organization->users()->role('owner')->first();
        } catch (\Throwable) {
            $owner = null;
        }

        return $owner ?? $organization->users()->first();
    }

    /**
     * Resolve a Plan by Stripe price ID.
     */
    public function resolvePlanFromStripePrice(string $priceId): ?Plan
    {
        return Plan::where('stripe_price_monthly', $priceId)
            ->orWhere('stripe_price_yearly', $priceId)
            ->first();
    }

    /**
     * Extract the primary price ID from a subscription object.
     */
    private function extractPriceId(array $subscription): ?string
    {
        return $subscription['items']['data'][0]['price']['id'] ?? null;
    }

    /**
     * Sync plan_id from a Stripe subscription ID.
     */
    private function syncPlanFromSubscriptionId(Organization $organization, string $subscriptionId): void
    {
        // Look up the local subscription to get the price
        $localSub = $organization->subscriptions()->where('stripe_id', $subscriptionId)->first();

        if ($localSub) {
            $plan = $this->resolvePlanFromStripePrice($localSub->stripe_price);

            if ($plan) {
                $organization->plan_id = $plan->id;
                $organization->save();
            }
        }
    }
}
