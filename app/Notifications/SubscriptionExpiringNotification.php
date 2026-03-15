<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SubscriptionExpiringNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Organization $organization,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your subscription is expiring soon')
            ->markdown('mail.billing.subscription-expiring', [
                'organization' => $this->organization,
                'plan' => $this->organization->plan,
                'endsAt' => $this->organization->subscription('default')?->ends_at,
                'resumeUrl' => route('billing.resume'),
            ]);
    }
}
