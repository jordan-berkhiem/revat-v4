<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\PlanEnforcement\PlanEnforcementService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Cashier\Exceptions\IncompletePayment;
use Laravel\Cashier\Exceptions\SubscriptionUpdateFailure;
use Symfony\Component\HttpFoundation\Response;

class BillingController extends Controller
{
    public function __construct(
        private PlanEnforcementService $enforcement,
    ) {}

    /**
     * Initiate Stripe Checkout for a plan subscription.
     */
    public function checkout(Request $request): RedirectResponse|Response
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_period' => ['required', 'in:monthly,yearly'],
        ]);

        $organization = $request->user()->currentOrganization;
        $plan = Plan::findOrFail($validated['plan_id']);

        // Prevent double-subscription
        if ($organization->subscribed('default')) {
            return redirect()->route('billing')
                ->with('error', 'You already have an active subscription. Use plan swap to change plans.');
        }

        $priceId = $validated['billing_period'] === 'monthly'
            ? $plan->stripe_price_monthly
            : $plan->stripe_price_yearly;

        try {
            return $organization
                ->newSubscription('default', $priceId)
                ->checkout([
                    'success_url' => route('billing').'?checkout=success',
                    'cancel_url' => route('billing').'?checkout=cancelled',
                ]);
        } catch (IncompletePayment $e) {
            return redirect()->route('cashier.payment', [$e->payment->id, 'redirect' => route('billing')]);
        }
    }

    /**
     * Swap to a different plan.
     */
    public function swap(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'exists:plans,id'],
            'billing_period' => ['required', 'in:monthly,yearly'],
        ]);

        $organization = $request->user()->currentOrganization;
        $plan = Plan::findOrFail($validated['plan_id']);

        $priceId = $validated['billing_period'] === 'monthly'
            ? $plan->stripe_price_monthly
            : $plan->stripe_price_yearly;

        // Determine if this is an upgrade or downgrade
        $currentPlan = $organization->plan;
        $isDowngrade = $currentPlan && $plan->sort_order < $currentPlan->sort_order;

        if ($isDowngrade) {
            // Wrap downgrade check and swap in a transaction with pessimistic lock
            $result = DB::transaction(function () use ($organization, $plan, $priceId) {
                // Pessimistic lock on organization row
                $organization = $organization->lockForUpdate()->find($organization->id);

                if (! $this->enforcement->canDowngradeTo($organization, $plan)) {
                    return 'blocked';
                }

                $organization->subscription('default')
                    ->swap($priceId);

                $organization->plan_id = $plan->id;
                $organization->save();

                return 'success';
            });

            if ($result === 'blocked') {
                return redirect()->route('billing')
                    ->with('error', 'Cannot downgrade: your current usage exceeds the target plan limits. Please reduce usage first.');
            }
        } else {
            // Upgrade: use always_invoice proration for immediate charge
            try {
                $organization->subscription('default')
                    ->setProrationBehavior('always_invoice')
                    ->swap($priceId);

                $organization->plan_id = $plan->id;
                $organization->save();
            } catch (SubscriptionUpdateFailure $e) {
                return redirect()->route('cashier.payment', [
                    $e->payment->id,
                    'redirect' => route('billing'),
                ]);
            }
        }

        return redirect()->route('billing')
            ->with('success', 'Your plan has been updated successfully.');
    }

    /**
     * Cancel the subscription (enters grace period).
     */
    public function cancel(Request $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization;

        $organization->subscription('default')->cancel();

        return redirect()->route('billing')
            ->with('success', 'Your subscription has been cancelled. You will retain access until the end of your billing period.');
    }

    /**
     * Resume a cancelled subscription during grace period.
     */
    public function resume(Request $request): RedirectResponse
    {
        $organization = $request->user()->currentOrganization;

        $organization->subscription('default')->resume();

        return redirect()->route('billing')
            ->with('success', 'Your subscription has been resumed.');
    }

    /**
     * Redirect to Stripe Customer Portal.
     */
    public function portal(Request $request): RedirectResponse|Response
    {
        return $request->user()->currentOrganization
            ->redirectToBillingPortal(route('billing'));
    }
}
