<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForgotPasswordNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $newPassword,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your New Password – OCC Intern Portal')
            ->line('We received a request to reset the password for your account.')
            ->line('Here is your system-generated password:')
            ->line('**New Password:** ' . $this->newPassword)
            ->line('Please log in using this password and change it immediately from your account settings.')
            ->action('Log In Now', config('app.url') . '/app/login')
            ->line('If you did not request a password reset, please contact your administrator.');
    }

    public function toArray(object $notifiable): array
    {
        return [];
    }
}
