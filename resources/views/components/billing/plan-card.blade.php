@props([
    'plan',
    'currentPlan' => null,
    'billingPeriod' => 'monthly',
    'canDowngrade' => true,
])

@php
    $isCurrent = $currentPlan && $currentPlan->id === $plan->id;
    $price = $billingPeriod === 'monthly' ? $plan->stripe_price_monthly : $plan->stripe_price_yearly;
    $isDowngrade = $currentPlan && $plan->sort_order < $currentPlan->sort_order;
    $isDisabled = $isDowngrade && !$canDowngrade;
@endphp

<div class="rounded-xl border p-6 {{ $isCurrent ? 'border-blue-500 ring-2 ring-blue-500/20 bg-blue-50/50 dark:bg-blue-950/20' : 'border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800' }}">
    <div class="flex items-center justify-between">
        <h3 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $plan->name }}</h3>
        @if($isCurrent)
            <span class="rounded-full bg-blue-100 px-3 py-1 text-xs font-medium text-blue-700 dark:bg-blue-900 dark:text-blue-300">Current Plan</span>
        @endif
    </div>

    <div class="mt-4">
        @if($plan->slug === 'free')
            <span class="text-3xl font-bold text-zinc-900 dark:text-white">Free</span>
        @else
            <span class="text-3xl font-bold text-zinc-900 dark:text-white">{{ $price ? '$' . number_format(0, 2) : 'Contact us' }}</span>
            <span class="text-sm text-zinc-500 dark:text-zinc-400">/{{ $billingPeriod === 'monthly' ? 'mo' : 'yr' }}</span>
        @endif
    </div>

    <ul class="mt-6 space-y-3 text-sm text-zinc-600 dark:text-zinc-400">
        <li class="flex items-center gap-2">
            <flux:icon.check class="size-4 text-green-500" />
            {{ $plan->max_users === -1 ? 'Unlimited' : $plan->max_users }} users
        </li>
        <li class="flex items-center gap-2">
            <flux:icon.check class="size-4 text-green-500" />
            {{ $plan->max_workspaces === -1 ? 'Unlimited' : $plan->max_workspaces }} workspaces
        </li>
        <li class="flex items-center gap-2">
            <flux:icon.check class="size-4 text-green-500" />
            {{ $plan->max_integrations_per_workspace === -1 ? 'Unlimited' : $plan->max_integrations_per_workspace }} integrations per workspace
        </li>
    </ul>

    <div class="mt-6">
        {{ $slot }}
    </div>
</div>
