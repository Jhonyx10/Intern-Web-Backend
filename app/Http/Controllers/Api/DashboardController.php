<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRequest;
use App\Models\Course;
use App\Models\Section;
use App\Models\Student;
use App\Models\Evaluation;
use App\Models\Supervisor;
use App\Models\TimeLog;
use App\Models\User;
use App\Support\DeanPortalScope;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /**
     * Return aggregated dashboard metrics for Super Admin.
     */
    public function superAdmin(Request $request): JsonResponse
    {
        // Role distribution
        $rolesDistribution = DB::table('users')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->select('roles.name as role', 'roles.label as label', DB::raw('count(users.id) as count'))
            ->groupBy('roles.id', 'roles.name', 'roles.label')
            ->get();

        // Company approval breakdown
        $totalCompanies = Company::count();
        $approvedCompanies = Company::where('is_approved', true)->count();
        $pendingCompanies = Company::where('is_approved', false)->count();

        // Student assignment breakdown
        $totalStudents = Student::count();
        $assignedStudentIds = DB::table('company_student')->pluck('student_id')->unique();
        $assignedStudentsCount = $assignedStudentIds->count();
        $unassignedStudentsCount = max(0, $totalStudents - $assignedStudentsCount);

        // Company requests count
        $pendingRequests = CompanyRequest::where('status', 'pending')->count();
        $acceptedRequests = CompanyRequest::where('status', 'accepted')->count();

        // Recent 14-day attendance logs activity count
        $dailyLogs = TimeLog::select(DB::raw('DATE(created_at) as date'), DB::raw('count(*) as count'))
            ->where('created_at', '>=', now()->subDays(14))
            ->groupBy(DB::raw('DATE(created_at)'))
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'data' => [
                'overview' => [
                    'total_users'             => User::count(),
                    'total_companies'         => $totalCompanies,
                    'approved_companies'      => $approvedCompanies,
                    'pending_companies'       => $pendingCompanies,
                    'total_students'          => $totalStudents,
                    'assigned_students'       => $assignedStudentsCount,
                    'unassigned_students'     => $unassignedStudentsCount,
                    'pending_company_requests'=> $pendingRequests,
                ],
                'charts' => [
                    'roles_distribution' => $rolesDistribution,
                    'company_status' => [
                        ['name' => 'Approved', 'value' => $approvedCompanies, 'color' => '#10B981'],
                        ['name' => 'Pending Approval', 'value' => $pendingCompanies, 'color' => '#F59E0B'],
                    ],
                    'student_placement' => [
                        ['name' => 'Assigned to Company', 'value' => $assignedStudentsCount, 'color' => '#6366F1'],
                        ['name' => 'Unassigned', 'value' => $unassignedStudentsCount, 'color' => '#9CA3AF'],
                    ],
                    'company_requests' => [
                        ['name' => 'Pending Review', 'value' => $pendingRequests, 'color' => '#F59E0B'],
                        ['name' => 'Accepted by Coordinator', 'value' => $acceptedRequests, 'color' => '#3B82F6'],
                    ],
                    'daily_logs_trend' => $dailyLogs,
                ]
            ]
        ]);
    }

    /**
     * Return aggregated dashboard metrics for Dean.
     */
    public function dean(Request $request): JsonResponse
    {
        $user = $request->user();
        $course = DeanPortalScope::course($user);
        $major = DeanPortalScope::major($user);

        if (!$course) {
            return response()->json([
                'data' => [
                    'course' => null,
                    'major'  => null,
                    'overview' => [
                        'total_sections'       => 0,
                        'total_students'       => 0,
                        'assigned_students'    => 0,
                        'unassigned_students'  => 0,
                        'total_hours_rendered' => 0,
                    ],
                    'charts' => [
                        'section_breakdown' => [],
                        'placement_status' => [
                            ['name' => 'Assigned', 'value' => 0, 'color' => '#10B981'],
                            ['name' => 'Unassigned', 'value' => 0, 'color' => '#EF4444'],
                        ],
                    ]
                ]
            ]);
        }

        // Get sections belonging to course/major scope
        $sections = DeanPortalScope::sectionsQuery($user)->with('students.timeLogs')->get();
        $students = DeanPortalScope::studentsQuery($user)->with(['companies', 'timeLogs', 'ojtSchedule'])->get();
        $totalStudents = $students->count();

        $assignedCount = $students->filter(fn($s) => $s->companies->isNotEmpty())->count();
        $unassignedCount = max(0, $totalStudents - $assignedCount);

        // Calculate hours per section
        $sectionBreakdown = $sections->map(function ($sec) {
            $secStudents = $sec->students;
            $totalHrs = 0;
            foreach ($secStudents as $s) {
                $totalHrs += round(($s->timeLogs->sum('duration_minutes') ?? 0) / 60, 1);
            }
            return [
                'id'            => $sec->id,
                'name'          => $sec->name,
                'student_count' => $secStudents->count(),
                'total_hours'   => round($totalHrs, 1),
                'avg_hours'     => $secStudents->count() > 0 ? round($totalHrs / $secStudents->count(), 1) : 0,
            ];
        });

        // Overall total hours rendered across scope
        $totalHoursRendered = 0;
        foreach ($students as $s) {
            $totalHoursRendered += round(($s->timeLogs->sum('duration_minutes') ?? 0) / 60, 1);
        }

        return response()->json([
            'data' => [
                'course' => [
                    'id'   => $course->id,
                    'code' => $course->code,
                    'name' => $course->name,
                ],
                'major' => $major ? [
                    'id'   => $major->id,
                    'code' => $major->code,
                    'name' => $major->name,
                ] : null,
                'overview' => [
                    'total_sections'       => $sections->count(),
                    'total_students'       => $totalStudents,
                    'assigned_students'    => $assignedCount,
                    'unassigned_students'  => $unassignedCount,
                    'total_hours_rendered' => round($totalHoursRendered, 1),
                ],
                'charts' => [
                    'section_breakdown' => $sectionBreakdown,
                    'placement_status' => [
                        ['name' => 'Assigned', 'value' => $assignedCount, 'color' => '#10B981'],
                        ['name' => 'Unassigned', 'value' => $unassignedCount, 'color' => '#EF4444'],
                    ],
                ]
            ]
        ]);
    }

    /**
     * Return aggregated dashboard metrics for Program Head.
     */
