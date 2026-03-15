<?php

namespace App\Services\PlanEnforcement;

use App\Enums\SubscriptionStatus;
use App\Models\Organization;
use App\Models\Plan;

/**
 * Resolves the effective plan for an organization, handling edge cases
 * like grace periods, ended subscriptions, and incomplete_expired status.
 */
class PlanResolver
{
    /**
     * Resolve the effective plan for the organization.
     *
     * Returns the organization's plan if active/valid, or null for free-tier fallback.
     */
    public static function resolve(Organization $organization): ?Plan
    {
        $plan = $organization->plan;

        if (! $plan) {
            return null;
        }

        $status = $organization->subscriptionStatus();

        // Grace period retains current plan limits
        if ($status === SubscriptionStatus::GracePeriod) {
            return $plan;
        }

        // Active, trialing, or past-due retain plan limits
        if (in_array($status, [
            SubscriptionStatus::Active,
            SubscriptionStatus::Trialing,
            SubscriptionStatus::PastDue,
        ], true)) {
            return $plan;
        }

        // Free plan slug always applies regardless of subscription status
        if ($plan->slug === 'free') {
            return $plan;
        }

        // Ended or incomplete_expired falls back to free-tier defaults
        if (in_array($status, [
            SubscriptionStatus::Ended,
            SubscriptionStatus::IncompleteExpired,
            SubscriptionStatus::Canceled,
        ], true)) {
            return null;
        }

        // No subscription at all — if plan is set, respect it (e.g., manually assigned)
        if ($status === SubscriptionStatus::None) {
            return $plan;
        }

        return null;
    }
}
