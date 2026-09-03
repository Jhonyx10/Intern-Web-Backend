<?php

namespace App\Notifications;

use App\Models\OjtEvaluation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EvaluationReadyNotification extends Notification implements ShouldQueue, ShouldBroadcast
{
    use Queueable;

    public function __construct(
        public OjtEvaluation $evaluation
    ) {}

    public function via(object $notifiable): array
    {
        // Delivers to database, mail, AND real-time Pusher channel
        return ['database', 'mail', 'broadcast'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $studentName = $this->evaluation->student->name ?? 'A student';
        $progress = $this->evaluation->student->progress_percentage ?? 90;

        return (new MailMessage)
            ->subject("Final Evaluation Ready: {$studentName}")
            ->greeting("Hello {$notifiable->name},")
            ->line("{$studentName} has completed {$progress}% of their required OJT hours.")
            ->line("Their final evaluation form is now ready for your review.")
            ->action('Complete Evaluation', url("/evaluations/{$this->evaluation->id}"));
    }

    public function toArray(object $notifiable): array
    {
        return [
            'evaluation_id' => $this->evaluation->id,
            'student_id' => $this->evaluation->student_id,
            'student_name' => $this->evaluation->student->name ?? 'Student',
            'progress' => $this->evaluation->student->progress_percentage ?? 90,
            'message' => "Final evaluation form is now available for {$this->evaluation->student->name}.",
            'action_url' => "/evaluations/{$this->evaluation->id}",
        ];
    }

    /**
     * Payload broadcast over Pusher
     */
    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage([
            'id' => $this->id,
            'data' => $this->toArray($notifiable),
            'read_at' => null,
            'created_at' => now()->toIso8601String(),
        ]);
    }
}