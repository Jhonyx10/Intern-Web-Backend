<?php

use App\Models\Company;
use App\Models\CompanyRequest;
use App\Models\Role;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function (): void {
    $role = Role::query()->create([
        'name' => 'super_admin',
        'label' => 'Super Admin',
    ]);

    $this->user = User::query()->create([
        'name' => 'Super Admin',
        'email' => 'superadmin@gmail.com',
        'password' => 'sadmin123',
        'role_id' => $role->id,
        'is_active' => true,
    ]);

    $internRole = Role::query()->create([
        'name' => 'intern',
        'label' => 'Intern',
    ]);

    $this->intern = User::query()->create([
        'name' => 'Demo Intern',
        'email' => 'intern@gmail.com',
        'password' => 'intern123',
        'role_id' => $internRole->id,
        'is_active' => true,
    ]);
});

it('lists companies for authenticated staff', function (): void {
    Company::query()->create([
        'name' => 'Mapbox Demo Co',
        'address' => 'Cagayan de Oro City, Misamis Oriental',
        'latitude' => 8.4822,
        'longitude' => 124.6472,
        'geofence_radius_meters' => 150,
        'geofence_enabled' => true,
        'is_active' => true,
        'is_approved' => true,
    ]);

    Sanctum::actingAs($this->user);

    $this->getJson('/api/companies')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Mapbox Demo Co')
        ->assertJsonPath('data.0.latitude', 8.4822);
});

it('requires authentication to list companies', function (): void {
    $this->getJson('/api/companies')->assertUnauthorized();
});

it('lists an empty companies table for a dean without querying course_id', function (): void {
    $deanRole = Role::query()->create([
        'name' => 'dean',
        'label' => 'Dean',
    ]);

    $dean = User::query()->create([
        'name' => 'Dean User',
        'email' => 'dean@example.com',
        'password' => 'password',
        'role_id' => $deanRole->id,
        'is_active' => true,
    ]);

    $course = \App\Models\Course::query()->create([
        'code' => 'BSCS',
        'name' => 'Computer Science',
        'required_hours' => 486,
        'dean_user_id' => $dean->id,
        'is_active' => true,
    ]);

    Sanctum::actingAs($dean->fresh(['role', 'courseAsDean']));

    $this->getJson('/api/companies')
        ->assertSuccessful()
        ->assertJsonPath('data', []);
});

