<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Support\DeanPortalScope;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    public function index()
    {
        $query = Course::with(['dean', 'programHead', 'majors']);
        $user = Auth::user();

        if ($user && DeanPortalScope::isPortalUser($user)) {
            $course = DeanPortalScope::course($user);

            if ($course === null) {
                return [];
            }

            $query->whereKey($course->id);
        }

        return $query->get();
    }

    public function show(Course $course)
    {
        $user = Auth::user();

        if ($user && DeanPortalScope::isPortalUser($user)) {
            $scoped = DeanPortalScope::course($user);

            if ($scoped === null || (int) $scoped->id !== (int) $course->id) {
                abort(404);
            }
        }

        return $course->load(['dean', 'programHead', 'majors', 'sections.students', 'sections.schoolYear']);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'code' => 'required|string|unique:courses,code',
            'name' => 'required|string',
            'required_hours' => 'required|integer|min:0',
            'dean_user_id' => 'required|exists:users,id',
            'program_head_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $course = Course::create($validated);
        
        return response()->json($course->load(['dean', 'programHead', 'majors']), 201);
    }

    public function update(Request $request, Course $course)
    {
        $validated = $request->validate([
            'code' => 'sometimes|string|unique:courses,code,' . $course->id,
            'name' => 'sometimes|string',
            'required_hours' => 'sometimes|integer|min:0',
            'dean_user_id' => 'sometimes|exists:users,id',
            'program_head_id' => 'nullable|exists:users,id',
            'is_active' => 'boolean',
        ]);

        $course->update($validated);
        
        return $course->load(['dean', 'programHead', 'majors']);
    }

    public function destroy(Course $course)
    {
        $course->delete();
        
        return response()->noContent();
    }
}