public function programHead(Request $request): JsonResponse
{
    $courses = Course::with([
        'documentRequirements',
        'sections.students.companies',
        'sections.students.timeLogs',
        'sections.students.ojtSchedule',
        'sections.students.ojtEvaluations',
        'sections.students.documents.documentRequirement',
    ])->get();

    $courseAnalytics = $courses->map(function ($course) {
        $students = $course->sections->flatMap(fn($sec) => $sec->students);
        $totalStudents = $students->count();

        $assignedStudents = $students->filter(fn($s) => $s->companies->isNotEmpty());
        $assignedCount = $assignedStudents->count();
        $unassignedCount = max(0, $totalStudents - $assignedCount);

        $totalRequiredHours = $students->sum(fn($s) => $s->ojtSchedule->required_hours ?? 0);

        $totalRenderedHours = $students->sum(function ($s) {
            return round(($s->timeLogs->sum('duration_minutes') ?? 0) / 60, 1);
        });

        $internshipPercentage = $totalRequiredHours > 0
            ? round(min(100, ($totalRenderedHours / $totalRequiredHours) * 100), 1)
            : 0;

        $completedCount = $students->filter(function ($s) {
            $required = $s->ojtSchedule->required_hours ?? 0;
            $rendered = round(($s->timeLogs->sum('duration_minutes') ?? 0) / 60, 1);
            return $required > 0 && $rendered >= $required;
        })->count();

        $inProgressCount = max(0, $assignedCount - $completedCount);

        $notStartedCount = $students->filter(function ($s) {
            return $s->companies->isNotEmpty() && $s->timeLogs->sum('duration_minutes') <= 0;
        })->count();

        $avgHoursPerStudent = $totalStudents > 0
            ? round($totalRenderedHours / $totalStudents, 1)
            : 0;

        $avgCompletionPct = $totalStudents > 0
            ? round($students->avg(function ($s) {
                $required = $s->ojtSchedule->required_hours ?? 0;
                $rendered = round(($s->timeLogs->sum('duration_minutes') ?? 0) / 60, 1);
                return $required > 0 ? min(100, ($rendered / $required) * 100) : 0;
            }), 1)
            : 0;

        // --- Evaluation status per course ---
        $evaluatedCount = $students->filter(function ($s) {
            return $s->ojtEvaluations->contains(function ($e) {
                    return $e->status !== 'pending'; // replace with your actual status value
                });
            })->count();

        $notEvaluatedCount = max(0, $totalStudents - $evaluatedCount);

        // --- Document requirements passed per course ---
        // Requirement IDs that apply to this course
        $requiredDocumentIds = $course->documentRequirements->pluck('id');
        $totalRequiredDocs = $requiredDocumentIds->count();

        $passedRequirementsCount = $students->filter(function ($s) use ($requiredDocumentIds, $totalRequiredDocs) {
            if ($totalRequiredDocs === 0) {
                return false; // nothing to pass against
            }

            $approvedRequirementIds = $s->documents
                ->filter(fn($doc) => ($doc->review_status?->value ?? $doc->review_status) === 'approved')
                ->pluck('document_requirement_id')
                ->unique();

            // Student passes only if every required document has an approved submission
            return $requiredDocumentIds->diff($approvedRequirementIds)->isEmpty();
        })->count();

        $notPassedRequirementsCount = max(0, $totalStudents - $passedRequirementsCount);

        return [
            'id'   => $course->id,
            'code' => $course->code,
            'name' => $course->name,

            'total_sections'  => $course->sections->count(),
            'total_students'  => $totalStudents,

            'assigned_students'   => $assignedCount,
            'unassigned_students' => $unassignedCount,
            'assignment_rate'     => $totalStudents > 0
                ? round(($assignedCount / $totalStudents) * 100, 1)
                : 0,

            'total_required_hours' => round($totalRequiredHours, 1),
            'total_rendered_hours' => round($totalRenderedHours, 1),
            'internship_percentage' => $internshipPercentage,
            'avg_hours_per_student' => $avgHoursPerStudent,
            'avg_completion_percentage' => $avgCompletionPct,

            'completed_students'    => $completedCount,
            'in_progress_students'  => $inProgressCount,
            'not_started_students'  => $notStartedCount,

            'evaluated_students'     => $evaluatedCount,
            'not_evaluated_students' => $notEvaluatedCount,

            'total_required_documents'    => $totalRequiredDocs,
            'passed_requirements_students' => $passedRequirementsCount,
            'not_passed_requirements_students' => $notPassedRequirementsCount,
        ];
    });

    $overview = [
        'total_courses'         => $courseAnalytics->count(),
        'total_sections'        => $courseAnalytics->sum('total_sections'),
        'total_students'        => $courseAnalytics->sum('total_students'),
        'assigned_students'     => $courseAnalytics->sum('assigned_students'),
        'unassigned_students'   => $courseAnalytics->sum('unassigned_students'),
        'total_required_hours'  => round($courseAnalytics->sum('total_required_hours'), 1),
        'total_rendered_hours'  => round($courseAnalytics->sum('total_rendered_hours'), 1),
        'completed_students'    => $courseAnalytics->sum('completed_students'),
        'in_progress_students'  => $courseAnalytics->sum('in_progress_students'),
        'not_started_students'  => $courseAnalytics->sum('not_started_students'),
        'evaluated_students'    => $courseAnalytics->sum('evaluated_students'),
        'not_evaluated_students' => $courseAnalytics->sum('not_evaluated_students'),
        'passed_requirements_students' => $courseAnalytics->sum('passed_requirements_students'),
        'not_passed_requirements_students' => $courseAnalytics->sum('not_passed_requirements_students'),
        'overall_internship_percentage' => $courseAnalytics->sum('total_required_hours') > 0
            ? round(min(100, ($courseAnalytics->sum('total_rendered_hours') / $courseAnalytics->sum('total_required_hours')) * 100), 1)
            : 0,
    ];

    return response()->json([
        'data' => [
            'overview' => $overview,
            'courses'  => $courseAnalytics->values(),

            'charts' => [
                'enrollment_by_course' => $courseAnalytics->map(fn($c) => [
                    'name'  => $c['code'],
                    'value' => $c['total_students'],
                ])->values(),

                'placement_by_course' => $courseAnalytics->map(fn($c) => [
                    'name'       => $c['code'],
                    'assigned'   => $c['assigned_students'],
                    'unassigned' => $c['unassigned_students'],
                ])->values(),

                'internship_progress_by_course' => $courseAnalytics->map(fn($c) => [
                    'name'       => $c['code'],
                    'percentage' => $c['internship_percentage'],
                ])->values(),

                'status_by_course' => $courseAnalytics->map(fn($c) => [
                    'name'         => $c['code'],
                    'completed'    => $c['completed_students'],
                    'in_progress'  => $c['in_progress_students'],
                    'not_started'  => $c['not_started_students'],
                ])->values(),

                // Evaluation coverage per course
                'evaluation_by_course' => $courseAnalytics->map(fn($c) => [
                    'name'          => $c['code'],
                    'evaluated'     => $c['evaluated_students'],
                    'not_evaluated' => $c['not_evaluated_students'],
                ])->values(),

                // Requirements passed per course
                'requirements_by_course' => $courseAnalytics->map(fn($c) => [
                    'name'   => $c['code'],
                    'passed' => $c['passed_requirements_students'],
                    'not_passed' => $c['not_passed_requirements_students'],
                ])->values(),

                'placement_status' => [
                    ['name' => 'Assigned', 'value' => $overview['assigned_students'], 'color' => '#10B981'],
                    ['name' => 'Unassigned', 'value' => $overview['unassigned_students'], 'color' => '#EF4444'],
                ],

                'completion_status' => [
                    ['name' => 'Completed', 'value' => $overview['completed_students'], 'color' => '#10B981'],
                    ['name' => 'In Progress', 'value' => $overview['in_progress_students'], 'color' => '#F59E0B'],
                    ['name' => 'Not Started', 'value' => $overview['not_started_students'], 'color' => '#6B7280'],
                ],

                'evaluation_status' => [
                    ['name' => 'Evaluated', 'value' => $overview['evaluated_students'], 'color' => '#10B981'],
                    ['name' => 'Not Evaluated', 'value' => $overview['not_evaluated_students'], 'color' => '#6B7280'],
                ],

                'requirements_status' => [
                    ['name' => 'Passed', 'value' => $overview['passed_requirements_students'], 'color' => '#10B981'],
                    ['name' => 'Not Passed', 'value' => $overview['not_passed_requirements_students'], 'color' => '#EF4444'],
                ],
            ],
        ],
    ]);
}

    /**
     * Return aggregated dashboard metrics for Coordinator.
     */
    public function coordinator(Request $request): JsonResponse
    {
        $user = $request->user();
        $section = $user->activeCoordinatorSection();

        $sectionId = $section?->id;

        $studentsQuery = Student::with(['companies', 'timeLogs', 'ojtSchedule']);
        if ($sectionId) {
            $studentsQuery->where('section_id', $sectionId);
        }

        $students = $studentsQuery->get();
        $totalStudents = $students->count();

        $assignedCount = $students->filter(fn($s) => $s->companies->isNotEmpty())->count();
        $unassignedCount = max(0, $totalStudents - $assignedCount);

        // Student progress breakdown (hours rendered vs required)
        $studentProgress = $students->take(10)->map(function ($s) {
            $rendered = round(($s->timeLogs->sum('duration_minutes') ?? 0) / 60, 1);
            $required = $s->ojtSchedule?->required_hours ?? 500;
            return [
                'name'          => "{$s->last_name}, {$s->first_name}",
                'rendered'      => $rendered,
                'required'      => $required,
                'pct'           => min(100, round(($rendered / max(1, $required)) * 100, 1)),
            ];
        });

        // Company requests for coordinator
        $pendingCompanyRequests = CompanyRequest::where('status', 'pending')->count();

        return response()->json([
            'data' => [
                'section' => $section ? [
                    'id'   => $section->id,
                    'name' => $section->name,
                    'code' => $section->code,
                ] : null,
                'overview' => [
                    'total_students'           => $totalStudents,
                    'assigned_students'        => $assignedCount,
                    'unassigned_students'      => $unassignedCount,
                    'pending_company_requests' => $pendingCompanyRequests,
                ],
                'charts' => [
                    'placement_status' => [
                        ['name' => 'Assigned', 'value' => $assignedCount, 'color' => '#6366F1'],
                        ['name' => 'Unassigned', 'value' => $unassignedCount, 'color' => '#F59E0B'],
                    ],
                    'student_progress' => $studentProgress,
                ]
            ]
        ]);
    }

    /**
     * Return aggregated dashboard metrics for Supervisor.
     */
    public function supervisor(Request $request): JsonResponse
    {
        $supervisor = Supervisor::with('company')
            ->where('user_id', $request->user()->id)
            ->where('is_active', true)
            ->first();

        if (!$supervisor || !$supervisor->company) {
            return response()->json([
                'data' => [
                    'company'  => null,
                    'overview' => [
                        'total_interns'      => 0,
                        'total_schedules'    => 0,
                        'total_hours_logged' => 0,
                    ],
                    'charts' => [
                        'intern_hours'       => [],
                        'attendance_trend'   => [],
                    ]
                ]
            ]);
        }

        $company = $supervisor->company;
        $interns = $company->students()->with(['timeLogs', 'ojtSchedule'])->get();
        $schedulesCount = $company->schedules()->count();

        $totalCompanyHours = 0;
        $internHoursChart = $interns->map(function ($intern) use (&$totalCompanyHours) {
            $hrs = round(($intern->timeLogs->sum('duration_minutes') ?? 0) / 60, 1);
            $totalCompanyHours += $hrs;
            $req = $intern->ojtSchedule?->required_hours ?? 500;
            return [
                'name'     => "{$intern->last_name}, {$intern->first_name}",
                'rendered' => $hrs,
                'required' => $req,
                'pct'      => min(100, round(($hrs / max(1, $req)) * 100, 1)),
            ];
        });

        // Attendance activity trend for company interns over last 14 days
        $internIds = $interns->pluck('id');
        $dailyTrend = TimeLog::select(DB::raw('DATE(time_in) as date'), DB::raw('count(*) as count'))
            ->whereIn('student_id', $internIds)
            ->where('time_in', '>=', now()->subDays(14))
            ->groupBy(DB::raw('DATE(time_in)'))
            ->orderBy('date', 'asc')
            ->get();

        return response()->json([
            'data' => [
                'company' => [
                    'id'      => $company->id,
                    'name'    => $company->name,
                    'address' => $company->address,
                ],
                'overview' => [
                    'total_interns'      => $interns->count(),
                    'total_schedules'    => $schedulesCount,
                    'total_hours_logged' => round($totalCompanyHours, 1),
                ],
                'charts' => [
                    'intern_hours'     => $internHoursChart,
                    'attendance_trend' => $dailyTrend,
                ]
            ]
        ]);
    }
}
