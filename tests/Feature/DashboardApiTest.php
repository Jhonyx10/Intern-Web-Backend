<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    private Role $deanRole;
    private Role $superAdminRole;
    private Role $coordinatorRole;

    protected function setUp(): void
    {
        parent::setUp();

        $this->superAdminRole = Role::create(['name' => 'super_admin', 'label' => 'Super Admin']);
        $this->deanRole = Role::create(['name' => 'dean', 'label' => 'Dean']);
        $this->coordinatorRole = Role::create(['name' => 'coordinator', 'label' => 'Coordinator']);
    }

    public function test_dean_dashboard_returns_success_when_dean_has_assigned_course(): void
    {
        $deanUser = User::factory()->create([
            'role_id' => $this->deanRole->id,
            'is_active' => true,
        ]);

        $course = Course::create([
            'code' => 'BSIT',
            'name' => 'Bachelor of Science in Information Technology',
            'required_hours' => 500,
            'dean_user_id' => $deanUser->id,
            'is_active' => true,
        ]);

        $sy = \App\Models\SchoolYear::create([
            'course_id' => $course->id,
            'name' => '2025-2026',
            'start_date' => '2025-08-01',
            'end_date' => '2026-05-31',
            'is_active' => true,
        ]);

        $section = Section::create([
            'school_year_id' => $sy->id,
            'course_id' => $course->id,
            'name' => 'BSIT 4-A',
            'code' => 'BSIT4A',
            'is_active' => true,
        ]);

        Sanctum::actingAs($deanUser);

        $response = $this->getJson('/api/dashboard/dean');

        $response->assertOk()
            ->assertJsonPath('data.course.id', $course->id)
            ->assertJsonPath('data.course.code', 'BSIT')
            ->assertJsonPath('data.overview.total_sections', 1);
    }

    public function test_dean_dashboard_returns_200_with_null_course_when_unassigned(): void
    {
        $deanUser = User::factory()->create([
            'role_id' => $this->deanRole->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($deanUser);

        $response = $this->getJson('/api/dashboard/dean');

        $response->assertOk()
            ->assertJsonPath('data.course', null)
            ->assertJsonPath('data.overview.total_sections', 0)
            ->assertJsonPath('data.overview.total_students', 0);
    }

    public function test_superadmin_dashboard_returns_success(): void
    {
        $admin = User::factory()->create([
            'role_id' => $this->superAdminRole->id,
            'is_active' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/api/dashboard/superadmin');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'overview' => [
                        'total_users',
                        'total_companies',
                        'approved_companies',
                        'pending_companies',
                        'total_students',
                        'assigned_students',
                        'unassigned_students',
                        'pending_company_requests',
                    ],
                    'charts' => [
                        'roles_distribution',
                        'company_status',
                        'student_placement',
                        'company_requests',
                        'daily_logs_trend',
                    ],
                ],
            ]);
    }
}
