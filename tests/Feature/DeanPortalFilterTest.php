<?php

use App\Models\Course;
use App\Models\Role;
use App\Models\SchoolYear;
use App\Models\Section;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

function deanPortalRole(string $name, string $label): Role
{
    return Role::query()->firstOrCreate(
        ['name' => $name],
        ['label' => $label],
    );
}

/**
 * @return array{0: User, 1: Course}
 */
function deanWithCourse(string $email, string $code, string $courseName): array
{
    $role = deanPortalRole('dean', 'Dean');

    $dean = User::query()->create([
        'name' => "{$courseName} Dean",
        'email' => $email,
        'password' => 'password',
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    $course = Course::query()->create([
        'code' => $code,
        'name' => $courseName,
        'required_hours' => 486,
        'dean_user_id' => $dean->id,
        'is_active' => true,
    ]);

    return [$dean->fresh(['role', 'courseAsDean']), $course];
}

beforeEach(function (): void {
    deanPortalRole('super_admin', 'Super Admin');
    deanPortalRole('dean', 'Dean');
    deanPortalRole('coordinator', 'Coordinator');
});

it('lets a dean see only school years and sections for their course', function (): void {
    [$deanA, $courseA] = deanWithCourse('deana@example.com', 'BSCS', 'Computer Science');
    [$deanB, $courseB] = deanWithCourse('deanb@example.com', 'BSIT', 'Information Technology');

    $yearA = SchoolYear::query()->create([
        'course_id' => $courseA->id,
        'name' => '2025-2026',
        'is_active' => true,
    ]);
    $yearB = SchoolYear::query()->create([
        'course_id' => $courseB->id,
        'name' => '2025-2026',
        'is_active' => true,
    ]);

    Section::query()->create([
        'course_id' => $courseA->id,
        'school_year_id' => $yearA->id,
        'name' => 'BSCS 4A',
        'code' => 'CS4A',
        'is_active' => true,
    ]);
    Section::query()->create([
        'course_id' => $courseB->id,
        'school_year_id' => $yearB->id,
        'name' => 'BSIT 4A',
        'code' => 'IT4A',
        'is_active' => true,
    ]);

    Sanctum::actingAs($deanA);

    $response = $this->getJson('/api/school-years')->assertSuccessful();

    expect($response->json())->toHaveCount(1)
        ->and($response->json('0.id'))->toBe($yearA->id)
        ->and($response->json('0.sections'))->toHaveCount(1)
        ->and($response->json('0.sections.0.name'))->toBe('BSCS 4A');

    $this->getJson("/api/school-years/{$yearA->id}/sections")
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonPath('0.name', 'BSCS 4A');

    $this->getJson("/api/school-years/{$yearB->id}/sections")->assertNotFound();
});

it('returns no school years for a dean without an assigned course', function (): void {
    $role = deanPortalRole('dean', 'Dean');

    $dean = User::query()->create([
        'name' => 'Unassigned Dean',
        'email' => 'unassigned-dean@example.com',
        'password' => 'password',
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    SchoolYear::query()->create([
        'course_id' => null,
        'name' => 'Global Year',
        'is_active' => true,
    ]);

    Sanctum::actingAs($dean->fresh('role'));

    $this->getJson('/api/school-years')
        ->assertSuccessful()
        ->assertExactJson([]);
});

it('lets super admin see school years from every course', function (): void {
    [, $courseA] = deanWithCourse('deana@example.com', 'BSCS', 'Computer Science');
    [, $courseB] = deanWithCourse('deanb@example.com', 'BSIT', 'Information Technology');

    SchoolYear::query()->create([
        'course_id' => $courseA->id,
        'name' => '2025-2026',
        'is_active' => true,
    ]);
    SchoolYear::query()->create([
        'course_id' => $courseB->id,
        'name' => '2025-2026',
        'is_active' => true,
    ]);

    $admin = User::query()->create([
        'name' => 'Super Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'role_id' => deanPortalRole('super_admin', 'Super Admin')->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($admin->fresh('role'));

    $this->getJson('/api/school-years')
        ->assertSuccessful()
        ->assertJsonCount(2);
});

it('forces a new section onto the dean course even if another course_id is sent', function (): void {
    [$deanA, $courseA] = deanWithCourse('deana@example.com', 'BSCS', 'Computer Science');
    [, $courseB] = deanWithCourse('deanb@example.com', 'BSIT', 'Information Technology');

    $yearA = SchoolYear::query()->create([
        'course_id' => $courseA->id,
        'name' => '2025-2026',
        'is_active' => true,
    ]);

    Sanctum::actingAs($deanA);

    $this->postJson("/api/school-years/{$yearA->id}/sections", [
        'name' => 'BSCS 4B',
        'code' => 'CS4B',
        'course_id' => $courseB->id,
    ])
        ->assertCreated()
        ->assertJsonPath('course_id', $courseA->id);

    $this->assertDatabaseHas('sections', [
        'name' => 'BSCS 4B',
        'course_id' => $courseA->id,
        'school_year_id' => $yearA->id,
    ]);
});

it('lets a dean see only coordinators they created', function (): void {
    [$deanA, $courseA] = deanWithCourse('deana@example.com', 'BSCS', 'Computer Science');
    [$deanB, $courseB] = deanWithCourse('deanb@example.com', 'BSIT', 'Information Technology');
    $coordinatorRole = deanPortalRole('coordinator', 'Coordinator');

    User::query()->create([
        'name' => 'Coord A',
        'email' => 'coord-a@example.com',
        'password' => 'password',
        'role_id' => $coordinatorRole->id,
        'course_id' => $courseA->id,
        'created_by' => $deanA->id,
        'is_active' => true,
    ]);
    User::query()->create([
        'name' => 'Coord B',
        'email' => 'coord-b@example.com',
        'password' => 'password',
        'role_id' => $coordinatorRole->id,
        'course_id' => $courseB->id,
        'created_by' => $deanB->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($deanA);

    $this->getJson('/api/coordinators')
        ->assertSuccessful()
        ->assertJsonCount(1)
        ->assertJsonPath('0.email', 'coord-a@example.com');
});

it('records the dean as creator when they add a coordinator', function (): void {
    [$deanA, $courseA] = deanWithCourse('deana@example.com', 'BSCS', 'Computer Science');

    Sanctum::actingAs($deanA);

    $this->postJson('/api/coordinators', [
        'name' => 'New Coordinator',
        'email' => 'new-coord@example.com',
        'password' => 'password123',
    ])->assertSuccessful();

    $this->assertDatabaseHas('users', [
        'email' => 'new-coord@example.com',
        'created_by' => $deanA->id,
        'course_id' => $courseA->id,
    ]);
});

it('lets super admin see every coordinator', function (): void {
    [$deanA, $courseA] = deanWithCourse('deana@example.com', 'BSCS', 'Computer Science');
    [$deanB, $courseB] = deanWithCourse('deanb@example.com', 'BSIT', 'Information Technology');
    $coordinatorRole = deanPortalRole('coordinator', 'Coordinator');

    User::query()->create([
        'name' => 'Coord A',
        'email' => 'coord-a@example.com',
        'password' => 'password',
        'role_id' => $coordinatorRole->id,
        'course_id' => $courseA->id,
        'created_by' => $deanA->id,
        'is_active' => true,
    ]);
    User::query()->create([
        'name' => 'Coord B',
        'email' => 'coord-b@example.com',
        'password' => 'password',
        'role_id' => $coordinatorRole->id,
        'course_id' => $courseB->id,
        'created_by' => $deanB->id,
        'is_active' => true,
    ]);

    $admin = User::query()->create([
        'name' => 'Super Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
        'role_id' => deanPortalRole('super_admin', 'Super Admin')->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($admin->fresh('role'));

    $this->getJson('/api/coordinators')
        ->assertSuccessful()
        ->assertJsonCount(2);
});
