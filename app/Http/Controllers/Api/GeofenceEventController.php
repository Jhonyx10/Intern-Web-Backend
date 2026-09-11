<?php

namespace App\Http\Controllers\Api;

use App\Events\GeofenceTransitionDetected;
use App\Http\Controllers\Controller;
use App\Models\GeofenceEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class GeofenceEventController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'event_type'  => ['required', 'in:exit,entry'],
            'latitude'    => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'   => ['nullable', 'numeric', 'between:-180,180'],
            'occurred_at' => ['nullable', 'date'],
        ]);

        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $openLog = $student->timeLogs()->whereNull('time_out')->latest('time_in')->first();

        if (!$openLog) {
            return response()->json(['message' => 'No active shift; event ignored.'], 200);
        }

        $eventType = $request->input('event_type');

        // Determine "first arrival" BEFORE creating the new record, since after
        // creation this event itself would count as an existing entry.
        $isFirstArrival = $eventType === 'entry'
            && ! $openLog->geofenceEvents()->where('event_type', 'entry')->exists();

        // Simple anti-flapping guard: skip notification (but still record) if the
        // opposite event type happened within the last 60 seconds — likely GPS jitter.
        $lastEvent = $openLog->geofenceEvents()->latest('occurred_at')->first();
        $isLikelyJitter = $lastEvent
            && $lastEvent->event_type !== $eventType
            && $lastEvent->occurred_at->diffInSeconds(Carbon::now()) < 60;

        $geofenceEvent = $openLog->geofenceEvents()->create([
            'student_id'  => $student->id,
            'event_type'  => $eventType,
            'latitude'    => $request->input('latitude'),
            'longitude'   => $request->input('longitude'),
            'occurred_at' => $request->filled('occurred_at')
                ? Carbon::parse($request->input('occurred_at'))
                : Carbon::now(),
        ]);

        if (! $isLikelyJitter) {
            event(new GeofenceTransitionDetected(
                $geofenceEvent->load('timeLog.student.section'),
                $isFirstArrival,
            ));
        }

        return response()->json(['message' => 'Recorded.'], 201);
    }

    private function resolveStudent(Request $request)
    {
        return $request->user()?->student;
    }

   public function forCourse(Request $request): JsonResponse
    {
        $user = $request->user();
        $courseId = $user->deanPortalCourse()?->id ?? $user->coordinatorCourse()?->id;

        if (!$courseId) {
            return response()->json(['message' => 'No course scope for this user.'], 403);
        }

        $events = GeofenceEvent::query()
            ->whereHas('timeLog.student.section', fn ($q) => $q->where('course_id', $courseId))
            ->whereDate('occurred_at', now()->toDateString())
            ->with(['timeLog.student:id,first_name,middle_name,last_name'])
            ->latest('occurred_at')
            ->get()
            ->groupBy('student_id')
            ->map(fn ($group) => $group->first())
            ->values();

        return response()->json([
            'data' => $events->map(fn ($e) => [
                'student_id'   => $e->student_id,
                'student_name' => $e->timeLog->student->fullName(),
                'event_type'   => $e->event_type,
                'latitude'     => $e->latitude,
                'longitude'    => $e->longitude,
                'occurred_at'  => $e->occurred_at->toIso8601String(),
            ]),
        ]);
    }
}