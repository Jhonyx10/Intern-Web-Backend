<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Company;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BuildingController extends Controller
{
    /**
     * List all buildings for a company.
     */
    public function index(Company $company): JsonResponse
    {
        return response()->json([
            'data' => $company->buildings()->orderBy('name')->get(),
        ]);
    }

    /**
     * Create a new building under a company.
     */
    public function store(Request $request, Company $company): JsonResponse
    {
        $validated = $this->validateBuilding($request);

        $building = $company->buildings()->create($validated);

        return response()->json(['data' => $building], 201);
    }

    /**
     * Show a single building.
     */
    public function show(Company $company, Building $building): JsonResponse
    {
        abort_unless($building->company_id === $company->id, 404);

        return response()->json(['data' => $building]);
    }

    /**
     * Update an existing building.
     */
    public function update(Request $request, Company $company, Building $building): JsonResponse
    {
        abort_unless($building->company_id === $company->id, 404);

        $validated = $this->validateBuilding($request, partial: true);
        $building->update($validated);

        return response()->json(['data' => $building->fresh()]);
    }

    /**
     * Delete a building.
     */
    public function destroy(Company $company, Building $building): JsonResponse
    {
        abort_unless($building->company_id === $company->id, 404);

        $building->delete();

        return response()->json(null, 204);
    }

    /**
     * Shared validation rules for a building.
     *
     * @return array<string, mixed>
     */
    private function validateBuilding(Request $request, bool $partial = false): array
    {
        $sometimes = $partial ? 'sometimes' : '';

        return $request->validate([
            'name'                    => array_filter([$sometimes, 'required', 'string', 'max:255']),
            'code'                    => array_filter([$sometimes, 'required', 'string', 'max:50']),
            'description'             => ['nullable', 'string', 'max:1000'],
            'latitude'                => ['nullable', 'numeric', 'between:-90,90'],
            'longitude'               => ['nullable', 'numeric', 'between:-180,180'],
            'geofence_radius_meters'  => ['nullable', 'integer', 'min:5', 'max:5000'],
            'geofence_enabled'        => ['nullable', 'boolean'],
            'geofence_polygon'        => ['nullable', 'array'],
            'geofence_polygon.type'   => ['required_with:geofence_polygon', 'in:Polygon'],
            'geofence_polygon.coordinates' => ['required_with:geofence_polygon', 'array', 'min:1'],
            'is_active'               => ['nullable', 'boolean'],
        ]);
    }
}
