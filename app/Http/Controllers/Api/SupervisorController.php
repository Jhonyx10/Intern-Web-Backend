<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CompanySchedule;
use App\Models\Supervisor;
use App\Models\Building;
use App\Models\Student;
use App\Models\BuildingAssignment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification as FirebaseNotification;
use Kreait\Laravel\Firebase\Facades\Firebase;
use Illuminate\Support\Facades\Log;

class SupervisorController extends Controller
{
    /**
     * Get the supervisor profile for the currently authenticated user.
     */
    private function getSupervisor(Request $request): ?Supervisor
    {
        return Supervisor::with('company')
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();
    }

    /**
     * Return supervisor profile including company info.
     */
    public function profile(Request $request): JsonResponse
    {
        $supervisor = $this->getSupervisor($request);
        if (!$supervisor) {
            return response()->json(['message' => 'Supervisor profile not found.'], 404);
        }

        return response()->json([
            'data' => [
                'id'             => $supervisor->id,
                'position_title' => $supervisor->position_title,
                'is_active'      => $supervisor->is_active,
                'company'        => $supervisor->company ? [
                    'id'      => $supervisor->company->id,
                    'name'    => $supervisor->company->name,
                    'address' => $supervisor->company->address,
                ] : null,
            ]
        ]);
    }

    /**
     * Return company schedules.
     */
    public function indexSchedules(Request $request): JsonResponse
    {
        $supervisor = $this->getSupervisor($request);
        if (!$supervisor || !$supervisor->company) {
            return response()->json(['data' => []]);
        }

        $schedules = CompanySchedule::where('company_id', $supervisor->company_id)
            ->orderBy('start_date', 'asc')
            ->get();

        return response()->json(['data' => $schedules]);
    }

    /**
     * Store a new company schedule.
     */
    public function storeSchedule(Request $request): JsonResponse
    {
        $supervisor = $this->getSupervisor($request);
        if (!$supervisor || !$supervisor->company) {
            return response()->json(['message' => 'Supervisor or company not found.'], 404);
        }

        $validated = $request->validate([
            'start_date'  => ['required', 'date'],
            'time_in'     => ['required', 'string'],
            'lunch_break' => ['nullable', 'string'],
            'time_out'    => ['required', 'string'],
        ]);

        $schedule = CompanySchedule::create([
            'company_id'    => $supervisor->company_id,
            'start_date'    => $validated['start_date'],
            'time_in'       => $validated['time_in'],
            'lunch_break'   => $validated['lunch_break'] ?? null,
            'time_out'      => $validated['time_out'],
            'supervisor_id' => $supervisor->id,
        ]);

        return response()->json(['message' => 'Schedule created successfully.', 'data' => $schedule], 201);
    }

