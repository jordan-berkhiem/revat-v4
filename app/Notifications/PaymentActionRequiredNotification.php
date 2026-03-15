<?php

namespace App\Notifications;

use App\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentActionRequiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Organization $organization,
        public string $paymentId,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Payment action required for your subscription')
            ->markdown('mail.billing.payment-action-required', [
                'organization' => $this->organization,
                'plan' => $this->organization->plan,
                'paymentUrl' => route('cashier.payment', [$this->paymentId, 'redirect' => route('billing')]),
            ]);
    }
}
