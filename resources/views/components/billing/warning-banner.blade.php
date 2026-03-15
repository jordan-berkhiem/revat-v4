@props([
    'type',
    'message',
    'actionUrl' => null,
    'actionLabel' => null,
])

@php
    $variant = match($type) {
        'past_due' => 'danger',
        'grace_period', 'incomplete', 'limit_reached' => 'warning',
        default => 'warning',
    };
@endphp

<flux:callout :variant="$variant">
    <flux:callout.heading>{{ $message }}</flux:callout.heading>
    @if($actionUrl && $actionLabel)
        <flux:callout.text>
            <a href="{{ $actionUrl }}" class="font-medium underline">{{ $actionLabel }}</a>
        </flux:callout.text>
    @endif
</flux:callout>
