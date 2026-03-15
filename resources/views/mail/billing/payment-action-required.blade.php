<x-mail::message>
# Payment Action Required

Your recent payment for **{{ $organization->name }}** on the **{{ $plan?->name ?? 'Free' }}** plan requires additional confirmation.

Please complete the payment to keep your subscription active.

<x-mail::button :url="$paymentUrl">
Complete Payment
</x-mail::button>

If you have any questions, please contact our support team.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
