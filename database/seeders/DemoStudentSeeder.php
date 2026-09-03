<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Course;
use App\Models\OjtSchedule;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\Student;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoStudentSeeder extends Seeder
{
    /**
     * Seed a demo student with all required relationships.
     */
    public function run(): void
    {
        // 1. Ensure the student role exists
        $role = Role::query()->where('name', 'intern')->first();
        if (!$role) {
            $this->command?->error('Intern role not found. Run RoleSeeder first.');
            return;
        }

        // 2. Get or create a School Year
        $schoolYear = SchoolYear::query()->first()
            ?? SchoolYear::create([
                'year_start' => 2025,
                'year_end'   => 2026,
                'label'      => 'A.Y. 2025-2026',
                'is_active'  => true,
            ]);

        // 3. Get or create a Course
        $course = Course::query()->where('code', 'BSIT')->first()
            ?? Course::create([
                'name'           => 'Bachelor of Science in Information Technology',
                'code'           => 'BSIT',
                'required_hours' => 500,
                'is_active'      => true,
            ]);

        // 4. Get or create a Section
        $section = Section::query()->where('name', 'BSIT 4-A')->first()
            ?? Section::create([
                'course_id'      => $course->id,
                'school_year_id' => $schoolYear->id,
                'name'           => 'BSIT 4-A',
                'code'           => 'BSIT4A',
                'is_active'      => true,
            ]);

        // 5. Create the User account for the student
        $user = User::query()->updateOrCreate(
            ['email' => '2023-1-0021'],
            [
                'name'              => 'Juan Dela Cruz',
                'password'          => Hash::make('2023-1-0021'),
                'role_id'           => $role->id,
                'is_active'         => true,
                'email_verified_at' => now(),
            ]
        );

        // 6. Create the Student record
        $student = Student::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'student_number' => '2022-01234',
                'first_name'     => 'Juan',
                'middle_name'    => 'Santos',
                'last_name'      => 'Dela Cruz',
                'section_id'     => $section->id,
                'is_active'      => true,
            ]
        );

        // 7. Create OJT Schedule for the student
        OjtSchedule::query()->updateOrCreate(
            ['student_id' => $student->id],
            [
                'hours_per_day' => 8,
                'days_per_week' => 5,
                'start_date'    => now()->startOfWeek()->toDateString(),
            ]
        );

        // 8. Attach to an approved company if one exists
        $company = Company::query()->where('is_approved', true)->first()
            ?? Company::query()->first();

        if ($company) {
            // Use the pivot table (company_student)
            if (!$student->companies()->where('companies.id', $company->id)->exists()) {
                $supervisor = Supervisor::query()
                    ->where('company_id', $company->id)
                    ->first();

                $student->companies()->attach($company->id, [
                    'supervisor_id' => $supervisor?->id,
                    'course_id'     => $course->id,
                ]);
            }
        }

        $this->command?->info('Demo student seeded: student@gmail.com / student123');
        $this->command?->info("Student: {$student->first_name} {$student->last_name} ({$student->student_number})");
    }
}
