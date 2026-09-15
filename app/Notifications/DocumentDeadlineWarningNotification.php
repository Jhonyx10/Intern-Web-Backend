<?php

namespace App\Notifications;

use App\Models\DocumentRequirement;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class DocumentDeadlineWarningNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public DocumentRequirement $requirement,
        public Carbon $deadline
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Add other channels like 'mail' or 'database' if needed.
        // We handle Firebase sending directly in a custom channel or directly in this class via a custom channel.
        // For simplicity and full control with kreait/laravel-firebase, we can use a custom channel.
        return [FirebaseChannel::class];
    }
}

class FirebaseChannel
{
    /**
     * Send the given notification.
     *
     * @param  mixed  $notifiable
     * @param  \Illuminate\Notifications\Notification  $notification
     * @return void
     */
    public function send($notifiable, Notification $notification)
    {
        if (! $notifiable->fcm_token) {
            return;
        }

        /** @var DocumentDeadlineWarningNotification $notification */
        $title = 'Document Deadline Approaching!';
        $body = "Your deadline for '{$notification->requirement->title}' is on " . $notification->deadline->format('M d, Y') . ". Please submit your documents before the deadline.";

        $message = CloudMessage::new()
            ->withNotification(FirebaseNotification::create($title, $body))
            ->withChangedTarget('token', $notifiable->fcm_token);

        try {
            $messaging = Firebase::messaging();
            $messaging->send($message);
        } catch (\Exception $e) {
            Log::error('Failed to send FCM notification: ' . $e->getMessage());
        }
    }
}
