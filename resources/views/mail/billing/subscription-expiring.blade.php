<x-mail::message>
# Your Subscription is Expiring

Your subscription for **{{ $organization->name }}** on the **{{ $plan?->name ?? 'Free' }}** plan is set to expire on **{{ $endsAt?->format('M j, Y') }}**.

After this date, you will lose access to paid features.

<x-mail::button :url="$resumeUrl">
Resume Subscription
</x-mail::button>

If you have any questions, please contact our support team.

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
