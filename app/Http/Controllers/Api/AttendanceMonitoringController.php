<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TimeLog;
use App\Support\DeanPortalScope;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AttendanceMonitoringController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $query = TimeLog::with([
            'student:id,student_number,first_name,middle_name,last_name,section_id',
            'student.section:id,name,course_id',
            'student.section.course:id,code,name',
        ]);

        // Scoping logic
        if (DeanPortalScope::isPortalUser($user)) {
            $studentIds = DeanPortalScope::studentsQuery($user)->pluck('id');
            $query->whereIn('student_id', $studentIds);
        } elseif ($user->hasRole('coordinator')) {
            $user->loadMissing('coordinator');
            $coordinatorId = $user->coordinator?->id;
            if ($coordinatorId) {
                $query->whereHas('student.section', function ($q) use ($coordinatorId) {
                    $q->where('coordinator_id', $coordinatorId);
                });
            } else {
                $query->whereRaw('1 = 0');
            }
        } elseif ($user->hasRole('super_admin')) {
            // Super Admin sees all logs
        } else {
            $query->whereRaw('1 = 0');
        }

        // Date / Range Filtering
        $range = $request->query('range', 'today'); // 'today', 'week', 'month', 'custom'
        $today = Carbon::today();

        if ($range === 'today') {
            $query->whereDate('time_in', $today);
        } elseif ($range === 'week') {
            $query->whereBetween('time_in', [$today->copy()->startOfWeek(), $today->copy()->endOfWeek()]);
        } elseif ($range === 'month') {
            $query->whereBetween('time_in', [$today->copy()->startOfMonth(), $today->copy()->endOfMonth()]);
        } elseif ($range === 'custom') {
            if ($request->filled('date_from')) {
                $query->whereDate('time_in', '>=', $request->query('date_from'));
            }
            if ($request->filled('date_to')) {
                $query->whereDate('time_in', '<=', $request->query('date_to'));
            }
        }

        // Search filtering (student name or number)
        if ($request->filled('search')) {
            $search = $request->query('search');
            $query->whereHas('student', function ($q) use ($search) {
                $q->where('student_number', 'like', "%{$search}%")
                  ->orWhere('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%");
            });
        }

        // Section filter if specified
        if ($request->filled('section_id')) {
            $query->whereHas('student', function ($q) use ($request) {
                $q->where('section_id', $request->query('section_id'));
            });
        }

        $logs = $query->latest('time_in')->get();

        $result = $logs->map(function ($log) {
            $student = $log->student;
            $section = $student?->section;
            $course = $section?->course;

            return [
                'id' => $log->id,
                'student_id' => $log->student_id,
                'student_name' => $student ? trim(collect([$student->first_name, $student->middle_name, $student->last_name])->filter()->implode(' ')) : 'Unknown Student',
                'student_number' => $student?->student_number ?? '—',
                'course_code' => $course?->code ?? '—',
                'section_code' => $section?->name ?? '—',
                'time_in' => $log->time_in,
                'break_out' => $log->break_out,
                'break_in' => $log->break_in,
                'time_out' => $log->time_out,
                'duration_minutes' => $log->duration_minutes,
                'verification_method' => $log->verification_method,
                'face_match_score' => $log->face_match_score,
                'task_note' => $log->task_note,
                'session_period' => $log->session_period,
                'status' => $log->status ?? 'pending',
            ];
        });

        return response()->json($result);
    }
}