it('creates a company for authenticated staff', function (): void {
    Sanctum::actingAs($this->user);

    $this->postJson('/api/companies', [
        'name' => 'New Mapbox Partner',
        'address' => 'Poblacion, El Salvador City',
        'latitude' => 8.5630,
        'longitude' => 124.5247,
        'geofence_radius_meters' => 150,
        'geofence_enabled' => true,
        'contact_person' => 'Ada Host',
        'contact_email' => 'ada@example.com',
        'contact_phone' => '09171234567',
    ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'New Mapbox Partner')
        ->assertJsonPath('data.latitude', 8.563);

    $this->assertDatabaseHas('companies', [
        'name' => 'New Mapbox Partner',
        'contact_email' => 'ada@example.com',
    ]);
});

it('requires authentication to create companies', function (): void {
    $this->postJson('/api/companies', [
        'name' => 'Unauthorized Co',
        'latitude' => 8.5,
        'longitude' => 124.5,
    ])->assertUnauthorized();
});

it('lists company requests for authenticated staff', function (): void {
    CompanyRequest::query()->create([
        'user_id' => $this->intern->id,
        'name' => 'Lagoon Tech Partners',
        'address' => 'El Salvador City',
        'latitude' => 8.5630,
        'longitude' => 124.5247,
        'status' => CompanyRequest::STATUS_PENDING,
    ]);

    Sanctum::actingAs($this->user);

    $this->getJson('/api/company-requests')
        ->assertSuccessful()
        ->assertJsonPath('data.0.name', 'Lagoon Tech Partners')
        ->assertJsonPath('data.0.user.email', 'intern@gmail.com');
});

/**
 * Coordinator accept — company is created with is_approved = false.
 */
it('coordinator accepts a company request and creates a pending company', function (): void {
    $companyRequest = CompanyRequest::query()->create([
        'user_id' => $this->intern->id,
        'name' => 'Molugan Industrial Hub',
        'address' => 'Molugan, El Salvador City',
        'latitude' => 8.5452,
        'longitude' => 124.5398,
        'status' => CompanyRequest::STATUS_PENDING,
    ]);

    Sanctum::actingAs($this->user);

    $this->postJson("/api/company-requests/{$companyRequest->id}/accept", [
        'geofence_radius_meters' => 180,
        'geofence_polygon' => [
            'type' => 'Polygon',
            'coordinates' => [[
                [124.5390, 8.5445],
                [124.5405, 8.5445],
                [124.5405, 8.5460],
                [124.5390, 8.5460],
                [124.5390, 8.5445],
            ]],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.company.name', 'Molugan Industrial Hub')
        ->assertJsonPath('data.company.geofence_polygon.type', 'Polygon')
        ->assertJsonPath('data.company.is_approved', false)
        ->assertJsonPath('data.company_request.status', CompanyRequest::STATUS_ACCEPTED);

    $this->assertDatabaseHas('companies', [
        'company_request_id' => $companyRequest->id,
        'name' => 'Molugan Industrial Hub',
        'is_approved' => false, // pending superadmin approval
    ]);

    $this->assertDatabaseHas('company_user', [
        'user_id' => $this->intern->id,
    ]);
});

it('requires a geofence polygon to accept a company request', function (): void {
    $companyRequest = CompanyRequest::query()->create([
        'user_id' => $this->intern->id,
        'name' => 'Missing Fence Co',
        'address' => 'El Salvador City',
        'latitude' => 8.5630,
        'longitude' => 124.5247,
        'status' => CompanyRequest::STATUS_PENDING,
    ]);

    Sanctum::actingAs($this->user);

    $this->postJson("/api/company-requests/{$companyRequest->id}/accept", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['geofence_polygon']);
});

/**
 * Superadmin pending list.
 */
it('superadmin can list pending companies', function (): void {
    Company::query()->create([
        'name' => 'Pending Corp',
        'latitude' => 8.5452,
        'longitude' => 124.5398,
        'is_active' => true,
        'is_approved' => false,
    ]);
    // Approved company should NOT appear
    Company::query()->create([
        'name' => 'Approved Corp',
        'latitude' => 8.5,
        'longitude' => 124.5,
        'is_active' => true,
        'is_approved' => true,
    ]);

    Sanctum::actingAs($this->user);

    $response = $this->getJson('/api/companies/pending')
        ->assertSuccessful();

    $data = $response->json('data');
    expect($data)->toHaveCount(1);
    expect($data[0]['name'])->toBe('Pending Corp');
});

/**
 * Superadmin approve a pending company.
 */
it('superadmin can approve a pending company', function (): void {
    $companyRequest = CompanyRequest::query()->create([
        'user_id' => $this->intern->id,
        'name' => 'Pending Corp',
        'latitude' => 8.5452,
        'longitude' => 124.5398,
        'status' => CompanyRequest::STATUS_ACCEPTED,
    ]);

    $company = Company::query()->create([
        'company_request_id' => $companyRequest->id,
        'name' => 'Pending Corp',
        'latitude' => 8.5452,
        'longitude' => 124.5398,
        'is_active' => true,
        'is_approved' => false,
    ]);

    Sanctum::actingAs($this->user);

    $this->postJson("/api/companies/{$company->id}/approve")
        ->assertSuccessful()
        ->assertJsonPath('data.is_approved', true);

    $this->assertDatabaseHas('companies', [
        'id' => $company->id,
        'is_approved' => true,
    ]);

    $this->assertDatabaseHas('company_requests', [
        'id' => $companyRequest->id,
        'status' => CompanyRequest::STATUS_APPROVED,
    ]);
});

/**
 * Superadmin reject a pending company.
 */
it('superadmin can reject a pending company', function (): void {
    $companyRequest = CompanyRequest::query()->create([
        'user_id' => $this->intern->id,
        'name' => 'Bad Corp',
        'latitude' => 8.5452,
        'longitude' => 124.5398,
        'status' => CompanyRequest::STATUS_ACCEPTED,
    ]);

    $company = Company::query()->create([
        'company_request_id' => $companyRequest->id,
        'name' => 'Bad Corp',
        'latitude' => 8.5452,
        'longitude' => 124.5398,
        'is_active' => true,
        'is_approved' => false,
    ]);

    Sanctum::actingAs($this->user);

    $this->postJson("/api/companies/{$company->id}/reject")
        ->assertSuccessful()
        ->assertJsonPath('data.is_active', false);

    $this->assertDatabaseHas('companies', [
        'id' => $company->id,
        'is_active' => false,
    ]);

    $this->assertDatabaseHas('company_requests', [
        'id' => $companyRequest->id,
        'status' => CompanyRequest::STATUS_REJECTED,
    ]);
});

/**
 * Coordinator accept with buildings — buildings are created in the database.
 */
it('coordinator accept creates buildings when provided', function (): void {
    $companyRequest = CompanyRequest::query()->create([
        'user_id' => $this->intern->id,
        'name' => 'Tech Campus',
        'address' => 'El Salvador City',
        'latitude' => 8.5452,
        'longitude' => 124.5398,
        'status' => CompanyRequest::STATUS_PENDING,
    ]);

    Sanctum::actingAs($this->user);

    $polygon = [
        'type' => 'Polygon',
        'coordinates' => [[[124.5390, 8.5445], [124.5405, 8.5445], [124.5405, 8.5460], [124.5390, 8.5460], [124.5390, 8.5445]]],
    ];

    $this->postJson("/api/company-requests/{$companyRequest->id}/accept", [
        'geofence_polygon' => $polygon,
        'geofence_enabled' => true,
        'buildings' => [
            [
                'name' => 'Main Hall',
                'code' => 'MH-1',
                'latitude' => 8.5452,
                'longitude' => 124.5398,
                'geofence_radius_meters' => 30,
                'geofence_enabled' => true,
                'geofence_polygon' => null,
            ],
            [
                'name' => 'Annex',
                'code' => 'AX-1',
                'latitude' => 8.5455,
                'longitude' => 124.5401,
                'geofence_radius_meters' => 25,
                'geofence_enabled' => true,
                'geofence_polygon' => null,
            ],
        ],
    ])
        ->assertSuccessful()
        ->assertJsonPath('data.company.name', 'Tech Campus')
        ->assertJsonCount(2, 'data.company.buildings');

    $company = \App\Models\Company::query()
        ->where('company_request_id', $companyRequest->id)
        ->firstOrFail();

    $this->assertDatabaseHas('buildings', [
        'company_id' => $company->id,
        'name' => 'Main Hall',
        'code' => 'MH-1',
    ]);

    $this->assertDatabaseHas('buildings', [
        'company_id' => $company->id,
        'name' => 'Annex',
        'code' => 'AX-1',
    ]);
});
