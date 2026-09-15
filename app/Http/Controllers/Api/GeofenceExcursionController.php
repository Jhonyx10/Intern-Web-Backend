<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GeofenceExcursion;
use App\Models\GeofenceExcursionPoint;
use Illuminate\Http\Request;

class GeofenceExcursionController extends Controller
{
    public function pending(Request $request)
    {
        $user = $request->user();
        if (!$user->student) {
            return response()->json(['message' => 'User is not a student'], 403);
        }

        $excursions = GeofenceExcursion::where('intern_id', $user->student->id)
            ->where('status', 'completed')
            ->whereNull('reason')
            ->get();

        return response()->json($excursions);
    }

    public function start(Request $request)
    {
        $user = $request->user();
        if (!$user->student) {
            return response()->json(['message' => 'User is not a student'], 403);
        }

        $excursion = GeofenceExcursion::create([
            'intern_id' => $user->student->id,
            'exit_at' => now(),
            'status' => 'ongoing',
        ]);

        return response()->json($excursion, 201);
    }

    public function addPoint(Request $request, $id)
    {
        $validated = $request->validate([
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $excursion = GeofenceExcursion::findOrFail($id);

        $point = $excursion->points()->create([
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'recorded_at' => now(),
        ]);

        return response()->json($point, 201);
    }

    public function complete(Request $request, $id)
    {
        $excursion = GeofenceExcursion::findOrFail($id);
        
        $now = now();
        $durationSeconds = $excursion->exit_at->diffInSeconds($now);

        $excursion->update([
            'return_at' => $now,
            'duration_seconds' => $durationSeconds,
            'status' => 'completed',
        ]);

        return response()->json($excursion);
    }

    public function updateReason(Request $request, $id)
    {
        $validated = $request->validate([
            'reason' => 'required|string',
        ]);

        $excursion = GeofenceExcursion::findOrFail($id);
        $excursion->update([
            'reason' => $validated['reason'],
        ]);

        return response()->json($excursion);
    }
}
