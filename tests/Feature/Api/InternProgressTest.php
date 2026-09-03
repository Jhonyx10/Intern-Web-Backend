<?php

use App\Models\Course;
use App\Models\Role;
use App\Models\Section;
use App\Models\Student;
use App\Models\User;
use App\Models\TimeLog;
use App\Models\OjtSchedule;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->role = Role::create([
        'name' => 'intern',
        'label' => 'Intern',
    ]);

    $this->user = User::factory()->create([
        'role_id' => $this->role->id,
        'is_active' => true,
    ]);

    $this->course = Course::create([
        'code' => 'CS101',
        'name' => 'Computer Science',
        'required_hours' => 200,
        'is_active' => true,
    ]);

    $this->section = Section::create([
        'course_id' => $this->course->id,
        'name' => 'BSCS-3A',
        'is_active' => true,
    ]);

    $this->student = Student::create([
        'user_id' => $this->user->id,
        'student_number' => '123456',
        'first_name' => 'John',
        'last_name' => 'Doe',
        'section_id' => $this->section->id,
        'is_active' => true,
    ]);
});

test('it returns intern progress successfully', function () {
    TimeLog::create([
        'student_id' => $this->student->id,
        'duration_minutes' => 120, // 2 hours
        'session_period' => 'AM',
        'time_in' => Carbon::parse('2026-09-01 08:00:00'),
    ]);
    
    TimeLog::create([
        'student_id' => $this->student->id,
        'duration_minutes' => 180, // 3 hours
        'session_period' => 'PM',
        'time_in' => Carbon::parse('2026-09-01 13:00:00'),
    ]);

    OjtSchedule::create([
        'student_id' => $this->student->id,
        'hours_per_day' => 8,
        'days_per_week' => 5,
        'start_date' => Carbon::parse('2026-09-01'),
    ]);

    $response = $this->actingAs($this->user)->getJson('/api/intern/progress');

    $response->assertStatus(200);

    $response->assertJsonStructure([
        'student' => ['id', 'full_name', 'student_number'],
        'course' => ['id', 'code', 'name'],
        'progress' => [
            'required_hours',
            'rendered_hours',
            'remaining_hours',
            'percent_complete',
            'time_log_count',
            'estimated_end_date',
            'estimated_end_basis',
            'estimated_end_is_approximate',
            'schedule' => [
                'hours_per_day',
                'days_per_week',
            ],
        ],
    ]);

    $response->assertJsonPath('progress.required_hours', 200);
    $response->assertJsonPath('progress.rendered_hours', 5.0);
    $response->assertJsonPath('progress.remaining_hours', 195.0);
    $response->assertJsonPath('progress.percent_complete', 2.5);
    $response->assertJsonPath('progress.time_log_count', 2);
    $response->assertJsonPath('progress.schedule.hours_per_day', 8.0);
});

test('it handles student without course or schedule gracefully', function () {
    $this->student->update(['section_id' => null]);

    $response = $this->actingAs($this->user)->getJson('/api/intern/progress');

    $response->assertStatus(200);
    $response->assertJsonPath('progress.required_hours', 0);
    $response->assertJsonPath('progress.rendered_hours', 0);
    $response->assertJsonPath('progress.percent_complete', 0);
    $response->assertJsonPath('progress.schedule', null);
});
