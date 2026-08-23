<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRequest;
use App\Models\Section;
use App\Models\Student;
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
        return $this->dean($request);
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
