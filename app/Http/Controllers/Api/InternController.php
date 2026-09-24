<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OjtSchedule;
use App\Models\Student;
use App\Models\TimeLog;
use App\Models\TimeLogTaskPhoto;
use App\Services\PythonMicroservice;
use App\Support\FaceEmbedding;
use App\Support\FaceMatcher;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class InternController extends Controller
{
    public function __construct(private readonly PythonMicroservice $python) {}

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
            'break_out'                   => $log->break_out?->toIso8601String(),
            'break_in'                    => $log->break_in?->toIso8601String(),
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
                'url'               => $p->file_path ? asset('storage/' . ltrim($p->file_path, '/')) : null,
            ])->values()->all();
        }

        return $data;
    }
    // --------------------------
    //helper
    // ----------------

    private function activeCompanyStudentId(Student $student): ?int
    {
        return \Illuminate\Support\Facades\DB::table('company_student')
            ->where('student_id', $student->id)
            ->where('status', 'active')
            ->value('id');
    }

    // -------------------------------------------------------------------------
    // Progress
    // -------------------------------------------------------------------------

    public function timeLogDetail(int $timeLogId, Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $log = $student->timeLogs()->with('taskPhotos')->find($timeLogId);

        if (!$log) {
            return response()->json(['message' => 'Time log not found.'], 404);
        }

        return response()->json([
            'log' => $this->formatLogSegment($log, withPhotos: true),
        ]);
    }

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

        $activePlacement = $student->companies()->wherePivot('status', 'active')->first();
        $latestPlacement = $activePlacement
            ?? $student->companies()->orderByDesc('company_student.updated_at')->first();

        $placementStatus = $activePlacement
            ? 'active'
            : ($latestPlacement ? 'removed' : 'unassigned');

        $removalReason = (!$activePlacement && $latestPlacement)
            ? $latestPlacement->pivot->removal_reason
            : null;

        $company = $activePlacement ?: $latestPlacement;

        $companyStudentId = $this->activeCompanyStudentId($student);

        // Approved OJT schedule request takes priority over the company's default schedule.
        $approvedOjtSchedule = $companyStudentId
            ? OjtSchedule::where('company_student_id', $companyStudentId)
                ->where('status', 'approved')
                ->orderByDesc('start_date')
                ->first()
            : null;

        $companySchedule = null;
        if (!$approvedOjtSchedule && $company) {
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

            if ($approvedOjtSchedule) {
                $hoursPerDay = (float) $approvedOjtSchedule->hours_per_day ?: 8;
                $daysPerWeek = (float) $approvedOjtSchedule->days_per_week ?: 5;
                $estimatedEndBasis = 'approved_ojt_schedule';

                if ($approvedOjtSchedule->start_date && $approvedOjtSchedule->start_date->isFuture()) {
                    $baseDate = $approvedOjtSchedule->start_date->copy();
                }
            } elseif ($companySchedule) {
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
            }

            $hoursPerWeek = $hoursPerDay * $daysPerWeek;
            $weeksNeeded  = $hoursPerWeek > 0 ? $remainingHours / $hoursPerWeek : 0;
            $daysNeeded   = (int) floor($weeksNeeded * 7);
            $estimatedEndDate = $baseDate->copy()->addDays($daysNeeded);
        }

        $scheduleInfo = null;
        if ($approvedOjtSchedule) {
            $scheduleInfo = [
                'hours_per_day' => (float) $approvedOjtSchedule->hours_per_day,
                'days_per_week' => (int) $approvedOjtSchedule->days_per_week,
                'time_in'       => Carbon::parse($approvedOjtSchedule->time_in)->format('h:i A'),
                'time_out'      => Carbon::parse($approvedOjtSchedule->time_out)->format('h:i A'),
                'start_date'    => $approvedOjtSchedule->start_date?->format('Y-m-d'),
                'source'        => 'approved_ojt_schedule',
            ];
        } elseif ($companySchedule) {
            $scheduleInfo = [
                'hours_per_day' => $hoursPerDay ?? 8,
                'days_per_week' => 5, // Generally M-F by default unless defined elsewhere
                'time_in'       => Carbon::parse($companySchedule->time_in)->format('h:i A'),
                'time_out'      => Carbon::parse($companySchedule->time_out)->format('h:i A'),
                'start_date'    => $companySchedule->start_date ? Carbon::parse($companySchedule->start_date)->format('Y-m-d') : null,
                'source'        => 'company_schedule',
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
                'geofence_polygon' => $company->geofence_polygon
            ] : null,
            'placement_status' => $placementStatus,
            'removal_reason'   => $removalReason,
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

        $todayLogs = $student->timeLogs()
            ->with('taskPhotos')
            ->whereBetween('time_in', [$today, $todayEnd])
            ->orderBy('time_in')
            ->get();

        $openLog = $todayLogs->first(fn($l) => $l->time_out === null);

        $todayMinutes = $todayLogs
            ->whereNotNull('time_out')
            ->sum('duration_minutes');

        if ($openLog) {
            $todayMinutes += $openLog->time_in->diffInMinutes(Carbon::now());
        }

        $activePlacement = $student->companies()->wherePivot('status', 'active')->first();
        $isRemoved = !$activePlacement && $student->companies()->exists();

        $company = $activePlacement;

        $companyStudentId = $this->activeCompanyStudentId($student);

        $approvedOjtSchedule = $companyStudentId
            ? OjtSchedule::where('company_student_id', $companyStudentId)
                ->where('status', 'approved')
                ->orderByDesc('start_date')
                ->first()
            : null;

        $companySchedule = null;
        if (!$approvedOjtSchedule && $company) {
            $companySchedule = \App\Models\CompanySchedule::where('company_id', $company->id)
                ->orderBy('start_date', 'desc')
                ->first();
        }

        $effectiveTimeIn    = $approvedOjtSchedule?->time_in ?? $companySchedule?->time_in;
        $effectiveTimeOut   = $approvedOjtSchedule?->time_out ?? $companySchedule?->time_out;
        $effectiveStartDate = $approvedOjtSchedule?->start_date
            ?? ($companySchedule?->start_date ? Carbon::parse($companySchedule->start_date) : null);

        $isInternshipStarted = !$effectiveStartDate
            || Carbon::now()->startOfDay()->gte($effectiveStartDate->copy()->startOfDay());

        $canPunchIn  = $faceEnrolled && $openLog === null && !$isRemoved && $isInternshipStarted;
        $canPunchOut = $faceEnrolled && $openLog !== null && !$isRemoved;

        // Geofence info is still keyed off the company record itself, not the schedule.
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

        // Lunch break policy stays sourced from the company's own schedule, since it's a
        // company-wide policy rather than something an intern requests per OJT schedule.
        $companyScheduleForLunch = $companySchedule
            ?? ($company
                ? \App\Models\CompanySchedule::where('company_id', $company->id)->orderBy('start_date', 'desc')->first()
                : null);

        $lunchBreakInfo = null;
        if ($companyScheduleForLunch && $companyScheduleForLunch->lunch_break) {
            try {
                $lunchParts = explode('-', str_replace(' ', '', $companyScheduleForLunch->lunch_break));
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
                    $lunchBreakInfo = [
                        'lunch_time'            => '12:00',
                        'lunch_time_label'      => '12:00 PM',
                        'afternoon_start_time'  => '13:00',
                        'afternoon_start_label' => '1:00 PM',
                        'policy_message'        => 'Lunch Policy: ' . $companyScheduleForLunch->lunch_break,
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
            'schedule_label'      => ($effectiveTimeIn && $effectiveTimeOut)
                ? Carbon::parse($effectiveTimeIn)->format('h:i A') . ' - ' . Carbon::parse($effectiveTimeOut)->format('h:i A')
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
            'placement_status' => $isRemoved ? 'removed' : ($company ? 'active' : 'unassigned'),
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

    private function autoCloseStaleOpenLog(Student $student, ?TimeLog $openLog): ?TimeLog
    {
        if (!$openLog) {
            return null;
        }

        // Still today's shift — nothing to do.
        if ($openLog->time_in->isToday()) {
            return $openLog;
        }

        $companyStudentId = $this->activeCompanyStudentId($student);

        // Prefer an approved OJT schedule request, fall back to the company's default schedule.
        $approvedOjtSchedule = $companyStudentId
            ? OjtSchedule::where('company_student_id', $companyStudentId)
                ->where('status', 'approved')
                ->orderByDesc('start_date')
                ->first()
            : null;

        $scheduledTimeOut = $approvedOjtSchedule->time_out ?? null;

        if (!$scheduledTimeOut) {
            $company = $student->companies()->wherePivot('status', 'active')->first();
            $companySchedule = $company
                ? \App\Models\CompanySchedule::where('company_id', $company->id)
                    ->orderBy('start_date', 'desc')
                    ->first()
                : null;
            $scheduledTimeOut = $companySchedule->time_out ?? null;
        }

        // Last-resort default if no schedule exists anywhere.
        $scheduledTimeOut = $scheduledTimeOut ?: '18:00';

        $bufferMinutes = (int) config('services.timelog.auto_close_buffer_minutes', 20);

        // Close it on the calendar day the shift STARTED, at scheduled time-out + buffer —
        // e.g. scheduled 6:00 PM out, buffer 20 min -> auto time_out of 6:20 PM that same day.
        $closeAt = Carbon::parse(
            $openLog->time_in->toDateString() . ' ' . Carbon::parse($scheduledTimeOut)->format('H:i:s')
        )->addMinutes($bufferMinutes);

        // Guard rails: never close before time_in started, and never into the future.
        if ($closeAt->lessThanOrEqualTo($openLog->time_in)) {
            $closeAt = $openLog->time_in->copy()->addHours(8);
        }
        if ($closeAt->greaterThan(Carbon::now())) {
            $closeAt = Carbon::now();
        }

        $grossDurationMinutes = (int) $openLog->time_in->diffInMinutes($closeAt);

        $breakDurationMinutes = 0;
        if ($openLog->break_out && $openLog->break_in) {
            $breakDurationMinutes = (int) Carbon::parse($openLog->break_out)
                ->diffInMinutes(Carbon::parse($openLog->break_in));
        }

        $netDurationMinutes = max(0, $grossDurationMinutes - $breakDurationMinutes);

        $openLog->update([
            'time_out'            => $closeAt,
            'duration_minutes'    => $netDurationMinutes,
            'verification_method' => 'auto_closed_missed_punch_out',
            'task_note'           => trim(
                ($openLog->task_note ? $openLog->task_note . ' ' : '') . '[Auto-closed: forgot to time out]'
            ),
        ]);

        Log::warning("Auto-closed stale open time log #{$openLog->id} for student #{$student->id} at {$closeAt}.");

        return null;
    }

    public function timePunch(Request $request): JsonResponse
    {
        $action = $request->input('action');

        $request->validate([
            'action'                   => ['required', 'in:time_in,time_out,break_out,break_in'],
            // Image is required ONLY for time_in and time_out
            'image'                    => ['required_if:action,time_in,time_out', 'nullable', 'image', 'max:5120'], 
            'device_info'              => ['nullable', 'string', 'max:500'],
            'latitude'                 => ['required', 'numeric', 'between:-90,90'],
            'longitude'                => ['required', 'numeric', 'between:-180,180'],
            'location_accuracy_meters' => ['nullable', 'numeric'],
            'timestamp'                => ['nullable', 'date'],
            'task_note'                => ['nullable', 'string', 'max:1000'], 
        ]);

        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $openLog = $student->timeLogs()->whereNull('time_out')->latest('time_in')->first();
        $wasAutoClosed = $openLog && !$openLog->time_in->isToday();
        $openLog = $this->autoCloseStaleOpenLog($student, $openLog);

        if ($action === 'time_out' && $wasAutoClosed) {
            return response()->json([
                'message' => 'Your previous shift was automatically timed out because you forgot to punch out. Please punch in to start today\'s shift.',
            ], 422);
        }

        $faceMatchScore = null;

        // --- FACE RECOGNITION (Only runs for time_in and time_out) ---
        if (in_array($action, ['time_in', 'time_out'])) {
            $faceProfile = $student->faceProfile;

            if (!$faceProfile || !$faceProfile->is_active || empty($faceProfile->face_embedding)) {
                return response()->json(['message' => 'Please enroll your face first before punching in/out.'], 422);
            }

            try {
                $embeddingList = $this->python->extractEmbedding($request->file('image'));
            } catch (ValidationException $e) {
                throw $e;
            } catch (\Exception $e) {
                Log::error('Face punch extraction error: ' . $e->getMessage());
                return response()->json([
                    'message' => 'Could not connect to the face recognition service. Please try again.',
                    'error'   => config('app.debug') ? $e->getMessage() : null,
                ], 500);
            }

            $scannedEmbedding = FaceEmbedding::normalize($embeddingList);
            $threshold        = (float) config('services.face.match_threshold', 0.45);
            $distance         = FaceMatcher::euclideanDistance($faceProfile->face_embedding, $scannedEmbedding);

            if ($distance > $threshold) {
                return response()->json([
                    'message'          => 'Face recognition failed. Please try again in better lighting.',
                    'face_match_score' => round($distance, 4),
                ], 422);
            }

            $faceMatchScore = round($distance, 4);
        }

        $now       = $request->filled('timestamp') ? Carbon::parse($request->input('timestamp')) : Carbon::now();
        $latitude  = $request->input('latitude');
        $longitude = $request->input('longitude');

        if ($action === 'time_in') {
            if ($openLog) {
                return response()->json(['message' => 'You already have an open time log. Please punch out first.'], 422);
            }

            $log = $student->timeLogs()->create([
                'time_in'             => $now,
                'company_student_id'  => $this->activeCompanyStudentId($student),
                'latitude_in'         => $latitude,
                'longitude_in'        => $longitude,
                'verification_method' => 'facial_recognition',
                'face_match_score'    => $faceMatchScore,
                'device_info'         => $request->input('device_info'),
            ]);

            $log->load('taskPhotos');

            return response()->json([
                'message' => 'Punched in successfully.',
                'log'     => $this->formatLogSegment($log),
            ], 201);
        }

        // Everything else requires an open log:
        if (!$openLog) {
            return response()->json(['message' => 'No active shift found. Please punch in first.'], 422);
        }

        if ($action === 'break_out') {
            if ($openLog->break_out) {
                return response()->json(['message' => 'You have already broken out.'], 422);
            }

            $openLog->update([
                'break_out' => $now,
                // No face score needed here
            ]);

            $openLog->load('taskPhotos');

            return response()->json([
                'message' => 'Break started successfully.',
                'log'     => $this->formatLogSegment($openLog),
            ]);
        }

        if ($action === 'break_in') {
            if (!$openLog->break_out) {
                return response()->json(['message' => 'You must break out before you can break in.'], 422);
            }
            if ($openLog->break_in) {
                return response()->json(['message' => 'You have already broken in.'], 422);
            }

            $openLog->update([
                'break_in' => $now,
                // No face score needed here
            ]);

            $openLog->load('taskPhotos');

            return response()->json([
                'message' => 'Break ended successfully. Welcome back!',
                'log'     => $this->formatLogSegment($openLog),
            ]);
        }

        if ($action === 'time_out') {
            $grossDurationMinutes = (int) Carbon::parse($openLog->time_in)->diffInMinutes($now);
            
            $breakDurationMinutes = 0;
            if ($openLog->break_out && $openLog->break_in) {
                $breakDurationMinutes = (int) Carbon::parse($openLog->break_out)->diffInMinutes(Carbon::parse($openLog->break_in));
            }

            $netDurationMinutes = max(0, $grossDurationMinutes - $breakDurationMinutes);

            $openLog->update([
                'time_out'         => $now,
                'latitude_out'     => $latitude,
                'longitude_out'    => $longitude,
                'duration_minutes' => $netDurationMinutes,
                'face_match_score' => $faceMatchScore,
                'task_note'        => $request->input('task_note'),
            ]);

            // --- AUTO-DEACTIVATE COMPANY PLACEMENT ON OJT COMPLETION ---
            $course        = $student->section?->course;
            $requiredHours = $course ? (float) $course->required_hours : 0;

            if ($requiredHours > 0) {
                $activeCompanyStudentId = $this->activeCompanyStudentId($student);

        $totalMinutes = $activeCompanyStudentId
        ? (float) $student->timeLogs()->where('company_student_id', $activeCompanyStudentId)->sum('duration_minutes')
        : 0.0;
                    $renderedHours = $totalMinutes / 60;

                    if ($renderedHours >= $requiredHours) {
                        \Illuminate\Support\Facades\DB::table('company_student')
                            ->where('student_id', $student->id)
                            ->where('status', 'active')
                            ->update([
                                'status'         => 'inactive',
                                'removal_reason' => 'internship_completed',
                                'updated_at'     => now(),
                            ]);

                        Log::info("Student #{$student->id} reached 100% OJT hours ({$renderedHours}/{$requiredHours}). Company placement deactivated.");
                    }
                }
                // --- END AUTO-DEACTIVATE ---

                $openLog->load('taskPhotos');

                return response()->json([
                    'message' => 'Punched out successfully.',
                    'log'     => $this->formatLogSegment($openLog),
                ]);
            }
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

        $company    = $student->companies()->wherePivot('status', 'active')->first();

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
                'email_verified_at' => $user->email_verified_at,
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
    // Update Profile Email
    // -------------------------------------------------------------------------

    public function updateEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email'            => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'current_password' => ['required', 'string'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            throw ValidationException::withMessages([
                'current_password' => ['The current password is incorrect.'],
            ]);
        }

        $user->update(['email' => $validated['email']]);

        return response()->json([
            'message' => 'Email updated successfully.',
            'user'    => [
                'id'    => $user->id,
                'name'  => $user->name,
                'email' => $user->email,
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

    // -------------------------------------------------------------------------
    // Document Upload & Fetch
    // -------------------------------------------------------------------------

     public function getDocuments(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $courseId = $student->section?->course_id;

        $requirements = $courseId
            ? \App\Models\Course::find($courseId)->documentRequirements()->with('documentType')->get()
            : collect();

        $submitted = \App\Models\StudentDocument::where('student_id', $student->id)
            ->orderByDesc('period_start')
            ->orderByDesc('uploaded_at')
            ->get()
            ->groupBy('document_requirement_id');

        $data = $requirements->map(function ($req) use ($submitted) {
            $subsForReq = $submitted->get($req->id, collect());
            $current = $subsForReq->first(); // newest, thanks to the ordering above
            $recurrence = $req->documentType->recurrence ?? 'none';

            $status = 'pending';
            if ($current) {
                $status = is_object($current->review_status) ? $current->review_status->value : $current->review_status;
                if ($status === 'pending') {
                    $status = 'uploaded';
                }
            }

            return [
                'id'          => (string) $req->id,
                'title'       => $req->title,
                'status'      => $status,
                'recurrence'  => $recurrence,
                'period_start'=> $current?->period_start,
                'uri'         => $current ? url('/api/intern/documents/download/' . $req->id) : null,
                'history'     => $recurrence !== 'none'
                    ? $subsForReq->skip(1)->values()->map(fn ($s) => [
                        'period_start' => $s->period_start,
                        'status'       => is_object($s->review_status) ? $s->review_status->value : $s->review_status,
                        'uri'          => url('/api/intern/documents/download/' . $req->id . '?period=' . $s->period_start),
                    ])
                    : [],
            ];
        });

        return response()->json($data);
    }

   public function downloadDocument(int $id, Request $request)
    {
        $student = $this->resolveStudent($request);
        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $query = \App\Models\StudentDocument::where('student_id', $student->id)
            ->where('document_requirement_id', $id);

        if ($request->filled('period')) {
            $query->where('period_start', $request->query('period'));
        } else {
            $query->orderByDesc('period_start')->orderByDesc('uploaded_at');
        }

        $sub = $query->first();

        if (!$sub || !$sub->file_path) {
            return response()->json(['message' => 'File not found.'], 404);
        }

        $fullPath = storage_path('app/public/' . ltrim($sub->file_path, '/'));
        if (!file_exists($fullPath)) {
            $fullPath = storage_path('app/' . ltrim($sub->file_path, '/'));
        }

        if (!file_exists($fullPath)) {
            return response()->json(['message' => 'File physical asset missing.'], 404);
        }

        return response()->file($fullPath, [
            'Content-Type' => $sub->mime_type ?: 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . ($sub->original_filename ?: 'document.pdf') . '"',
        ]);
    }

    public function uploadDocument(Request $request): JsonResponse
    {
        $request->validate([
            'document_id' => ['required', 'integer'],
            'file'        => ['required', 'file', 'max:10240'], // Max 10MB
        ]);

        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $requirement = \App\Models\DocumentRequirement::with('documentType')->find($request->document_id);
        if (!$requirement) {
            return response()->json(['message' => 'Document requirement not found.'], 404);
        }

        $recurrence = $requirement->documentType->recurrence ?? 'none';
        $periodStart = match ($recurrence) {
            'weekly' => Carbon::now()->startOfWeek()->toDateString(),
            'daily'  => Carbon::now()->startOfDay()->toDateString(),
            default  => null,
        };

        $file = $request->file('file');
        $path = $file->store('student_documents', 'public');

        $studentDocument = \App\Models\StudentDocument::updateOrCreate(
            [
                'student_id'              => $student->id,
                'document_requirement_id' => $requirement->id,
                'period_start'            => $periodStart,
            ],
            [
                'file_path'         => $path,
                'original_filename' => $file->getClientOriginalName(),
                'file_size'         => $file->getSize(),
                'mime_type'         => $file->getMimeType(),
                'uploaded_at'       => Carbon::now(),
                'review_status'     => \App\Support\DocumentReviewStatus::Pending,
                'rejection_reason'  => null,
            ]
        );

        return response()->json([
            'message'  => 'Document uploaded successfully.',
            'document' => $studentDocument,
        ], 200);
    }

    public function requestCompany(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'address'   => ['required', 'string', 'max:500'],
            'latitude'  => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $companyRequest = \App\Models\CompanyRequest::create([
            'user_id'   => auth()->id(),
            'name'      => $validated['name'],
            'address'   => $validated['address'],
            'latitude'  => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'status'    => \App\Models\CompanyRequest::STATUS_PENDING,
        ]);

        return response()->json([
            'message' => 'Company request submitted successfully.',
            'request' => $companyRequest,
        ], 201);
    }

    // -------------------------------------------------------------------------
// Time: Task note & photo update (for an open or recent time log)
// -------------------------------------------------------------------------

    public function taskChecker(int $timeLogId, Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $timeLog = $student->timeLogs()->with('taskPhotos')->where('id', $timeLogId)->first();

        if (!$timeLog) {
            return response()->json(['message' => 'Time log not found.'], 404);
        }

        $isToday = $timeLog->time_in && $timeLog->time_in->isToday();

        if (!$isToday) {
            return response()->json([
                'time_log_id'  => $timeLog->id,
                'is_today'     => false,
                'has_note'     => filled($timeLog->task_note),
                'has_photos'   => $timeLog->taskPhotos->isNotEmpty(),
                'needs_update' => false,
                'message'      => 'This time log is not from today; no reminder needed.',
            ]);
        }

        $hasNote = filled($timeLog->task_note);
        $hasPhotos = $timeLog->taskPhotos->isNotEmpty();
        $needsUpdate = !$hasNote || !$hasPhotos;

        return response()->json([
            'time_log_id'  => $timeLog->id,
            'is_today'     => true,
            'has_note'     => $hasNote,
            'has_photos'   => $hasPhotos,
            'needs_update' => $needsUpdate,
            'message'      => $needsUpdate
                ? 'Please add a task note and photo(s) for this time log.'
                : 'Task note and photos are complete.',
        ]);
    }

    public function taskUpdate(Request $request, int $timeLogId): JsonResponse
    {
        $request->validate([
            'note'     => ['nullable', 'string', 'max:1000'],
            'files'    => ['nullable', 'array'],
            'files.*'  => ['file', 'image', 'max:5120'], // 5MB per photo, images only
        ]);

        $student = $this->resolveStudent($request);

        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $timeLog = $student->timeLogs()->where('id', $timeLogId)->first();

        if (!$timeLog) {
            return response()->json(['message' => 'Time log not found.'], 404);
        }

        if ($request->has('note')) {
            $timeLog->update(['task_note' => $request->input('note')]);
        }

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('task_photos', 'public');

                TimeLogTaskPhoto::create([
                    'time_log_id'       => $timeLog->id,
                    'student_id'        => $student->id,
                    'file_path'         => $path,
                    'original_filename' => $file->getClientOriginalName(),
                    'file_size'         => $file->getSize(),
                    'mime_type'         => $file->getMimeType(),
                    'status'            => TimeLogTaskPhoto::STATUS_SUBMITTED,
                    'submitted_at'      => Carbon::now(),
                ]);
            }
        }

        $timeLog->load('taskPhotos');

        return response()->json([
            'message' => 'Task updated successfully.',
            'log'     => $this->formatLogSegment($timeLog, withPhotos: true),
        ]);
    }

    // -------------------------------------------------------------------------
    // Schedule Request
    // -------------------------------------------------------------------------

    /**
     * Intern submits a schedule request (start date, time in/out, hours/days).
     */
    public function requestSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date'   => ['required', 'date', 'after_or_equal:today'],
            'time_in'      => ['required', 'date_format:H:i'],
            'time_out'     => ['required', 'date_format:H:i', 'after:time_in'],
            'hours_per_day'=> ['nullable', 'numeric', 'min:1', 'max:24'],
            'days_per_week'=> ['nullable', 'integer', 'min:1', 'max:7'],
            'reason'       => ['nullable', 'string', 'max:1000'],
        ]);

        $student = $this->resolveStudent($request);
        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        $companyStudentId = $this->activeCompanyStudentId($student);
        if (!$companyStudentId) {
            return response()->json(['message' => 'You must be assigned to a company before requesting a schedule.'], 422);
        }

        // Only one pending request allowed at a time
        $existingPending = OjtSchedule::where('company_student_id', $companyStudentId)
            ->where('status', 'pending')
            ->exists();

        if ($existingPending) {
            return response()->json(['message' => 'You already have a pending schedule request. Please wait for it to be reviewed.'], 422);
        }

        $schedule = OjtSchedule::create([
            'company_student_id' => $companyStudentId,
            'start_date'         => $validated['start_date'],
            'time_in'            => $validated['time_in'],
            'time_out'           => $validated['time_out'],
            'hours_per_day'      => $validated['hours_per_day'] ?? 8,
            'days_per_week'      => $validated['days_per_week'] ?? 5,
            'reason'             => $validated['reason'] ?? null,
            'status'             => 'pending',
        ]);

        return response()->json([
            'message'  => 'Schedule request submitted successfully.',
            'schedule' => [
                'id'                 => $schedule->id,
                'company_student_id' => $schedule->company_student_id,
                'start_date'         => $schedule->start_date?->toDateString(),
                'time_in'            => $schedule->time_in,
                'time_out'           => $schedule->time_out,
                'hours_per_day'      => (float) $schedule->hours_per_day,
                'days_per_week'      => (int) $schedule->days_per_week,
                'reason'             => $schedule->reason,
                'status'             => $schedule->status,
                'created_at'         => $schedule->created_at?->toIso8601String(),
            ],
        ], 201);
    }

    /**
     * Intern views their schedule requests.
     */
    public function getScheduleRequests(Request $request): JsonResponse
    {
        $student = $this->resolveStudent($request);
        if (!$student) {
            return response()->json(['message' => 'Student record not found.'], 404);
        }

        // Collect all company_student IDs for this student
        $companyStudentIds = \Illuminate\Support\Facades\DB::table('company_student')
            ->where('student_id', $student->id)
            ->pluck('id');

        $schedules = OjtSchedule::whereIn('company_student_id', $companyStudentIds)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($s) => [
                'id'                 => $s->id,
                'company_student_id' => $s->company_student_id,
                'start_date'         => $s->start_date?->toDateString(),
                'time_in'            => $s->time_in,
                'time_out'           => $s->time_out,
                'hours_per_day'      => (float) $s->hours_per_day,
                'days_per_week'      => (int) $s->days_per_week,
                'reason'             => $s->reason,
                'status'             => $s->status,
                'created_at'         => $s->created_at?->toIso8601String(),
                'updated_at'         => $s->updated_at?->toIso8601String(),
            ]);

        return response()->json(['schedules' => $schedules]);
    }
}

