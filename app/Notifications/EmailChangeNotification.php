<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmailChangeNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $oldEmail,
        public string $newEmail,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Email address change requested')
            ->line('A request was made to change the email address associated with your Revat account.')
            ->line("The email is being changed from {$this->oldEmail} to {$this->newEmail}.")
            ->line('If you did not make this change, please contact support immediately.')
            ->action('Contact Support', url('/'));
    }
}
