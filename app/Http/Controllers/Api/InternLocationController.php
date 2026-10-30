<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InternLocation;
use App\Events\InternLocationUpdated;
use App\Models\Student;
use App\Models\TimeLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InternLocationController extends Controller
{
    // Intern's phone -> save latest position
   public function update(Request $request)
    {
        $data = $request->validate([
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
            'accuracy_meters' => 'nullable|numeric',
        ]);

        $user = Auth::user();
        $student = Student::where('user_id', $user->id)->first();

        if (! $student) {
            return response()->json(['message' => 'No student record for this user.'], 422);
        }

        $hasOpenLog = TimeLog::where('student_id', $student->id)
            ->whereNull('time_out')
            ->exists();

        if (! $hasOpenLog) {
            return response()->json(['message' => 'No active time log — location not saved.'], 409);
        }

        $company = $student->companies()->first();

        if (! $company) {
            return response()->json(['message' => 'Intern has no assigned company.'], 422);
        }

        $location = InternLocation::updateOrCreate(
            ['student_id' => $student->id],
            [
                'user_id' => $user->id,
                'company_id' => $company->id,
                'latitude' => $data['latitude'],
                'longitude' => $data['longitude'],
                'accuracy_meters' => $data['accuracy_meters'] ?? null,
                'recorded_at' => now(),
            ]
        );

        // The row is already saved, so a broadcast failure (e.g. Pusher down)
        // shouldn't make the intern's ping fail.
        try {
            broadcast(new InternLocationUpdated(
                internId: $user->id,
                internName: $user->name,
                companyId: $company->id,
                latitude: $location->latitude,
                longitude: $location->longitude,
                accuracyMeters: $location->accuracy_meters,
                recordedAt: $location->recorded_at->toIso8601String(),
            ));
        } catch (\Throwable $e) {
            report($e);
        }

        return response()->json(['message' => 'Location saved.']);
    }

    // Dashboard -> fetch latest positions
    public function index(Request $request)
    {
        $validated = $request->validate([
            'company_ids' => 'required|array',
            'company_ids.*' => 'integer',
        ]);

        // TODO: restrict $validated['company_ids'] to companies this
        // supervisor/coordinator may see (this replaces the old
        // routes/channels.php authorization).
        $companyIds = $validated['company_ids'];

        $locations = InternLocation::with('user:id,name')
            ->whereIn('company_id', $companyIds)
            // only interns who are still clocked in...
            ->whereIn('student_id', TimeLog::whereNull('time_out')->select('student_id'))
            // ...and whose last ping isn't stale
            ->where('recorded_at', '>=', now()->subMinutes(5))
            ->get()
            ->map(fn ($loc) => [
                'intern_id' => $loc->user_id,
                'intern_name' => $loc->user?->name,
                'latitude' => $loc->latitude,
                'longitude' => $loc->longitude,
                'accuracy_meters' => $loc->accuracy_meters,
                'recorded_at' => $loc->recorded_at->toIso8601String(),
            ]);

        return response()->json(['data' => $locations]);
    }
}