<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\TimeLog;
use App\Support\FaceEmbedding;
use App\Support\FaceMatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class InternController extends Controller
{
    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Resolve the authenticated student (or return null if not found).
     */
    private function resolveStudent(Request $request): ?Student
    {
        return Student::with([
            'section.course',
            'ojtSchedule',
            'faceProfile',
            'companies.buildings',
        ])->where('user_id', $request->user()->id)->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function formatLogSegment(TimeLog $log, bool $withPhotos = false): array
    {
        $durationMinutes = $log->duration_minutes;

        $data = [
            'id'                          => $log->id,
            'session_period'              => $log->session_period,
            'task_note'                   => $log->task_note,
            'time_in'                     => $log->time_in?->toIso8601String(),
            'time_out'                    => $log->time_out?->toIso8601String(),
            'duration_minutes'            => $durationMinutes,
            'duration_hours'              => $durationMinutes !== null ? round($durationMinutes / 60, 2) : null,
            'verification_method'         => $log->verification_method,
            'face_match_score'            => $log->face_match_score ? (float) $log->face_match_score : null,
            'is_open'                     => $log->time_out === null,
            'task_photos_count'           => $log->taskPhotos->count(),
            'submitted_task_photos_count' => $log->taskPhotos->where('status', 'submitted')->count(),
        ];

        if ($withPhotos) {
            $data['task_photos'] = $log->taskPhotos->map(fn($p) => [
                'id'                => $p->id,
                'time_log_id'       => $p->time_log_id,
                'original_filename' => $p->original_filename,
                'file_size'         => $p->file_size,
                'mime_type'         => $p->mime_type,
                'status'            => $p->status,
                'submitted_at'      => $p->submitted_at?->toIso8601String(),
                'created_at'        => $p->created_at?->toIso8601String(),
            ])->values()->all();
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Progress
    // -------------------------------------------------------------------------

    public function progress(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $course = $student->section?->course;
        $requiredHours  = $course ? (float) $course->required_hours : 0;
        $totalMinutes   = (float) $student->timeLogs()->sum('duration_minutes');
        $renderedHours  = round($totalMinutes / 60, 2);
        $remainingHours = max(0, $requiredHours - $renderedHours);
        $percentComplete = $requiredHours > 0 ? round(($renderedHours / $requiredHours) * 100, 1) : 0;
        $timeLogCount    = $student->timeLogs()->count();
        $schedule        = $student->ojtSchedule;

        $company         = $student->companies()->first();
        $companySchedule = null;
        if ($company) {
            $companySchedule = \App\Models\CompanySchedule::where('company_id', $company->id)
                ->orderBy('start_date', 'desc')
                ->first();
        }

        $estimatedEndBasis          = 'default';
        $estimatedEndIsApproximate  = true;

        if ($remainingHours <= 0) {
            $estimatedEndDate          = Carbon::now();
            $estimatedEndBasis         = 'completed';
            $estimatedEndIsApproximate = false;
        } else {
            $hoursPerDay = 8;
            $daysPerWeek = 5;
            $baseDate = Carbon::now();

            if ($companySchedule) {
                // Approximate hours per day based on CompanySchedule time in/out minus 1 lunch break hr
                try {
                    $in = Carbon::parse($companySchedule->time_in);
                    $out = Carbon::parse($companySchedule->time_out);
                    $shiftHours = $in->diffInHours($out) - 1; // Assume 1 hr break
                    $hoursPerDay = max((float)$shiftHours, 1.0);
                } catch (\Exception $e) {
                    $hoursPerDay = 8;
                }
                $estimatedEndBasis = 'company_schedule';
                
                if ($companySchedule->start_date) {
                    $startDate = Carbon::parse($companySchedule->start_date);
                    if ($startDate->isFuture()) {
                        $baseDate = $startDate;
                    }
                }
            } else if ($schedule) {
                $hoursPerDay       = (float) $schedule->hours_per_day ?: 8;
                $daysPerWeek       = (float) $schedule->days_per_week ?: 5;
                $estimatedEndBasis = 'ojt_schedule';
            }

            $hoursPerWeek = $hoursPerDay * $daysPerWeek;
            $weeksNeeded  = $hoursPerWeek > 0 ? $remainingHours / $hoursPerWeek : 0;
            $daysNeeded   = (int) floor($weeksNeeded * 7);
            $estimatedEndDate = $baseDate->copy()->addDays($daysNeeded);
        }

        $scheduleInfo = null;
        if ($companySchedule) {
            $scheduleInfo = [
                'hours_per_day' => $hoursPerDay,
                'days_per_week' => 5, // Generally M-F by default unless defined elsewhere
                'time_in'       => Carbon::parse($companySchedule->time_in)->format('h:i A'),
                'time_out'      => Carbon::parse($companySchedule->time_out)->format('h:i A'),
                'start_date'    => $companySchedule->start_date ? Carbon::parse($companySchedule->start_date)->format('Y-m-d') : null,
            ];
        } else if ($schedule) {
             $scheduleInfo = [
                'hours_per_day' => (float) $schedule->hours_per_day,
                'days_per_week' => (float) $schedule->days_per_week,
                'start_date'    => null,
             ];
        }

        return response()->json([
            'student' => [
                'id'             => $student->id,
                'full_name'      => $student->fullName(),
                'student_number' => $student->student_number,
                'section'        => $student->section ? $student->section->name : null,
            ],
            'course' => $course ? [
                'id'   => $course->id,
                'code' => $course->code,
                'name' => $course->name,
            ] : null,
            'company' => $company ? [
                'name' => $company->name,
                'latitude' => (float)$company->latitude,
                'longitude' => (float)$company->longitude,
                'radius_meters' => (float)$company->geofence_radius_meters,
            ] : null,
            'progress' => [
                'required_hours'               => $requiredHours,
                'rendered_hours'               => $renderedHours,
                'remaining_hours'              => round($remainingHours, 2),
                'percent_complete'             => min(100, $percentComplete),
                'time_log_count'               => $timeLogCount,
                'estimated_end_date'           => $estimatedEndDate->format('Y-m-d'),
                'estimated_end_basis'          => $estimatedEndBasis,
                'estimated_end_is_approximate' => $estimatedEndIsApproximate,
                'schedule'                     => $scheduleInfo,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Time: Status
    // -------------------------------------------------------------------------

    public function timeStatus(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $faceProfile  = $student->faceProfile;
        $faceEnrolled = $faceProfile && $faceProfile->is_active && !empty($faceProfile->face_embedding);

        $today    = Carbon::today();
        $todayEnd = Carbon::today()->endOfDay();

        // All time logs for today
        $todayLogs = $student->timeLogs()
            ->with('taskPhotos')
            ->whereBetween('time_in', [$today, $todayEnd])
            ->orderBy('time_in')
            ->get();

        $openLog = $todayLogs->first(fn($l) => $l->time_out === null);

        $todayMinutes = $todayLogs
            ->whereNotNull('time_out')
            ->sum('duration_minutes');

        // Add minutes from open log so far
        if ($openLog) {
            $todayMinutes += $openLog->time_in->diffInMinutes(Carbon::now());
        }

        $canPunchIn  = $faceEnrolled && $openLog === null;
        $canPunchOut = $faceEnrolled && $openLog !== null;

        $company    = $student->companies()->first();
        
        // Lookup company schedule
        $companySchedule = null;
        if ($company) {
            $companySchedule = \App\Models\CompanySchedule::where('company_id', $company->id)
                ->orderBy('start_date', 'desc')
                ->first();
        }

        // Basic geofence info from student's company
        $geofence = null;
        if ($company) {
            $geofence = [
                'required'      => (bool) $company->geofence_enabled,
                'enabled'       => (bool) $company->geofence_enabled,
                'configured'    => $company->latitude !== null && $company->longitude !== null,
                'company_name'  => $company->name,
                'latitude'      => $company->latitude ? (float) $company->latitude : null,
                'longitude'     => $company->longitude ? (float) $company->longitude : null,
                'radius_meters' => $company->geofence_radius_meters ? (float) $company->geofence_radius_meters : null,
            ];
        }

        // Generate Lunch Break Info if we have a company schedule
        $lunchBreakInfo = null;
        if ($companySchedule && $companySchedule->lunch_break) {
            try {
                // If it's a fixed lunch break setting ("12:00-13:00"), we can provide some structured data
                $lunchParts = explode('-', str_replace(' ', '', $companySchedule->lunch_break));
                if (count($lunchParts) === 2) {
                    $start = Carbon::parse($lunchParts[0]);
                    $end = Carbon::parse($lunchParts[1]);
                    
                    $lunchBreakInfo = [
                        'lunch_time'            => $start->format('H:i'),
                        'lunch_time_label'      => $start->format('h:i A'),
                        'afternoon_start_time'  => $end->format('H:i'),
                        'afternoon_start_label' => $end->format('h:i A'),
                        'policy_message'        => 'Don\'t forget to time out for lunch!',
                    ];
                } else {
                    // It's a text descriptor like "1 hour"
                    $lunchBreakInfo = [
                        'lunch_time'            => '12:00',
                        'lunch_time_label'      => '12:00 PM', // Fallback defaults
                        'afternoon_start_time'  => '13:00',
                        'afternoon_start_label' => '1:00 PM',
                        'policy_message'        => 'Lunch Policy: ' . $companySchedule->lunch_break,
                    ];
                }
            } catch (\Exception $e) {}
        }
        
        $todayAttendance = [
            'status'              => $openLog ? 'present' : ($todayLogs->count() > 0 ? 'present' : 'not_started'),
            'label'               => $openLog ? 'Present (Active)' : ($todayLogs->count() > 0 ? 'Present (Closed)' : 'Not Started'),
            'minutes'             => (int) $todayMinutes,
            'hours'               => round($todayMinutes / 60, 2),
            'is_scheduled_today'  => Carbon::now()->isWeekday(),
            'schedule_label'      => $companySchedule 
                ? Carbon::parse($companySchedule->time_in)->format('h:i A') . ' - ' . Carbon::parse($companySchedule->time_out)->format('h:i A') 
                : null,
            'absence_id'          => null,
            'needs_justification' => false,
        ];

        return response()->json([
            'face_enrolled'    => $faceEnrolled,
            'face_enrolled_at' => $faceProfile?->enrolled_at?->toIso8601String(),
            'face_embedding'   => $faceEnrolled ? $faceProfile->face_embedding : null,
            'can_punch_in'     => $canPunchIn,
            'can_punch_out'    => $canPunchOut,
            'open_log'         => $openLog ? $this->formatLogSegment($openLog) : null,
            'today_segments'   => $todayLogs->map(fn($l) => $this->formatLogSegment($l))->values(),
            'today_minutes'    => (int) $todayMinutes,
            'today_hours'      => round($todayMinutes / 60, 2),
            'geofence'         => $geofence,
            'lunch_break'      => $lunchBreakInfo,
            'today_attendance' => $todayAttendance,
        ]);
    }

    // -------------------------------------------------------------------------
    // Time: Logs list
    // -------------------------------------------------------------------------

    public function timeLogs(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $perPage = min((int) $request->query('per_page', 50), 200);

        $logs = $student->timeLogs()
            ->with('taskPhotos')
            ->orderByDesc('time_in')
            ->paginate($perPage);

        return response()->json([
            'logs'        => collect($logs->items())->map(fn($l) => $this->formatLogSegment($l, true))->values(),
            'total_count' => $logs->total(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Time: Punch in / out
    // -------------------------------------------------------------------------

    public function timePunch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'action'                  => ['required', 'in:time_in,time_out'],
            'embedding'               => ['required', 'array', 'size:' . FaceEmbedding::LENGTH],
            'embedding.*'             => ['numeric'],
            'device_info'             => ['nullable', 'string', 'max:500'],
            'latitude'                => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'               => ['nullable', 'numeric', 'between:-180,180'],
            'location_accuracy_meters' => ['nullable', 'numeric'],
        ]);

        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $faceProfile = $student->faceProfile;

        if (!$faceProfile || !$faceProfile->is_active || empty($faceProfile->face_embedding)) {
            return response()->json(['message' => 'Please enroll your face first before punching in/out.'], 422);
        }

        // Verify face match
        $scannedEmbedding = FaceEmbedding::normalize($validated['embedding']);
        $threshold        = (float) config('services.face.match_threshold', 0.45);
        $distance         = FaceMatcher::euclideanDistance($faceProfile->face_embedding, $scannedEmbedding);

        if ($distance > $threshold) {
            return response()->json([
                'message'         => 'Face recognition failed. Please try again in better lighting.',
                'face_match_score' => round($distance, 4),
            ], 422);
        }

        $action         = $validated['action'];
        $now            = Carbon::now();
        $faceMatchScore = round($distance, 4);

        if ($action === 'time_in') {
            // Check no open log already exists
            $existing = $student->timeLogs()->whereNull('time_out')->first();
            if ($existing) {
                return response()->json(['message' => 'You already have an open time log. Please punch out first.'], 422);
            }

            $log = $student->timeLogs()->create([
                'time_in'             => $now,
                'verification_method' => 'facial_recognition',
                'face_match_score'    => $faceMatchScore,
                'device_info'         => $validated['device_info'] ?? null,
            ]);

            $log->load('taskPhotos');

            return response()->json([
                'message' => 'Punched in successfully.',
                'log'     => $this->formatLogSegment($log),
            ], 201);
        }

        // time_out
        $openLog = $student->timeLogs()->whereNull('time_out')->latest('time_in')->first();

        if (!$openLog) {
            return response()->json(['message' => 'No open time log found. Please punch in first.'], 422);
        }

        $durationMinutes = (int) $openLog->time_in->diffInMinutes($now);

        $openLog->update([
            'time_out'         => $now,
            'duration_minutes' => $durationMinutes,
            'face_match_score' => $faceMatchScore,
        ]);

        $openLog->load('taskPhotos');

        return response()->json([
            'message' => 'Punched out successfully.',
            'log'     => $this->formatLogSegment($openLog),
        ]);
    }

    // -------------------------------------------------------------------------
    // Profile
    // -------------------------------------------------------------------------

    public function profile(Request $request): JsonResponse
    {
        $user    = $request->user();
        $student = Student::with([
            'section.course',
            'companies',
        ])->where('user_id', $user->id)->first();

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $company    = $student->companies()->first();
        $supervisor = null;

        if ($company) {
            $sup = \App\Models\Supervisor::where('company_id', $company->id)->first();
            if ($sup) {
                $supUser    = $sup->user;
                $supervisor = [
                    'id'             => $sup->id,
                    'name'           => $supUser?->name,
                    'email'          => $supUser?->email,
                    'position_title' => $sup->position_title,
                ];
            }
        }

        $section = $student->section;
        $course  = $section?->course;

        return response()->json([
            'student' => [
                'id'             => $student->id,
                'student_number' => $student->student_number,
                'full_name'      => $student->fullName(),
            ],
            'user' => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
            ],
            'section' => $section ? [
                'id'   => $section->id,
                'name' => $section->name,
                'course' => $course ? [
                    'code' => $course->code,
                    'name' => $course->name,
                ] : null,
            ] : null,
            'placement' => [
                'company'    => $company ? [
                    'id'      => $company->id,
                    'name'    => $company->name,
                    'address' => $company->address,
                ] : null,
                'department' => null, // extend when departments are modelled
                'supervisor' => $supervisor,
            ],
        ]);
    }

    // -------------------------------------------------------------------------
    // Update Password
    // -------------------------------------------------------------------------

    public function updatePassword(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'current_password'      => ['required', 'string'],
            'password'              => ['required', 'string', 'min:8', 'confirmed'],
            'password_confirmation' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['password' => Hash::make($validated['password'])]);

        return response()->json(['message' => 'Password updated successfully.']);
    }
}

