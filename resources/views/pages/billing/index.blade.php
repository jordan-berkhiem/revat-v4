<?php

use App\Services\PlanEnforcement\PlanEnforcementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public function getOrganizationProperty()
    {
        return Auth::user()->currentOrganization;
    }

    public function getPlanProperty()
    {
        return $this->organization?->plan;
    }

    public function getSubscriptionProperty()
    {
        return $this->organization?->subscription('default');
    }

    public function getEnforcementStatusProperty()
    {
        if (! $this->organization) {
            return [];
        }

        return app(PlanEnforcementService::class)->getEnforcementStatus($this->organization);
    }

    public function getSubscriptionStatusProperty()
    {
        return $this->organization?->subscriptionStatus();
    }

    public function getIsOnGracePeriodProperty(): bool
    {
        return $this->subscription?->onGracePeriod() ?? false;
    }

    public function getIsPastDueProperty(): bool
    {
        return $this->subscription?->pastDue() ?? false;
    }

    public function getHasIncompletePaymentProperty(): bool
    {
        return $this->organization?->hasIncompletePayment() ?? false;
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Billing</x-slot:title>

    @volt('billing.index')
    <div class="max-w-4xl mx-auto">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white">Billing</h1>

        {{-- Warning Banners --}}
        <div class="mt-6 space-y-3">
            @if($this->isOnGracePeriod)
                <x-billing.warning-banner
                    type="grace_period"
                    message="Your subscription has been cancelled. You will retain access until {{ $this->subscription->ends_at->format('M j, Y') }}."
                    :action-url="route('billing.resume')"
                    action-label="Resume Subscription"
                />
            @endif

            @if($this->isPastDue)
                <x-billing.warning-banner
                    type="past_due"
                    message="Your payment is past due. Please update your payment method to maintain access."
                    :action-url="route('billing.portal')"
                    action-label="Update Payment Method"
                />
            @endif

            @if($this->hasIncompletePayment)
                <x-billing.warning-banner
                    type="incomplete"
                    message="Your payment requires action. Please complete the payment to activate your subscription."
                />
            @endif
        </div>

        <div class="mt-8 grid gap-6 lg:grid-cols-2">
            {{-- Current Plan Card --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Current Plan</h2>

                <div class="mt-4">
                    <p class="text-2xl font-bold text-zinc-900 dark:text-white">
                        {{ $this->plan?->name ?? 'Free' }}
                    </p>

                    @if($this->subscription)
                        <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">
                            Status: <span class="font-medium">{{ $this->subscriptionStatus->value ?? 'None' }}</span>
                        </p>
                    @endif
                </div>

                <div class="mt-6 flex flex-wrap gap-3">
                    <flux:button href="{{ route('billing.subscribe') }}" variant="primary">
                        Change Plan
                    </flux:button>

                    @if($this->subscription && !$this->isOnGracePeriod)
                        <form method="POST" action="{{ route('billing.cancel') }}">
                            @csrf
                            <flux:button type="submit" variant="ghost">
                                Cancel Subscription
                            </flux:button>
                        </form>
                    @endif

                    @if($this->isOnGracePeriod)
                        <form method="POST" action="{{ route('billing.resume') }}">
                            @csrf
                            <flux:button type="submit" variant="primary">
                                Resume Subscription
                            </flux:button>
                        </form>
                    @endif

                    <flux:button href="{{ route('billing.portal') }}" variant="ghost">
                        Manage Payment Method
                    </flux:button>
                </div>
            </div>

            {{-- Usage Summary --}}
            <div class="rounded-xl border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-6">
                <h2 class="text-lg font-semibold text-zinc-900 dark:text-white">Usage</h2>

                <div class="mt-4">
                    <x-billing.usage-summary :enforcement-status="$this->enforcementStatus" />
                </div>
            </div>
        </div>
    </div>
    @endvolt
</x-layouts.app>
