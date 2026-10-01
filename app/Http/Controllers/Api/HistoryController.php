<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\StudentDocument;
use Illuminate\Support\Facades\DB;

class HistoryController extends Controller
{
    /**
     * Get inactive/past students.
     */
    public function students(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('super_admin') && !$user->hasRole('dean') && !$user->hasRole('coordinator'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = Student::with(['section.course', 'company'])
            ->where('is_active', false)
            ->latest();

        // Optional filtering by course/program
        if ($user->hasRole('dean') && $user->deanPortalCourse()) {
            $courseId = $user->deanPortalCourse()->id;
            $query->whereHas('section', function($q) use ($courseId) {
                $q->where('course_id', $courseId);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('student_number', 'like', "%{$search}%");
            });
        }

        return response()->json([
            'data' => $query->paginate(50)
        ]);
    }

    /**
     * Get documents for past students/academic years.
     */
    public function documents(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('super_admin') && !$user->hasRole('dean') && !$user->hasRole('coordinator'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = StudentDocument::with(['student.section.course', 'documentType', 'documentRequirement'])
            ->whereHas('student', function($q) {
                $q->where('is_active', false);
            })
            ->latest('uploaded_at');

        if ($user->hasRole('dean') && $user->deanPortalCourse()) {
            $courseId = $user->deanPortalCourse()->id;
            $query->whereHas('student.section', function($q) use ($courseId) {
                $q->where('course_id', $courseId);
            });
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                  ->orWhereHas('student', function($sq) use ($search) {
                      $sq->where('first_name', 'like', "%{$search}%")
                         ->orWhere('last_name', 'like', "%{$search}%")
                         ->orWhere('student_number', 'like', "%{$search}%");
                  });
            });
        }

        return response()->json([
            'data' => $query->paginate(50)
        ]);
    }

    /**
     * Get past school years with aggregated stats.
     */
    public function schoolYears(Request $request)
    {
        $user = $request->user();
        if (!$user || (!$user->hasRole('admin') && !$user->hasRole('super_admin') && !$user->hasRole('dean') && !$user->hasRole('coordinator'))) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = \App\Models\SchoolYear::with('sections')
            ->where('is_active', false)
            ->orderByDesc('end_date');

        // Scope by course
        if ($user->hasRole('dean') && $user->deanPortalCourse()) {
            $query->where('course_id', $user->deanPortalCourse()->id);
        } elseif ($user->hasRole('coordinator') && $user->course_id) {
            $query->where('course_id', $user->course_id);
        } elseif ($user->hasRole('admin') && $user->course_id) {
            $query->where('course_id', $user->course_id);
        }

        $schoolYears = $query->get()->map(function ($sy) {
            $sections = $sy->sections;
            $studentCount = $sections->sum(fn($s) => $s->students()->count());
            $coordinatorIds = $sections->pluck('coordinator_user_id')->filter()->unique();

            $deanCount = \App\Models\User::whereHas('roles', fn($q) => $q->where('name', 'dean'))
                ->where('course_id', $sy->course_id)
                ->count();

            return [
                'id'                => $sy->id,
                'name'              => $sy->name,
                'start_date'        => $sy->start_date,
                'end_date'          => $sy->end_date,
                'section_count'     => $sections->count(),
                'student_count'     => $studentCount,
                'coordinator_count' => $coordinatorIds->count(),
                'dean_count'        => $deanCount,
                'course_id'         => $sy->course_id,
            ];
        });

        return response()->json(['data' => $schoolYears]);
    }
}
