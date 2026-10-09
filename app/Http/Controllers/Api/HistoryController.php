<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SchoolYear;
use App\Models\Student;
use App\Models\StudentDocument;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HistoryController extends Controller
{
    /**
     * Past school years with aggregated stats (the history cards).
     */
    public function schoolYears(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$this->canViewHistory($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $query = SchoolYear::with('sections')
            ->where('is_active', false)
            ->orderByDesc('end_date');

        $courseId = $this->scopedCourseId($user);
        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        $schoolYears = $query->get()->map(function ($sy) {
            $sections = $sy->sections;
            $studentCount = $sections->sum(fn ($s) => $s->students()->count());
            $coordinatorIds = $sections->pluck('coordinator_user_id')->filter()->unique();

            $deanCount = User::whereHas('roles', fn ($q) => $q->where('name', 'dean'))
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

    /**
     * Students and documents belonging to a single past school year.
     *
     * Query params:
     *  - search          filters both lists (name / student number / filename)
     *  - students_page   page number for the students list
     *  - documents_page  page number for the documents list
     */
    public function schoolYearDetails(Request $request, SchoolYear $schoolYear): JsonResponse
    {
        $user = $request->user();
        if (!$this->canViewHistory($user)) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        // Deans, coordinators and admins tied to a course can only open that course's school years.
        $courseId = $this->scopedCourseId($user);
        if ($courseId && (int) $schoolYear->course_id !== (int) $courseId) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $search = $request->filled('search') ? $request->input('search') : null;

        $inSchoolYear = fn ($q) => $q->where('school_year_id', $schoolYear->id);

        // --- Students ---
        $studentsQuery = Student::with(['section.course', 'company'])
            ->whereHas('section', $inSchoolYear)
            ->latest();

        if ($search) {
            $studentsQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('student_number', 'like', "%{$search}%");
            });
        }

        // --- Documents ---
        $documentsQuery = StudentDocument::with(['student.section.course', 'documentType', 'documentRequirement'])
            ->whereHas('student.section', $inSchoolYear)
            ->latest('uploaded_at');

        if ($search) {
            $documentsQuery->where(function ($q) use ($search) {
                $q->where('original_filename', 'like', "%{$search}%")
                    ->orWhereHas('student', function ($sq) use ($search) {
                        $sq->where('first_name', 'like', "%{$search}%")
                            ->orWhere('last_name', 'like', "%{$search}%")
                            ->orWhere('student_number', 'like', "%{$search}%");
                    });
            });
        }

        return response()->json([
            'school_year' => [
                'id'         => $schoolYear->id,
                'name'       => $schoolYear->name,
                'start_date' => $schoolYear->start_date,
                'end_date'   => $schoolYear->end_date,
                'course_id'  => $schoolYear->course_id,
            ],
            'students'  => $studentsQuery->paginate(50, ['*'], 'students_page'),
            'documents' => $documentsQuery->paginate(50, ['*'], 'documents_page'),
        ]);
    }

    private function canViewHistory(?User $user): bool
    {
        return $user && (
            $user->hasRole('admin')
            || $user->hasRole('super_admin')
            || $user->hasRole('dean')
            || $user->hasRole('coordinator')
        );
    }

    /**
     * The course a user is restricted to, or null when they can see every course.
     */
    private function scopedCourseId(User $user): ?int
    {
        if ($user->hasRole('dean') && $user->deanPortalCourse()) {
            return $user->deanPortalCourse()->id;
        }

        if (($user->hasRole('coordinator') || $user->hasRole('admin')) && $user->course_id) {
            return $user->course_id;
        }

        return null;
    }
}