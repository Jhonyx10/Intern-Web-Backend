<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\CompanyRequest;
use App\Models\Role;
use App\Models\Supervisor;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CompanyController extends Controller
{
    /**
     * List approved & active companies (used by the Mapbox companies map).
     */
    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->where('is_active', true)
            ->where('is_approved', true)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'address',
                'latitude',
                'longitude',
                'geofence_radius_meters',
                'geofence_enabled',
                'geofence_polygon',
                'contact_person',
                'contact_email',
                'contact_phone',
                'is_approved',
            ]);

        return response()->json([
            'data' => $companies,
        ]);
    }

    /**
     * Show a single company.
     */
    public function show(Company $company): JsonResponse
    {
        $company->load(['students', 'supervisors.user', 'buildings']);
        return response()->json([
            'data' => $company->only([
                'id',
                'name',
                'address',
                'latitude',
                'longitude',
                'geofence_radius_meters',
                'geofence_enabled',
                'geofence_polygon',
                'contact_person',
                'contact_email',
                'contact_phone',
                'is_active',
                'is_approved',
                'students',
                'supervisors',
                'buildings',
            ]),
        ]);
    }

    /**
     * Superadmin directly creates a company (auto-approved).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'company_request_id' => ['nullable', 'integer', 'exists:company_requests,id'],
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'geofence_radius_meters' => ['nullable', 'integer', 'min:10', 'max:5000'],
            'geofence_enabled' => ['sometimes', 'boolean'],
            'geofence_polygon' => ['nullable', 'array'],
            'geofence_polygon.type' => ['required_with:geofence_polygon', 'in:Polygon'],
            'geofence_polygon.coordinates' => ['required_with:geofence_polygon', 'array', 'min:1'],
            'contact_person' => ['nullable', 'string', 'max:255'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:50'],
        ]);

        $company = Company::query()->create([
            ...$validated,
            'geofence_enabled' => $validated['geofence_enabled'] ?? true,
            'is_active' => true,
            'is_approved' => true, // superadmin direct creation is auto-approved
        ]);

        return response()->json([
            'data' => $company->only([
                'id',
                'company_request_id',
                'name',
                'address',
                'latitude',
                'longitude',
                'geofence_radius_meters',
                'geofence_enabled',
                'geofence_polygon',
                'contact_person',
                'contact_email',
                'contact_phone',
                'is_approved',
            ]),
        ], 201);
    }

    /**
     * List companies pending superadmin approval (is_approved = false).
     */
    public function indexPending(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->where('is_approved', false)
            ->where('is_active', true)
            ->with('companyRequest:id,name,status,user_id')
            ->orderByDesc('created_at')
            ->get([
                'id',
                'company_request_id',
                'name',
                'address',
                'latitude',
                'longitude',
                'geofence_radius_meters',
                'geofence_enabled',
                'geofence_polygon',
                'contact_person',
                'contact_email',
                'contact_phone',
                'is_active',
                'is_approved',
                'created_at',
            ]);

        return response()->json([
            'data' => $companies,
        ]);
    }

    /**
     * Superadmin approves a pending company (is_approved = false → true).
     */
    public function approvePending(Company $company): JsonResponse
    {
        if ($company->is_approved) {
            throw ValidationException::withMessages([
                'company' => ['This company is already approved.'],
            ]);
        }

        $company->update(['is_approved' => true]);

        // Also mark the linked company request as fully approved (if any)
        if ($company->company_request_id !== null) {
            CompanyRequest::query()
                ->where('id', $company->company_request_id)
                ->update(['status' => CompanyRequest::STATUS_APPROVED]);
        }

        return response()->json([
            'data' => $company->fresh()->only([
                'id',
                'company_request_id',
                'name',
                'address',
                'latitude',
                'longitude',
                'geofence_radius_meters',
                'geofence_enabled',
                'geofence_polygon',
                'contact_person',
                'contact_email',
                'contact_phone',
                'is_active',
                'is_approved',
            ]),
        ]);
    }

    /**
     * Superadmin rejects a pending company (soft-reject: is_active = false).
     */
    public function rejectPending(Company $company): JsonResponse
    {
        if (!$company->is_active) {
            throw ValidationException::withMessages([
                'company' => ['This company has already been rejected (inactive).'],
            ]);
        }

        $company->update(['is_active' => false]);

        // Also mark the linked company request as rejected (if any)
        if ($company->company_request_id !== null) {
            CompanyRequest::query()
                ->where('id', $company->company_request_id)
                ->update(['status' => CompanyRequest::STATUS_REJECTED]);
        }

        return response()->json([
            'data' => $company->fresh()->only([
                'id',
                'company_request_id',
                'name',
                'is_active',
                'is_approved',
            ]),
        ]);
    }

    /**
     * Assign a student to this company.
     */
    public function assignStudent(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'student_id' => ['required', 'integer', 'exists:students,id'],
            'supervisor_id' => ['nullable', 'integer', 'exists:supervisors,id'],
        ]);

        $user = $request->user();
        $courseId = null;

        // If coordinator, auto-assign their course id
        if ($user && $user->hasRole('coordinator') && $user->coordinatorCourse()) {
            $courseId = $user->coordinatorCourse()->id;
        }

        $company->students()->syncWithoutDetaching([
            $validated['student_id'] => [
                'course_id' => $courseId,
                'supervisor_id' => $validated['supervisor_id'] ?? null,
            ]
        ]);

        return response()->json(['message' => 'Student successfully assigned to the company.']);
    }

    /**
     * Add a supervisor to this company.
     */
    public function storeSupervisor(Request $request, Company $company): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email', 'unique:users,email'],
            'first_name' => ['required', 'string'],
            'last_name' => ['required', 'string'],
            'position_title' => ['required', 'string'],
        ]);

        $role = Role::where('name', 'supervisor')->first();
        if (!$role) {
            return response()->json(['message' => 'Supervisor role not found.'], 500);
        }

        $user = User::create([
            'name' => trim($validated['first_name'] . ' ' . $validated['last_name']),
            'email' => $validated['email'],
            'password' => Hash::make('password123'),
            'role_id' => $role->id,
            'is_active' => true,
        ]);

        $supervisor = Supervisor::create([
            'user_id' => $user->id,
            'company_id' => $company->id,
            'position_title' => $validated['position_title'],
            'is_active' => true,
        ]);

        return response()->json([
            'message' => 'Supervisor added successfully.',
            'data' => $supervisor->load('user')
        ], 201);
    }
}
