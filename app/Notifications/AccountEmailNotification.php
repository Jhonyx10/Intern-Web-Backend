<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountEmailNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $email,
        public string $password,
        public string $loginUrl,
    ) {
        //
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Account Has Been Created')
            ->line('An account has been created for you.')
            ->line('Here are your login credentials:')
            ->line('**Email:** ' . $this->email)
            ->line('**Temporary Password:** ' . $this->password)
            ->action('Log In', $this->loginUrl)
            ->line('For your security, please log in and change your password as soon as possible.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}