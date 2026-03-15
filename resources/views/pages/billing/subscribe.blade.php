<?php

use App\Models\Plan;
use App\Services\PlanEnforcement\PlanEnforcementService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $billingPeriod = 'monthly';

    public function getOrganizationProperty()
    {
        return Auth::user()->currentOrganization;
    }

    public function getCurrentPlanProperty()
    {
        return $this->organization?->plan;
    }

    public function getPlansProperty()
    {
        return Plan::visible()->get();
    }

    public function canDowngradeTo(int $planId): bool
    {
        $plan = Plan::find($planId);
        if (! $plan || ! $this->organization) {
            return false;
        }

        return app(PlanEnforcementService::class)->canDowngradeTo($this->organization, $plan);
    }

    public function toggleBillingPeriod(): void
    {
        $this->billingPeriod = $this->billingPeriod === 'monthly' ? 'yearly' : 'monthly';
    }
}; ?>

<x-layouts.app>
    <x-slot:title>Choose a Plan</x-slot:title>

    @volt('billing.subscribe')
    <div class="max-w-5xl mx-auto">
        <h1 class="text-2xl font-bold text-zinc-900 dark:text-white text-center">Choose a Plan</h1>

        {{-- Billing Period Toggle --}}
        <div class="mt-6 flex items-center justify-center gap-3">
            <span class="text-sm font-medium {{ $billingPeriod === 'monthly' ? 'text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400' }}">
                Monthly
            </span>
            <flux:switch
                wire:model.live="billingPeriod"
                wire:click="toggleBillingPeriod"
                :checked="$billingPeriod === 'yearly'"
            />
            <span class="text-sm font-medium {{ $billingPeriod === 'yearly' ? 'text-zinc-900 dark:text-white' : 'text-zinc-500 dark:text-zinc-400' }}">
                Yearly
            </span>
        </div>

        {{-- Plan Grid --}}
        <div class="mt-8 grid grid-cols-1 gap-6 md:grid-cols-2 lg:grid-cols-3">
            @foreach($this->plans as $plan)
                @php
                    $isCurrent = $this->currentPlan && $this->currentPlan->id === $plan->id;
                    $isDowngrade = $this->currentPlan && $plan->sort_order < $this->currentPlan->sort_order;
                    $canDowngrade = !$isDowngrade || $this->canDowngradeTo($plan->id);
                @endphp

                <x-billing.plan-card
                    :plan="$plan"
                    :current-plan="$this->currentPlan"
                    :billing-period="$billingPeriod"
                    :can-downgrade="$canDowngrade"
                >
                    @if($isCurrent)
                        <flux:button variant="ghost" class="w-full" disabled>
                            Current Plan
                        </flux:button>
                    @elseif($this->organization?->subscribed('default'))
                        @if($isDowngrade && !$canDowngrade)
                            <flux:button variant="ghost" class="w-full" disabled>
                                Usage exceeds limits
                            </flux:button>
                        @else
                            <form method="POST" action="{{ route('billing.swap') }}">
                                @csrf
                                @method('PUT')
                                <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                                <input type="hidden" name="billing_period" value="{{ $billingPeriod }}">
                                <flux:button type="submit" variant="primary" class="w-full">
                                    Switch Plan
                                </flux:button>
                            </form>
                        @endif
                    @else
                        <form method="POST" action="{{ route('billing.checkout') }}">
                            @csrf
                            <input type="hidden" name="plan_id" value="{{ $plan->id }}">
                            <input type="hidden" name="billing_period" value="{{ $billingPeriod }}">
                            <flux:button type="submit" variant="primary" class="w-full">
                                Subscribe
                            </flux:button>
                        </form>
                    @endif
                </x-billing.plan-card>
            @endforeach
        </div>
    </div>
    @endvolt
</x-layouts.app>
