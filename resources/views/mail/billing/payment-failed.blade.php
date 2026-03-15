<x-mail::message>
# Payment Failed

We were unable to process a payment for **{{ $organization->name }}** on the **{{ $plan?->name ?? 'Free' }}** plan.

Please update your payment method to maintain your subscription.

<x-mail::button :url="$portalUrl">
Update Payment Method
</x-mail::button>

If you have any questions, please contact our support team.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
