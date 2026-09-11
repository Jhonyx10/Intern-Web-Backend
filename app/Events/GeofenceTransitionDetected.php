<?php

namespace App\Events;

use App\Models\GeofenceEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GeofenceTransitionDetected implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * @param  bool  $isFirstArrival  True only when this is the very first
     *                                geofence entry recorded for this shift.
     */
    public function __construct(
        public GeofenceEvent $geofenceEvent,
        public bool $isFirstArrival = false,
    ) {
    }

    public function broadcastOn(): array
    {
        $courseId = $this->geofenceEvent->timeLog->student->section?->course_id;

        if (!$courseId) {
            return [];
        }

        return [
            new PrivateChannel("course.{$courseId}.supervisors"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'geofence.transition';
    }

    public function broadcastWith(): array
    {
        $student = $this->geofenceEvent->timeLog->student;
        $type = $this->geofenceEvent->event_type; // 'exit' | 'entry'

        $message = match (true) {
            $type === 'exit' => "{$student->fullName()} left the company premises while their shift is still open.",
            $type === 'entry' && $this->isFirstArrival => "{$student->fullName()} has arrived on-site for their shift.",
            default => "{$student->fullName()} has returned to the company premises.",
        };

        return [
            'student_id'       => $student->id,
            'student_name'     => $student->fullName(),
            'event_type'       => $type,
            'is_first_arrival' => $this->isFirstArrival,
            'occurred_at'      => $this->geofenceEvent->occurred_at->toIso8601String(),
            'message'          => $message,
        ];
    }
}