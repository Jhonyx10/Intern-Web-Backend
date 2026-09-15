<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\InternLocationUpdated;
use App\Models\TimeLog;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InternLocationController extends Controller
{
    public function update(Request $request)
{
    $request->validate([
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
        return response()->json([
            'message' => 'No active time log — location not broadcast.',
        ], 409);
    }

    $company = $student->companies()->first();

    if (! $company) {
        return response()->json(['message' => 'Intern has no assigned company.'], 422);
    }

    broadcast(new InternLocationUpdated(
        internId: $user->id,
        internName: $user->name,
        companyId: $company->id,
        latitude: (float) $request->latitude,
        longitude: (float) $request->longitude,
        accuracyMeters: $request->accuracy_meters !== null ? (float) $request->accuracy_meters : null,
        recordedAt: now()->toIso8601String(),
    ));

    return response()->json(['message' => 'Location broadcast.']);
}
}