    /**
     * Update an existing company schedule.
     */
    public function updateSchedule(Request $request, CompanySchedule $schedule): JsonResponse
    {
        $supervisor = $this->getSupervisor($request);
        if (!$supervisor || $schedule->company_id !== $supervisor->company_id) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'start_date'  => ['sometimes', 'required', 'date'],
            'time_in'     => ['sometimes', 'required', 'string'],
            'lunch_break' => ['nullable', 'string'],
            'time_out'    => ['sometimes', 'required', 'string'],
        ]);

        $schedule->update($validated);

        return response()->json(['message' => 'Schedule updated successfully.', 'data' => $schedule]);
    }

    /**
     * Delete a company schedule.
     */
    public function destroySchedule(Request $request, CompanySchedule $schedule): JsonResponse
    {
        $supervisor = $this->getSupervisor($request);
        if (!$supervisor || $schedule->company_id !== $supervisor->company_id) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $schedule->delete();

        return response()->json(['message' => 'Schedule deleted successfully.']);
    }

    /**
     * Return the list of interns (students) assigned to this supervisor's company.
     */
   public function interns(Request $request): JsonResponse
{
    $supervisor = $this->getSupervisor($request);
    if (!$supervisor || !$supervisor->company) {
        return response()->json(['data' => []]);
    }

    $students = $supervisor->company->students()
        ->wherePivot('status', 'active')
        ->with(['section', 'ojtSchedule', 'buildings', 'ojtEvaluations.template.items'])
        ->get()
        ->map(function ($s) {
            $companyStudentId = $s->pivot->id;

            $totalMinutes = \App\Models\TimeLog::where('student_id', $s->id)
                ->where('company_student_id', $companyStudentId)
                ->sum('duration_minutes');

            return [
                'id'             => $s->id,
                'student_number' => $s->student_number,
                'first_name'     => $s->first_name,
                'middle_name'    => $s->middle_name,
                'last_name'      => $s->last_name,
                'is_active'      => $s->is_active,
                'section'        => $s->section ? ['id' => $s->section->id, 'name' => $s->section->name] : null,
                'required_hours' => $s->ojtSchedule?->required_hours ?? null,
                'total_hours'    => round($totalMinutes / 60, 2),
                'building_id'    => $s->activeBuildings->first()?->id,
                'ojt_evaluations' => $s->ojtEvaluations,
            ];
        });

    return response()->json(['data' => $students]);
}

    /**
     * Return recent time logs (attendance) for all interns in this supervisor's company.
     */
    public function attendance(Request $request): JsonResponse
    {
        $supervisor = $this->getSupervisor($request);
        if (!$supervisor || !$supervisor->company) {
            return response()->json(['data' => []]);
        }

        $studentIds = $supervisor->company->students()->pluck('students.id');

        $logs = \App\Models\TimeLog::with('student')
            ->whereIn('student_id', $studentIds)
            ->orderByDesc('time_in')
            ->take(200)
            ->get()
            ->map(fn($log) => [
                'id'                  => $log->id,
                'student_id'          => $log->student_id,
                'student_name'        => $log->student
                    ? "{$log->student->last_name}, {$log->student->first_name}"
                    : '—',
                'student_number'      => $log->student?->student_number,
                'time_in'             => $log->time_in?->toIso8601String(),
                'time_out'            => $log->time_out?->toIso8601String(),
                'duration_minutes'    => $log->duration_minutes,
                'task_note'           => $log->task_note,
                'verification_method' => $log->verification_method,
            ]);

        return response()->json(['data' => $logs]);
    }

 public function assignInterns(Request $request, Building $building): JsonResponse
    {
        $supervisor = $this->getSupervisor($request);

        if (!$supervisor || $supervisor->company_id !== $building->company_id) {
            return response()->json(['message' => 'Unauthorized action.'], 403);
        }

        $validated = $request->validate([
            'student_ids'   => ['required', 'array', 'min:1'],
            'student_ids.*' => ['integer', 'exists:students,id'],
            'date_start'    => ['required', 'date'],
            'date_end'      => ['nullable', 'date', 'after_or_equal:date_start'],
        ]);

        DB::transaction(function () use ($validated, $building, $supervisor) {
            foreach ($validated['student_ids'] as $studentId) {
                // End any existing active assignment for this student (any building)
                BuildingAssignment::where('student_id', $studentId)
                    ->where('is_active', true)
                    ->update([
                        'is_active' => false,
                        'date_end' => $validated['date_start'],
                    ]);

                // Create the new assignment
                BuildingAssignment::create([
                    'student_id'  => $studentId,
                    'building_id' => $building->id,
                    'assigned_by' => $supervisor->user_id,
                    'date_start'  => $validated['date_start'],
                    'date_end'    => $validated['date_end'] ?? null,
                    'is_active'   => true,
                ]);
            }
        });

        return response()->json(['message' => 'Interns assigned successfully.']);
    }

   public function removeIntern(Request $request, Student $student): JsonResponse
    {
        $supervisor = $this->getSupervisor($request);

        if (!$supervisor || !$supervisor->company) {
            return response()->json(['message' => 'Supervisor or company not found.'], 404);
        }

        // Confirm this student actually belongs to the supervisor's company.
        $isAssigned = $supervisor->company->students()
            ->where('students.id', $student->id)
            ->exists();

        if (!$isAssigned) {
            return response()->json(['message' => 'This intern is not assigned to your company.'], 403);
        }

        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:1000'],
        ]);

        $supervisor->company->students()->updateExistingPivot($student->id, [
            'status'         => 'inactive',
            'removal_reason' => $validated['reason'],
        ]);

        $this->sendTerminationNotification($student, $supervisor->company->name, $validated['reason']);

        return response()->json(['message' => 'Intern removed from the internship program.']);
    }

    protected function sendTerminationNotification(Student $student, string $companyName, string $reason): void
    {
        $fcmToken = $student->user?->fcm_token;

        if (! $fcmToken) {
            return;
        }

        $title = 'Removed from Internship Program';
        $body = "You have been removed from your internship at {$companyName}. Reason: {$reason}";

        $message = CloudMessage::new()
            ->withToken($fcmToken)
            ->withNotification(FirebaseNotification::create($title, $body));

        try {
            $messaging = Firebase::messaging();
            $messaging->send($message);
        } catch (\Exception $e) {
            Log::error('Failed to send intern termination FCM notification: ' . $e->getMessage());
        }
    }

}
