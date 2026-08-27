<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Building;
use App\Models\Company;
use App\Models\CompanyRequest;
use App\Models\Student;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompanyRequestController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $status = $request->string('status')->toString();

        $requests = CompanyRequest::query()
            ->with([
                'user:id,name,email',
                'company:id,company_request_id,name,geofence_enabled,geofence_polygon,is_approved',
            ])
            ->when(
                $status !== '',
                fn ($query) => $query->where('status', $status),
            )
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CompanyRequest $companyRequest) => $this->serialize($companyRequest));

        return response()->json([
            'data' => $requests,
        ]);
    }

    /**
     * Coordinator accepts a student's company request.
     * Creates the company record with is_approved = false (pending superadmin approval).
     */
    public function coordinatorAccept(Request $request, CompanyRequest $companyRequest): JsonResponse
    {
        if ($companyRequest->status === CompanyRequest::STATUS_ACCEPTED) {
            throw ValidationException::withMessages([
                'company_request' => ['This company request was already accepted by a coordinator.'],
            ]);
        }

        if ($companyRequest->status === CompanyRequest::STATUS_APPROVED) {
            throw ValidationException::withMessages([
                'company_request' => ['This company request was already approved.'],
            ]);
        }

        $validated = $request->validate([
            'geofence_radius_meters'                         => ['nullable', 'integer', 'min:10', 'max:5000'],
            'geofence_enabled'                               => ['sometimes', 'boolean'],
            'geofence_polygon'                               => ['required', 'array'],
            'geofence_polygon.type'                          => ['required', 'in:Polygon'],
            'geofence_polygon.coordinates'                   => ['required', 'array', 'min:1'],
            'geofence_polygon.coordinates.0'                 => ['required', 'array', 'min:4'],
            'contact_person'                                 => ['nullable', 'string', 'max:255'],
            'contact_email'                                  => ['nullable', 'email', 'max:255'],
            'contact_phone'                                  => ['nullable', 'string', 'max:50'],
            // Optional buildings
            'buildings'                                      => ['nullable', 'array'],
            'buildings.*.name'                               => ['required', 'string', 'max:255'],
            'buildings.*.code'                               => ['required', 'string', 'max:50'],
            'buildings.*.latitude'                           => ['nullable', 'numeric', 'between:-90,90'],
            'buildings.*.longitude'                          => ['nullable', 'numeric', 'between:-180,180'],
            'buildings.*.geofence_radius_meters'             => ['nullable', 'integer', 'min:5', 'max:5000'],
            'buildings.*.geofence_enabled'                   => ['nullable', 'boolean'],
            'buildings.*.geofence_polygon'                   => ['nullable', 'array'],
            'buildings.*.geofence_polygon.type'              => ['required_with:buildings.*.geofence_polygon', 'in:Polygon'],
            'buildings.*.geofence_polygon.coordinates'       => ['required_with:buildings.*.geofence_polygon', 'array', 'min:1'],
        ]);

        $company = DB::transaction(function () use ($companyRequest, $validated) {
            $companyRequest->update([
                'status' => CompanyRequest::STATUS_ACCEPTED,
            ]);

            $company = Company::query()->updateOrCreate(
                ['company_request_id' => $companyRequest->id],
                [
                    'name' => $companyRequest->name,
                    'address' => $companyRequest->address,
                    'latitude' => $companyRequest->latitude,
                    'longitude' => $companyRequest->longitude,
                    'geofence_radius_meters' => $validated['geofence_radius_meters'] ?? 150,
                    'geofence_enabled' => $validated['geofence_enabled'] ?? true,
                    'geofence_polygon' => $validated['geofence_polygon'],
                    'contact_person' => $validated['contact_person'] ?? null,
                    'contact_email' => $validated['contact_email'] ?? null,
                    'contact_phone' => $validated['contact_phone'] ?? null,
                    'is_active' => true,
                    'is_approved' => false, // superadmin must approve
                ],
            );

            $company->users()->syncWithoutDetaching([$companyRequest->user_id]);

            $student = Student::query()->where('user_id', $companyRequest->user_id)->first();
            if ($student !== null) {
                $company->students()->syncWithoutDetaching([$student->id]);
            }

            // Bulk-create buildings if provided
            if (!empty($validated['buildings'])) {
                // Remove any UI-only keys that are not on the Building model
                $buildingRows = array_map(fn (array $b) => array_diff_key($b, ['tempId' => true]), $validated['buildings']);
                $company->buildings()->createMany($buildingRows);
            }

            return $company->load(['companyRequest', 'buildings']);
        });

        return response()->json([
            'data' => [
                'company' => array_merge(
                    $company->only([
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
                    ['buildings' => $company->buildings],
                ),
                'company_request' => $this->serialize($companyRequest->fresh([
                    'user:id,name,email',
                    'company:id,company_request_id,name,geofence_enabled,geofence_polygon,is_approved',
                ])),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(CompanyRequest $companyRequest): array
    {
        return [
            'id' => $companyRequest->id,
            'name' => $companyRequest->name,
            'address' => $companyRequest->address,
            'latitude' => $companyRequest->latitude,
            'longitude' => $companyRequest->longitude,
            'status' => $companyRequest->status,
            'created_at' => $companyRequest->created_at,
            'user' => $companyRequest->user
                ? [
                    'id' => $companyRequest->user->id,
                    'name' => $companyRequest->user->name,
                    'email' => $companyRequest->user->email,
                ]
                : null,
            'company_id' => $companyRequest->company?->id,
            'company_is_approved' => $companyRequest->company?->is_approved,
            'geofence_polygon' => $companyRequest->company?->geofence_enabled
                ? $companyRequest->company->geofence_polygon
                : null,
        ];
    }
}
