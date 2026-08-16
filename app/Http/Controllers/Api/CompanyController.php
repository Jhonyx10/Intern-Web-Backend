<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    /**
     * List companies for the Mapbox companies map (foundation).
     * Role-scoped filtering will be added when coordinator portals are ported.
     */
    public function index(Request $request): JsonResponse
    {
        $companies = Company::query()
            ->where('is_active', true)
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
            ]);

        return response()->json([
            'data' => $companies,
        ]);
    }

    public function show(Company $company): JsonResponse
    {
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
            ]),
        ]);
    }

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
            ]),
        ], 201);
    }
}
